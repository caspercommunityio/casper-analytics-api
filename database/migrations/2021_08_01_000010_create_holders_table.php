<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHoldersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('holders', function (Blueprint $table) {
            $table->string('publicKey', 100);
            $table->string('accountHash', 100)->nullable();
            $table->string('mainPurse', 150)->nullable();
            $table->decimal('staking', $precision = 26, $scale = 6)->nullable();
            $table->decimal('balance', $precision = 26, $scale = 6)->nullable();
            $table->boolean('processed')->default(false);
            $table->datetime('lastProcessed')->nullable();
            $table->primary(['publicKey']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('holders');
    }
}
