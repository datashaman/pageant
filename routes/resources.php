<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('agents', 'pages::agents.index')->name('agents.index');
    Route::livewire('agents/create', 'pages::agents.create')->name('agents.create');
    Route::livewire('agents/{agent}', 'pages::agents.show')->name('agents.show');
    Route::livewire('agents/{agent}/edit', 'pages::agents.edit')->name('agents.edit');

    Route::livewire('repos', 'pages::repos.index')->name('repos.index');
    Route::livewire('repos/{repo}', 'pages::repos.show')->name('repos.show');
    Route::livewire('repos/{repo}/edit', 'pages::repos.edit')->name('repos.edit');

    Route::livewire('skills', 'pages::skills.index')->name('skills.index');
    Route::livewire('skills/create', 'pages::skills.create')->name('skills.create');
    Route::livewire('skills/registry', 'pages::skills.registry')->name('skills.registry');
    Route::livewire('skills/{skill}', 'pages::skills.show')->name('skills.show');
    Route::livewire('skills/{skill}/edit', 'pages::skills.edit')->name('skills.edit');

    Route::livewire('projects', 'pages::projects.index')->name('projects.index');
    Route::livewire('projects/create', 'pages::projects.create')->name('projects.create');
    Route::livewire('projects/{project}', 'pages::projects.show')->name('projects.show');
    Route::livewire('projects/{project}/edit', 'pages::projects.edit')->name('projects.edit');

    Route::livewire('work-items', 'pages::work-items.index')->name('work-items.index');
    Route::livewire('work-items/{workItem}', 'pages::work-items.show')->name('work-items.show');
    Route::livewire('work-items/{workItem}/edit', 'pages::work-items.edit')->name('work-items.edit');
});
