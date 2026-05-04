<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformSettingsValueTextAndJobs extends Migration
{
    public function up()
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE platform_settings MODIFY value TEXT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite: recreate minimal — skip if not needed for dev
        } else {
            Schema::table('platform_settings', function (Blueprint $table) {
                $table->text('value')->nullable()->change();
            });
        }

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('jobs');
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE platform_settings MODIFY value VARCHAR(255) NULL');
        }
    }
}
