<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn([
                'late_payment_sms_text',
                'late_payment_viber_text',
                'due_payment_sms_text',
                'due_payment_viber_text',
                'incoming_payment_sms_text',
                'incoming_payment_viber_text'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->text('late_payment_sms_text')->after('late_payment_subject_text')->nullable();
            $table->text('late_payment_viber_text')->after('late_payment_sms_text')->nullable();

            $table->text('due_payment_sms_text')->after('due_payment_subject_text')->nullable();
            $table->text('due_payment_viber_text')->after('due_payment_sms_text')->nullable();

            $table->text('incoming_payment_sms_text')->after('incoming_payment_subject_text')->nullable();
            $table->text('incoming_payment_viber_text')->after('incoming_payment_sms_text')->nullable();
        });
    }
};
