<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

// Production is seeded once, so seeder fixes reach it through here. The demo descriptions promised courts that
// can't be booked (three padel courts in Prilep, several in Tetovo, pool lanes in Ohrid), and Ohrid's outdoor
// pool, described as open May–September, could be booked all year. Marketing phrases are toned down too.
return new class extends Migration
{
    private const TEXT = [
        ['Prilep Professional Arena', 'three outdoor padel courts', 'an outdoor padel court'],
        ['Prilep Professional Arena', "Prilep's top-tier venue, used by regional clubs for training. A professional hybrid-grass pitch, a championship hard tennis court", 'Used by regional clubs for training. A hybrid-grass pitch, a hard tennis court'],
        ['Tetovo Sports Complex', 'The padel courts are panoramic glass', 'The padel court is panoramic glass'],
        ['Tetovo Sports Complex', 'A modern complex under the Shar Mountains', 'A complex under the Shar Mountains'],
        ['Ohrid Aquatic Center', 'Lane bookings are by the hour', 'Each pool is booked whole, by the hour'],
        ['Ohrid Aquatic Center', 'Swim with a view of the lake. The center has an outdoor', 'By the lake, with an outdoor'],
    ];

    public function up(): void
    {
        foreach (self::TEXT as [$name, $from, $to]) {
            $facility = DB::table('facilities')->where('name', $name)->first();
            if ($facility && str_contains($facility->description, $from)) {
                DB::table('facilities')->where('id', $facility->id)->update(['description' => str_replace($from, $to, $facility->description)]);
            }
        }

        $ohrid = DB::table('facilities')->where('name', 'Ohrid Aquatic Center')->value('id');
        DB::table('courts')
            ->where('facility_id', $ohrid)
            ->where('name', 'Olympic Pool')
            ->whereNull('season_start_month')
            ->update(['season_start_month' => 5, 'season_end_month' => 9]);
    }

    public function down(): void
    {
    }
};
