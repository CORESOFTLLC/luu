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
        Schema::create('lottery_invalidation_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lottery_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('note');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->unsignedInteger('transactions_count')->default(0);
            $table->unsignedInteger('tickets_count')->default(0);
            $table->unsignedBigInteger('amount_total')->default(0);
            $table->unsignedInteger('last_ticket_number')->default(0);
            $table->timestamps();

            $table->index(['lottery_id', 'starts_on', 'ends_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lottery_invalidation_periods');
    }
};
