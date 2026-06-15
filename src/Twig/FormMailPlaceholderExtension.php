<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\FormMailPlaceholderRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class FormMailPlaceholderExtension extends AbstractExtension
{
    public function __construct(private readonly FormMailPlaceholderRenderer $renderer)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('form_mail_placeholders', $this->render(...)),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     */
    public function render(?string $text, array $fields): string
    {
        return $this->renderer->renderSerializedFields($text, $fields);
    }
}
