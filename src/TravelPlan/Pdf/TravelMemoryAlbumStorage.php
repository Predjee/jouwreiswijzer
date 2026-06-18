<?php

declare(strict_types=1);

namespace App\TravelPlan\Pdf;

use App\Entity\TravelMemoryAlbum;
use App\Entity\TravelPlan;
use App\Repository\TravelMemoryAlbumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Bundle\MediaBundle\Media\Exception\MediaNotFoundException;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Component\Media\SystemCollections\SystemCollectionManagerInterface;
use Sulu\Component\Security\Authentication\UserInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final readonly class TravelMemoryAlbumStorage
{
    private const COLLECTION_KEY = 'travel_memory_albums';

    public function __construct(
        private TravelMemoryAlbumGenerator $albumGenerator,
        private MediaManagerInterface $mediaManager,
        private SystemCollectionManagerInterface $systemCollectionManager,
        private EntityManagerInterface $entityManager,
        private TravelMemoryAlbumRepository $albumRepository,
        private Security $security,
        private SluggerInterface $slugger,
    ) {
    }

    /**
     * @param list<array{path?: string, url?: string, src?: string, caption?: string|null, capturedAt?: \DateTimeInterface|string|null}> $photos
     */
    public function generateAndStore(
        TravelPlan $travelPlan,
        array $photos,
        string $albumTitle,
        string $albumIntro,
    ): TravelMemoryAlbum {
        $content = $this->albumGenerator->generate($albumTitle, $albumIntro, $photos);
        $filename = $this->createFilename($travelPlan);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'travel-memory-album-');

        if (false === $temporaryFile) {
            throw new \RuntimeException('Unable to create a temporary TravelMemoryAlbum PDF.');
        }

        if (false === file_put_contents($temporaryFile, $content)) {
            unlink($temporaryFile);

            throw new \RuntimeException('Unable to write the temporary TravelMemoryAlbum PDF.');
        }

        try {
            $album = $this->albumRepository->findOneByTravelPlan($travelPlan) ?? (new TravelMemoryAlbum())
                ->setTravelPlan($travelPlan);

            $uploadedFile = new UploadedFile(
                $temporaryFile,
                $filename,
                'application/pdf',
                null,
                true,
            );
            $media = $this->saveMedia($uploadedFile, $album, $filename);
            $mediaEntity = $media->getEntity();
            $contact = $travelPlan->getTravelRequest()->getContact();

            if (!$contact->getMedias()->contains($mediaEntity)) {
                $contact->addMedia($mediaEntity);
            }

            $album
                ->setMediaId($media->getId())
                ->setPhotoCount(\count($photos))
                ->setGeneratedAt(new \DateTimeImmutable())
                ->setStatus(TravelMemoryAlbum::STATUS_READY);

            $this->entityManager->persist($album);
            $this->entityManager->flush();
        } finally {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }

        return $album;
    }

    private function saveMedia(
        UploadedFile $uploadedFile,
        TravelMemoryAlbum $album,
        string $filename,
    ): \Sulu\Bundle\MediaBundle\Api\Media {
        $user = $this->security->getUser();
        $locale = $user instanceof UserInterface ? ($user->getLocale() ?: 'nl') : 'nl';
        $data = [
            'collection' => $this->systemCollectionManager->getSystemCollection(self::COLLECTION_KEY),
            'locale' => $locale,
            'title' => pathinfo($filename, \PATHINFO_FILENAME),
        ];

        if (null !== $album->getMediaId()) {
            $data['id'] = $album->getMediaId();
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

    public function createFilename(TravelPlan $travelPlan): string
    {
        $titleSlug = strtolower((string) $this->slugger->slug($travelPlan->getTitle()));

        return sprintf(
            'album-%s-%d.pdf',
            '' !== $titleSlug ? $titleSlug : 'reisplan',
            $travelPlan->getId(),
        );
    }
}
