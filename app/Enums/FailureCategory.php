<?php

namespace App\Enums;

enum FailureCategory: string
{
    case RateLimit = 'rate_limit';
    case GithubApi = 'github_api';
    case ToolError = 'tool_error';
    case ModelError = 'model_error';
    case Timeout = 'timeout';
    case Unknown = 'unknown';
}
