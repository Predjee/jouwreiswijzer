<?php

declare(strict_types=1);

namespace App\TravelPlan;

use App\Entity\TravelMemoryAlbum;
use App\Entity\TravelPlan;
use App\Entity\TravelRequest;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\MediaBundle\Media\Exception\MediaNotFoundException;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;

/**
 * Verwijdert een reisaanvraag inclusief het gekoppelde reisplan en alle
 * afgeleiden. Feedback, checklist-status, herinneringsalbums en geplande
 * pushberichten hangen met onDelete=CASCADE aan het reisplan en gaan op
 * databaseniveau mee; de PDF- en albummedia worden expliciet opgeruimd
 * omdat die alleen via een id (zonder FK) gekoppeld zijn.
 */
final readonly class TravelRequestRemover
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediaManagerInterface $mediaManager,
    ) {
    }

    public function remove(TravelRequest $travelRequest): void
    {
        $travelPlan = $this->entityManager
            ->getRepository(TravelPlan::class)
            ->findOneBy(['travelRequest' => $travelRequest]);

        if ($travelPlan instanceof TravelPlan) {
            $this->removeMedia($travelPlan->getPdfMediaId());

            $album = $this->entityManager
                ->getRepository(TravelMemoryAlbum::class)
                ->findOneBy(['travelPlan' => $travelPlan]);

            if ($album instanceof TravelMemoryAlbum) {
                $this->removeMedia($album->getMediaId());
            }

            // Doctrine verwijdert het plan vóór de aanvraag (FK-volgorde);
            // de database cascadet de rest van de planrelaties.
            $this->entityManager->remove($travelPlan);
        }

        $this->entityManager->remove($travelRequest);
        $this->entityManager->flush();
    }

    private function removeMedia(?int $mediaId): void
    {
        if (null === $mediaId) {
            return;
        }

        try {
            $this->mediaManager->delete($mediaId);
        } catch (MediaNotFoundException) {
            // Al weg; geen reden om het verwijderen van de aanvraag te blokkeren.
        }
    }
}
