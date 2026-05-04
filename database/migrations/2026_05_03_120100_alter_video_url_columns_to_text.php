<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlterVideoUrlColumnsToText extends Migration
{
    public function up()
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }
        DB::statement('ALTER TABLE videos MODIFY video_url TEXT NOT NULL');
        DB::statement('ALTER TABLE videos MODIFY preview_url TEXT NULL');
        DB::statement('ALTER TABLE videos MODIFY thumbnail_url TEXT NULL');
        DB::statement('ALTER TABLE video_media MODIFY url TEXT NOT NULL');
    }

    public function down()
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }
        DB::statement('ALTER TABLE video_media MODIFY url VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE videos MODIFY thumbnail_url VARCHAR(255) NULL');
        DB::statement('ALTER TABLE videos MODIFY preview_url VARCHAR(255) NULL');
        DB::statement('ALTER TABLE videos MODIFY video_url VARCHAR(255) NOT NULL');
    }
}
