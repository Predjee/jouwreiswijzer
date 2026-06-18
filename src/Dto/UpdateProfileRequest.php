<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateProfileRequest
{
    #[Assert\NotBlank(message: 'Vul je voornaam in.')]
    public string $firstName;

    #[Assert\NotBlank(message: 'Vul je achternaam in.')]
    public string $lastName;

    public ?string $phone;

    public function __construct(string $firstName, string $lastName, ?string $phone)
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->phone = $phone;
    }
}
