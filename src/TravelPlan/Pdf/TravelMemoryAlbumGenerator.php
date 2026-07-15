<?php

declare(strict_types=1);

namespace App\TravelPlan\Pdf;

use App\TravelPlan\Renderer\TravelMemoryAlbumRenderer;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class TravelMemoryAlbumGenerator
{
    public function __construct(
        private TravelMemoryAlbumRenderer $renderer,
        #[Autowire('%kernel.cache_dir%/mpdf')]
        private string $tempDir,
        #[Autowire('%kernel.project_dir%/assets/pdf/fonts')]
        private string $fontDir,
        #[Autowire('%kernel.project_dir%/assets/styles/travel-memory-album-pdf.css')]
        private string $pdfStylesheet,
    ) {
    }

    /**
     * @param list<array{path?: string, url?: string, src?: string, caption?: string|null, capturedAt?: \DateTimeInterface|string|null}> $photos
     *
     * @throws MpdfException
     */
    public function generate(string $albumTitle, ?string $albumIntro, array $photos): string
    {
        if (!\class_exists(Mpdf::class)) {
            throw new \LogicException(
                'The mpdf/mpdf package is required to generate TravelMemoryAlbum PDFs.',
            );
        }

        if (!\is_dir($this->tempDir) && !\mkdir($this->tempDir, 0775, true) && !\is_dir($this->tempDir)) {
            throw new \RuntimeException(\sprintf(
                'Unable to create the mPDF temporary directory "%s".',
                $this->tempDir,
            ));
        }

        // mPDF's getDefaults() is ongetypeerd (mixed); expliciet vernauwen.
        $defaultConfig = (new ConfigVariables())->getDefaults();
        $defaultFontConfig = (new FontVariables())->getDefaults();
        $defaultFontDirs = \is_array($defaultConfig) && \is_array($defaultConfig['fontDir'] ?? null)
            ? \array_values($defaultConfig['fontDir'])
            : [];
        $fontData = \is_array($defaultFontConfig) && \is_array($defaultFontConfig['fontdata'] ?? null)
            ? $defaultFontConfig['fontdata']
            : [];

        $mpdf = new Mpdf([
            'format' => 'A4',
            'tempDir' => $this->tempDir,
            'fontDir' => [...$defaultFontDirs, $this->fontDir],
            'fontdata' => \array_merge($fontData, [
                'jost' => [
                    'R' => 'Jost-Light.ttf',
                    'B' => 'Jost-Medium.ttf',
                ],
                'jostregular' => [
                    'R' => 'Jost-Regular.ttf',
                    'B' => 'Jost-Medium.ttf',
                ],
                'cormorant' => [
                    'R' => 'CormorantGaramond-Regular.ttf',
                    'B' => 'CormorantGaramond-Medium.ttf',
                    'I' => 'CormorantGaramond-Italic.ttf',
                ],
            ]),
            'default_font' => 'jost',
            'margin_top' => 0,
            'margin_right' => 0,
            'margin_bottom' => 0,
            'margin_left' => 0,
        ]);

        $stylesheet = \file_get_contents($this->pdfStylesheet);

        if (false === $stylesheet) {
            throw new \RuntimeException(\sprintf(
                'Unable to read the TravelMemoryAlbum stylesheet "%s".',
                $this->pdfStylesheet,
            ));
        }

        $mpdf->WriteHTML($stylesheet, HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($this->renderer->render($albumTitle, $albumIntro, $photos), HTMLParserMode::HTML_BODY);

        $output = $mpdf->Output('', Destination::STRING_RETURN);

        if (!\is_string($output)) {
            throw new \RuntimeException('mPDF gaf geen PDF-string terug.');
        }

        return $output;
    }
}
