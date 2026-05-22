<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facility_user', function (Blueprint $table) {
            $table->uuid('facility_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->primary(['facility_id', 'user_id']);
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facility_user');
    }
};
