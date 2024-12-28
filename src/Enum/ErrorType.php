<?php

namespace App\Enum;

enum ErrorType: string
{
    case SUCCESS = 'OK.';
    case BAD_REQUEST = 'Bad Request.';
    case UNAUTHORIZED = 'Unauthorized.';
    case FORBIDDEN = 'Access denied.';
    case NOT_FOUND = 'Not found.';
    case METHOD_NOT_ALLOWED = 'Method not allowed.';
}
