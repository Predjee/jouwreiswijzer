<?php

declare(strict_types=1);

namespace App\Api\App\Command;

use Sulu\Bundle\ContactBundle\Entity\Contact;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class MarkNotificationReadCommand
{
    public function __construct(
        #[Assert\Positive]
        public int $notificationId,
        public Contact $contact,
    ) {
    }
}
