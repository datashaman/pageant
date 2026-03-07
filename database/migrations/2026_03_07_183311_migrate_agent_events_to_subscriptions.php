<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('agents')->whereNotNull('events')->cursor()->each(function ($agent) {
            $events = json_decode($agent->events, true);

            if (! is_array($events)) {
                return;
            }

            $alreadyMigrated = collect($events)->every(fn ($e) => is_array($e) && isset($e['event']));

            if ($alreadyMigrated) {
                return;
            }

            $migrated = collect($events)->map(function ($event) {
                if (is_string($event)) {
                    return ['event' => $event, 'filters' => []];
                }

                return $event;
            })->values()->toArray();

            DB::table('agents')->where('id', $agent->id)->update([
                'events' => json_encode($migrated),
            ]);
        });
    }

    public function down(): void
    {
        DB::table('agents')->whereNotNull('events')->cursor()->each(function ($agent) {
            $events = json_decode($agent->events, true);

            if (! is_array($events)) {
                return;
            }

            $reverted = collect($events)->map(function ($event) {
                if (is_array($event) && isset($event['event'])) {
                    $key = $event['event'];

                    if (str_contains($key, '.')) {
                        [$key] = explode('.', $key, 2);
                    }

                    return $key;
                }

                return $event;
            })->unique()->values()->toArray();

            DB::table('agents')->where('id', $agent->id)->update([
                'events' => json_encode($reverted),
            ]);
        });
    }
};
