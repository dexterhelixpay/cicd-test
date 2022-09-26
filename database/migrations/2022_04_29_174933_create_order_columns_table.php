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
        Schema::create('table_columns', function (Blueprint $table) {
            $table->id();
            $table->string('table');
            $table->string('text')->nullable();
            $table->string('value')->nullable();
            $table->string('width')->nullable();
            $table->string('align')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('sortable')->default(true);
            $table->unsignedBigInteger('sort');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('table_columns');
    }
};
