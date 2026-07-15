<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AccountTokenHasher
{
    public function __construct(
        #[Autowire('%kernel.secret%')]
        private string $secret,
    ) {
    }

    public function hash(string $token): string
    {
        return \hash('sha256', $this->secret . '%' . $token);
    }
}
