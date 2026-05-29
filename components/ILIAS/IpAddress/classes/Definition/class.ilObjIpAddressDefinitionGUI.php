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

use ILIAS\IpAddress\Objects\IpAddress;
use ILIAS\IpAddress\Objects\IpAddressRange;

use ILIAS\UI\URLBuilder;
use ILIAS\UI\URLBuilderToken;

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

        $df = new \ILIAS\Data\Factory();
        [$this->url_builder, $this->action_token, $this->row_token] = new URLBuilder(
            $df->uri($this->request->getUri()->__toString())
        )->acquireParameters([ 'ipar' ], "action", "row_id");
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

    private function handleRequest(): void
    {
        if ($this->request_wrapper->has($this->action_token->getName())) {
            $action = $this->request_wrapper->retrieve($this->action_token->getName(), $this->refinery->to()->string());
            $ids = $this->request_wrapper->retrieve($this->row_token->getName(), $this->refinery->custom()->transformation(fn($v) => $v));

            if ($action === 'add') {
                $modal = $this->buildModal($action)->withRequest($this->request);
                $data = $modal->getData();

                if ($data === null) {
                    $this->tpl->setVariable(
                        'IL_OBJECT_EPHEMRAL_MODALS',
                        $this->ui_renderer->render($modal->withOnLoad($modal->getShowSignal()))
                    );

                    return;
                }

                $this->object->getRanges()->create($data['ip_range'], $this->object->getRefId());
            }

            if ($action === 'update' && $this->request->getMethod() === 'POST') {
                $modal = $this->buildModal($action, null, intval($ids))->withRequest($this->request);
                $data = $modal->getData();

                if ($data === null) {
                    $this->tpl->setVariable(
                        'IL_OBJECT_EPHEMRAL_MODALS',
                        $this->ui_renderer->render($modal->withOnLoad($modal->getShowSignal()))
                    );

                    return;
                }

                $this->object->getRanges()->update($data['ip_range'], intval($ids), $this->object->getRefId());
            }

            if ($action === 'update' && $this->request->getMethod() === 'GET') {
                $id = intval($ids[0]);

                echo($this->ui_renderer->renderAsync([
                    $this->buildModal($action, $this->object->getRanges()->findByObjectId($id), $id)
                ]));
                exit();
            }

            if ($action === 'delete' && $this->request->getMethod() === 'POST') {
                if ($ids === [ 'ALL_OBJECTS' ]) {
                    $this->object->getRanges()->deleteAll();
                } else {
                    foreach ($ids as $id) {
                        $this->object->getRanges()->deleteByObjId(intval($id));
                    }
                }
            }

            if ($action === 'delete' && $this->request->getMethod() === 'GET') {

                $modal = $this->ui_factory->modal()->interruptive(
                    $this->lng->txt("ipar_" . $action),
                    $this->lng->txt('msg_ipar_delete'),
                    $this->url_builder
                        ->withParameter($this->action_token, $action)
                        ->withParameter($this->row_token, $ids)
                        ->buildURI()->__toString()
                )->withAffectedItems(array_map(
                    fn($id) => $this->ui_factory->modal()->interruptiveItem()->keyValue(
                        $id,
                        $this->lng->txt('obj_ipar'),
                        $this->object->getRanges()->findByObjectId((int) $id)->toString()
                    ),
                    $ids
                ));

                echo($this->ui_renderer->renderAsync([ $modal ]));
                exit();
            }

            $this->tpl->setOnScreenMessage('success', $this->lng->txt("ipar_" . $action . "_success"), true);
            $this->ctrl->redirect($this, "view");
        }
    }

    public function buildModal(string $action, ?IpAddressRange $range = null, ?int $range_id = null): ILIAS\UI\Implementation\Component\Modal\RoundTrip
    {
        $from_ip_constraint = $this->refinery->custom()->constraint(
            fn (?string $ip): bool => IpAddress::isValid($ip),
            $this->lng->txt('err_invalid_ip')
        );

        $to_ip_constraint = $this->refinery->custom()->constraint(
            function (?string $ip): bool {
                if ($ip === null || $ip === "") return true;
                return IpAddress::isValid($ip);
            },
            $this->lng->txt('err_invalid_ip')
        );

        $ip_range_trafo = $this->refinery->custom()->transformation(
            function (?array $vs) use ($range): ?IpAddressRange {
                if ($vs === null) return null;

                $from_address = new IpAddress($vs['from']);

                $to_address = null;
                if (($vs['to'] !== null) && ($vs['to'] !== "")) {
                    $to_address = new IpAddress($vs['to']);
                }

                return new IpAddressRange($from_address, $to_address);
            }
        );

        $url_builder = $this->url_builder->withParameter($this->action_token, $action);

        if ($range) {
            $url_builder = $url_builder->withParameter($this->row_token, strval($range_id));
        }

        $to_address = "";
        if ($range !== null) {
            if ($range->getToAddress(true) !== null) $to_address = $range->getToAddress()->toString();
        }

        return $this->ui_factory->modal()->roundtrip(
            $this->lng->txt("ipar_" . $action),
            null,
            [
                'ip_range' => $this->ui_factory->input()->field()->group([
                    'from' => $this->ui_factory->input()->field()->text(
                        $this->lng->txt('ipar_min_label'),
                        $this->lng->txt('ipar_byline')
                    )
                    ->withRequired(true)
                    ->withAdditionalTransformation($from_ip_constraint),
                    'to' => $this->ui_factory->input()->field()->text(
                        $this->lng->txt('ipar_max_label'),
                        $this->lng->txt('ipar_to_byline')
                    )
                    ->withRequired(false)
                    ->withAdditionalTransformation($to_ip_constraint),
                ])
                ->withValue([
                    'from' => $range !== null ? $range->getFromAddress()->toString() : "",
                    'to' => $to_address
                ])
                ->withAdditionalTransformation($ip_range_trafo)
            ],
            $url_builder->buildURI()->__toString()
        )->withSubmitLabel($this->lng->txt($action));
    }

    public function view(): void
    {
        $this->tabs_gui->activateTab('view');

        $this->handleRequest();

        $modal = $this->buildModal("add");

        if ($this->checkPermissionBool('write')) {
            $this->toolbar->addComponent(
                $this->ui_factory->button()->primary(
                    $this->lng->txt('cntr_add_new_item'),
                    $modal->getShowSignal()
                )
            );
        }

        $this->tpl->setVariable('IL_OBJECT_ADD_NEW_ITEM_MODAL', $this->ui_renderer->render($modal));
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
