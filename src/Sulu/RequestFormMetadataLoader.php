<?php

declare(strict_types=1);

namespace App\Sulu;

use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FieldMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\ItemMetadata;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\FormMetadataLoaderInterface;
use Sulu\Bundle\AdminBundle\Metadata\FormMetadata\SectionMetadata;
use Sulu\Bundle\AdminBundle\Metadata\MetadataInterface;
use Sulu\Bundle\FormBundle\Metadata\DynamicFormMetadataLoader;

final class RequestFormMetadataLoader implements FormMetadataLoaderInterface
{
    public function __construct(private readonly DynamicFormMetadataLoader $inner)
    {
    }

    /**
     * @param array<string, mixed> $metadataOptions
     */
    public function getMetadata(
        string $key,
        string $locale,
        array $metadataOptions = [],
    ): ?MetadataInterface {
        $metadata = $this->inner->getMetadata($key, $locale, $metadataOptions);

        if (!$metadata instanceof FormMetadata || 'form_details' !== $key) {
            return $metadata;
        }

        $field = new FieldMetadata('isRequestForm');
        $field->setType('checkbox');
        $field->setLabels([
            'de' => 'Aanvraagformulier',
            'en' => 'Aanvraagformulier',
            'fr' => 'Aanvraagformulier',
            'nl' => 'Aanvraagformulier',
        ]);

        $items = $metadata->getItems();

        $items =
            \array_slice($items, 0, 1, true)
            + ['isRequestForm' => $field]
            + \array_slice($items, 1, null, true);

        $this->setMailTextEditorType($items);
        $metadata->setItems($items);

        return $metadata;
    }

    /**
     * @param array<ItemMetadata> $items
     */
    private function setMailTextEditorType(array $items): bool
    {
        foreach ($items as $item) {
            if ($item instanceof FieldMetadata && 'mailText' === $item->getName()) {
                $item->setType('form_mail_text_editor');

                return true;
            }

            if (
                $item instanceof SectionMetadata
                && $this->setMailTextEditorType($item->getItems())
            ) {
                return true;
            }

            if ($item instanceof FieldMetadata) {
                foreach ($item->getTypes() as $type) {
                    if ($this->setMailTextEditorType($type->getItems())) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
