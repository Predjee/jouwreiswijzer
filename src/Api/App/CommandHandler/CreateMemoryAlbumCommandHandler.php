<?php

declare(strict_types=1);

namespace App\Api\App\CommandHandler;

use App\Api\App\Dto\CreateMemoryAlbumPhotoRequest;
use App\Api\App\Dto\CreateMemoryAlbumRequest;
use App\Entity\TravelMemoryAlbum;
use App\Entity\TravelPlan;
use App\Repository\TravelMemoryAlbumRepository;
use App\TravelPlan\Pdf\TravelMemoryAlbumStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class CreateMemoryAlbumCommandHandler
{
    private const MAX_IMAGE_WIDTH = 1600;
    private const JPEG_QUALITY = 82;

    public function __construct(
        private readonly TravelMemoryAlbumRepository $albumRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TravelMemoryAlbumStorage $albumStorage,
    ) {
    }

    public function handle(TravelPlan $travelPlan, CreateMemoryAlbumRequest $request): TravelMemoryAlbum
    {
        $album = $this->albumRepository->findOneByTravelPlan($travelPlan);

        if (
            $album instanceof TravelMemoryAlbum
            && TravelMemoryAlbum::STATUS_READY === $album->getStatus()
            && !$request->regenerate
        ) {
            throw new ConflictHttpException('memory_album_already_ready');
        }

        if (!$album instanceof TravelMemoryAlbum) {
            $album = (new TravelMemoryAlbum())->setTravelPlan($travelPlan);
            $this->entityManager->persist($album);
        }

        $album->setStatus(TravelMemoryAlbum::STATUS_PROCESSING);
        $this->entityManager->flush();

        $temporaryFiles = [];

        try {
            $photos = $this->resizePhotos($request->photos, $temporaryFiles);
            $title = '' !== $request->albumTitle ? $request->albumTitle : $travelPlan->getTitle();

            return $this->albumStorage->generateAndStore(
                $travelPlan,
                $photos,
                $title,
                $request->albumIntro,
            );
        } catch (\Throwable $exception) {
            $album->setStatus(TravelMemoryAlbum::STATUS_FAILED);
            $this->entityManager->flush();

            throw $exception;
        } finally {
            foreach ($temporaryFiles as $temporaryFile) {
                if (\is_file($temporaryFile)) {
                    \unlink($temporaryFile);
                }
            }
        }
    }

    /**
     * @param list<CreateMemoryAlbumPhotoRequest> $photos
     * @param list<string>                        $temporaryFiles
     *
     * @return list<array{path: string, caption?: string|null, capturedAt?: string|null}>
     */
    private function resizePhotos(array $photos, array &$temporaryFiles): array
    {
        if (!\extension_loaded('gd')) {
            throw new \RuntimeException('The GD extension is required to resize memory album photos.');
        }

        $resizedPhotos = [];

        foreach ($photos as $photo) {
            $resizedPath = $this->resizePhoto($photo->image);
            $temporaryFiles[] = $resizedPath;

            $resizedPhotos[] = [
                'path' => $resizedPath,
                'caption' => $photo->caption,
                'capturedAt' => $photo->capturedAt,
            ];
        }

        return $resizedPhotos;
    }

    private function resizePhoto(?UploadedFile $file): string
    {
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            throw new UnprocessableEntityHttpException('invalid_photo_upload');
        }

        $sourcePath = $file->getRealPath();

        if (false === $sourcePath) {
            throw new UnprocessableEntityHttpException('invalid_photo_upload');
        }

        $sourceContent = \file_get_contents($sourcePath);

        if (false === $sourceContent) {
            throw new UnprocessableEntityHttpException('invalid_photo_upload');
        }

        $sourceImage = \imagecreatefromstring($sourceContent);

        if (false === $sourceImage) {
            throw new UnprocessableEntityHttpException('invalid_photo_upload');
        }

        try {
            $sourceWidth = \imagesx($sourceImage);
            $sourceHeight = \imagesy($sourceImage);
            $targetWidth = \min($sourceWidth, self::MAX_IMAGE_WIDTH);
            $targetHeight = \max(1, (int) \round($sourceHeight * ($targetWidth / $sourceWidth)));
            $targetImage = \imagecreatetruecolor($targetWidth, $targetHeight);

            if (false === $targetImage) {
                throw new \RuntimeException('Unable to create resized memory album photo.');
            }

            try {
                $white = \imagecolorallocate($targetImage, 255, 255, 255);

                if (false !== $white) {
                    \imagefill($targetImage, 0, 0, $white);
                }

                \imagecopyresampled(
                    $targetImage,
                    $sourceImage,
                    0,
                    0,
                    0,
                    0,
                    $targetWidth,
                    $targetHeight,
                    $sourceWidth,
                    $sourceHeight,
                );

                $temporaryFile = \tempnam(\sys_get_temp_dir(), 'memory-album-photo-');

                if (false === $temporaryFile) {
                    throw new \RuntimeException('Unable to create a temporary memory album photo.');
                }

                $resizedPath = $temporaryFile . '.jpg';
                \unlink($temporaryFile);

                if (!\imagejpeg($targetImage, $resizedPath, self::JPEG_QUALITY)) {
                    throw new \RuntimeException('Unable to write a resized memory album photo.');
                }

                return $resizedPath;
            } finally {
                \imagedestroy($targetImage);
            }
        } finally {
            \imagedestroy($sourceImage);
        }
    }
}
