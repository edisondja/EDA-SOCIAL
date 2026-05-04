<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPlatformFieldsToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 80)->unique()->after('name');
            $table->unsignedBigInteger('role_id')->nullable()->after('username');
            $table->string('avatar_url', 255)->nullable()->after('email_verified_at');
            $table->enum('status', ['active', 'banned', 'pending'])->default('active')->after('avatar_url');
            $table->text('ban_reason')->nullable()->after('status');
            $table->timestamp('banned_until')->nullable()->after('ban_reason');
            $table->string('api_token', 80)->nullable()->unique()->after('remember_token');

            $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn([
                'username',
                'role_id',
                'avatar_url',
                'status',
                'ban_reason',
                'banned_until',
                'api_token',
            ]);
        });
    }
}
