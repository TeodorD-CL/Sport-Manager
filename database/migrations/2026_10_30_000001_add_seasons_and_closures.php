<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A court with no season is open all year. May–September is 5 and 9; November–March (11, 3) wraps over New Year.
        Schema::table('courts', function (Blueprint $table) {
            $table->unsignedTinyInteger('season_start_month')->nullable()->after('image_path');
            $table->unsignedTinyInteger('season_end_month')->nullable()->after('season_start_month');
        });

        // Times the venue can't take bookings: the whole facility when court_id is null, otherwise one court.
        Schema::create('closures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('facility_id');
            $table->uuid('court_id')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->foreign('facility_id')->references('id')->on('facilities')->cascadeOnDelete();
            $table->foreign('court_id')->references('id')->on('courts')->cascadeOnDelete();
            $table->index(['facility_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('closures');
        Schema::table('courts', function (Blueprint $table) {
            $table->dropColumn(['season_start_month', 'season_end_month']);
        });
    }
};
