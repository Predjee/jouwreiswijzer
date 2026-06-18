<?php

declare(strict_types=1);

namespace App\Api\App\Query;

use Sulu\Bundle\ContactBundle\Entity\Contact;

final readonly class GetNotificationsQuery
{
    public function __construct(public Contact $contact)
    {
    }
}
