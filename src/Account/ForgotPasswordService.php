<?php

declare(strict_types=1);

namespace App\Account;

use App\Security\AccountTokenHasher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email as Mail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class ForgotPasswordService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        private AccountTokenHasher $accountTokenHasher,
        #[Autowire('%env(FROM_EMAIL)%')]
        private string $fromEmail,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function requestResetLink(string $email): void
    {
        if (false === \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user instanceof User || !\in_array('ROLE_SULU_CUSTOMER', $user->getRoles(), true)) {
            return;
        }

        $token = \bin2hex(\random_bytes(32));
        $user
            ->setPasswordResetToken($this->accountTokenHasher->hash($token))
            ->setPasswordResetTokenExpiresAt((new \DateTime())->add(new \DateInterval('PT48H')))
            ->setPasswordResetTokenEmailsSent(1);

        $resetUrl = $this->urlGenerator->generate(
            'account_password_reset',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->entityManager->flush();

        try {
            $this->mailer->send((new Mail())
                ->from($this->fromEmail)
                ->to($email)
                ->subject('Wachtwoord opnieuw instellen')
                ->html($this->twig->render('emails/password_reset_requested.html.twig', [
                    'reset_url' => $resetUrl,
                ])));
        } catch (\Throwable $exception) {
            $this->logger?->error('Unable to send password reset request email.', [
                'exception' => $exception,
                'email' => $email,
                'userId' => $user->getId(),
            ]);
        }
    }
}
