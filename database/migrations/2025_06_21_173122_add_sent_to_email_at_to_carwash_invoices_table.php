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
        Schema::table('carwash_invoices', function (Blueprint $table) {
            $table->timestamp('sent_to_email_at')->nullable()->after('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('carwash_invoices', function (Blueprint $table) {
            $table->dropColumn('sent_to_email_at');
        });
    }
};
