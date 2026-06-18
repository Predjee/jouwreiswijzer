<?php

declare(strict_types=1);

namespace App\Api\App\Command;

use Sulu\Bundle\ContactBundle\Entity\Contact;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ToggleChecklistItemCommand
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex('/^[a-f0-9]{40}$/i')]
        public string $itemId,
        public Contact $contact,
    ) {
    }
}
