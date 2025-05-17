<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarwashClientsTable extends Migration
{
    public function up()
    {
        Schema::create('carwash_clients', function (Blueprint $table) {
            $table->id();
            $table->string('short_name');
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            $table->char('unp', 9);
            $table->string('bank_account_number');
            $table->string('bank_bic');
            $table->enum('status', ['active', 'inactive', 'blocked'])->default('inactive');
            $table->boolean('invoice_email_required')->default(false);
            $table->date('invoice_email_date')->nullable();
            $table->string('postal_address');
            $table->string('bank_postal_address');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('carwash_clients');
    }
}
