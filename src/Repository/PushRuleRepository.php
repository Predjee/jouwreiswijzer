<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PushRule;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushRule>
 */
final class PushRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushRule::class);
    }

    /**
     * @return list<PushRule>
     */
    public function findActive(): array
    {
        return $this->findBy([
            'active' => true,
        ]);
    }
}
