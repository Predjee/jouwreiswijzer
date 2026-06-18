<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TravelMemoryAlbum;
use App\Entity\TravelPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TravelMemoryAlbum>
 */
final class TravelMemoryAlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TravelMemoryAlbum::class);
    }

    public function findOneByTravelPlan(TravelPlan $travelPlan): ?TravelMemoryAlbum
    {
        return $this->findOneBy([
            'travelPlan' => $travelPlan,
        ]);
    }
}
