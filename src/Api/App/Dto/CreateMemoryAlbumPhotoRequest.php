<?php

declare(strict_types=1);

namespace App\Api\App\Dto;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class CreateMemoryAlbumPhotoRequest
{
    public function __construct(
        #[Assert\NotNull(message: 'Een foto is verplicht.')]
        #[Assert\Image(
            mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
            mimeTypesMessage: 'Alleen JPEG, PNG en WebP afbeeldingen zijn toegestaan.',
        )]
        public ?UploadedFile $image,
        #[Assert\Length(max: 300)]
        public ?string $caption = null,
        #[Assert\Length(max: 80)]
        public ?string $capturedAt = null,
    ) {
    }

    #[Assert\Callback]
    public function validateUploadSize(ExecutionContextInterface $context): void
    {
        if (!$this->image instanceof UploadedFile || !$this->image->isValid()) {
            return;
        }

        $maxFilesize = UploadedFile::getMaxFilesize();
        $size = $this->image->getSize();

        if (null !== $size && $size > $maxFilesize) {
            $context
                ->buildViolation('De foto is groter dan de toegestane uploadlimiet.')
                ->atPath('image')
                ->addViolation();
        }
    }
}
