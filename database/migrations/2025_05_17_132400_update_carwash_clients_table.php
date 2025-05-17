<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('carwash_clients', function (Blueprint $table) {
            // Удаляем phone
            $table->dropColumn('phone');
            // Добавляем contract
            $table->string('contract')->nullable()->after('bank_postal_address');
            // Добавляем invoice_email_day
            $table->integer('invoice_email_day')->nullable()->after('invoice_email_required');
        });

        // Переносим данные из invoice_email_date
        DB::table('carwash_clients')
            ->whereNotNull('invoice_email_date')
            ->update([
                'invoice_email_day' => DB::raw('DAY(invoice_email_date)'),
            ]);

        // Удаляем invoice_email_date
        Schema::table('carwash_clients', function (Blueprint $table) {
            $table->dropColumn('invoice_email_date');
        });
    }

    public function down()
    {
        Schema::table('carwash_clients', function (Blueprint $table) {
            // Восстанавливаем invoice_email_date
            $table->date('invoice_email_date')->nullable()->after('invoice_email_required');
            // Удаляем invoice_email_day
            $table->dropColumn('invoice_email_day');
            // Удаляем contract
            $table->dropColumn('contract');
            // Восстанавливаем phone
            $table->string('phone')->nullable()->after('email');
        });

        // Восстанавливаем данные invoice_email_date
        DB::table('carwash_clients')
            ->whereNotNull('invoice_email_day')
            ->update([
                'invoice_email_date' => DB::raw('CONCAT(YEAR(CURDATE()), "-", MONTH(CURDATE()), "-", invoice_email_day)'),
            ]);
    }
};