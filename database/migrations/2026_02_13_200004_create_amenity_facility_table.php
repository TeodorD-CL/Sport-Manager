<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amenity_facility', function (Blueprint $table) {
            $table->uuid('facility_id');
            $table->uuid('amenity_id');
            $table->primary(['facility_id', 'amenity_id']);
            $table->foreign('facility_id')->references('id')->on('facilities')->onDelete('cascade');
            $table->foreign('amenity_id')->references('id')->on('amenities')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenity_facility');
    }
};
