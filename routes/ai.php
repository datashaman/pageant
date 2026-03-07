<?php

use App\Mcp\Servers\GitHubServer;
use App\Mcp\Servers\PageantServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::oauthRoutes();

Mcp::web('/mcp/github', GitHubServer::class)
    ->middleware('auth:api');

Mcp::web('/mcp/pageant', PageantServer::class)
    ->middleware('auth:api');

Mcp::local('github', GitHubServer::class);
Mcp::local('pageant', PageantServer::class);
