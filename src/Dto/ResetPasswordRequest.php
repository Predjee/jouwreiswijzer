<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ResetPasswordRequest
{
    #[Assert\NotBlank(message: 'Gebruik minimaal 8 tekens.')]
    #[Assert\Length(min: 8, minMessage: 'Gebruik minimaal 8 tekens.')]
    public string $newPassword;

    #[Assert\NotBlank(message: 'De wachtwoorden komen niet overeen.')]
    public string $confirmPassword;

    public function __construct(string $newPassword, string $confirmPassword)
    {
        $this->newPassword = $newPassword;
        $this->confirmPassword = $confirmPassword;
    }
}
