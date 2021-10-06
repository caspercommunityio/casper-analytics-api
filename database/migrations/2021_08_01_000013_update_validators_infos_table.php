<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateValidatorsInfosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::table('validators', function (Blueprint $table) {
            //
            $table->json('infos')->nullable()->after('delegationRate');
            ;
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
        Schema::table('validators', function (Blueprint $table) {
            //
            $table->dropColumn('infos');
        });
    }
}
