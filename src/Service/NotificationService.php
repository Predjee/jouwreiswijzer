<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\TravelPlan;
use App\Entity\TravelPlanFeedback;
use App\Repository\NotificationRepository;
use App\Repository\TravelPlanFeedbackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email as Mail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
        private TravelPlanFeedbackRepository $feedbackRepository,
        private MailerInterface $mailer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(FROM_EMAIL)%')]
        private string $fromEmail,
        #[Autowire('%app.admin_email%')]
        private string $adminEmail,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function notifyTravelPlanPdfReleased(TravelPlan $travelPlan): Notification
    {
        return $this->createForContact(
            $travelPlan->getTravelRequest()->getContact(),
            Notification::TYPE_TRAVEL_PLAN_PDF_RELEASED,
            'Je definitieve reisgids staat klaar',
            \sprintf('De PDF van "%s" is vrijgegeven en kan worden gedownload.', $travelPlan->getTitle()),
            $this->urlGenerator->generate('account_travel_plan', ['id' => $travelPlan->getId()]),
        );
    }

    public function notifyFeedbackRoundSubmitted(TravelPlan $travelPlan): int
    {
        $feedbackItems = $this->feedbackRepository->findActiveForTravelPlan($travelPlan);
        $feedbackCount = \count($feedbackItems);

        if (0 === $feedbackCount) {
            return 0;
        }

        $contact = $travelPlan->getTravelRequest()->getContact();
        $adminUrl = '/admin/';

        try {
            $this->createForAdmin(
                Notification::TYPE_TRAVEL_PLAN_FEEDBACK_SUBMITTED,
                'Nieuwe feedbackronde ontvangen',
                \sprintf(
                    '%s heeft %d feedbackpunt(en) verstuurd voor "%s".',
                    $contact->getFullName(),
                    $feedbackCount,
                    $travelPlan->getTitle(),
                ),
                $adminUrl,
            );
        } catch (\Throwable $exception) {
            $this->logger?->error('Unable to create admin feedback round notification.', [
                'exception' => $exception,
                'travelPlanId' => $travelPlan->getId(),
            ]);
        }

        try {
            $this->mailer->send((new Mail())
                ->from($this->fromEmail)
                ->to($this->adminEmail)
                ->subject('Nieuwe feedbackronde ontvangen')
                ->html($this->twig->render('emails/admin_feedback_received.html.twig', [
                    'feedback_items' => $feedbackItems,
                    'feedback_count' => $feedbackCount,
                    'travel_plan' => $travelPlan,
                    'contact' => $contact,
                    'admin_url' => $adminUrl,
                ])));
        } catch (\Throwable $exception) {
            $this->logger?->error('Unable to send admin feedback round email.', [
                'exception' => $exception,
                'travelPlanId' => $travelPlan->getId(),
                'adminEmail' => $this->adminEmail,
            ]);
        }

        return $feedbackCount;
    }

    public function notifyFeedbackProcessed(TravelPlan $travelPlan): void
    {
        $contact = $travelPlan->getTravelRequest()->getContact();
        $accountUrl = $this->urlGenerator->generate('account_travel_plan', [
            'id' => $travelPlan->getId(),
            'mode' => 'feedback',
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $this->createForContact(
            $contact,
            Notification::TYPE_TRAVEL_PLAN_FEEDBACK_RESOLVED,
            'Je feedback is verwerkt',
            \sprintf('Je feedback op "%s" is verwerkt.', $travelPlan->getTitle()),
            $this->urlGenerator->generate('account_travel_plan', [
                'id' => $travelPlan->getId(),
                'mode' => 'feedback',
            ]),
        );

        $recipient = $this->resolveContactEmail($contact);

        if (null === $recipient) {
            $this->logger?->warning('Unable to send feedback processed email because contact has no email.', [
                'travelPlanId' => $travelPlan->getId(),
                'contactId' => $contact->getId(),
            ]);

            return;
        }

        try {
            $this->mailer->send((new Mail())
                ->from($this->fromEmail)
                ->to($recipient)
                ->subject('Je feedback is verwerkt')
                ->html($this->twig->render('emails/feedback_processed.html.twig', [
                    'travel_plan' => $travelPlan,
                    'contact' => $contact,
                    'account_url' => $accountUrl,
                ])));
        } catch (\Throwable $exception) {
            $this->logger?->error('Unable to send feedback processed customer email.', [
                'exception' => $exception,
                'travelPlanId' => $travelPlan->getId(),
                'recipient' => $recipient,
            ]);
        }
    }

    public function createForContact(
        Contact $contact,
        string $type,
        string $title,
        string $message,
        ?string $url = null,
    ): Notification {
        $notification = (new Notification())
            ->setRecipientContact($contact)
            ->setType($type)
            ->setTitle($title)
            ->setMessage($message)
            ->setUrl($url);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    public function createForAdmin(
        string $type,
        string $title,
        string $message,
        ?string $url = null,
        ?User $user = null,
    ): Notification {
        $notification = (new Notification())
            ->setRecipientUser($user)
            ->setType($type)
            ->setTitle($title)
            ->setMessage($message)
            ->setUrl($url);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    public function markAsRead(Notification $notification): void
    {
        if ($notification->isRead()) {
            return;
        }

        $notification->setReadAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    public function markAllAsRead(Contact $contact): void
    {
        $unreadNotifications = $this->notificationRepository->findUnreadForContact($contact);

        foreach ($unreadNotifications as $notification) {
            $notification->setReadAt(new \DateTimeImmutable());
        }

        if ([] !== $unreadNotifications) {
            $this->entityManager->flush();
        }
    }

    private function resolveContactEmail(Contact $contact): ?string
    {
        $email = $contact->getMainEmail();

        if (\is_string($email) && false !== \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['contact' => $contact]);

        if ($user instanceof User && false !== \filter_var($user->getEmail(), \FILTER_VALIDATE_EMAIL)) {
            return $user->getEmail();
        }

        return null;
    }
}
