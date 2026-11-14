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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('transfer_identity', 64)->nullable()->after('bank_reference');

            // Not led by lottery_id: MySQL would otherwise drop the implicit index of the
            // existing lottery_id foreign key in favour of this one.
            $table->index(['transfer_identity', 'lottery_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['transfer_identity', 'lottery_id']);
            $table->dropColumn('transfer_identity');
        });
    }
};
