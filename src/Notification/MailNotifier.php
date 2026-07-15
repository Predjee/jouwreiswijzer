<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\TravelPlan;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email as Mail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final readonly class MailNotifier
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(FROM_EMAIL)%')]
        private string $fromEmail,
    ) {
    }

    public function sendTravelPlanPublished(TravelPlan $travelPlan): void
    {
        $contact = $travelPlan->getTravelRequest()->getContact();
        $recipient = $this->resolveContactEmail($contact);

        if (null === $recipient) {
            throw new \RuntimeException(\sprintf(
                'Unable to send travel plan published email because contact "%d" has no email.',
                $contact->getId(),
            ));
        }

        $url = $this->urlGenerator->generate(
            'account_travel_plan',
            ['id' => $travelPlan->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $this->mailer->send((new Mail())
            ->from($this->fromEmail)
            ->to($recipient)
            ->subject('Je reisplan staat klaar')
            ->html($this->twig->render('emails/travel_plan_published.html.twig', [
                'travel_plan' => $travelPlan,
                'contact' => $contact,
                'account_url' => $url,
            ]))
            ->text(\sprintf(
                "Je persoonlijke reisplan \"%s\" is gepubliceerd en staat klaar in Mijn Omgeving.\n\n%s",
                $travelPlan->getTitle(),
                $url,
            )));
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
