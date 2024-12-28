<?php

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function getNotificationEmails($clientId): array
    {
        $results = $this->createQueryBuilder('c')
            ->select('u.notification_email')
            ->join('c.users', 'u')
            ->where('c.id = :clientId')
            ->andWhere('u.notification_email IS NOT NULL')
            ->setParameter('clientId', $clientId)
            ->getQuery()
            ->getResult();
    
        return array_column($results, 'notification_email');
    }
}
