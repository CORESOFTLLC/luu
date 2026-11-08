<?php

namespace App\Http\Controllers;

use App\Models\Lottery;
use App\Models\LotteryBank;
use App\Models\LotteryTicket;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LotteryTicketController extends Controller
{
    public function check(Request $request, Lottery $lottery): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'regex:/^\d{8}$/'],
        ]);

        $tickets = LotteryTicket::where('lottery_id', $lottery->id)
            ->where('phone', $validated['phone'])
            ->with('transaction:id,amount,description,transacted_at')
            ->orderBy('ticket_number')
            ->get();

        if (! $lottery->show_ticket_numbers) {
            $tickets->each->makeHidden('ticket_number');
        }

        // Get remainder bank entries (no ticket assigned) with transaction for date
        $remainders = LotteryBank::where('lottery_id', $lottery->id)
            ->where('phone', $validated['phone'])
            ->where('is_excluded', false)
            ->whereNull('ticket_number')
            ->with('transaction:id,amount,description,transacted_at')
            ->get(['id', 'transaction_id', 'phone', 'amount']);

        $remainderTotal = $remainders->sum('amount');

        // Get all transactions for this phone
        $transactions = Transaction::where('lottery_id', $lottery->id)
            ->where('phone', $validated['phone'])
            ->where('is_excluded', false)
            ->orderBy('transacted_at')
            ->get(['id', 'amount', 'description', 'transacted_at']);

        return response()->json([
            'tickets' => $tickets,
            'count' => $tickets->count(),
            'remainders' => $remainders,
            'remainder_total' => $remainderTotal,
            'transactions' => $transactions,
            'price_per_ticket' => $lottery->price_per_ticket,
            'show_ticket_numbers' => $lottery->show_ticket_numbers,
        ]);
    }
}
