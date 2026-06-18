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

final class PushRuleAdmin extends Admin
{
    public const RESOURCE_KEY = 'push_rules';
    public const LIST_KEY = 'push_rules';
    public const FORM_KEY = 'push_rule_details';
    public const SECURITY_CONTEXT = 'jouwreiswijzer.push_rules';

    private const LIST_VIEW = 'jouwreiswijzer.push_rules.list';
    private const ADD_VIEW = 'jouwreiswijzer.push_rules.add';
    private const EDIT_VIEW = 'jouwreiswijzer.push_rules.edit';

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

        $module = new NavigationItem('Pushmeldingen');
        $module->setPosition(40);

        $rules = new NavigationItem('Pushregels');
        $rules->setPosition(10);
        $rules->setView(self::LIST_VIEW);

        $module->addChild($rules);
        $navigationItemCollection->add($module);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            return;
        }

        $listView = $this->viewBuilderFactory->createListViewBuilder(self::LIST_VIEW, '/push-rules')
            ->setResourceKey(self::RESOURCE_KEY)
            ->setListKey(self::LIST_KEY)
            ->setTitle('Pushregels')
            ->addListAdapters(['table'])
            ->setEditView(self::EDIT_VIEW);

        if ($this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::ADD)) {
            $listView
                ->setAddView(self::ADD_VIEW)
                ->addToolbarActions([new ToolbarAction('sulu_admin.add')]);
        }

        if ($this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::DELETE)) {
            $listView->addToolbarActions([new ToolbarAction('sulu_admin.delete')]);
        }

        $viewCollection->add($listView);

        if ($this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::ADD)) {
            $viewCollection->add(
                $this->viewBuilderFactory->createResourceTabViewBuilder(self::ADD_VIEW, '/push-rules/add')
                    ->setResourceKey(self::RESOURCE_KEY)
                    ->setBackView(self::LIST_VIEW),
            );

            $viewCollection->add(
                $this->viewBuilderFactory->createFormViewBuilder(self::ADD_VIEW.'.details', '/details')
                    ->setResourceKey(self::RESOURCE_KEY)
                    ->setFormKey(self::FORM_KEY)
                    ->setTabTitle('Details')
                    ->setEditView(self::EDIT_VIEW)
                    ->setParent(self::ADD_VIEW)
                    ->addToolbarActions([new ToolbarAction('sulu_admin.save')]),
            );
        }

        $viewCollection->add(
            $this->viewBuilderFactory->createResourceTabViewBuilder(self::EDIT_VIEW, '/push-rules/:id')
                ->setResourceKey(self::RESOURCE_KEY)
                ->setBackView(self::LIST_VIEW)
                ->setTitleProperty('name'),
        );

        $formView = $this->viewBuilderFactory->createFormViewBuilder(self::EDIT_VIEW.'.details', '/details')
            ->setResourceKey(self::RESOURCE_KEY)
            ->setFormKey(self::FORM_KEY)
            ->setTabTitle('Details')
            ->setParent(self::EDIT_VIEW);

        if ($this->securityChecker->hasPermission(self::SECURITY_CONTEXT, PermissionTypes::EDIT)) {
            $formView->addToolbarActions([new ToolbarAction('sulu_admin.save')]);
        }

        $viewCollection->add($formView);
    }

    public function getSecurityContexts(): array
    {
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'Pushmeldingen' => [
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
