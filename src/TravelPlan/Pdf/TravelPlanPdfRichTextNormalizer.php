<?php

declare(strict_types=1);

namespace App\TravelPlan\Pdf;

final readonly class TravelPlanPdfRichTextNormalizer
{
    public function normalize(string $html): string
    {
        if (!\str_contains($html, '<table') && !\str_contains($html, '<p')) {
            return $html;
        }

        $previousUseErrors = \libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',
            \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD,
        );
        \libxml_clear_errors();
        \libxml_use_internal_errors($previousUseErrors);

        if (!$loaded) {
            return $html;
        }

        $xpath = new \DOMXPath($dom);
        $this->replaceEditorTables($dom, $xpath);
        $this->addParagraphSpacers($dom, $xpath);

        $root = $dom->documentElement;

        if (!$root instanceof \DOMElement) {
            return $html;
        }

        $normalizedHtml = '';

        foreach ($root->childNodes as $childNode) {
            $normalizedHtml .= $dom->saveHTML($childNode);
        }

        return $normalizedHtml;
    }

    private function replaceEditorTables(\DOMDocument $dom, \DOMXPath $xpath): void
    {
        $tables = [];

        foreach ($xpath->query('//figure[contains(concat(" ", normalize-space(@class), " "), " table ")]') ?: [] as $figure) {
            $tables[] = $figure;
        }

        foreach ($xpath->query('//table[not(ancestor::figure[contains(concat(" ", normalize-space(@class), " "), " table ")])]') ?: [] as $table) {
            $tables[] = $table;
        }

        foreach ($tables as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $replacement = $this->buildTableReplacement($dom, $xpath, $node);

            if (null === $replacement || null === $node->parentNode) {
                continue;
            }

            $node->parentNode->replaceChild($replacement, $node);
        }
    }

    private function addParagraphSpacers(\DOMDocument $dom, \DOMXPath $xpath): void
    {
        $paragraphs = [];

        foreach ($xpath->query('//p[not(ancestor::table[contains(concat(" ", normalize-space(@class), " "), " travel-plan-editor-table ")])]') ?: [] as $paragraph) {
            if ($paragraph instanceof \DOMElement) {
                $paragraphs[] = $paragraph;
            }
        }

        foreach ($paragraphs as $paragraph) {
            if (!$this->hasFollowingRichTextSibling($paragraph) || null === $paragraph->parentNode) {
                continue;
            }

            $spacer = $dom->createElement('div', "\xc2\xa0");
            $spacer->setAttribute('class', 'travel-plan-paragraph-spacer');
            $paragraph->parentNode->insertBefore($spacer, $paragraph->nextSibling);
        }
    }

    private function hasFollowingRichTextSibling(\DOMElement $element): bool
    {
        for ($sibling = $element->nextSibling; null !== $sibling; $sibling = $sibling->nextSibling) {
            if ($sibling instanceof \DOMText && '' === \trim(\str_replace("\xc2\xa0", ' ', $sibling->textContent))) {
                continue;
            }

            if (!$sibling instanceof \DOMElement) {
                continue;
            }

            if ('div' === $sibling->tagName && 'travel-plan-paragraph-spacer' === $sibling->getAttribute('class')) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function buildTableReplacement(\DOMDocument $dom, \DOMXPath $xpath, \DOMElement $node): ?\DOMElement
    {
        $table = 'table' === $node->tagName ? $node : $xpath->query('.//table', $node)?->item(0);

        if (!$table instanceof \DOMElement) {
            return null;
        }

        $rows = [];

        foreach ($xpath->query('.//tr', $table) ?: [] as $row) {
            if (!$row instanceof \DOMElement) {
                continue;
            }

            $cells = [];

            foreach ($xpath->query('./th|./td', $row) ?: [] as $cell) {
                if (!$cell instanceof \DOMElement) {
                    continue;
                }

                if ('' === \trim(\str_replace("\xc2\xa0", ' ', $cell->textContent))) {
                    continue;
                }

                $cells[] = $cell;
            }

            if ([] !== $cells) {
                $rows[] = $cells;
            }
        }

        if (\count($rows) < 2) {
            return null;
        }

        $headings = $rows[0];
        $bodies = $rows[1];
        $columnCount = \min(\count($headings), \count($bodies));

        if (0 === $columnCount) {
            return null;
        }

        $replacementTable = $dom->createElement('table');
        $replacementTable->setAttribute('class', 'travel-plan-editor-table');
        $headingRow = $dom->createElement('tr');
        $bodyRow = $dom->createElement('tr');
        $columnWidth = \sprintf('%.4F%%', 100 / $columnCount);

        for ($index = 0; $index < $columnCount; ++$index) {
            $heading = $dom->createElement('th');
            $heading->setAttribute('style', 'width: ' . $columnWidth . ';');
            $this->appendChildren($dom, $heading, $headings[$index]);
            $headingRow->appendChild($heading);

            $body = $dom->createElement('td');
            $body->setAttribute('style', 'width: ' . $columnWidth . ';');
            $this->appendChildren($dom, $body, $bodies[$index]);
            $bodyRow->appendChild($body);
        }

        $replacementTable->appendChild($headingRow);
        $replacementTable->appendChild($bodyRow);

        return $replacementTable;
    }

    private function appendChildren(\DOMDocument $dom, \DOMElement $target, \DOMElement $source): void
    {
        foreach ($source->childNodes as $childNode) {
            $target->appendChild($dom->importNode($childNode, true));
        }
    }
}
