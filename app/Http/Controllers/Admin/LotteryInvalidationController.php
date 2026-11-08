<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewLotteryInvalidationRequest;
use App\Http\Requests\Admin\StoreLotteryInvalidationRequest;
use App\Models\Lottery;
use App\Services\LotteryInvalidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LotteryInvalidationController extends Controller
{
    public function preview(PreviewLotteryInvalidationRequest $request, Lottery $lottery, LotteryInvalidationService $invalidations): JsonResponse
    {
        return response()->json(
            $invalidations->preview($lottery, $request->validated('starts_on'), $request->validated('ends_on')),
        );
    }

    public function store(StoreLotteryInvalidationRequest $request, Lottery $lottery, LotteryInvalidationService $invalidations): RedirectResponse
    {
        $period = $invalidations->invalidate(
            $lottery,
            $request->user(),
            $request->validated('starts_on'),
            $request->validated('ends_on'),
            $request->validated('note'),
            (int) $request->validated('expected_transactions'),
            (int) $request->validated('expected_tickets'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$period->transactions_count} гүйлгээ, {$period->tickets_count} сугалаа хүчингүй боллоо.",
        ]);

        return back();
    }
}
