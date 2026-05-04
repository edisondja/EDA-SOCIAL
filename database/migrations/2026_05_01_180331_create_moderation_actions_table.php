<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateModerationActionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('moderator_id');
            $table->string('target_type', 40);
            $table->unsignedBigInteger('target_id');
            $table->string('action', 80);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index(['moderator_id', 'created_at']);
            $table->foreign('moderator_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('moderation_actions');
    }
}
