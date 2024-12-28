<?php

namespace App\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class UserNotActivatedException extends AuthenticationException
{
    public function getMessageKey(): string
    {
        return 'Your account is not activated. Please confirm your email.';
    }
}
