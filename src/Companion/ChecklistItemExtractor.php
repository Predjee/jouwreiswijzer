<?php

declare(strict_types=1);

namespace App\Companion;

/**
 * Haalt checklist-items uit CMS-richtext.
 *
 * LET OP — sleutelstabiliteit: de item-key is sha1(pad|index|label) en
 * daar hangt opgeslagen afvinkstatus van gebruikers aan. De extractie
 * gebruikt daarom bewust dezelfde regex + strip_tags als altijd: een
 * DOM-parser zou HTML-entities decoderen (&amp; -> &), de labels — en
 * dus de sleutels — veranderen en alle vinkjes resetten. Niet
 * "moderniseren" zonder key-migratie.
 */
final readonly class ChecklistItemExtractor
{
    /**
     * @param array<string, bool> $checkedItems
     *
     * @return list<array{key: string, label: string, checked: bool}>
     */
    public function extract(string $text, string $path, array $checkedItems): array
    {
        $labels = [];

        if (1 === \preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $text, $matches)) {
            foreach ($matches[1] as $item) {
                $labels[] = \trim(\strip_tags((string) $item));
            }
        } else {
            foreach (\preg_split('/\R/', \strip_tags($text)) ?: [] as $line) {
                $line = \trim((string) $line, " \t\n\r\0\x0B-*•");

                if ('' !== $line) {
                    $labels[] = $line;
                }
            }
        }

        $items = [];

        foreach (\array_values(\array_unique(\array_filter($labels))) as $index => $label) {
            $key = \substr(\sha1($path.'|'.$index.'|'.$label), 0, 40);
            $items[] = [
                'key' => $key,
                'label' => $label,
                'checked' => $checkedItems[$key] ?? false,
            ];
        }

        return $items;
    }
}
