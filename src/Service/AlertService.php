<?php

namespace App\Service;

use App\Entity\Alerts;
use App\Entity\Status;
use App\Entity\Metrics;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Repository\AlertsRepository;
use App\Service\MailerService;

class AlertService
{
    private EntityManagerInterface $entityManager;
    private AlertsRepository $alertsRepository;
    private LoggerInterface $logger;
    private MailerService $mailerservice;
    

    public function __construct(EntityManagerInterface $entityManager, AlertsRepository $alertsRepository, LoggerInterface $logger, MailerService $mailerservice)
    {
        $this->entityManager = $entityManager;
        $this->alertsRepository = $alertsRepository;
        $this->logger = $logger;
        $this->mailerservice = $mailerservice;
    }

    public function createAlert(object $entity, string $kind): void
    {
        $alert = new Alerts();

        if ($entity instanceof Metrics) {
            $alert->setMetrics($entity);
            $alert->setWebsite($entity->getWebsiteId());
        } elseif ($entity instanceof Status) {
            $alert->setStatus($entity);
            $alert->setWebsite($entity->getWebsiteId());
        } else {
            $this->logger->error('Unsupported entity type for alert creation:', ['class' => get_class($entity)]);
            return;
        }

        $alert->setKind($kind);
        $alert->setCreatedAt(new \DateTimeImmutable());
        $alert->setResolved(false);

        $this->entityManager->persist($alert);
        $this->entityManager->flush();

        // Excluding the actual alert
        $lastActiveAlert = $this->alertsRepository->findLastActiveAlertExcludingCurrent(
            $alert->getWebsite()->getId(),
            $kind,
            $alert->getId()
        );

        $now = new \DateTimeImmutable();

        // If alert exists, do not report before 24 hours
        if ($lastActiveAlert) {
            $lastSent = $lastActiveAlert->getCreatedAt();
            if ($lastSent && $now->getTimestamp() - $lastSent->getTimestamp() < 86400) {
                $this->logger->info("Email for {$kind} alert not sent; active alert was sent less than 24 hours ago.");
                return;
            }
        }

        // Send mail always if no alert before
        $this->mailerservice->sendAlert($entity, $kind);
        $this->logger->info("Alert email sent for type {$kind} for Website ID: {$alert->getWebsite()->getId()}");
    }


    public function resolveAlert(object $entity, string $kind): void
    {
        $website = $entity->getWebsiteId();

        $alerts = $this->alertsRepository->findBy([
            'website' => $website,
            'kind' => $kind,
            'isResolved' => false,
        ]);

        if (count($alerts) > 0) {
            foreach ($alerts as $alert) {
                $alert->resolve();
            }
            $this->entityManager->flush();
            $this->mailerservice->sendRecoverAlert($entity, $kind);
        }
    }
}
