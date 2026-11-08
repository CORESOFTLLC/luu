<?php

namespace App\Services;

use App\Models\Lottery;
use App\Models\LotteryBank;
use App\Models\LotteryTicket;
use App\Models\Transaction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StatementImportService
{
    /**
     * Static import block list by date+description fingerprint, grouped by bank-local day.
     *
     * Each digest is PBKDF2-SHA256 of "Y-m-d H:i:s|normalized_description" so the
     * statement details (phone numbers, names) stay out of the public repository.
     *
     * @var array<string, list<string>>
     */
    private const BLOCKED_IMPORT_DIGESTS = [
        '2026-04-14' => ['cb6fc5095ee29f6a84c8acfa17060bb9a6796a741cdc63a574f1a08894b79723'],
        '2026-04-21' => [
            'f7bf6374ef0440efbfc3b56b3a260a306c144365a594069e46cdf35e5e2ce972',
            'bae298a50c4d048ed26c33ee493a23a1cd5d56d7c264fffd23fd9fcf9804d56c',
        ],
        '2026-04-24' => ['2b93726170a48ea6636ae7248f9f943ef8560384c9be44f5dd7091b250671c94'],
    ];

    private const BLOCKED_IMPORT_DIGEST_SALT = 'luu-import-block-v1';

    private const BLOCKED_IMPORT_DIGEST_ITERATIONS = 100000;

    /**
     * @return array{imported: int, tickets_created: int, skipped: int}
     */
    public function import(Lottery $lottery, UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        if (count($rows) < 2) {
            return ['imported' => 0, 'tickets_created' => 0, 'skipped' => 0];
        }

        $headerRowIndex = $this->findHeaderRow($rows);
        $headers = array_map(fn ($h) => mb_strtolower(trim((string) $h)), $rows[$headerRowIndex]);
        $columnMap = $this->detectColumns($headers);

        $imported = 0;
        $ticketsCreated = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $headerRowIndex, $columnMap, $lottery, &$imported, &$ticketsCreated, &$skipped) {
            $nextTicketNumber = ($lottery->tickets()->max('ticket_number') ?? 0) + 1;

            for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
                $row = $rows[$i];

                // Skip summary/empty rows
                $firstCell = mb_strtolower(trim((string) ($row[0] ?? '')));
                if ($firstCell === '' || str_contains($firstCell, 'нийт дүн') || str_contains($firstCell, 'total')) {
                    continue;
                }

                $amount = $this->parseAmount($row[$columnMap['credit']] ?? null);

                if ($amount <= 0) {
                    $skipped++;

                    continue;
                }

                $description = trim((string) ($row[$columnMap['description']] ?? ''));
                $date = $this->parseDate($row[$columnMap['date']] ?? null);
                $reference = $this->resolveReference(
                    $row[$columnMap['reference']] ?? null,
                    $date,
                    $description,
                    $amount,
                    $i,
                    $row,
                );

                $fingerprint = $this->fingerprintByDateDescription(
                    $date?->format('Y-m-d H:i:s'),
                    $description,
                );

                // Static block for excluded transactions to prevent re-import.
                if ($fingerprint && $this->isBlockedFingerprint($fingerprint)) {
                    $skipped++;

                    continue;
                }

                // Search for phone in all columns, not just description
                $phone = $this->extractPhoneFromRow($row);

                if (! $phone) {
                    $skipped++;

                    continue;
                }

                if (Transaction::where('bank_reference', $reference)->exists()) {
                    $skipped++;

                    continue;
                }

                $transaction = Transaction::create([
                    'lottery_id' => $lottery->id,
                    'bank_reference' => $reference,
                    'phone' => $phone,
                    'amount' => $amount,
                    'description' => $description,
                    'transacted_at' => $date ?? now(),
                ]);

                $ticketCount = intdiv((int) $amount, $lottery->price_per_ticket);
                $remainder = (int) $amount % $lottery->price_per_ticket;
                $cap = $lottery->max_tickets_per_transaction;

                if ($cap !== null) {
                    // Capped lottery: tickets = min(floor(amount/price), cap).
                    // Everything not converted to tickets is refunded manually.
                    $ticketCount = min($ticketCount, $cap);
                    $refund = (int) $amount - $ticketCount * $lottery->price_per_ticket;
                    if ($refund > 0) {
                        $transaction->update(['refund_amount' => $refund]);
                    }
                    $remainder = 0;
                }

                for ($t = 0; $t < $ticketCount; $t++) {
                    $bankEntry = LotteryBank::create([
                        'lottery_id' => $lottery->id,
                        'transaction_id' => $transaction->id,
                        'phone' => $phone,
                        'amount' => $lottery->price_per_ticket,
                        'ticket_number' => $nextTicketNumber,
                    ]);

                    LotteryTicket::create([
                        'lottery_id' => $lottery->id,
                        'transaction_id' => $transaction->id,
                        'ticket_number' => $nextTicketNumber++,
                        'phone' => $phone,
                    ]);
                    $ticketsCreated++;
                }

                // Record remainder as a bank entry without ticket
                if ($remainder > 0) {
                    LotteryBank::create([
                        'lottery_id' => $lottery->id,
                        'transaction_id' => $transaction->id,
                        'phone' => $phone,
                        'amount' => $remainder,
                        'ticket_number' => null,
                    ]);
                }

                $imported++;
            }

            // Combine remainder bank entries across transactions for the same phone
            $ticketsFromRemainders = $this->combineRemainders($lottery, $nextTicketNumber);
            $ticketsCreated += $ticketsFromRemainders;

            $lottery->increment('sold_tickets', $ticketsCreated);
        });

        return ['imported' => $imported, 'tickets_created' => $ticketsCreated, 'skipped' => $skipped];
    }

    /**
     * Combine unassigned remainder bank entries for the same phone to create additional tickets.
     */
    private function combineRemainders(Lottery $lottery, int &$nextTicketNumber): int
    {
        $ticketsCreated = 0;

        // Group unassigned bank entries by phone
        $phoneGroups = LotteryBank::where('lottery_id', $lottery->id)
            ->whereNull('ticket_number')
            ->orderBy('id')
            ->get()
            ->groupBy('phone');

        foreach ($phoneGroups as $phone => $entries) {
            $total = $entries->sum('amount');

            while ($total >= $lottery->price_per_ticket) {
                // Assign ticket to enough entries to cover price
                $accumulated = 0;
                $firstTransaction = null;

                foreach ($entries as $entry) {
                    if ($entry->ticket_number !== null) {
                        continue; // already assigned in this loop
                    }

                    $entry->update(['ticket_number' => $nextTicketNumber]);
                    $accumulated += $entry->amount;
                    $firstTransaction ??= $entry->transaction_id;

                    if ($accumulated >= $lottery->price_per_ticket) {
                        break;
                    }
                }

                LotteryTicket::create([
                    'lottery_id' => $lottery->id,
                    'transaction_id' => $firstTransaction,
                    'ticket_number' => $nextTicketNumber++,
                    'phone' => $phone,
                ]);

                $ticketsCreated++;
                $total -= $lottery->price_per_ticket;
            }
        }

        return $ticketsCreated;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{date: int, description: int, credit: int, reference: int}
     */
    private function detectColumns(array $headers): array
    {
        $map = ['date' => 0, 'description' => 1, 'credit' => 3, 'reference' => 0];

        foreach ($headers as $index => $header) {
            if (str_contains($header, 'огноо') || str_contains($header, 'date')) {
                $map['date'] = $index;
            }
            if (str_contains($header, 'гүйлгээний утга') || str_contains($header, 'description') || str_contains($header, 'утга')) {
                $map['description'] = $index;
            }
            if (str_contains($header, 'орлого') || str_contains($header, 'credit') || str_contains($header, 'кредит')) {
                $map['credit'] = $index;
            }
            if (str_contains($header, 'лавлах') || str_contains($header, 'reference') || str_contains($header, 'ref')) {
                $map['reference'] = $index;
            }
        }

        return $map;
    }

    /**
     * Find the header row by scanning for a row containing 'огноо'.
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function findHeaderRow(array $rows): int
    {
        foreach ($rows as $index => $row) {
            $joined = mb_strtolower(implode(' ', array_map(fn ($c) => trim((string) $c), $row)));
            if (str_contains($joined, 'гүйлгээний огноо') || (str_contains($joined, 'огноо') && str_contains($joined, 'кредит'))) {
                return $index;
            }
        }

        return 0;
    }

    private function parseAmount(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        $cleaned = preg_replace('/[^0-9.]/', '', (string) $value);

        return (int) round((float) $cleaned);
    }

    private function parseDate(mixed $value): ?\DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Exception) {
            return null;
        }
    }

    private function extractPhone(string $description): ?string
    {
        // Ignore sender/account fragments that appear after source-account text.
        $sourceText = trim((string) preg_split('/ХААНААС|FROM/i', $description)[0]);

        if (preg_match('/(?<!\d)([6-9][0-9]{7})(?!\d)/', $sourceText, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractFallbackPhone(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // Skip date/reference-like cells to avoid using timestamps as phones.
        if (preg_match('/^\d{4}[\/\-.]\d{2}[\/\-.]\d{2}(\s+\d{2}:\d{2}(:\d{2})?)?$/', $value)) {
            return null;
        }

        $digits = preg_replace('/[^0-9]+/', '', $value);
        if ($digits === null || strlen($digits) < 8) {
            return null;
        }

        // Prefer right-most Mongolian-looking phone window.
        for ($i = strlen($digits) - 8; $i >= 0; $i--) {
            $candidate = substr($digits, $i, 8);
            if (preg_match('/^[6-9][0-9]{7}$/', $candidate)) {
                return $candidate;
            }
        }

        // Fallback for non-MN numbers: keep last 8 digits for current schema.
        return substr($digits, -8);
    }

    /**
     * Search all columns in a row for a Mongolian phone number (8 digits starting with 6-9).
     *
     * @param  array<int, mixed>  $row
     */
    private function extractPhoneFromRow(array $row): ?string
    {
        foreach ($row as $cell) {
            $value = trim((string) $cell);
            if ($value === '') {
                continue;
            }
            $phone = $this->extractPhone($value);
            if ($phone) {
                return $phone;
            }
        }

        // Second pass: allow foreign-format phones from phone-like text cells.
        foreach ($row as $cell) {
            $value = trim((string) $cell);
            if ($value === '') {
                continue;
            }

            $sourceText = trim((string) preg_split('/ХААНААС|FROM/i', $value)[0]);
            if ($sourceText === '') {
                continue;
            }

            $hasPhoneLikeMarkers = str_starts_with($sourceText, '0')
                || str_contains($sourceText, '+')
                || preg_match('/[A-Za-zА-Яа-я]/u', $sourceText) === 1;

            if (! $hasPhoneLikeMarkers) {
                continue;
            }

            $fallback = $this->extractFallbackPhone($sourceText);
            if ($fallback) {
                return $fallback;
            }
        }

        return null;
    }

    private function fingerprintByDateDescription(?string $date, ?string $description): ?string
    {
        if (! $date || ! $description) {
            return null;
        }

        $normalizedDescription = preg_replace('/\s+/u', ' ', mb_strtolower(trim($description)));
        if ($normalizedDescription === null || $normalizedDescription === '') {
            return null;
        }

        return $date.'|'.$normalizedDescription;
    }

    private function isBlockedFingerprint(string $fingerprint): bool
    {
        $digests = self::BLOCKED_IMPORT_DIGESTS[substr($fingerprint, 0, 10)] ?? [];

        if ($digests === []) {
            return false;
        }

        $digest = hash_pbkdf2('sha256', $fingerprint, self::BLOCKED_IMPORT_DIGEST_SALT, self::BLOCKED_IMPORT_DIGEST_ITERATIONS);

        return in_array($digest, $digests, true);
    }

    /**
     * Build a stable transaction reference for deduplication.
     *
     * If the parsed reference looks like a datetime (common in some KHAN exports),
     * we compose a stronger key using date + description + amount.
     * This prevents different transactions in the same second from collapsing.
     *
     * @param  array<int, mixed>  $row
     */
    private function resolveReference(mixed $rawReference, ?\DateTimeInterface $date, string $description, int $amount, int $rowIndex, array $row): string
    {
        $reference = trim((string) $rawReference);
        if ($reference === '') {
            return 'ROW-'.$rowIndex.'-'.md5(implode('|', array_map('strval', $row)));
        }

        $looksLikeDateTime = preg_match('/^\d{4}[\/-]\d{2}[\/-]\d{2}\s+\d{2}:\d{2}(:\d{2})?$/', $reference) === 1;
        if (! $looksLikeDateTime) {
            return $reference;
        }

        $normalizedDescription = preg_replace('/\s+/u', ' ', mb_strtolower(trim($description))) ?? '';
        $datePart = $date?->format('Y-m-d H:i:s') ?? $reference;

        return 'AUTO-'.$datePart.'-'.$amount.'-'.md5($normalizedDescription);
    }
}
