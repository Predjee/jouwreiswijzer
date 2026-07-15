<?php

declare(strict_types=1);

namespace App\Api\App\Dto;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class CreateMemoryAlbumRequest
{
    /**
     * @param list<CreateMemoryAlbumPhotoRequest> $photos
     */
    public function __construct(
        #[Assert\Count(
            min: 1,
            max: 40,
            minMessage: 'Upload minimaal een foto.',
            maxMessage: 'Upload maximaal 40 foto\'s per album.',
        )]
        #[Assert\Valid]
        public array $photos,
        #[Assert\Length(max: 120)]
        public string $albumTitle = '',
        #[Assert\Length(max: 1000)]
        public string $albumIntro = '',
        #[Assert\Type('bool')]
        public bool $regenerate = false,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $photoData = $request->request->all('photos');
        $photoFiles = $request->files->all('photos');

        $indexes = \array_values(\array_unique([
            ...\array_keys($photoData),
            ...\array_keys($photoFiles),
        ]));
        \sort($indexes);

        $photos = [];

        foreach ($indexes as $index) {
            $data = \is_array($photoData[$index] ?? null) ? $photoData[$index] : [];
            $file = self::imageFile($photoFiles[$index] ?? null);

            $photos[] = new CreateMemoryAlbumPhotoRequest(
                $file,
                self::nullableString($data['caption'] ?? null),
                self::nullableString($data['capturedAt'] ?? null),
            );
        }

        return new self(
            $photos,
            self::string($request->request->get('albumTitle')),
            self::string($request->request->get('albumIntro')),
            self::bool($request->request->get('regenerate')),
        );
    }

    #[Assert\Callback]
    public function validateUploadCount(ExecutionContextInterface $context): void
    {
        $maxFileUploads = (int) \ini_get('max_file_uploads');

        if ($maxFileUploads > 0 && \count($this->photos) >= $maxFileUploads && $maxFileUploads < 40) {
            $context
                ->buildViolation('Het aantal foto\'s bereikt de serverlimiet voor uploads; upload minder foto\'s per request.')
                ->atPath('photos')
                ->addViolation();
        }
    }

    private static function imageFile(mixed $file): ?UploadedFile
    {
        if ($file instanceof UploadedFile) {
            return $file;
        }

        if (\is_array($file) && ($file['image'] ?? null) instanceof UploadedFile) {
            return $file['image'];
        }

        return null;
    }

    private static function string(mixed $value): string
    {
        return \is_string($value) ? \trim($value) : '';
    }

    private static function nullableString(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $value = \trim($value);

        return '' !== $value ? $value : null;
    }

    private static function bool(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (!\is_string($value) && !\is_int($value)) {
            return false;
        }

        return true === \filter_var($value, \FILTER_VALIDATE_BOOLEAN);
    }
}
