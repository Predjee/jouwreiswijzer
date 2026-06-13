<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TravelPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TravelPlan>
 */
final class TravelPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TravelPlan::class);
    }
}
