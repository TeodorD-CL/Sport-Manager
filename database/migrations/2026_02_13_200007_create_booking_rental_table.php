<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_rental', function (Blueprint $table) {
            $table->uuid('booking_id');
            $table->uuid('rental_id');
            $table->integer('quantity')->default(1);
            $table->primary(['booking_id', 'rental_id']);
            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('rental_id')->references('id')->on('rentals')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_rental');
    }
};
