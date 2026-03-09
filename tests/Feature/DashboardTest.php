<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard cards are clickable links to their respective index pages', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test('pages::dashboard');

    $html = $component->html();

    $expectedRoutes = [
        route('projects.index'),
        route('repos.index'),
        route('work-items.index'),
        route('agents.index'),
        route('skills.index'),
    ];

    foreach ($expectedRoutes as $route) {
        expect($html)->toContain('href="'.$route.'"');
    }
});

test('dashboard cards have hover effects', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test('pages::dashboard');

    $html = $component->html();

    preg_match_all('/<a\b[^>]*class="[^"]*hover:border-zinc-300[^"]*hover:shadow-sm[^"]*"/', $html, $matches);

    expect(count($matches[0]))->toBe(5, 'Expected 5 dashboard card links with hover effects');
});

test('dashboard cards display icons', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test('pages::dashboard');

    $html = $component->html();

    preg_match_all('/<svg\b/', $html, $svgMatches);

    expect(count($svgMatches[0]))->toBe(5, 'Expected exactly 5 SVG icons for the 5 dashboard cards');
});

test('work item count shows only open items', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();
    $user->organizations()->attach($organization);
    $user->update(['current_organization_id' => $organization->id]);

    WorkItem::factory()->for($organization)->create(['status' => 'open']);
    WorkItem::factory()->for($organization)->closed()->create();
    WorkItem::factory()->for($organization)->closed()->create();

    $component = Livewire::actingAs($user)->test('pages::dashboard');

    expect($component->instance()->workItemCount)->toBe(1);
});
