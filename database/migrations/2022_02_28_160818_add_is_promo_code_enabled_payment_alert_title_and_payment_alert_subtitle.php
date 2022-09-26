<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsPromoCodeEnabledPaymentAlertTitleAndPaymentAlertSubtitle extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->text('payment_alert_title')->after('shipped_email_subheader_text')->nullable();
            $table->text('payment_alert_subtitle')->after('payment_alert_title')->nullable();
            $table->boolean('is_promo_code_enabled')->default(true);
            $table->text('late_payment_subject_text')->after('console_created_email_headline_text')->nullable();
            $table->text('incoming_payment_subject_text')->after('late_payment_subheader_text')->nullable();
            $table->text('incoming_payment_subheader_text')->after('incoming_payment_subject_text')->nullable();
            $table->text('incoming_payment_headline_text')->after('incoming_payment_subheader_text')->nullable();
            $table->text('due_payment_subject_text')->after('incoming_payment_headline_text')->nullable();
            $table->text('due_payment_subheader_text')->after('due_payment_subject_text')->nullable();
            $table->text('due_payment_headline_text')->after('due_payment_subheader_text')->nullable();
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
            $table->dropColumn('payment_alert_title');
            $table->dropColumn('payment_alert_subtitle');
            $table->dropColumn('is_promo_code_enabled');
            $table->dropColumn('late_payment_subject_text');
            $table->dropColumn('incoming_payment_subject_text');
            $table->dropColumn('incoming_payment_subheader_text');
            $table->dropColumn('incoming_payment_headline_text');
            $table->dropColumn('due_payment_subject_text');
            $table->dropColumn('due_payment_subheader_text');
            $table->dropColumn('due_payment_headline_text');
        });
    }
}
