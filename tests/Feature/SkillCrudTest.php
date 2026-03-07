<?php

use App\Models\Organization;
use App\Models\Skill;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->user->update(['current_organization_id' => $this->organization->id]);
    $this->skill = Skill::factory()->for($this->organization)->create();
});

it('shows the skills index page', function () {
    $this->actingAs($this->user)
        ->get(route('skills.index'))
        ->assertOk()
        ->assertSee($this->skill->name);
});

it('can create a skill', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::skills.create')
        ->set('name', 'test-skill')
        ->set('description', 'A test skill')
        ->set('enabled', true)
        ->set('allowedToolsText', 'read, write')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $skill = Skill::where('name', 'test-skill')->first();
    expect($skill)->not->toBeNull()
        ->and($skill->organization_id)->toBe($this->organization->id)
        ->and($skill->allowed_tools)->toBe(['read', 'write']);
});

it('validates required fields on create', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::skills.create')
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('shows the skill detail page', function () {
    $this->actingAs($this->user)
        ->get(route('skills.show', $this->skill))
        ->assertOk()
        ->assertSee($this->skill->name);
});

it('can update a skill', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::skills.edit', ['skill' => $this->skill])
        ->set('name', 'updated-skill')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    expect($this->skill->fresh()->name)->toBe('updated-skill');
});

it('can delete a skill', function () {
    Livewire\Livewire::actingAs($this->user)
        ->test('pages::skills.index')
        ->call('delete', $this->skill->id);

    $this->assertDatabaseMissing('skills', [
        'id' => $this->skill->id,
    ]);
});

it('prevents access to skills from other organizations', function () {
    $otherOrg = Organization::factory()->create();
    $otherSkill = Skill::factory()->for($otherOrg)->create();

    $this->actingAs($this->user)
        ->get(route('skills.show', $otherSkill))
        ->assertForbidden();
});

it('requires authentication for index', function () {
    $this->get(route('skills.index'))
        ->assertRedirect(route('login'));
});
