<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVideoDailyViewsTable extends Migration
{
    public function up()
    {
        Schema::create('video_daily_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('video_id');
            $table->date('stat_date');
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();

            $table->unique(['video_id', 'stat_date']);
            $table->foreign('video_id')->references('id')->on('videos')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('video_daily_views');
    }
}
