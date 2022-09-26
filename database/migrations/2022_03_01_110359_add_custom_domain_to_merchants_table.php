<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomDomainToMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->string('custom_domain')->nullable()->after('subdomain');
            $table->boolean('is_custom_domain_used')->default(false)->after('are_multiple_products_selectable');
            $table->timestamp('custom_domain_verified_at')->nullable()->after('is_custom_fields_enabled');
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
            $table->dropColumn(['custom_domain', 'is_custom_domain_used', 'custom_domain_verified_at']);
        });
    }
}
