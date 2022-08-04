<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddFriends extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('friendships', function (Blueprint $table) {
            // $table->integer('id');
            $table->unsignedBigInteger('from_id');
            $table->unsignedBigInteger('to_id');

            $table->enum('status', ['pending', 'confirmed', 'blocked']);
            $table->dateTime('created_at')->nullable();

            $table->foreign('from_id', 'friendship_from')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('to_id', 'friendship_to')->references('id')->on('users')->onDelete('cascade');
            $table->unique(array('to_id', 'from_id'));
            $keys = array('from_id', 'to_id');
            $table->primary($keys);
        });
        DB::statement('ALTER TABLE friendships ADD CONSTRAINT check_different CHECK ("from_id" != "to_id")');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('friendships');
    }
}
