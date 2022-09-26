<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMembershipColumnsToMerchantEmailBlastsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchant_email_blasts', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('merchant_id');
            $table->boolean('has_limited_availability')->default(false);
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->default(now());
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_email_blasts', function (Blueprint $table) {
            $table->dropColumn([
                'slug',
                'has_limited_availability',
                'is_published',
                'published_at'
            ]);
        });
    }
}
