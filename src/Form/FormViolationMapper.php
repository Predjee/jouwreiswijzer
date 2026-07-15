<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Validator\ConstraintViolationListInterface;

final readonly class FormViolationMapper
{
    /**
     * @return array<string, string>
     */
    public function toErrorArray(ConstraintViolationListInterface $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $propertyPath = $violation->getPropertyPath();

            if ('' === $propertyPath) {
                continue;
            }

            $errors[$propertyPath] = (string) $violation->getMessage();
        }

        return $errors;
    }
}
