<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RecreateCandidatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('candidates2', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('author_id1');
            $table->unsignedBigInteger('author_id2');
            $table->foreign('author_id1')->references('id')->on('authors');
            $table->foreign('author_id2')->references('id')->on('authors');
            $table->integer('label')->nullable();
            $table->decimal('measure1', 8, 2)->nullable();
            $table->decimal('measure2', 8, 2)->nullable();
            $table->decimal('measure3', 8, 2)->nullable();
            $table->decimal('measure4', 8, 2)->nullable();
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
        Schema::dropIfExists('candidates2');
    }
}
