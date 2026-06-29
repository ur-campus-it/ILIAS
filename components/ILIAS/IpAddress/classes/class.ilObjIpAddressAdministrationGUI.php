<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

use ILIAS\IpAddress\Component\ilIpAddressDefinitionFormGUI;
use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;

/**
 * Class ilObjIpAddressAdministrationGUI GUI class
 * @author            : Bastian Meissner <bastian.meissner@ur.de>
 * @ilCtrl_IsCalledBy ilObjIpAddressAdministrationGUI: ilAdministrationGUI
 * @ilCtrl_Calls      ilObjIpAddressAdministrationGUI: ilCommonActionDispatcherGUI
 * @ilCtrl_Calls      ilObjIpAddressAdministrationGUI: ilColumnGUI, ilObjectCopyGUI, ilUserTableGUI
 * @ilCtrl_Calls      ilObjIpAddressAdministrationGUI: ilPermissionGUI
 * @ilCtrl_Calls      ilObjIpAddressAdministrationGUI: ilInfoScreenGUI
 * @ilCtrl_Calls      ilObjIpAddressAdministrationGUI: ilPropertyFormGUI
 * @ilCtrl_Calls      ilObjIpAddressAdministrationGUI: ilIpAddressDefinitionFormGUI
 */
final class ilObjIpAddressAdministrationGUI extends ilContainerGUI
{
    public function __construct()
    {
        $this->type = 'ipaa';

        $ref_id = ilObjIpAddressAdministration::getRootRefId();
        parent::__construct([], $ref_id, true, false);

        $this->lng->loadLanguageModule("ipad");
        $this->lng->loadLanguageModule("cntr");
    }

    protected function supportsPageEditor(): bool
    {
        return false;
    }

    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd();
        $next_class = $this->ctrl->getNextClass($this);

        switch ($next_class) {
            case strtolower(ilPermissionGUI::class):
                parent::prepareOutput();
                $this->tabs_gui->activateTab('perm_settings');
                $ilPermissionGUI = new ilPermissionGUI($this);
                $this->ctrl->forwardCommand($ilPermissionGUI);
                break;
            case strtolower(ilInfoScreenGUI::class):
                parent::prepareOutput();
                $this->tabs_gui->activateTab('info_short');
                $ilInfoScreenGUI = new ilInfoScreenGUI($this);
                $this->ctrl->forwardCommand($ilInfoScreenGUI);
                break;
            case strtolower(ilObjIpAddressDefinitionGUI::class):

                if (!$this->request_wrapper->has("ref_id")) {
                    break;
                }

                $ilIpAddressDefinitionGUI = new ilObjIpAddressDefinitionGUI();
                $ilIpAddressDefinitionGUI->setAdminMode($this->admin_mode);

                $this->ctrl->setParameter(
                    $this,
                    'ref_id',
                    ilObjIpAddressAdministration::getRootRefId()
                );
                $this->tabs->setBackTarget(
                    $this->lng->txt('objs_ipad'),
                    $this->ctrl->getLinkTarget($this, 'view')
                );
                $this->ctrl->clearParameters($this);
                $this->ctrl->forwardCommand($ilIpAddressDefinitionGUI);
                break;
            default:
                parent::executeCommand();
        }
    }

    public function deleteObject(bool $error = false): void
    {

        $ref_id = null;
        if ($this->request_wrapper->has("item_ref_id")) {
            $ref_id = $this->request_wrapper->retrieve("item_ref_id", $this->refinery->kindlyTo()->int());
        } else {
            $ref_id = $this->post_wrapper->retrieve(
                'id',
                $this->refinery->kindlyTo()->listOf(
                    $this->refinery->kindlyTo()->int()
                )
            );
        }

        if ($this->checkReferences($ref_id)) {

            if (is_int($ref_id)) {
                $obj = new ilObjIpAddressDefinition($ref_id);
                $ref_tsts = implode(", ", iterator_to_array($obj->getTestReferences()));
                $txt = sprintf($this->lng->txt('msg_ref_by_tst'), $ref_tsts);
            }

            if (is_array($ref_id) && count($ref_id) === 1) {
                $obj = new ilObjIpAddressDefinition(current($ref_id));
                $ref_tsts = implode(", ", iterator_to_array($obj->getTestReferences()));
                $txt = sprintf($this->lng->txt('msg_ref_by_tst'), $ref_tsts);
            }

            if (is_array($ref_id) && count($ref_id) !== 1) {
                $txt = $this->lng->txt('msg_ref_by_tst_plural');
            }

            $this->tpl->setOnScreenMessage('failure', $txt, true);
            $this->ctrl->returnToParent($this);
        } else {
            parent::deleteObject($error);
        }
    }

    protected function checkReferences(array | int $ref_id): bool
    {
        if (is_int($ref_id)) {
            return new ilObjIpAddressDefinition($ref_id)->isReferenced();
        }

        foreach($ref_id as $id) {
            if (new ilObjIpAddressDefinition($id)->isReferenced()) return true;
        }

        return false;
    }

    /**
     * called by prepare output
     */
    protected function setTitleAndDescription(): void
    {
        parent::setTitleAndDescription();
        $this->tpl->setTitle($this->lng->txt("objs_ipad"));
        $this->tpl->setDescription($this->lng->txt("objs_ipad"));
        $this->tpl->setTitleIcon("", $this->lng->txt("obj_ipad"));
    }

    protected function showPossibleSubObjects(): void
    {
        $subtypes = $this->getCreatableObjectTypes();
        if (empty($subtypes)) {
            return;
        }
        $gui = new ILIAS\ILIASObject\Creation\AddNewItemGUI(
            [$this->buildGroup(
                self::class,
                array_keys($subtypes),
                $this->lng->txt('other'),
                $subtypes
            )]
        );
        $gui->render();
    }

    protected function getCreatableObjectTypes(): array
    {
        $subtypes = $this->obj_definition->getCreatableSubObjects(
            $this->object->getType(),
            ilObjectDefinition::MODE_ADMINISTRATION,
            $this->object->getRefId()
        );

        return array_filter(
            $subtypes,
            fn($key) => $this->access->checkAccess('create_' . $key, '', $this->ref_id, $this->type),
            ARRAY_FILTER_USE_KEY
        );
    }

    public function viewObject(): void
    {
        $this->tabs_gui->activateTab('view_content');

        if (!$this->rbacsystem->checkAccess("read", $this->getRefId())) {
            $this->ilias->raiseError($this->lng->txt("msg_no_perm_read"), $this->ilias->error_obj->WARNING);
        }

        if (!in_array(SYSTEM_ROLE_ID, $this->rbacreview->assignedRoles($this->user->getId()), true)) {
            $this->tpl->setOnScreenMessage(
                ilGlobalTemplateInterface::MESSAGE_TYPE_INFO,
                $this->lng->txt("msg_no_perm_view")
            );
        }

        parent::renderObject();
    }

    public function returnObject(): void
    {
        $this->viewObject();
    }

    protected function getCreationFormTitle(): string
    {
        return $this->lng->txt('ipad_add');
    }

    protected function initCreateForm(string $new_type): StandardForm
    {
        $form = new ilIpAddressDefinitionFormGUI("save")->get($this);
        return $form;
    }

    protected function getTabs(): void
    {
        $read_access_ref_id = $this->rbacsystem->checkAccess('read', $this->object->getRefId());
        if ($read_access_ref_id) {
            $this->tabs_gui->addTab('view_content', $this->lng->txt("content"), $this->ctrl->getLinkTarget($this, "view"));
            $this->tabs_gui->addTab(
                "info_short",
                $this->lng->txt('tab_info'),
                $this->ctrl->getLinkTargetByClass([
                    strtolower(self::class),
                    strtolower(ilInfoScreenGUI::class)
                ], "showSummary")
            );
        }
        if ($this->tree->getSavedNodeData($this->object->getRefId())) {
            $this->tabs_gui->addTarget('trash', $this->ctrl->getLinkTarget($this, 'trash'), 'trash', get_class($this));
        }
        parent::getTabs();
    }

    /**
     * @param ilTabsGUI $tabs_gui
     */
    public function getAdminTabs(): void
    {
        $this->getTabs();
    }

    public static function _goto(): void
    {
        $container = $GLOBALS['DIC'];
        $container->ctrl()->setParameterByClass(strtolower(self::class), 'ref_id', ilObjIpAddressAdministration::getRootRefId());
        $container->ctrl()->redirectByClass([
            strtolower(ilAdministrationGUI::class),
            strtolower(self::class),
        ], 'view');
    }

}
