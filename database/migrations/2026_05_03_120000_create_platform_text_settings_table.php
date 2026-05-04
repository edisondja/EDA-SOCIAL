<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePlatformTextSettingsTable extends Migration
{
    public function up()
    {
        Schema::create('platform_text_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->text('body')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('platform_text_settings');
    }
}
