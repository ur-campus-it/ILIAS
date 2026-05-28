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

/**
 * Class ilObjIpAddressDefinitionGUI
 *
 * @author            : Bastian Meissner <bastian.meissner@ur.de>
 *
 * @ilCtrl_IsCalledBy ilObjIpAddressDefinitionGUI: ilAdministrationGUI, ilObjIPAddressAdministrationGUI
 * @ilCtrl_Calls      ilObjIpAddressDefinitionGUI: ilCommonActionDispatcherGUI
 * @ilCtrl_Calls      ilObjIpAddressDefinitionGUI: ilColumnGUI, ilObjectCopyGUI, ilUserTableGUI
 * @ilCtrl_Calls      ilObjIpAddressDefinitionGUI: ilPermissionGUI
 * @ilCtrl_Calls      ilObjIpAddressDefinitionGUI: ilInfoScreenGUI
 * @ilCtrl_Calls      ilObjIpAddressDefinitionGUI: ilExportGUI
 */
final class ilObjIpAddressDefinitionGUI extends ilObject2GUI
{

    public function __construct()
    {
        $this->type = 'ipad';

        $container = $GLOBALS['DIC'];
        $ref_id = $container->http()->wrapper()->query()->retrieve("ref_id", $container->refinery()->kindlyTo()->int());
        $root_id = ilObjIpAddressAdministration::getRootObjId();

        parent::__construct($ref_id, self::REPOSITORY_NODE_ID, $root_id);

        $this->lng->loadLanguageModule("ipad");
        $this->lng->loadLanguageModule("meta");
    }

    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd();
        $next_class = $this->ctrl->getNextClass($this);

        if (!$next_class && ($cmd === 'create' || $cmd === 'save')) {
            $this->setCreationMode();
        }

        if ($this->id_type === self::REPOSITORY_NODE_ID) {
            $this->setLocator();
        }

        switch ($next_class) {
            case strtolower(ilInfoScreenGUI::class):
            case "infoScreen":
                parent::prepareOutput();
                $this->tabs_gui->activateTab('info_short');
                $ilInfoScreenGUI = new ilInfoScreenGUI($this);
                $this->ctrl->forwardCommand($ilInfoScreenGUI);
                break;
            default:
                parent::executeCommand();
        }
    }

    public function view(): void
    {
        $this->tabs_gui->activateTab('view');
    }

    public function infoScreen(): void
    {
        $this->ctrl->redirectByClass(strtolower(ilInfoScreenGUI::class), "showSummary");
    }

    protected function getTabs(): void
    {
        if ($this->checkPermissionBool('visible,read')) {
            $this->tabs_gui->addTab('view', $this->lng->txt("view"), $this->ctrl->getLinkTargetByClass(strtolower($this::class), "view"));
            $this->tabs_gui->addTab("info_short", "Info", $this->ctrl->getLinkTargetByClass(strtolower(ilInfoScreenGUI::class), "showSummary"));
        }

        if ($this->checkPermissionBool('write')) {
            $this->tabs_gui->addTab('settings', $this->lng->txt("settings"), $this->ctrl->getLinkTarget($this, "edit"));
        }
    }

    public function getType(): string
    {
        return $this->type;
    }

    public static function _goto(string $refId): void
    {
        /**
         * @var \ILIAS\DI\Container $container
         */
        $container = $GLOBALS['DIC'];
        if (!ilObject::_exists((int) $refId, true)) {
            $container["tpl"]->setOnScreenMessage(
                'failure',
                $container->language()->txt("permission_denied"),
                true
            );
            $container->ctrl()->redirectByClass(ilDashboardGUI::class, "");
        }
        $container->ctrl()->setParameterByClass(strtolower(self::class), 'ref_id', $refId);
        $container->ctrl()->redirectByClass([
            strtolower(ilAdministrationGUI::class),
            strtolower(ilObjIpAddressAdministrationGUI::class),
            strtolower(self::class),
        ], 'view');
    }
}
