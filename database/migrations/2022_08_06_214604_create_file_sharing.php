<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('file_sharing', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('friend_id');
            $table->unsignedBigInteger('file_id');
            $table->longText('enc_key');
            $table->foreign('owner_id', 'owner')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('friend_id', 'friend')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('file_id', 'file')->references('id')->on('files')->onDelete('cascade');
            $table->unique(array('owner_id', 'friend_id', 'file_id'));
            $keys = array('owner_id', 'friend_id', 'file_id');
            $table->primary($keys);
        });
        DB::statement('ALTER TABLE file_sharing ADD CONSTRAINT owner_diff_than_friend CHECK ("owner_id" != "friend_id")');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('file_sharing');
    }
};
