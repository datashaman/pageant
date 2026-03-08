<?php

use App\Models\Agent;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Map old deprecated tool names to their new equivalents.
     *
     * @var array<string, string>
     */
    private const TOOL_MAPPING = [
        'get_file_contents' => 'read_file',
        'create_or_update_file' => 'write_file',
        'delete_file' => 'bash',
        'search_code' => 'grep',
        'get_repository_tree' => 'glob',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Agent::query()
            ->whereNotNull('tools')
            ->each(function (Agent $agent) {
                $tools = $agent->tools;
                $changed = false;

                foreach (self::TOOL_MAPPING as $oldName => $newName) {
                    $index = array_search($oldName, $tools);
                    if ($index !== false) {
                        unset($tools[$index]);
                        if (! in_array($newName, $tools)) {
                            $tools[] = $newName;
                        }
                        $changed = true;
                    }
                }

                if ($changed) {
                    $agent->update(['tools' => array_values($tools)]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Agent::query()
            ->whereNotNull('tools')
            ->each(function (Agent $agent) {
                $tools = $agent->tools;
                $changed = false;

                foreach (self::TOOL_MAPPING as $oldName => $newName) {
                    $index = array_search($newName, $tools);
                    if ($index !== false) {
                        unset($tools[$index]);
                        if (! in_array($oldName, $tools)) {
                            $tools[] = $oldName;
                        }
                        $changed = true;
                    }
                }

                if ($changed) {
                    $agent->update(['tools' => array_values($tools)]);
                }
            });
    }
};
