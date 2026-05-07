<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVideoRatingsTable extends Migration
{
    public function up()
    {
        Schema::create('video_ratings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('video_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('score');
            $table->timestamps();

            $table->foreign('video_id')->references('id')->on('videos')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['video_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('video_ratings');
    }
}
