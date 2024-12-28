<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use App\Exception\UserNotActivatedException;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof \App\Entity\User) {
            return;
        }

        if (!$user->isActive()) {
            throw new UserNotActivatedException();
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    
    }
}
