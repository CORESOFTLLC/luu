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
            $table->boolean("show_ticket_numbers")->default(false)->after("max_tickets_per_transaction");
        });
        // Lottery 1 already has numbered tickets; keep showing them.
        DB::table("lotteries")->where("id", 1)->update(["show_ticket_numbers" => true]);
    }

    public function down(): void
    {
        Schema::table("lotteries", fn (Blueprint $t) => $t->dropColumn("show_ticket_numbers"));
    }
};
