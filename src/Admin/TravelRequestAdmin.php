<?php

declare(strict_types=1);

namespace App\Admin;

use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

final class TravelRequestAdmin extends Admin
{
    public const RESOURCE_KEY = 'travel_requests';
    public const LIST_KEY = 'travel_requests';
    public const SECURITY_CONTEXT = 'jouwreiswijzer.travel_requests';

    private const LIST_VIEW = 'jouwreiswijzer.travel_requests.list';
    private const EDIT_VIEW = 'jouwreiswijzer.travel_requests.edit';

    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly SecurityCheckerInterface $securityChecker,
    ) {
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        if (!$this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            return;
        }

        $item = new NavigationItem('Aanvragen');
        $item->setPosition(30);
        $item->setView(self::LIST_VIEW);
        $navigationItemCollection->add($item);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            return;
        }

        $viewCollection->add(
            $this->viewBuilderFactory->createListViewBuilder(self::LIST_VIEW, '/travel-requests')
                ->setResourceKey(self::RESOURCE_KEY)
                ->setListKey(self::LIST_KEY)
                ->setTitle('Aanvragen')
                ->addListAdapters(['table'])
                ->setEditView(self::EDIT_VIEW),
        );

        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder(
                self::EDIT_VIEW,
                '/travel-requests/:id',
            )
                ->setResourceKey(self::RESOURCE_KEY)
                ->setBackView(self::LIST_VIEW)
                ->setTitleProperty('contactName'),
        );

        $formView = $this->viewBuilderFactory
            ->createFormViewBuilder(self::EDIT_VIEW . '.details', '/details')
            ->setResourceKey(self::RESOURCE_KEY)
            ->setFormKey('travel_request_details')
            ->setTabTitle('Details')
            ->setParent(self::EDIT_VIEW);

        if ($this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            $formView->addToolbarActions([new ToolbarAction('sulu_admin.save')]);
        }

        $viewCollection->add($formView);

        $travelPlanView = $this->viewBuilderFactory
            ->createFormViewBuilder(self::EDIT_VIEW . '.travel_plan', '/travel-plan')
            ->setResourceKey('travel_request_plans')
            ->setFormKey('travel_plan_details')
            ->setTabTitle('Reisplan')
            ->setParent(self::EDIT_VIEW);

        if ($this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            $travelPlanView->addToolbarActions([
                new ToolbarAction('sulu_admin.save'),
                new ToolbarAction('sulu_admin.reload_form_store', [
                    'label' => 'PDF bijwerken',
                    'icon' => 'su-sync',
                    'route' => 'travel_request_plan.generate_pdf',
                    'dialogKey' => 'generate-travel-plan-pdf',
                    'dialogTitle' => 'PDF bijwerken',
                    'dialogDescription' => 'De huidige reisplangegevens worden als nieuwe PDF-versie opgeslagen.',
                    'dialogCancelText' => 'Annuleren',
                    'dialogOkText' => 'PDF bijwerken',
                ]),
                new ToolbarAction('sulu_admin.reload_form_store', [
                    'label' => 'Feedback verwerkt melden',
                    'icon' => 'su-envelope',
                    'route' => 'travel_request_plan.notify_feedback_processed',
                    'dialogKey' => 'notify-feedback-processed',
                    'dialogTitle' => 'Feedback verwerkt melden',
                    'dialogDescription' => 'De klant ontvangt één melding en e-mail dat de feedback is verwerkt.',
                    'dialogCancelText' => 'Annuleren',
                    'dialogOkText' => 'Melden',
                ]),
                new ToolbarAction('app.release_pdf', [
                    'url' => '/admin/api/travel-request-plans/{id}/release-pdf',
                    'label' => 'PDF vrijgeven',
                    'icon' => 'su-check-circle',
                    'loadingText' => 'PDF wordt vrijgegeven...',
                    'successText' => 'PDF is vrijgegeven voor de klant.',
                    'errorText' => 'PDF vrijgeven mislukt.',
                ]),
                new ToolbarAction('app.download', [
                    'url' => '/admin/api/travel-plans/{id}/pdf/download',
                    'label' => 'PDF downloaden',
                    'icon' => 'su-download',
                    'loadingText' => 'PDF wordt voorbereid...',
                    'successText' => 'PDF succesvol gedownload.',
                    'errorText' => 'PDF downloaden mislukt.',
                    'filename' => 'reisplan.pdf',
                ]),
            ]);
        }

        $viewCollection->add($travelPlanView);
    }

    public function getSecurityContexts(): array
    {
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'JouwReisWijzer' => [
                    self::SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::EDIT,
                        PermissionTypes::ADD,
                        PermissionTypes::DELETE,
                    ],
                ],
            ],
        ];
    }
}
