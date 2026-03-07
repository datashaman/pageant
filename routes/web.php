<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\GitHubAuthController;
use App\Http\Controllers\GitHubWebhookController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('auth/github', [GitHubAuthController::class, 'redirect'])->name('auth.github');
Route::get('auth/github/callback', [GitHubAuthController::class, 'callback'])->name('auth.github.callback');

Route::post('webhooks/github', [GitHubWebhookController::class, 'handle'])
    ->name('webhooks.github')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::post('chat/stream', [ChatController::class, 'stream'])->name('chat.stream');
    Route::get('chat/messages', [ChatController::class, 'messages'])->name('chat.messages');
});

require __DIR__.'/resources.php';
require __DIR__.'/settings.php';
