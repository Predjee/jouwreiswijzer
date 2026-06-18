<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\TravelPlan;
use App\Entity\TravelPlanChecklistState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sulu\Bundle\ContactBundle\Entity\Contact;

/**
 * @extends ServiceEntityRepository<TravelPlanChecklistState>
 */
final class TravelPlanChecklistStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TravelPlanChecklistState::class);
    }

    public function findOneForItem(Contact $contact, TravelPlan $travelPlan, string $itemKey): ?TravelPlanChecklistState
    {
        return $this->findOneBy([
            'contact' => $contact,
            'travelPlan' => $travelPlan,
            'itemKey' => $itemKey,
        ]);
    }

    /**
     * @return array<string, bool>
     */
    public function checkedMapForPlan(Contact $contact, TravelPlan $travelPlan): array
    {
        $states = $this->createQueryBuilder('state')
            ->andWhere('state.contact = :contact')
            ->andWhere('state.travelPlan = :travelPlan')
            ->andWhere('state.checkedAt IS NOT NULL')
            ->setParameter('contact', $contact)
            ->setParameter('travelPlan', $travelPlan)
            ->getQuery()
            ->getResult();

        $checked = [];

        foreach ($states as $state) {
            if ($state instanceof TravelPlanChecklistState) {
                $checked[$state->getItemKey()] = true;
            }
        }

        return $checked;
    }
}
