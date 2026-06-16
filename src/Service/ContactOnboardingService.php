<?php

declare(strict_types=1);

namespace App\Service;

use App\Security\AccountTokenHasher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Bundle\SecurityBundle\Entity\UserRole;
use Sulu\Component\Security\Authentication\RoleRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email as Mail;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

final readonly class ContactOnboardingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RoleRepositoryInterface $roleRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private MailerInterface $mailer,
        private Environment $twig,
        private RouterInterface $router,
        private AccountTokenHasher $accountTokenHasher,
        #[Autowire('%env(FROM_EMAIL)%')]
        private string $fromEmail,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function onboard(Contact $contact, string $email): void
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user instanceof User) {
            $user = $userRepository->findOneBy(['contact' => $contact]);
        }

        if (!$user instanceof User) {
            $role = null;

            foreach ($this->roleRepository->findAllRoles(['anonymous' => false]) as $candidate) {
                if ('ROLE_SULU_CUSTOMER' === $candidate->getIdentifier()) {
                    $role = $candidate;
                    break;
                }
            }

            if (null === $role) {
                throw new \LogicException('The Sulu role ROLE_SULU_CUSTOMER is not configured.');
            }

            $user = (new User())
                ->setUsername($email)
                ->setEmail($email)
                ->setLocale('nl');
            $user->setPassword($this->passwordHasher->hashPassword($user, \bin2hex(\random_bytes(32))));

            $userRole = (new UserRole())
                ->setUser($user)
                ->setRole($role)
                ->setLocale(\json_encode(['nl'], \JSON_THROW_ON_ERROR));

            $user->addUserRole($userRole);
            $this->entityManager->persist($user);
            $this->entityManager->persist($userRole);
        }

        $token = \bin2hex(\random_bytes(32));
        $user
            ->setContact($contact)
            ->setPasswordResetToken($this->accountTokenHasher->hash($token))
            ->setPasswordResetTokenExpiresAt((new \DateTime())->add(new \DateInterval('PT48H')))
            ->setPasswordResetTokenEmailsSent(1);

        $resetUrl = $this->router->generate(
            'account_password_reset',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->entityManager->flush();

        try {
            $this->mailer->send((new Mail())
                ->from($this->fromEmail)
                ->to($email)
                ->subject('Welkom bij JouwReisWijzer')
                ->html($this->twig->render('emails/account_created.html.twig', [
                    'contact' => $contact,
                    'reset_url' => $resetUrl,
                ])));
        } catch (\Throwable $exception) {
            $this->logger?->error('Unable to send account onboarding email.', [
                'exception' => $exception,
                'contactId' => $contact->getId(),
                'email' => $email,
            ]);
        }
    }
}
