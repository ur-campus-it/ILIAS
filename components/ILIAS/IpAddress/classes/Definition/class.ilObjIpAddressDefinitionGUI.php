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

use ILIAS\Data\Ip\IpAddress;
use ILIAS\Data\Ip\IpAddressRange;
use ILIAS\IpAddress\Component\ilIpAddressDefinitionFormGUI;

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

        parent::__construct($ref_id);
        
        $this->lng->loadLanguageModule("ipad");
        $this->lng->loadLanguageModule("meta");

        $this->df = new \ILIAS\Data\Factory();
        [$this->url_builder, $this->action_token, $this->row_token] = (new URLBuilder(
            $this->df->uri($this->request->getUri()->__toString())
        ))->acquireParameters([ 'ipar' ], "action", "row_id");
    }

    protected function setTitleAndDescription(): void
    {
        // Ugly hack to bypass the fact that addAdminLocatorItems has been
        // set final.
        $this->locator->clearItems();
        $this->locator->addContextItems($this->node_id, false, ROOT_FOLDER_ID);

        parent::setTitleAndDescription();
    }

    public function executeCommand(): void
    {
        $cmd = $this->ctrl->getCmd();
        $next_class = $this->ctrl->getNextClass($this);

        if (!$next_class && ($cmd === 'create' || $cmd === 'save')) {
            $this->setCreationMode();
        }

        switch ($next_class) {
            case strtolower(ilInfoScreenGUI::class):
            case "infoScreen":
                parent::prepareOutput();
                $this->tabs_gui->activateTab('info_short');
                $ilInfoScreenGUI = new ilInfoScreenGUI($this);
                $this->ctrl->forwardCommand($ilInfoScreenGUI);
                break;
            case strtolower(ilExportGUI::class):
                parent::prepareOutput();
                $this->tabs_gui->activateTab('export');
                $ilExportGUI = new ilExportGUI($this);
                $this->ctrl->forwardCommand($ilExportGUI);
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

                $from_address = $this->df->ip()->address($vs['from']);

                $to_address = null;
                if (($vs['to'] !== null) && ($vs['to'] !== "")) {
                    $to_address = $this->df->ip()->address($vs['to']);
                }

                return $this->df->ip()->range($from_address, $to_address);
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

    public function renderTable(): string
    {
        $columns = [
            'ip_range_from' => $this->ui_factory->table()->column()->text($this->lng->txt("ipar_min_label"))
                ->withIsSortable(false),
            'ip_range_to' => $this->ui_factory->table()->column()->text($this->lng->txt("ipar_max_label"))
                ->withIsSortable(false)
        ];

        $actions = $this->checkPermissionBool('write') ? [
            'update' => $this->ui_factory->table()->action()->single(
                $this->lng->txt('update'),
                $this->url_builder->withParameter($this->action_token, "update"),
                $this->row_token
            )->withAsync(),
            'delete' => $this->ui_factory->table()->action()->standard(
                $this->lng->txt('delete'),
                $this->url_builder->withParameter($this->action_token, "delete"),
                $this->row_token
            )->withAsync()
        ] : [];

        $table = $this->ui_factory->table()->data(
            $this->object->getRanges(),
            $this->lng->txt('objs_ipar'),
            $columns
        )->withActions($actions)->withRequest($this->request);

        return $this->ui_renderer->render($table);
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

        $this->tpl->setContent(
            $this->renderTable()
        );
        $this->tpl->setVariable('IL_OBJECT_ADD_NEW_ITEM_MODAL', $this->ui_renderer->render($modal));
    }

    public function putObjectInTree(ilObject $obj, ?int $parent_node_id = null): void
    {
        if (!$parent_node_id) {
            $parent_node_id = $this->requested_ref_id;
        }

        // add new object to custom parent container
        if ($this->requested_crtptrefid > 0) {
            $parent_node_id = $this->requested_crtptrefid;
        }

        // Skip actual reference creation and tree insertion
        // as that is handled by ilIpAddressImporter

        $this->obj_id = $obj->getId();
        $refs = ilObject::_getAllReferences($this->obj_id);
        if (count($refs) === 1) {
            $this->ref_id = current($refs);
        }

        // BEGIN ChangeEvent: Record save object.
        ilChangeEvent::_recordWriteEvent($this->obj_id, $this->user->getId(), 'create');
        // END ChangeEvent: Record save object.

        // rbac log
        $rbac_log_roles = $this->rbac_review->getParentRoleIds($this->ref_id, false);
        $rbac_log = ilRbacLog::gatherFaPa($this->ref_id, array_keys($rbac_log_roles), true);
        ilRbacLog::add(ilRbacLog::CREATE_OBJECT, $this->ref_id, $rbac_log);

        // use forced callback after object creation
        $this->callCreationCallback($obj, $this->obj_definition, $this->requested_crtcb);
    }

    public function infoScreen(): void
    {
        $this->ctrl->redirectByClass(strtolower(ilInfoScreenGUI::class), "showSummary");
    }

    protected function getCreationFormTitle(): string
    {
        return $this->lng->txt('ipad_update');
    }

    public function edit(): void
    {
        if (!$this->checkPermissionBool("write")) {
            $this->error->raiseError($this->lng->txt("msg_no_perm_write"), $this->error->MESSAGE);
        }

        $this->tabs_gui->activateTab("settings");

        $form = (new ilIpAddressDefinitionFormGUI(
            "update",
            $this->getEditFormValues()
        ))->get($this);

        $this->tpl->setContent($this->getCreationFormsHTML($form));
    }

    protected function getEditFormValues(): array
    {
        return [
            'title_and_description' => [
                'title' => $this->object->getTitle(),
                'desc' => $this->object->getDescription()
            ],
            'activation_online' => !$this->object->getOfflineStatus()
        ];
    }

    public function update(): void
    {
        if (!$this->checkPermissionBool("write")) {
            $this->error->raiseError($this->lng->txt("permission_denied"), $this->error->MESSAGE);
        }

        $form = (new ilIpAddressDefinitionFormGUI(
            "update",
            $this->getEditFormValues()
        ))->get($this)->withRequest($this->request);

        $data = $form->getData();

        if ($data === null) {
            $this->tabs_gui->activateTab("settings");
            $this->tpl->setContent($this->getCreationFormsHTML($form));
            return;
        }

        $this->object->setTitle($data['title_and_description']->getTitle());
        $this->object->setDescription($data['title_and_description']->getDescription());
        $this->object->setOfflineStatus(!$data['activation_online']);
        $this->object->update();

        $this->afterUpdate();
        return;
    }

    public function getAdminTabs(): void
    {
        if ($this->checkPermissionBool('visible,read')) {
            $this->tabs_gui->addTab('view', $this->lng->txt("view"), $this->ctrl->getLinkTargetByClass(strtolower($this::class), "view"));
            $this->tabs_gui->addTab("info_short", "Info", $this->ctrl->getLinkTargetByClass(strtolower(ilInfoScreenGUI::class), "showSummary"));
        }

        if ($this->checkPermissionBool('read')) {
            $this->tabs_gui->addTab('export', $this->lng->txt("export"), $this->ctrl->getLinkTargetByClass(strtolower(ilExportGUI::class), "export"));
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
