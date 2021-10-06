<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateBlocksProcessedTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('blocks_processed', function (Blueprint $table) {
            //
            $table->integer('blockHeight')->nullable();
            $table->integer('deploys')->default(0);
            $table->integer('transfers')->default(0);
            $table->integer('eraId')->default(0);
            $table->boolean('switchBlock')->default(false);
            $table->boolean('processed')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::table('blocks_processed', function (Blueprint $table) {
            //
            $table->dropColumn('processed');
            $table->dropColumn('switchBlock');
            $table->dropColumn('deploys');
            $table->dropColumn('transfers');
            $table->dropColumn('blockHeight');
            $table->dropColumn('eraId');
        });
    }
}
