<?php

declare(strict_types=1);

namespace App\TravelPlan\Pdf;

use App\Entity\TravelPlan;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\MediaBundle\Media\Exception\MediaNotFoundException;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Media\SystemCollections\SystemCollectionManagerInterface;
use Sulu\Component\Security\Authentication\UserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class TravelPlanPdfStorage
{
    private const COLLECTION_KEY = 'travel_plan_documents';

    public function __construct(
        private TravelPlanPdfGenerator $pdfGenerator,
        private MediaManagerInterface $mediaManager,
        private SystemCollectionManagerInterface $systemCollectionManager,
        private EntityManagerInterface $entityManager,
        private Security $security,
        private SluggerInterface $slugger,
    ) {
    }

    public function generateAndStore(TravelPlan $travelPlan): int
    {
        $content = $this->pdfGenerator->generate($travelPlan);
        $filename = $this->createFilename($travelPlan);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'travel-plan-');

        if (false === $temporaryFile) {
            throw new \RuntimeException('Unable to create a temporary TravelPlan PDF.');
        }

        if (false === file_put_contents($temporaryFile, $content)) {
            unlink($temporaryFile);

            throw new \RuntimeException('Unable to write the temporary TravelPlan PDF.');
        }

        try {
            $uploadedFile = new UploadedFile(
                $temporaryFile,
                $filename,
                'application/pdf',
                null,
                true,
            );
            $media = $this->saveMedia($uploadedFile, $travelPlan, $filename);
            $mediaEntity = $media->getEntity();
            $contact = $travelPlan->getTravelRequest()->getContact();

            if (!$contact->getMedias()->contains($mediaEntity)) {
                $contact->addMedia($mediaEntity);
            }

            $travelPlan
                ->setPdfMediaId($media->getId())
                ->setPdfGeneratedAt(new \DateTimeImmutable());

            $this->entityManager->flush();
        } finally {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }

        return $media->getId();
    }

    private function saveMedia(
        UploadedFile $uploadedFile,
        TravelPlan $travelPlan,
        string $filename,
    ): \Sulu\Bundle\MediaBundle\Api\Media {
        $user = $this->security->getUser();
        $locale = $user instanceof UserInterface ? ($user->getLocale() ?: 'nl') : 'nl';
        $data = [
            'collection' => $this->systemCollectionManager->getSystemCollection(self::COLLECTION_KEY),
            'locale' => $locale,
            'title' => pathinfo($filename, \PATHINFO_FILENAME),
        ];

        if (null !== $travelPlan->getPdfMediaId()) {
            $data['id'] = $travelPlan->getPdfMediaId();
        }

        try {
            return $this->mediaManager->save(
                $uploadedFile,
                $data,
                $user instanceof UserInterface ? $user->getId() : null,
            );
        } catch (MediaNotFoundException) {
            unset($data['id']);

            return $this->mediaManager->save(
                $uploadedFile,
                $data,
                $user instanceof UserInterface ? $user->getId() : null,
            );
        }
    }

    private function createFilename(TravelPlan $travelPlan): string
    {
        $contact = $travelPlan->getTravelRequest()->getContact();
        $contactName = trim($contact->getFullName());
        $contactSlug = strtolower((string) $this->slugger->slug(
            '' !== $contactName ? $contactName : 'contact-'.$contact->getId(),
        ));

        return sprintf('reisplan-%s-%d.pdf', $contactSlug, $travelPlan->getId());
    }
}
