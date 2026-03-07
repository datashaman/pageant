<?php

use App\Ai\EventSubscription;

// --- fromArray / toArray ---

it('parses event type without action', function () {
    $sub = EventSubscription::fromArray(['event' => 'issues', 'filters' => []]);

    expect($sub->eventType)->toBe('issues')
        ->and($sub->action)->toBeNull()
        ->and($sub->filters)->toBe([]);
});

it('parses event type with action', function () {
    $sub = EventSubscription::fromArray(['event' => 'issues.opened', 'filters' => ['labels' => ['bug']]]);

    expect($sub->eventType)->toBe('issues')
        ->and($sub->action)->toBe('opened')
        ->and($sub->filters)->toBe(['labels' => ['bug']]);
});

it('serializes to array', function () {
    $sub = new EventSubscription('pull_request', 'opened', ['base_branch' => 'main']);

    expect($sub->toArray())->toBe([
        'event' => 'pull_request.opened',
        'filters' => ['base_branch' => 'main'],
    ]);
});

it('serializes bare event type to array', function () {
    $sub = new EventSubscription('push', null, []);

    expect($sub->toArray())->toBe([
        'event' => 'push',
        'filters' => [],
    ]);
});

// --- matches: event type ---

it('matches same event type', function () {
    $sub = EventSubscription::fromArray(['event' => 'issues', 'filters' => []]);

    expect($sub->matches('issues', 'opened'))->toBeTrue();
});

it('does not match different event type', function () {
    $sub = EventSubscription::fromArray(['event' => 'issues', 'filters' => []]);

    expect($sub->matches('push', null))->toBeFalse();
});

// --- matches: action filtering ---

it('wildcard action matches any action', function () {
    $sub = EventSubscription::fromArray(['event' => 'issues', 'filters' => []]);

    expect($sub->matches('issues', 'opened'))->toBeTrue()
        ->and($sub->matches('issues', 'closed'))->toBeTrue()
        ->and($sub->matches('issues', 'labeled'))->toBeTrue();
});

it('specific action only matches that action', function () {
    $sub = EventSubscription::fromArray(['event' => 'issues.opened', 'filters' => []]);

    expect($sub->matches('issues', 'opened'))->toBeTrue()
        ->and($sub->matches('issues', 'closed'))->toBeFalse();
});

// --- matches: label filter ---

it('label filter matches when context has matching label', function () {
    $sub = EventSubscription::fromArray(['event' => 'issues.opened', 'filters' => ['labels' => ['bug']]]);

    expect($sub->matches('issues', 'opened', ['labels' => ['bug', 'priority']]))->toBeTrue();
});

it('label filter does not match when context has no matching label', function () {
    $sub = EventSubscription::fromArray(['event' => 'issues.opened', 'filters' => ['labels' => ['security']]]);

    expect($sub->matches('issues', 'opened', ['labels' => ['bug']]))->toBeFalse();
});

it('label filter does not match when context has no labels', function () {
    $sub = EventSubscription::fromArray(['event' => 'issues.opened', 'filters' => ['labels' => ['bug']]]);

    expect($sub->matches('issues', 'opened', ['labels' => []]))->toBeFalse()
        ->and($sub->matches('issues', 'opened', []))->toBeFalse();
});

it('label filter is case insensitive', function () {
    $sub = EventSubscription::fromArray(['event' => 'issues.opened', 'filters' => ['labels' => ['Bug']]]);

    expect($sub->matches('issues', 'opened', ['labels' => ['bug']]))->toBeTrue();
});

// --- matches: base_branch filter ---

it('base_branch filter matches exact branch', function () {
    $sub = EventSubscription::fromArray(['event' => 'pull_request.opened', 'filters' => ['base_branch' => 'main']]);

    expect($sub->matches('pull_request', 'opened', ['base_branch' => 'main']))->toBeTrue();
});

it('base_branch filter does not match different branch', function () {
    $sub = EventSubscription::fromArray(['event' => 'pull_request.opened', 'filters' => ['base_branch' => 'main']]);

    expect($sub->matches('pull_request', 'opened', ['base_branch' => 'develop']))->toBeFalse();
});

// --- matches: branches filter (glob) ---

it('branches filter matches exact branch name', function () {
    $sub = EventSubscription::fromArray(['event' => 'push', 'filters' => ['branches' => ['main']]]);

    expect($sub->matches('push', null, ['branch' => 'main']))->toBeTrue();
});

it('branches filter matches glob pattern', function () {
    $sub = EventSubscription::fromArray(['event' => 'push', 'filters' => ['branches' => ['release/*']]]);

    expect($sub->matches('push', null, ['branch' => 'release/v1.0']))->toBeTrue();
});

it('branches filter does not match non-matching branch', function () {
    $sub = EventSubscription::fromArray(['event' => 'push', 'filters' => ['branches' => ['main']]]);

    expect($sub->matches('push', null, ['branch' => 'feature/foo']))->toBeFalse();
});

it('branches filter does not match when no branch in context', function () {
    $sub = EventSubscription::fromArray(['event' => 'push', 'filters' => ['branches' => ['main']]]);

    expect($sub->matches('push', null, []))->toBeFalse();
});

it('branches filter matches any of multiple patterns', function () {
    $sub = EventSubscription::fromArray(['event' => 'push', 'filters' => ['branches' => ['main', 'release/*']]]);

    expect($sub->matches('push', null, ['branch' => 'main']))->toBeTrue()
        ->and($sub->matches('push', null, ['branch' => 'release/v2']))->toBeTrue()
        ->and($sub->matches('push', null, ['branch' => 'feature/x']))->toBeFalse();
});

// --- matches: combined filters (AND logic) ---

it('all filters must match (AND)', function () {
    $sub = EventSubscription::fromArray(['event' => 'pull_request.opened', 'filters' => [
        'labels' => ['bug'],
        'base_branch' => 'main',
    ]]);

    // Both match
    expect($sub->matches('pull_request', 'opened', ['labels' => ['bug'], 'base_branch' => 'main']))->toBeTrue();

    // Only labels match
    expect($sub->matches('pull_request', 'opened', ['labels' => ['bug'], 'base_branch' => 'develop']))->toBeFalse();

    // Only base_branch matches
    expect($sub->matches('pull_request', 'opened', ['labels' => ['feature'], 'base_branch' => 'main']))->toBeFalse();
});

// --- matches: no filters ---

it('no filters means match on event+action only', function () {
    $sub = EventSubscription::fromArray(['event' => 'push', 'filters' => []]);

    expect($sub->matches('push', null, ['branch' => 'anything']))->toBeTrue();
});
