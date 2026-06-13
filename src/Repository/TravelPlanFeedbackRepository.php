<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sulu\Bundle\ContactBundle\Entity\Contact;

/**
 * @extends ServiceEntityRepository<TravelPlanFeedback>
 */
final class TravelPlanFeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TravelPlanFeedback::class);
    }

    public function findActiveForTarget(
        TravelPlan $travelPlan,
        Contact $contact,
        ?string $blockPath,
    ): ?TravelPlanFeedback {
        $queryBuilder = $this->createQueryBuilder('feedback')
            ->andWhere('feedback.travelPlan = :travelPlan')
            ->andWhere('feedback.contact = :contact')
            ->andWhere('feedback.status IN (:statuses)')
            ->setParameter('travelPlan', $travelPlan)
            ->setParameter('contact', $contact)
            ->setParameter('statuses', [
                TravelPlanFeedback::STATUS_OPEN,
                TravelPlanFeedback::STATUS_IN_PROGRESS,
            ])
            ->orderBy('feedback.createdAt', 'DESC')
            ->setMaxResults(1);

        if (null === $blockPath) {
            $queryBuilder->andWhere('feedback.blockPath IS NULL');
        } else {
            $queryBuilder
                ->andWhere('feedback.blockPath = :blockPath')
                ->setParameter('blockPath', $blockPath);
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    /**
     * @return list<TravelPlanFeedback>
     */
    public function findForPlanAndContact(TravelPlan $travelPlan, Contact $contact): array
    {
        return $this->createQueryBuilder('feedback')
            ->andWhere('feedback.travelPlan = :travelPlan')
            ->andWhere('feedback.contact = :contact')
            ->setParameter('travelPlan', $travelPlan)
            ->setParameter('contact', $contact)
            ->orderBy('feedback.createdAt', 'DESC')
            ->addOrderBy('feedback.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
