<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddVideoseggPostIdToVideosTable extends Migration
{
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->unsignedBigInteger('videosegg_post_id')->nullable()->after('moderation_status');
            $table->unique('videosegg_post_id');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropUnique(['videosegg_post_id']);
            $table->dropColumn('videosegg_post_id');
        });
    }
}
