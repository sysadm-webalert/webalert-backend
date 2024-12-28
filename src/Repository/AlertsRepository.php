<?php

namespace App\Repository;

use App\Entity\Alerts;
use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Alerts>
 */
class AlertsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alerts::class);
    }

    public function findByClient(Client $client): array
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.website', 'w')
            ->andWhere('w.client = :client')
            ->setParameter('client', $client)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLastActiveAlertExcludingCurrent(int $websiteId, string $kind, int $excludeAlertId): ?Alerts
    {
        return $this->createQueryBuilder('a')
            ->where('a.website = :websiteId')
            ->andWhere('a.kind = :kind')
            ->andWhere('a.isResolved = false')
            ->andWhere('a.id != :excludeId')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(1)
            ->setParameter('websiteId', $websiteId)
            ->setParameter('kind', $kind)
            ->setParameter('excludeId', $excludeAlertId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveAlertsForMetrics(int $websiteId, Metrics $metrics): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.website = :website')
            ->andWhere('a.metrics = :metrics')
            ->andWhere('a.isResolved = false')
            ->setParameter('website', $websiteId)
            ->setParameter('metrics', $metrics)
            ->getQuery()
            ->getResult();
    }
}
