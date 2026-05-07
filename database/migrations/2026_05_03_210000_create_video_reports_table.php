<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVideoReportsTable extends Migration
{
    public function up()
    {
        Schema::create('video_reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('video_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('reason', 64);
            $table->text('details')->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestamps();

            $table->foreign('video_id')->references('id')->on('videos')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'created_at']);
            $table->index(['video_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('video_reports');
    }
}
