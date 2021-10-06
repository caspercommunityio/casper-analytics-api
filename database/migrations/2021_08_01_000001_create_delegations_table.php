<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDelegationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    protected $primaryKey = 'blockHash';
    public function up()
    {
        Schema::create('delegations', function (Blueprint $table) {
            $table->string('blockHash', 100);
            $table->string('deployHash', 100);
            $table->string('method', 40);
            $table->string('delegator', 100);
            $table->string('validator', 100);
            $table->decimal('amount', $precision = 20, $scale = 2);
            $table->dateTime('deploymentDate');
            $table->text('message');

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
        Schema::dropIfExists('delegations');
    }
}
