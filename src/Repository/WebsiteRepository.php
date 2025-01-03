<?php

namespace App\Repository;

use App\Entity\Website;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Website>
 */
class WebsiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Website::class);
    }

    public function existingWebsite($id, $url, $clientId): ?Website
    {
        $normalizedUrl = preg_replace('/^https?:\/\//', '', rtrim($url, '/'));

        $qb = $this->createQueryBuilder('w')
            ->where('w.client = :client')
            ->andWhere('w.url LIKE :url')
            ->setParameter('client', $clientId)
            ->setParameter('url', "%$normalizedUrl%");

        if ($id) {
            $qb->andWhere('w.id != :id')
            ->setParameter('id', $id);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}
