<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TravelRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TravelRequest>
 */
final class TravelRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TravelRequest::class);
    }
}
