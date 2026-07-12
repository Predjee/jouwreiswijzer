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
    /** Sectietypes die (nog) via de gedeelde templates renderen. */
    private const SHARED_TEMPLATE_SECTIONS = [
        SectionType::RouteOverview->value => 'travel_plan/render/sections/route_overview.html.twig',
        SectionType::Checklist->value => 'travel_plan/render/sections/checklist.html.twig',
        SectionType::BudgetNote->value => 'travel_plan/render/sections/budget_note.html.twig',
        SectionType::PersonalNote->value => 'travel_plan/render/sections/personal_note.html.twig',
        SectionType::Image->value => 'travel_plan/render/sections/image.html.twig',
    ];

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
            $sections[] = $this->destinationViewModel($destination, $tocDepth, $tableOfContents, $theme, $travelPlan);

            foreach ($destination->sections as $section) {
                $renderedSection = $this->sectionViewModel($section, $tocDepth, $tableOfContents, $theme, $travelPlan);

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
        TravelPlan $travelPlan,
    ): RenderedSection {
        $tocTitle = $this->registerTocEntry($tableOfContents, $destination->title, 0, $tocDepth);
        $html = SectionType::Image->value === $destination->type
            ? $this->renderSharedDestination($destination, $travelPlan)
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
        TravelPlan $travelPlan,
    ): ?RenderedSection {
        $tocTitle = $this->registerTocEntry($tableOfContents, $section->title, 1, $tocDepth);
        $html = match ($section->type) {
            SectionType::Day->value => $this->renderDayGroup($section, $theme),
            SectionType::FreeText->value, SectionType::PracticalInfo->value => $this->renderFrame($section, $theme),
            default => $this->renderSharedSection($section, $travelPlan),
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

    private function renderSharedSection(SectionView $section, TravelPlan $travelPlan): ?string
    {
        $template = self::SHARED_TEMPLATE_SECTIONS[$section->type] ?? null;

        if (null === $template) {
            return null;
        }

        return $this->twig->render($template, [
            'section' => $section,
            'accountView' => false,
            'travelPlan' => $travelPlan,
            'feedbackEnabled' => false,
        ]);
    }

    private function renderSharedDestination(DestinationView $destination, TravelPlan $travelPlan): string
    {
        return $this->twig->render(self::SHARED_TEMPLATE_SECTIONS[$destination->type], [
            'section' => $destination,
            'accountView' => false,
            'travelPlan' => $travelPlan,
            'feedbackEnabled' => false,
        ]);
    }

}
