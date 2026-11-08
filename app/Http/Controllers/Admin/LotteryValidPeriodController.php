<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLotteryValidPeriodRequest;
use App\Models\Lottery;
use App\Services\LotteryInvalidationService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LotteryValidPeriodController extends Controller
{
    public function update(UpdateLotteryValidPeriodRequest $request, Lottery $lottery, LotteryInvalidationService $invalidations): RedirectResponse
    {
        $invalidations->updateValidPeriod($lottery, $request->validated('valid_from'), $request->validated('valid_until'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Хүчинтэй хугацаа хадгалагдлаа.',
        ]);

        return back();
    }
}
