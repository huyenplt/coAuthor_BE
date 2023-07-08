<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCoAuthorTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('co_author', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('author_id1');
            $table->unsignedBigInteger('author_id2');
            $table->foreign('author_id1')->references('id')->on('authors');
            $table->foreign('author_id2')->references('id')->on('authors');
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
        Schema::dropIfExists('co_author');
    }
}
