<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStatsPerEraTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stats_per_era', function (Blueprint $table) {
            $table->string('validator', 100);
            $table->integer('eraId');
            $table->integer('position')->default(0);
            $table->decimal('csprStaked', $precision = 20, $scale = 2);
            $table->decimal('rewards', $precision = 20, $scale = 2);
            $table->string('delegators', 100);
            $table->decimal('apy', $precision = 10, $scale = 2);
            $table->text('message');

            $table->primary(['validator','eraId']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stats_per_era');
    }
}
