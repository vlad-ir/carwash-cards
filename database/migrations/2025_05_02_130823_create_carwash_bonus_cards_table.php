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
            $table->decimal('discount_percentage', 5, 2);
            $table->time('balance')->default('00:00:00');
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('inactive');
            $table->string('car_license_plate')->nullable();
            $table->decimal('rate_per_minute', 10, 2);
            $table->boolean('invoice_required')->default(false);
            $table->foreignId('client_id')->constrained('carwash_clients')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('carwash_bonus_cards');
    }
}
