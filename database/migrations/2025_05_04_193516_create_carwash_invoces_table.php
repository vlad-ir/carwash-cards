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
        Schema::create('carwash_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('carwash_clients')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->integer('total_cards_count');
            $table->integer('active_cards_count');
            $table->integer('blocked_cards_count');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('file_path')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carwash_invoices');
    }
};
