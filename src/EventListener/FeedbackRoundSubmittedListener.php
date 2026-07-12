<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Notification;
use App\Event\FeedbackRoundSubmittedEvent;
use App\Notification\NotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email as Mail;
use Twig\Environment;

final readonly class FeedbackRoundSubmittedListener implements EventSubscriberInterface
{
    public function __construct(
        private NotificationService $notificationService,
        private MailerInterface $mailer,
        private Environment $twig,
        #[Autowire('%env(FROM_EMAIL)%')]
        private string $fromEmail,
        #[Autowire('%app.admin_email%')]
        private string $adminEmail,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FeedbackRoundSubmittedEvent::class => 'onFeedbackRoundSubmitted',
        ];
    }

    public function onFeedbackRoundSubmitted(FeedbackRoundSubmittedEvent $event): void
    {
        $travelPlan = $event->getTravelPlan();
        $contact = $travelPlan->getTravelRequest()->getContact();
        $feedbackCount = $event->getFeedbackCount();
        $adminUrl = '/admin/';

        try {
            $this->notificationService->createForAdmin(
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
                    'feedback_items' => $event->getFeedbackItems(),
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
    }
}
