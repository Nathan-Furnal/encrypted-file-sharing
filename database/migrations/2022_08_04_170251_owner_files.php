<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->longText('name');
            $table->string('file_ext');
            $table->dateTime('created_at')->nullable();
            $table->foreign('owner_id', 'owner_file_id')->references('id')->on('users')->onDelete('cascade');
            $table->longText('enc_key');
            $table->longText('signature');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('files');
    }
};
