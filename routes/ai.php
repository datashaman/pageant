<?php

use App\Mcp\Servers\GitHubServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('github', GitHubServer::class);
