<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("lotteries", function (Blueprint $table) {
            // NULL = no per-transaction cap
            $table->unsignedInteger("max_tickets_per_transaction")->nullable()->default(15)->after("price_per_ticket");
        });
        Schema::table("transactions", function (Blueprint $table) {
            $table->unsignedInteger("refund_amount")->default(0)->after("amount");
        });
        // Existing lottery 1 keeps its original behaviour (no cap).
        DB::table("lotteries")->where("id", "!=", 2)->update(["max_tickets_per_transaction" => null]);
    }

    public function down(): void
    {
        Schema::table("transactions", fn (Blueprint $t) => $t->dropColumn("refund_amount"));
        Schema::table("lotteries", fn (Blueprint $t) => $t->dropColumn("max_tickets_per_transaction"));
    }
};
