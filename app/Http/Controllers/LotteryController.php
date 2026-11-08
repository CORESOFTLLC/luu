<?php

namespace App\Http\Controllers;

use App\Models\Lottery;
use Inertia\Inertia;
use Inertia\Response;

class LotteryController extends Controller
{
    public function index(): Response
    {
        $lotteries = Lottery::query()
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Lottery $lottery) => [
                'id' => $lottery->id,
                'name' => $lottery->name,
                'slug' => $lottery->slug,
                'image' => $lottery->image,
                'price_per_ticket' => $lottery->price_per_ticket,
                'max_tickets_per_transaction' => $lottery->max_tickets_per_transaction ?? 15,
                'is_finished' => $lottery->isFinished(),
                'starts_at' => $lottery->starts_at->format('Y-m-d H:i'),
                'draws_at' => $lottery->draws_at->format('Y-m-d H:i'),
            ]);

        return Inertia::render('lotteries/index', [
            'lotteries' => $lotteries,
        ]);
    }

    public function show(Lottery $lottery): Response
    {
        return Inertia::render('lotteries/show', [
            'lottery' => [
                'id' => $lottery->id,
                'name' => $lottery->name,
                'slug' => $lottery->slug,
                'image' => $lottery->image,
                'description' => $lottery->description,
                'bank_account' => $lottery->bank_account,
                'bank_name' => $lottery->bank_name,
                'location' => $lottery->location,
                'price_per_ticket' => $lottery->price_per_ticket,
                'max_tickets_per_transaction' => $lottery->max_tickets_per_transaction ?? 15,
                'is_finished' => $lottery->isFinished(),
                'starts_at' => $lottery->starts_at->format('Y-m-d H:i'),
                'draws_at' => $lottery->draws_at->format('Y-m-d H:i'),
            ],
        ]);
    }
}
