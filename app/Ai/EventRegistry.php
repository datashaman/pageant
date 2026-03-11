<?php

namespace App\Ai;

class EventRegistry
{
    /** @var array<string, array{label: string, description: string, group: string, category: string, actions: string[], filters: string[]}> */
    private const EVENT_MAP = [
        // Code
        'push' => [
            'label' => 'Code',
            'description' => 'Pushed',
            'group' => 'Code',
            'category' => 'github',
            'actions' => [],
            'filters' => ['branches'],
        ],

        // Issues
        'issues' => [
            'label' => 'Issues',
            'description' => 'Issue opened, closed, labeled, etc.',
            'group' => 'Issues',
            'category' => 'github',
            'actions' => ['opened', 'edited', 'closed', 'reopened', 'labeled', 'unlabeled', 'assigned', 'unassigned', 'locked', 'unlocked', 'transferred', 'milestoned', 'demilestoned', 'pinned', 'unpinned', 'deleted'],
            'filters' => ['labels'],
        ],
        'issue_comment' => [
            'label' => 'Comments',
            'description' => 'Comment on an issue or PR',
            'group' => 'Issues',
            'category' => 'github',
            'actions' => ['created', 'edited', 'deleted'],
            'filters' => ['labels'],
        ],

        // Pull Requests
        'pull_request' => [
            'label' => 'Pull Requests',
            'description' => 'PR opened, closed, synchronized, etc.',
            'group' => 'Pull Requests',
            'category' => 'github',
            'actions' => ['opened', 'edited', 'closed', 'reopened', 'synchronize', 'labeled', 'unlabeled', 'review_requested', 'ready_for_review', 'converted_to_draft', 'locked', 'unlocked'],
            'filters' => ['labels', 'base_branch'],
        ],
        'pull_request_review' => [
            'label' => 'Reviews',
            'description' => 'Review submitted on a PR',
            'group' => 'Pull Requests',
            'category' => 'github',
            'actions' => ['submitted', 'edited', 'dismissed'],
            'filters' => ['labels', 'base_branch'],
        ],

        // Plans
        'plan_step_completed' => [
            'label' => 'Plans',
            'description' => 'Plan step completed successfully',
            'group' => 'Plans',
            'category' => 'pageant',
            'actions' => [],
            'filters' => [],
        ],
        'plan_step_failed' => [
            'label' => 'Plans',
            'description' => 'Plan step failed',
            'group' => 'Plans',
            'category' => 'pageant',
            'actions' => [],
            'filters' => [],
        ],
        'plan_completed' => [
            'label' => 'Plans',
            'description' => 'Plan completed successfully',
            'group' => 'Plans',
            'category' => 'pageant',
            'actions' => [],
            'filters' => [],
        ],
        'plan_failed' => [
            'label' => 'Plans',
            'description' => 'Plan failed',
            'group' => 'Plans',
            'category' => 'pageant',
            'actions' => [],
            'filters' => [],
        ],
        'plan_step_partial' => [
            'label' => 'Plans',
            'description' => 'Plan step partially completed due to limits',
            'group' => 'Plans',
            'category' => 'pageant',
            'actions' => [],
            'filters' => [],
        ],
        'plan_limit_reached' => [
            'label' => 'Plans',
            'description' => 'Plan reached execution limits with partial progress',
            'group' => 'Plans',
            'category' => 'pageant',
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

    /**
     * @return array<string, array<string, array<string, array{label: string, description: string, actions: string[], filters: string[]}>>>
     */
    public static function groupedByCategory(): array
    {
        $categories = [];

        foreach (self::EVENT_MAP as $name => $entry) {
            $category = $entry['category'];
            $categories[$category][$entry['group']][$name] = [
                'label' => $entry['label'],
                'description' => $entry['description'],
                'actions' => $entry['actions'],
                'filters' => $entry['filters'],
            ];
        }

        return $categories;
    }
}
