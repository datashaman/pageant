<?php

namespace App\Ai;

class EventRegistry
{
    /** @var array<string, array{description: string, group: string}> */
    private const EVENT_MAP = [
        // Code
        'push' => ['description' => 'Code pushed to a branch', 'group' => 'Code'],

        // Issues
        'issues' => ['description' => 'Issue opened, closed, labeled, etc.', 'group' => 'Issues'],
        'issue_comment' => ['description' => 'Comment on an issue or PR', 'group' => 'Issues'],

        // Pull Requests
        'pull_request' => ['description' => 'PR opened, closed, synchronized, etc.', 'group' => 'Pull Requests'],
        'pull_request_review' => ['description' => 'Review submitted on a PR', 'group' => 'Pull Requests'],

        // Work Items
        'work_item_created' => ['description' => 'Work item created in Pageant', 'group' => 'Work Items'],
    ];

    /**
     * @return array<string, string>
     */
    public static function available(): array
    {
        return array_map(fn (array $entry) => $entry['description'], self::EVENT_MAP);
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::EVENT_MAP as $name => $entry) {
            $groups[$entry['group']][$name] = $entry['description'];
        }

        return $groups;
    }
}
