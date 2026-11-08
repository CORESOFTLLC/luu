<?php

use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\LotteryController as AdminLotteryController;
use App\Http\Controllers\Admin\LotteryInvalidationController;
use App\Http\Controllers\Admin\LotteryValidPeriodController;
use App\Http\Controllers\LotteryController;
use App\Http\Controllers\LotteryTicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LotteryController::class, 'index'])->name('home');
Route::get('/lotteries/{lottery}', [LotteryController::class, 'show'])->name('lotteries.show');
Route::post('/lotteries/{lottery}/check', [LotteryTicketController::class, 'check'])->name('lotteries.check');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/admin/lotteries')->name('dashboard');
});

Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('lotteries', [AdminLotteryController::class, 'index'])->name('lotteries.index');
    Route::get('lotteries/create', [AdminLotteryController::class, 'create'])->name('lotteries.create');
    Route::post('lotteries', [AdminLotteryController::class, 'store'])->name('lotteries.store');
    Route::get('lotteries/{lottery}/edit', [AdminLotteryController::class, 'edit'])->name('lotteries.edit');
    Route::put('lotteries/{lottery}', [AdminLotteryController::class, 'update'])->name('lotteries.update');
    Route::delete('lotteries/{lottery}', [AdminLotteryController::class, 'destroy'])->name('lotteries.destroy');
    Route::post('lotteries/{lottery}/toggle', [AdminLotteryController::class, 'toggle'])->name('lotteries.toggle');
    Route::post('lotteries/{lottery}/images', [AdminLotteryController::class, 'uploadImages'])->name('lotteries.images.store');
    Route::delete('lotteries/images/{lotteryImage}', [AdminLotteryController::class, 'deleteImage'])->name('lotteries.images.destroy');
    Route::get('lotteries/{lottery}/import', [ImportController::class, 'show'])->name('lotteries.import');
    Route::post('lotteries/{lottery}/import', [ImportController::class, 'store'])->name('lotteries.import.store');
    Route::delete('lotteries/{lottery}/import', [ImportController::class, 'destroy'])->name('lotteries.import.destroy');
    Route::get('lotteries/{lottery}/bank', [ImportController::class, 'bankIndex'])->name('lotteries.bank.index');
    Route::get('lotteries/{lottery}/tickets/print', [ImportController::class, 'printTickets'])->name('lotteries.tickets.print');
    Route::get('lotteries/{lottery}/tickets', [ImportController::class, 'ticketsIndex'])->name('lotteries.tickets.index');
    Route::post('lotteries/{lottery}/bank/{lotteryBank}/toggle-exclude', [ImportController::class, 'toggleExclude'])->name('lotteries.bank.toggle-exclude');
    Route::post('lotteries/{lottery}/transactions/{transaction}/toggle-exclude', [ImportController::class, 'toggleTransactionExclude'])->name('lotteries.transactions.toggle-exclude');

    Route::middleware('can:manage-lottery-invalidations')->group(function () {
        Route::get('lotteries/{lottery}/invalidations/preview', [LotteryInvalidationController::class, 'preview'])->name('lotteries.invalidations.preview');
        Route::post('lotteries/{lottery}/invalidations', [LotteryInvalidationController::class, 'store'])->name('lotteries.invalidations.store');
        Route::put('lotteries/{lottery}/valid-period', [LotteryValidPeriodController::class, 'update'])->name('lotteries.valid-period.update');
    });
});

require __DIR__.'/settings.php';
