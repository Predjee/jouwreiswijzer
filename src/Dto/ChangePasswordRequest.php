<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ChangePasswordRequest
{
    #[Assert\NotBlank(message: 'Het huidige wachtwoord is niet juist.')]
    public string $currentPassword;

    #[Assert\NotBlank(message: 'Gebruik minimaal 8 tekens.')]
    #[Assert\Length(min: 8, minMessage: 'Gebruik minimaal 8 tekens.')]
    public string $newPassword;

    #[Assert\NotBlank(message: 'De nieuwe wachtwoorden komen niet overeen.')]
    public string $confirmPassword;

    public function __construct(string $currentPassword, string $newPassword, string $confirmPassword)
    {
        $this->currentPassword = $currentPassword;
        $this->newPassword = $newPassword;
        $this->confirmPassword = $confirmPassword;
    }
}
