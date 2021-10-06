<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePeersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('peers', function (Blueprint $table) {
            $table->string('ip', 100);
            $table->string('publicKey', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('countryCode', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('latitude', $precision = 15, $scale = 4)->nullable();
            $table->decimal('longitude', $precision = 15, $scale = 4)->nullable();
            $table->boolean('deleted')->default(false)->nullable();

            $table->primary(['ip']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('peers');
    }
}
