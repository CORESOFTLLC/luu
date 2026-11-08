<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lottery;
use App\Models\LotteryBank;
use App\Models\LotteryTicket;
use App\Models\Transaction;
use App\Services\StatementImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function show(Request $request, Lottery $lottery): Response
    {
        $lottery->loadCount('tickets');

        $search = $request->string('search')->trim()->value();
        $status = $request->string('status')->trim()->value();
        $sortBy = $request->string('sort_by')->trim()->value();
        $sortDir = $request->string('sort_dir')->trim()->lower()->value();

        $allowedSorts = ['bank_reference', 'phone', 'amount', 'description', 'transacted_at', 'is_excluded', 'refund_amount'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'transacted_at';
        }

        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'desc';
        }

        $totalAmount = $lottery->transactions()->sum('amount');
        $refundTotal = (int) $lottery->transactions()->where('is_excluded', false)->sum('refund_amount');
        $excludedCount = $lottery->bankEntries()->where('is_excluded', true)->count();
        $activeCount = $lottery->bankEntries()->where('is_excluded', false)->count();
        $transactionsCount = $lottery->transactions()->count();

        $recentTransactions = $lottery->transactions()
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('phone', 'like', "%{$search}%")
                ->orWhere('bank_reference', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->when($status === 'refund', fn ($q) => $q->where('refund_amount', '>', 0))
            ->when($status === 'excluded', fn ($q) => $q->where('is_excluded', true))
            ->when($status === 'active', fn ($q) => $q->where('is_excluded', false))
            ->orderBy($sortBy, $sortDir)
            ->paginate(20, ['id', 'bank_reference', 'phone', 'amount', 'refund_amount', 'description', 'transacted_at', 'is_excluded'], 'transactions_page')
            ->withQueryString();

        return Inertia::render('admin/lotteries/import', [
            'lottery' => [
                'id' => $lottery->id,
                'name' => $lottery->name,
                'total_tickets' => $lottery->total_tickets,
                'sold_tickets' => $lottery->sold_tickets,
                'tickets_count' => $lottery->tickets_count,
                'price_per_ticket' => $lottery->price_per_ticket,
                'total_amount' => $totalAmount,
                'excluded_count' => $excludedCount,
                'active_count' => $activeCount,
                'transactions_count' => $transactionsCount,
                'refund_total' => $refundTotal,
            ],
            'recentTransactions' => $recentTransactions,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function bankIndex(Request $request, Lottery $lottery): Response
    {
        $search = $request->string('search')->trim()->value();
        $status = $request->string('status')->trim()->value();
        $sortBy = $request->string('sort_by')->trim()->value();
        $sortDir = $request->string('sort_dir')->trim()->lower()->value();

        $allowedSorts = ['ticket_number', 'phone', 'amount', 'description', 'transacted_at', 'is_excluded', 'refund_amount'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'ticket_number';
        }

        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        $bankEntriesQuery = $lottery->bankEntries()
            ->with('transaction:id,bank_reference,description,transacted_at')
            ->when($search, fn ($q) => $q->where('phone', 'like', "%{$search}%")
                ->orWhere('ticket_number', $search)
                ->orWhereHas('transaction', fn ($q) => $q->where('bank_reference', 'like', "%{$search}%")))
            ->when($status === 'excluded', fn ($q) => $q->where('is_excluded', true))
            ->when($status === 'active', fn ($q) => $q->where('is_excluded', false));

        if (in_array($sortBy, ['description', 'transacted_at'], true)) {
            $bankEntriesQuery->orderBy(
                Transaction::query()
                    ->select($sortBy)
                    ->whereColumn('transactions.id', 'lottery_banks.transaction_id')
                    ->limit(1),
                $sortDir,
            );
        } else {
            $bankEntriesQuery->orderBy($sortBy, $sortDir);
        }

        $bankEntries = $bankEntriesQuery
            ->paginate(100, ['id', 'lottery_id', 'transaction_id', 'phone', 'amount', 'ticket_number', 'is_excluded', 'created_at'], 'page')
            ->withQueryString();

        return Inertia::render('admin/lotteries/bank', [
            'lottery' => [
                'id' => $lottery->id,
                'name' => $lottery->name,
                'price_per_ticket' => $lottery->price_per_ticket,
                'show_ticket_numbers' => $lottery->show_ticket_numbers,
            ],
            'bankEntries' => $bankEntries,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    /**
     * Server-rendered A4 print sheet: 3 columns x 13 rows, one cell per ticket,
     * phone repeated once per ticket, in import order.
     */
    public function printTickets(Request $request, Lottery $lottery): \Illuminate\Contracts\View\View
    {
        // Optional range on the bank statement transfer datetime (transactions.transacted_at).
        $parse = function (?string $v) {
            if (! $v) {
                return null;
            }
            try {
                return \Carbon\Carbon::parse($v);
            } catch (\Throwable) {
                return null;
            }
        };
        $from = $parse($request->query('from'));
        $to = $parse($request->query('to'));

        $tickets = \Illuminate\Support\Facades\DB::table('lottery_tickets')
            ->join('transactions', 'transactions.id', '=', 'lottery_tickets.transaction_id')
            ->where('lottery_tickets.lottery_id', $lottery->id)
            ->when($from, fn ($q) => $q->where('transactions.transacted_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('transactions.transacted_at', '<=', $to))
            ->orderBy('transactions.transacted_at')
            ->orderBy('lottery_tickets.id')
            ->get(['lottery_tickets.id', 'lottery_tickets.ticket_number', 'lottery_tickets.phone']);

        // Statement totals for the same lottery and range.
        $stats = \Illuminate\Support\Facades\DB::table('transactions')
            ->where('lottery_id', $lottery->id)
            ->when($from, fn ($q) => $q->where('transacted_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('transacted_at', '<=', $to))
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount),0) as amount, COALESCE(SUM(refund_amount),0) as refund, SUM(is_excluded) as excluded')
            ->first();

        // Keep each phone's tickets together (by first appearance) and number them #1..#N per phone.
        $tickets = $tickets->groupBy('phone')
            ->flatMap(fn ($group) => $group->values()->map(function ($t, $i) {
                $t->seq = $i + 1;

                return $t;
            }))
            ->values();

        return view('admin.print-tickets', [
            'lottery' => $lottery,
            'total' => $tickets->count(),
            'pages' => $tickets->chunk(36),
            'showNumbers' => (bool) $lottery->show_ticket_numbers,
            'from' => $from,
            'to' => $to,
            'stats' => $stats,
        ]);
    }

    public function ticketsIndex(Request $request, Lottery $lottery): Response
    {
        $lottery->loadCount('tickets');

        $search = $request->string('search')->trim()->value();
        $sortBy = $request->string('sort_by')->trim()->value();
        $sortDir = $request->string('sort_dir')->trim()->lower()->value();

        $allowedSorts = ['ticket_number', 'phone', 'transacted_at'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'ticket_number';
        }

        if (! in_array($sortDir, ['asc', 'desc'], true)) {
            $sortDir = 'asc';
        }

        $ticketsQuery = $lottery->tickets()
            ->with('transaction:id,transacted_at')
            ->when($search, fn ($q) => $q->where('phone', 'like', "%{$search}%")
                ->orWhere('ticket_number', $search));

        if ($sortBy === 'transacted_at') {
            $ticketsQuery->orderBy(
                Transaction::query()
                    ->select('transacted_at')
                    ->whereColumn('transactions.id', 'lottery_tickets.transaction_id')
                    ->limit(1),
                $sortDir,
            );
        } else {
            $ticketsQuery->orderBy($sortBy, $sortDir);
        }

        $tickets = $ticketsQuery
            ->paginate(100, ['id', 'transaction_id', 'ticket_number', 'phone'], 'page')
            ->withQueryString();

        return Inertia::render('admin/lotteries/tickets', [
            'lottery' => [
                'id' => $lottery->id,
                'name' => $lottery->name,
                'tickets_count' => $lottery->tickets_count,
                'show_ticket_numbers' => $lottery->show_ticket_numbers,
            ],
            'tickets' => $tickets,
            'filters' => [
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function store(Request $request, Lottery $lottery, StatementImportService $service): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $result = $service->import($lottery, $request->file('file'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$result['imported']} гүйлгээ импорт хийгдлээ. {$result['tickets_created']} сугалаа үүслээ. {$result['skipped']} алгаслаа.",
        ]);

        return back();
    }

    public function destroy(Lottery $lottery): RedirectResponse
    {
        $lottery->tickets()->delete();
        $lottery->bankEntries()->delete();
        $lottery->transactions()->delete();
        $lottery->update(['sold_tickets' => 0]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Бүх гүйлгээ, сугалаа, банкны бүртгэл арилгагдлаа.',
        ]);

        return back();
    }

    public function toggleExclude(Lottery $lottery, LotteryBank $lotteryBank): RedirectResponse
    {
        $excluding = ! $lotteryBank->is_excluded;

        if ($excluding) {
            // Remove associated tickets for this bank entry's transaction + phone
            $ticketNumbers = LotteryBank::where('lottery_id', $lottery->id)
                ->where('transaction_id', $lotteryBank->transaction_id)
                ->where('phone', $lotteryBank->phone)
                ->whereNotNull('ticket_number')
                ->pluck('ticket_number');

            if ($ticketNumbers->isNotEmpty()) {
                LotteryTicket::where('lottery_id', $lottery->id)
                    ->whereIn('ticket_number', $ticketNumbers)
                    ->delete();

                $lottery->decrement('sold_tickets', $ticketNumbers->count());
            }

            // Mark all bank entries for same transaction+phone as excluded
            LotteryBank::where('lottery_id', $lottery->id)
                ->where('transaction_id', $lotteryBank->transaction_id)
                ->where('phone', $lotteryBank->phone)
                ->update(['is_excluded' => true, 'ticket_number' => null]);
        } else {
            // Restore: un-exclude all bank entries for same transaction+phone
            LotteryBank::where('lottery_id', $lottery->id)
                ->where('transaction_id', $lotteryBank->transaction_id)
                ->where('phone', $lotteryBank->phone)
                ->update(['is_excluded' => false]);

            // Re-create tickets
            $entries = LotteryBank::where('lottery_id', $lottery->id)
                ->where('transaction_id', $lotteryBank->transaction_id)
                ->where('phone', $lotteryBank->phone)
                ->orderBy('id')
                ->get();

            $nextTicketNumber = ($lottery->tickets()->max('ticket_number') ?? 0) + 1;
            $totalAmount = $entries->sum('amount');
            $ticketCount = intdiv($totalAmount, $lottery->price_per_ticket);

            $ticketsCreated = 0;
            foreach ($entries as $entry) {
                if ($ticketsCreated >= $ticketCount) {
                    break;
                }
                $entry->update(['ticket_number' => $nextTicketNumber]);
                LotteryTicket::create([
                    'lottery_id' => $lottery->id,
                    'transaction_id' => $lotteryBank->transaction_id,
                    'ticket_number' => $nextTicketNumber++,
                    'phone' => $lotteryBank->phone,
                ]);
                $ticketsCreated++;
            }

            if ($ticketsCreated > 0) {
                $lottery->increment('sold_tickets', $ticketsCreated);
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $excluding ? 'Сугалаа биш гэж тэмдэглэлээ.' : 'Сугалаа сэргээлээ.',
        ]);

        return back();
    }

    public function toggleTransactionExclude(Lottery $lottery, Transaction $transaction): RedirectResponse
    {
        $excluding = ! $transaction->is_excluded;

        if ($excluding) {
            // Remove associated tickets for all bank entries of this transaction
            $ticketNumbers = LotteryBank::where('lottery_id', $lottery->id)
                ->where('transaction_id', $transaction->id)
                ->whereNotNull('ticket_number')
                ->pluck('ticket_number');

            if ($ticketNumbers->isNotEmpty()) {
                LotteryTicket::where('lottery_id', $lottery->id)
                    ->whereIn('ticket_number', $ticketNumbers)
                    ->delete();

                $lottery->decrement('sold_tickets', $ticketNumbers->count());
            }

            // Mark all bank entries for this transaction as excluded
            LotteryBank::where('lottery_id', $lottery->id)
                ->where('transaction_id', $transaction->id)
                ->update(['is_excluded' => true, 'ticket_number' => null]);

            $transaction->update(['is_excluded' => true]);
        } else {
            // Restore: un-exclude all bank entries for this transaction
            LotteryBank::where('lottery_id', $lottery->id)
                ->where('transaction_id', $transaction->id)
                ->update(['is_excluded' => false]);

            $transaction->update(['is_excluded' => false]);

            // Re-create tickets
            $entries = LotteryBank::where('lottery_id', $lottery->id)
                ->where('transaction_id', $transaction->id)
                ->orderBy('id')
                ->get();

            $nextTicketNumber = ($lottery->tickets()->max('ticket_number') ?? 0) + 1;
            $totalAmount = $entries->sum('amount');
            $ticketCount = intdiv($totalAmount, $lottery->price_per_ticket);

            $ticketsCreated = 0;
            foreach ($entries as $entry) {
                if ($ticketsCreated >= $ticketCount) {
                    break;
                }
                $entry->update(['ticket_number' => $nextTicketNumber]);
                LotteryTicket::create([
                    'lottery_id' => $lottery->id,
                    'transaction_id' => $transaction->id,
                    'ticket_number' => $nextTicketNumber++,
                    'phone' => $transaction->phone,
                ]);
                $ticketsCreated++;
            }

            if ($ticketsCreated > 0) {
                $lottery->increment('sold_tickets', $ticketsCreated);
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $excluding ? 'Гүйлгээ цуцлагдлаа.' : 'Гүйлгээ сэргээгдлээ.',
        ]);

        return back();
    }
}
