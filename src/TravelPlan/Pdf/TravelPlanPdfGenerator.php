<?php

declare(strict_types=1);

namespace App\TravelPlan\Pdf;

use App\Entity\TravelPlan;
use App\TravelPlan\Renderer\TravelPlanPdfRenderer;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class TravelPlanPdfGenerator
{
    public function __construct(
        private TravelPlanPdfRenderer $renderer,
        #[Autowire('%kernel.cache_dir%/mpdf')]
        private string $tempDir,
        #[Autowire('%kernel.project_dir%/assets/pdf/fonts')]
        private string $fontDir,
        #[Autowire('%kernel.project_dir%/assets/styles/travel-plan-pdf.css')]
        private string $pdfStylesheet,
    ) {
    }

    /**
     * @throws MpdfException
     */
    public function generate(TravelPlan $travelPlan): string
    {
        if (!\class_exists(Mpdf::class)) {
            throw new \LogicException(
                'The mpdf/mpdf package is required to generate TravelPlan PDFs.',
            );
        }

        if (!\is_dir($this->tempDir) && !\mkdir($this->tempDir, 0775, true) && !\is_dir($this->tempDir)) {
            throw new \RuntimeException(\sprintf(
                'Unable to create the mPDF temporary directory "%s".',
                $this->tempDir,
            ));
        }

        $defaultConfig = (new ConfigVariables())->getDefaults();
        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf = new Mpdf([
            'format' => 'A4',
            'tempDir' => $this->tempDir,
            'fontDir' => [...$defaultConfig['fontDir'], $this->fontDir],
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
            // Verbied het verkleinen van tabellen die nét niet in de
            // resterende paginaruimte passen (mini-tekst in kaarten);
            // met waarde 1 schuiven ze gewoon door naar de volgende pagina.
            'shrink_tables_to_fit' => 1,
            'margin_top' => 0,
            'margin_right' => 0,
            'margin_bottom' => 0,
            'margin_left' => 0,
        ]);

        $stylesheet = \file_get_contents($this->pdfStylesheet);

        if (false === $stylesheet) {
            throw new \RuntimeException(\sprintf(
                'Unable to read the TravelPlan stylesheet "%s".',
                $this->pdfStylesheet,
            ));
        }

        $mpdf->WriteHTML($stylesheet, HTMLParserMode::HEADER_CSS);

        // NB: géén extra wrapper-div per chunk toevoegen: een div om elke
        // chunk laat mPDF na de eerste automatische paginaovergang de
        // achtergrondkleuren van latere blokken "vergeten" (verbleekte
        // hero's). Basisstijlen worden daarom via sectieklassen
        // geselecteerd (.travel-plan-section h2, ...) i.p.v. .travel-plan.
        foreach ($this->splitHtmlBody($this->renderer->render($travelPlan)) as $htmlChunk) {
            if ('<!--PDF-CHUNK-->' === $htmlChunk) {
                continue;
            }

            $mpdf->WriteHTML($htmlChunk, HTMLParserMode::HTML_BODY);
        }

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * @return list<string>
     */
    private function splitHtmlBody(string $html): array
    {
        $parts = \preg_split(
            '/(<(?:pagebreak|tocpagebreak|tocentry)\b[^>]*\/>|<!--PDF-CHUNK-->)/i',
            $html,
            -1,
            \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY,
        );

        if (false === $parts) {
            return [$html];
        }

        return \array_values(\array_filter(
            $parts,
            static fn (string $part): bool => '' !== \trim($part),
        ));
    }
}
