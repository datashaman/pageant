<?php

use App\Models\User;

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

test('dashboard cards link to their respective index pages', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    $content = $response->getContent();

    expect($content)
        ->toContain(route('projects.index'))
        ->toContain(route('repos.index'))
        ->toContain(route('work-items.index'))
        ->toContain(route('agents.index'))
        ->toContain(route('skills.index'));
});

test('dashboard cards display icons', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    $content = $response->getContent();

    preg_match_all('/<svg\b/', $content, $svgMatches);

    expect(count($svgMatches[0]))->toBeGreaterThanOrEqual(5, 'Expected at least 5 SVG icons on the dashboard cards');
});
