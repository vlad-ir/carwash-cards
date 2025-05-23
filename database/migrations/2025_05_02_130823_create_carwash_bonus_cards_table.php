<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarwashBonusCardsTable extends Migration
{
    public function up()
    {
        Schema::create('carwash_bonus_cards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('card_number')->unique();
            $table->enum('status', ['active', 'blocked'])->default('active');
            $table->decimal('rate_per_minute', 10, 2);
            $table->foreignId('client_id')->constrained('carwash_clients')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('carwash_bonus_cards');
    }
}
