<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SubmitTravelPlanFeedbackRequest
{
    #[Assert\NotBlank(message: 'Vul een bericht in voordat je de feedback verstuurt.')]
    #[Assert\Length(max: 5000, maxMessage: 'Gebruik maximaal 5000 tekens voor je feedback.')]
    public string $message;

    #[Assert\Type('string')]
    public ?string $blockPath;

    #[Assert\NotBlank]
    public string $csrfToken;

    public function __construct(string $message, ?string $blockPath, string $csrfToken)
    {
        $this->message = $message;
        $this->blockPath = $blockPath;
        $this->csrfToken = $csrfToken;
    }
}
