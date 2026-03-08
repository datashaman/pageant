<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('organization.{organization}', function (User $user, Organization $organization) {
    return $user->organizations()->whereKey($organization->getKey())->exists();
});
