<?php

use App\Mcp\Servers\GitHubServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp/github', GitHubServer::class)
    ->middleware('auth:api');

Mcp::local('github', GitHubServer::class);
