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
            $table->unsignedBigInteger('owner_id');
            $table->string('path');
            $table->longText('name');
            $table->dateTime('created_at')->nullable();
            $table->foreign('owner_id', 'owner_file_id')->references('id')->on('users')->onDelete('cascade');
            $table->primary(['owner_id', 'path']);
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
