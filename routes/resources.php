<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('agents', 'pages::agents.index')->name('agents.index');
    Route::livewire('agents/create', 'pages::agents.create')->name('agents.create');
    Route::livewire('agents/{agent}', 'pages::agents.show')->name('agents.show');
    Route::livewire('agents/{agent}/edit', 'pages::agents.edit')->name('agents.edit');

    Route::livewire('skills', 'pages::skills.index')->name('skills.index');
    Route::livewire('skills/create', 'pages::skills.create')->name('skills.create');
    Route::livewire('skills/registry', 'pages::skills.registry')->name('skills.registry');
    Route::livewire('skills/{skill}', 'pages::skills.show')->name('skills.show');
    Route::livewire('skills/{skill}/edit', 'pages::skills.edit')->name('skills.edit');

    Route::livewire('workspaces', 'pages::workspaces.index')->name('workspaces.index');
    Route::livewire('workspaces/create', 'pages::workspaces.create')->name('workspaces.create');
    Route::livewire('workspaces/{workspace}', 'pages::workspaces.show')->name('workspaces.show');
    Route::livewire('workspaces/{workspace}/edit', 'pages::workspaces.edit')->name('workspaces.edit');
});
