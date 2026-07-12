<?php

declare(strict_types=1);

namespace App\Account;

use App\Security\AccountTokenHasher;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class PasswordResetService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private AccountTokenHasher $accountTokenHasher,
    ) {
    }

    public function findValidUser(string $token): ?User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'passwordResetToken' => $this->accountTokenHasher->hash($token),
        ]);

        if (!$user instanceof User) {
            return null;
        }

        if (
            null === $user->getPasswordResetTokenExpiresAt()
            || new \DateTime() > $user->getPasswordResetTokenExpiresAt()
        ) {
            return null;
        }

        return $user;
    }

    public function resetPassword(User $user, string $newPassword): void
    {
        $user
            ->setPassword($this->passwordHasher->hashPassword($user, $newPassword))
            ->setPasswordResetToken(null)
            ->setPasswordResetTokenExpiresAt(null)
            ->setPasswordResetTokenEmailsSent(null);

        $this->entityManager->flush();
    }
}
