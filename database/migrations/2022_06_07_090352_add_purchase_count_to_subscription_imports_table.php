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
        Schema::table('subscription_imports', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_count')->default(0)->after('links_opened_count');
            $table->string('purchase_percentage')->nullable()->after('purchase_count');
            $table->string('open_percentage')->nullable()->after('purchase_percentage');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subscription_imports', function (Blueprint $table) {
            $table->dropColumn([
                'purchase_count',
                'purchase_percentage',
                'open_percentage'
            ]);
        });
    }
};
