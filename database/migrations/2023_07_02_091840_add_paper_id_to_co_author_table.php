<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaperIdToCoAuthorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('co_author', function (Blueprint $table) {
            $table->unsignedBigInteger('paper_id');
            $table->foreign('paper_id')->references('id')->on('papers');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('co_author', function (Blueprint $table) {
            $table->dropForeign(['paper_id']);
            $table->dropColumn('paper_id');
        });
    }
}
