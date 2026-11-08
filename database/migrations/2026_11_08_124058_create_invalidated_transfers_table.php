<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invalidated_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lottery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lottery_invalidation_period_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 16);
            $table->string('bank_reference');
            $table->string('transfer_identity', 64)->nullable();
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('amount');
            $table->dateTime('transacted_at');
            $table->timestamps();

            $table->index(['lottery_id', 'bank_reference']);
            $table->index(['lottery_id', 'transfer_identity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invalidated_transfers');
    }
};
