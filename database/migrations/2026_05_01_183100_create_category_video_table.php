<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCategoryVideoTable extends Migration
{
    public function up()
    {
        Schema::create('category_video', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('video_id');
            $table->timestamps();

            $table->unique(['category_id', 'video_id']);
            $table->index(['video_id', 'category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('video_id')->references('id')->on('videos')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('category_video');
    }
}
