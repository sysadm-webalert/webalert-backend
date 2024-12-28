<?php

namespace App\Service;

use App\Entity\Events;
use App\Entity\User;
use App\Entity\Website;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Enum\EventsType;

class EventsService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    
    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function createEvent(User $user, string $message, EventsType $kind ): void 
    {
        $event = new Events();
        $event->setClient($user->getClientId());
        $event->setMessage($message);
        $event->setKind($kind->value);
        $event->setAcknowledge(false);
        $event->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        $this->logger->info(sprintf('Event generated from user %s: %s', $user->getEmail(),$message));
    }
}
