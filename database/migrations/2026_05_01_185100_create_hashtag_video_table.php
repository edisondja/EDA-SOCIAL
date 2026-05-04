<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHashtagVideoTable extends Migration
{
    public function up()
    {
        Schema::create('hashtag_video', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hashtag_id');
            $table->unsignedBigInteger('video_id');
            $table->timestamps();

            $table->unique(['hashtag_id', 'video_id']);
            $table->index(['video_id', 'hashtag_id']);
            $table->foreign('hashtag_id')->references('id')->on('hashtags')->onDelete('cascade');
            $table->foreign('video_id')->references('id')->on('videos')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('hashtag_video');
    }
}
