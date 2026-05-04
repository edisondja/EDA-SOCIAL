<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVideoMediaTable extends Migration
{
    public function up()
    {
        Schema::create('video_media', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_id');
            $table->enum('type', ['image', 'video']);
            $table->string('url', 255);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['video_id', 'position']);
            $table->foreign('video_id')->references('id')->on('videos')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('video_media');
    }
}
