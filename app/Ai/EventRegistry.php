<?php

namespace App\Ai;

class EventRegistry
{
    /** @var array<string, array{label: string, description: string, group: string, actions: string[], filters: string[]}> */
    private const EVENT_MAP = [
        // Code
        'push' => [
            'label' => 'Code',
            'description' => 'Pushed',
            'group' => 'Code',
            'actions' => [],
            'filters' => ['branches'],
        ],

        // Issues
        'issues' => [
            'label' => 'Issues',
            'description' => 'Issue opened, closed, labeled, etc.',
            'group' => 'Issues',
            'actions' => ['opened', 'edited', 'closed', 'reopened', 'labeled', 'unlabeled', 'assigned', 'unassigned', 'locked', 'unlocked', 'transferred', 'milestoned', 'demilestoned', 'pinned', 'unpinned', 'deleted'],
            'filters' => ['labels'],
        ],
        'issue_comment' => [
            'label' => 'Comments',
            'description' => 'Comment on an issue or PR',
            'group' => 'Issues',
            'actions' => ['created', 'edited', 'deleted'],
            'filters' => ['labels'],
        ],

        // Pull Requests
        'pull_request' => [
            'label' => 'Pull Requests',
            'description' => 'PR opened, closed, synchronized, etc.',
            'group' => 'Pull Requests',
            'actions' => ['opened', 'edited', 'closed', 'reopened', 'synchronize', 'labeled', 'unlabeled', 'review_requested', 'ready_for_review', 'converted_to_draft', 'locked', 'unlocked'],
            'filters' => ['labels', 'base_branch'],
        ],
        'pull_request_review' => [
            'label' => 'Reviews',
            'description' => 'Review submitted on a PR',
            'group' => 'Pull Requests',
            'actions' => ['submitted', 'edited', 'dismissed'],
            'filters' => ['labels', 'base_branch'],
        ],

        // Work Items
        'work_item_created' => [
            'label' => 'Work Items',
            'description' => 'Created',
            'group' => 'Work Items',
            'actions' => [],
            'filters' => [],
        ],
        'work_item_deleted' => [
            'label' => 'Work Items',
            'description' => 'Deleted',
            'group' => 'Work Items',
            'actions' => [],
            'filters' => [],
        ],
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

    /**
     * @return string[]
     */
    public static function actionsFor(string $eventType): array
    {
        return self::EVENT_MAP[$eventType]['actions'] ?? [];
    }

    /**
     * @return string[]
     */
    public static function filtersFor(string $eventType): array
    {
        return self::EVENT_MAP[$eventType]['filters'] ?? [];
    }

    /**
     * @return string[]
     */
    public static function allEventKeys(): array
    {
        $keys = [];

        foreach (self::EVENT_MAP as $name => $entry) {
            $keys[] = $name;

            foreach ($entry['actions'] as $action) {
                $keys[] = "{$name}.{$action}";
            }
        }

        return $keys;
    }

    public static function isValidEventKey(string $key): bool
    {
        return in_array($key, self::allEventKeys(), true);
    }

    /**
     * @return array<string, array<string, array{label: string, description: string, actions: string[], filters: string[]}>>
     */
    public static function groupedWithDetails(): array
    {
        $groups = [];

        foreach (self::EVENT_MAP as $name => $entry) {
            $groups[$entry['group']][$name] = [
                'label' => $entry['label'],
                'description' => $entry['description'],
                'actions' => $entry['actions'],
                'filters' => $entry['filters'],
            ];
        }

        return $groups;
    }
}
