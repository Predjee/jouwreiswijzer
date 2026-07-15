<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\NotificationRepository;
use Sulu\Bundle\ContactBundle\Entity\Contact;
use Sulu\Bundle\SecurityBundle\Entity\User as SuluUser;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class NotificationExtension extends AbstractExtension
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('account_unread_notification_count', $this->getUnreadCount(...)),
        ];
    }

    public function getUnreadCount(): int
    {
        $user = $this->security->getUser();
        $contact = $user instanceof SuluUser ? $user->getContact() : null;

        if (!$contact instanceof Contact) {
            return 0;
        }

        return $this->notificationRepository->countUnreadForContact($contact);
    }
}
