<?php

use App\Ai\EventRegistry;

describe('EventRegistry', function () {
    it('groups events by category', function () {
        $categories = EventRegistry::groupedByCategory();

        expect($categories)->toHaveKeys(['github', 'pageant']);
    });

    it('places GitHub events under the github category', function () {
        $categories = EventRegistry::groupedByCategory();
        $githubGroups = $categories['github'];

        expect($githubGroups)->toHaveKeys(['Code', 'Issues', 'Pull Requests']);

        $allEventNames = collect($githubGroups)->flatMap(fn ($group) => array_keys($group))->all();
        expect($allEventNames)->toContain('push', 'issues', 'issue_comment', 'pull_request', 'pull_request_review');
    });

    it('places Pageant events under the pageant category', function () {
        $categories = EventRegistry::groupedByCategory();
        $pageantGroups = $categories['pageant'];

        expect($pageantGroups)->toHaveKeys(['Plans']);

        $allEventNames = collect($pageantGroups)->flatMap(fn ($group) => array_keys($group))->all();
        expect($allEventNames)->toContain('plan_completed', 'plan_failed');
    });

    it('includes actions and filters in category details', function () {
        $categories = EventRegistry::groupedByCategory();
        $issues = $categories['github']['Issues']['issues'];

        expect($issues)
            ->toHaveKeys(['label', 'description', 'actions', 'filters'])
            ->and($issues['actions'])->toContain('opened', 'closed')
            ->and($issues['filters'])->toContain('labels');
    });
});
