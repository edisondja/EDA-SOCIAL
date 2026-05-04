<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVideosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id');
            $table->unsignedBigInteger('author_id');
            $table->string('title', 180);
            $table->string('slug', 220)->unique();
            $table->text('description')->nullable();
            $table->string('video_url', 255);
            $table->string('preview_url', 255)->nullable();
            $table->string('thumbnail_url', 255)->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('likes_count')->default(0);
            $table->unsignedBigInteger('dislikes_count')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->enum('moderation_status', ['active', 'blocked', 'review'])->default('active');
            $table->timestamps();

            $table->index(['is_published', 'published_at']);
            $table->index(['moderation_status', 'created_at']);
            $table->foreign('channel_id')->references('id')->on('channels')->onDelete('cascade');
            $table->foreign('author_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('videos');
    }
}
