<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\RequestFormConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\FormBundle\Entity\Form;

/**
 * Bepaalt of een Sulu-formulier het reisaanvraagformulier is (via de
 * gekoppelde RequestFormConfiguration). Gedeeld door de submit-listener
 * en de rate-limit-listener.
 */
final readonly class RequestFormResolver
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function isRequestForm(Form $form): bool
    {
        $configuration = $this->entityManager
            ->getRepository(RequestFormConfiguration::class)
            ->findOneBy(['form' => $form]);

        return $configuration instanceof RequestFormConfiguration
            && $configuration->isRequestForm();
    }
}
