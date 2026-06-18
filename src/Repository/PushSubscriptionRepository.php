<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PushSubscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sulu\Bundle\ContactBundle\Entity\Contact;

/**
 * @extends ServiceEntityRepository<PushSubscription>
 */
final class PushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSubscription::class);
    }

    public function findOneByToken(string $expoPushToken): ?PushSubscription
    {
        return $this->findOneBy([
            'expoPushToken' => $expoPushToken,
        ]);
    }

    /**
     * @return list<PushSubscription>
     */
    public function findForContact(Contact $contact): array
    {
        return $this->findBy([
            'contact' => $contact,
        ]);
    }
}
