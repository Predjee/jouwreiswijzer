<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\SecurityBundle\Entity\User;

/**
 * @extends ServiceEntityRepository<Notification>
 */
final class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return list<Notification>
     */
    public function findForContact(Contact $contact): array
    {
        return $this->createQueryBuilder('notification')
            ->andWhere('notification.recipientContact = :contact')
            ->setParameter('contact', $contact)
            ->orderBy('notification.createdAt', 'DESC')
            ->addOrderBy('notification.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Notification>
     */
    public function findRecentForContact(Contact $contact, int $limit = 5): array
    {
        return $this->createQueryBuilder('notification')
            ->andWhere('notification.recipientContact = :contact')
            ->setParameter('contact', $contact)
            ->orderBy('notification.createdAt', 'DESC')
            ->addOrderBy('notification.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnreadForContact(Contact $contact): int
    {
        return (int) $this->createQueryBuilder('notification')
            ->select('COUNT(notification.id)')
            ->andWhere('notification.recipientContact = :contact')
            ->andWhere('notification.readAt IS NULL')
            ->setParameter('contact', $contact)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Notification>
     */
    public function findUnreadForContact(Contact $contact): array
    {
        return $this->createQueryBuilder('notification')
            ->andWhere('notification.recipientContact = :contact')
            ->andWhere('notification.readAt IS NULL')
            ->setParameter('contact', $contact)
            ->orderBy('notification.createdAt', 'DESC')
            ->addOrderBy('notification.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Notification>
     */
    public function findRecentForUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('notification')
            ->andWhere('notification.recipientUser = :user')
            ->setParameter('user', $user)
            ->orderBy('notification.createdAt', 'DESC')
            ->addOrderBy('notification.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
