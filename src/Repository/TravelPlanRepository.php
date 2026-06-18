<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TravelPlan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sulu\Bundle\ContactBundle\Entity\Contact;

/**
 * @extends ServiceEntityRepository<TravelPlan>
 */
final class TravelPlanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TravelPlan::class);
    }

    /**
     * @return list<TravelPlan>
     */
    public function findPublishedByContact(Contact $contact): array
    {
        return $this->createQueryBuilder('travelPlan')
            ->innerJoin('travelPlan.travelRequest', 'travelRequest')
            ->andWhere('travelRequest.contact = :contact')
            ->andWhere('travelPlan.status = :status')
            ->setParameter('contact', $contact)
            ->setParameter('status', TravelPlan::STATUS_PUBLISHED)
            ->orderBy('travelPlan.publishedAt', 'DESC')
            ->addOrderBy('travelPlan.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findPublishedForContact(int $id, Contact $contact): ?TravelPlan
    {
        return $this->createQueryBuilder('travelPlan')
            ->innerJoin('travelPlan.travelRequest', 'travelRequest')
            ->andWhere('travelPlan.id = :id')
            ->andWhere('travelRequest.contact = :contact')
            ->andWhere('travelPlan.status = :status')
            ->setParameter('id', $id)
            ->setParameter('contact', $contact)
            ->setParameter('status', TravelPlan::STATUS_PUBLISHED)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return iterable<TravelPlan>
     */
    public function findPublishedForPushRuleEvaluation(): iterable
    {
        return $this->createQueryBuilder('travelPlan')
            ->andWhere('travelPlan.status = :status')
            ->setParameter('status', TravelPlan::STATUS_PUBLISHED)
            ->orderBy('travelPlan.id', 'ASC')
            ->getQuery()
            ->toIterable();
    }
}
