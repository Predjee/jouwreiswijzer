<?php

declare(strict_types=1);

namespace App\TravelPlan\Renderer;

use App\Entity\TravelPlan;
use App\TravelPlan\Content\SectionType;
use App\TravelPlan\Content\TravelPlanContent;
use App\TravelPlan\View\DestinationView;
use App\TravelPlan\View\RenderedSection;
use App\TravelPlan\View\SectionView;
use App\TravelPlan\View\ThemeView;
use App\TravelPlan\View\TravelPlanViewFactory;
use Twig\Environment;

/**
 * Bouwt de HTML voor de PDF-weergave van een reisplan.
 *
 * Werkt op het getypeerde contentmodel (TravelPlanContent); alle markup en
 * inline styling staat in de Twig-templates onder templates/travel_plan/pdf/
 * met de tokens uit TravelPlanStyle. De account-weergave heeft zijn eigen
 * renderer (TravelPlanRenderer).
 */
final readonly class TravelPlanPdfRenderer
{
    public function __construct(
        private Environment $twig,
        private TravelPlanContentHelper $helper,
        private TravelPlanViewFactory $viewFactory,
    ) {
    }

    public function render(TravelPlan $travelPlan): string
    {
        $content = TravelPlanContent::fromArray($travelPlan->getContent());
        $theme = $this->viewFactory->theme();
        $tocDepth = $this->helper->tableOfContentsDepth($content->tripProfile->showTableOfContents);
        $sections = [];
        $tableOfContents = [];

        foreach ($this->viewFactory->destinations($content) as $destination) {
            $sections[] = $this->destinationViewModel($destination, $tocDepth, $tableOfContents, $theme);

            foreach ($destination->sections as $section) {
                $renderedSection = $this->sectionViewModel($section, $tocDepth, $tableOfContents, $theme);

                if (null !== $renderedSection) {
                    $sections[] = $renderedSection;
                }
            }
        }

        $contact = $travelPlan->getTravelRequest()->getContact();
        $customerName = \trim(\implode(' ', \array_filter([$contact->getFirstName(), $contact->getLastName()])));

        return $this->twig->render('travel_plan/pdf/document.html.twig', [
            'travelPlan' => $travelPlan,
            'customerName' => $customerName,
            'intro' => ['title' => $content->introTitle, 'text' => $content->introText],
            'tripProfile' => $content->tripProfile->raw,
            'logoSrc' => $this->helper->assetDataUri('assets/images/pdf/logo-pdf.png', 'image/png'),
            'showTableOfContents' => $tocDepth > 0,
            'tableOfContents' => $tableOfContents,
            'sections' => $sections,
            'theme' => $theme,
        ]);
    }

    /**
     * @param list<array{title: string, level: int}> $tableOfContents
     */
    private function destinationViewModel(
        DestinationView $destination,
        int $tocDepth,
        array &$tableOfContents,
        ThemeView $theme,
    ): RenderedSection {
        $tocTitle = $this->registerTocEntry($tableOfContents, $destination->title, 0, $tocDepth);
        $html = SectionType::Image->value === $destination->type
            ? $this->renderPdfImage($destination)
            : $this->twig->render('travel_plan/pdf/destination.html.twig', [
                'section' => $destination,
                'theme' => $theme,
            ]);

        return new RenderedSection(
            html: $html,
            path: $destination->path,
            blockPath: (string) $destination->path,
            blockType: $destination->type,
            feedback: null,
            tocTitle: $tocTitle,
            tocLevel: 0,
            startOnNewPage: $destination->startOnNewPage,
        );
    }

    /**
     * @param list<array{title: string, level: int}> $tableOfContents
     */
    private function sectionViewModel(
        SectionView $section,
        int $tocDepth,
        array &$tableOfContents,
        ThemeView $theme,
    ): ?RenderedSection {
        $tocTitle = $this->registerTocEntry($tableOfContents, $section->title, 1, $tocDepth);
        $html = match ($section->type) {
            SectionType::Day->value => $this->renderDayGroup($section, $theme),
            SectionType::FreeText->value, SectionType::PracticalInfo->value => $this->renderFrame($section, $theme),
            default => $this->renderPdfSection($section, $theme),
        };

        if (null === $html) {
            return null;
        }

        return new RenderedSection(
            html: $html,
            path: $section->path,
            blockPath: (string) $section->path,
            blockType: $section->type,
            feedback: null,
            tocTitle: $tocTitle,
            tocLevel: 1,
            startOnNewPage: $section->startOnNewPage,
        );
    }

    /**
     * @param list<array{title: string, level: int}> $tableOfContents
     */
    private function registerTocEntry(array &$tableOfContents, string $title, int $level, int $tocDepth): ?string
    {
        if ($level >= $tocDepth) {
            return null;
        }

        $tocTitle = $this->helper->tableOfContentsTitle($title);

        if (null !== $tocTitle) {
            $tableOfContents[] = ['title' => $tocTitle, 'level' => $level];
        }

        return $tocTitle;
    }

    private function renderDayGroup(SectionView $section, ThemeView $theme): string
    {
        return $this->twig->render('travel_plan/pdf/day_group.html.twig', [
            'group' => $section->day ?? $section,
            'theme' => $theme,
        ]);
    }

    private function renderFrame(SectionView $section, ThemeView $theme): string
    {
        return $this->twig->render('travel_plan/pdf/frame_section.html.twig', [
            'section' => $section,
            'theme' => $theme,
        ]);
    }

    private function renderPdfSection(SectionView $section, ThemeView $theme): ?string
    {
        if (SectionType::RouteOverview->value === $section->type) {
            return $this->renderPdfRouteOverview($section);
        }

        if (\in_array($section->type, [
            SectionType::Checklist->value,
            SectionType::BudgetNote->value,
            SectionType::PersonalNote->value,
        ], true)) {
            return $this->renderFrame($section, $theme);
        }

        return null;
    }

    private function renderPdfRouteOverview(SectionView $section): string
    {
        $html = '<div class="travel-plan-section travel-plan-section--route">';

        if ('' !== $section->title) {
            $html .= '<h2>' . $this->escape($section->title) . '</h2>';
        }

        if ('' !== $section->text) {
            $html .= '<div class="travel-plan-section__content">' . $section->text . '</div>';
        }

        if ([] !== $section->routeStops) {
            $html .= '<table class="travel-plan-route">';

            foreach ($section->routeStops as $index => $stop) {
                $html .= '<tr><td class="travel-plan-route__marker">';
                $html .= '' !== ($stop['_iconMarkup'] ?? '') ? $stop['_iconMarkup'] : (string) ($index + 1);
                $html .= '</td><td class="travel-plan-route__content">';

                if ('' !== ($stop['title'] ?? '')) {
                    $html .= '<h3>' . $this->escape($stop['title']) . '</h3>';
                }

                if ('' !== ($stop['location'] ?? '')) {
                    $html .= '<p class="travel-plan-route__location">' . $this->escape($stop['location']) . '</p>';
                }

                if ('' !== ($stop['text'] ?? '')) {
                    $html .= '<div class="travel-plan-section__content">' . $stop['text'] . '</div>';
                }

                $html .= '</td></tr>';
            }

            $html .= '</table>';
        }

        return $html . '</div>';
    }

    private function renderPdfImage(DestinationView $destination): string
    {
        $html = '<div class="travel-plan-section travel-plan-section--image">';

        if ('' !== $destination->title) {
            $html .= '<h2>' . $this->escape($destination->title) . '</h2>';
        }

        if (null !== $destination->imageSrc) {
            $html .= '<figure class="travel-plan-image-block">';
            $html .= '<img src="' . $this->escape($destination->imageSrc) . '" alt="' . $this->escape('' !== $destination->title ? $destination->title : 'Reisbeeld') . '">';

            if ('' !== $destination->caption) {
                $html .= '<figcaption>' . $this->escape($destination->caption) . '</figcaption>';
            }

            $html .= '</figure>';
        }

        return $html . '</div>';
    }

    private function escape(string $value): string
    {
        return \htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
