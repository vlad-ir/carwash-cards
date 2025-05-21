<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarwashBonusCardStatsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('carwash_bonus_card_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('carwash_bonus_cards')->onDelete('cascade');
            $table->dateTime('start_time');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('remaining_balance_seconds')->nullable();
            $table->date('import_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carwash_bonus_card_stats');
    }
};
