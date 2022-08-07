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
            $table->string('path');
            $table->longText('name');
            $table->foreign('owner_id', 'owner')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('friend_id', 'friend')->references('id')->on('users')->onDelete('cascade');
            $table->foreign(['owner_id', 'path'], 'owner_and_file')->references(['owner_id', 'path'])->on('files')->onDelete('cascade');
            $table->unique(array('owner_id', 'friend_id', 'path'));
            $keys = array('owner_id', 'friend_id', 'path');
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
