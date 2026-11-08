<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * total_tickets = NULL means the lottery has no total limit (Хязгааргүй).
     */
    public function up(): void
    {
        Schema::table("lotteries", function (Blueprint $table) {
            $table->unsignedInteger("total_tickets")->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table("lotteries", function (Blueprint $table) {
            $table->unsignedInteger("total_tickets")->nullable(false)->default(0)->change();
        });
    }
};
