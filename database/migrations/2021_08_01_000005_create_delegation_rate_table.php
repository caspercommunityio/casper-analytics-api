<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDelegationRateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('delegation_rate', function (Blueprint $table) {
            $table->string('blockHash', 100);
            $table->string('deployHash', 100);
            $table->dateTime('deploymentDate');
            $table->string('validator', 100);
            $table->decimal('delegationRate', $precision = 10, $scale = 2);

            $table->primary(['blockHash','deployHash']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('delegation_rate');
    }
}
