<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PushRule;
use App\Entity\ScheduledPushMessage;
use App\Entity\TravelPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScheduledPushMessage>
 */
final class ScheduledPushMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScheduledPushMessage::class);
    }

    /**
     * Berichten die nu verstuurd moeten worden: status pending en scheduledFor verstreken.
     * Gebruikt door de Messenger-consumer (cronjob-gedreven, zie ARCHITECTURE.md sectie 16a).
     *
     * @return list<ScheduledPushMessage>
     */
    public function findPendingDue(\DateTimeImmutable $now, int $limit = 50): array
    {
        return $this->createQueryBuilder('message')
            ->andWhere('message.status = :status')
            ->andWhere('message.scheduledFor <= :now')
            ->setParameter('status', ScheduledPushMessage::STATUS_PENDING)
            ->setParameter('now', $now)
            ->orderBy('message.scheduledFor', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function existsForRuleAndTravelPlan(PushRule $pushRule, TravelPlan $travelPlan): bool
    {
        $result = $this->createQueryBuilder('message')
            ->select('message.id')
            ->andWhere('message.pushRule = :pushRule')
            ->andWhere('message.travelPlan = :travelPlan')
            ->setParameter('pushRule', $pushRule)
            ->setParameter('travelPlan', $travelPlan)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $result;
    }

    public function existsForRule(PushRule $pushRule): bool
    {
        $result = $this->createQueryBuilder('message')
            ->select('message.id')
            ->andWhere('message.pushRule = :pushRule')
            ->setParameter('pushRule', $pushRule)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $result;
    }

    public function existsForSourceKey(string $sourceKey): bool
    {
        $result = $this->createQueryBuilder('message')
            ->select('message.id')
            ->andWhere('message.sourceKey = :sourceKey')
            ->setParameter('sourceKey', $sourceKey)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return null !== $result;
    }
}
