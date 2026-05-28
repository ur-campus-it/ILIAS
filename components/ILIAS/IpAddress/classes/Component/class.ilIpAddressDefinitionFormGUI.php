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

namespace ILIAS\IpAddress\Component;

use ilObjectGUI;
use ILIAS\IpAddress\Objects\IpAddressRange;
use ILIAS\IpAddress\Objects\IpAddress;
use ilObjIpAddressDefinition;

use ILIAS\UI\URLBuilder;
use ILIAS\UI\URLBuilderToken;

use ILIAS\UI\Component\Input\Container\Form\Standard as StandardForm;

use ILIAS\ILIASObject\Properties\Property;
use ILIAS\ILIASObject\Properties\CoreProperties\TitleAndDescription;

use ILIAS\Refinery\Factory as Refinery;

use ilLanguage;
use ilCtrl;
use ILIAS\UI\Factory as UIFactory;

/**
 * Class ilIpAddressDefinitionFormGUI GUI class
 * @author            : Bastian Meissner <bastian.meissner@ur.de>
 * @ilCtrl_IsCalledBy ilIpAddressDefinitionFormGUI: ilObjIpAddressAdministrationGUI, ilObjIpAddressDefinitionGUI
 */
class ilIpAddressDefinitionFormGUI
{

    protected Refinery $refinery;
    protected UIFactory $ui_factory;
    protected ilLanguage $lng;
    protected ilCtrl $ctrl;

    private array $values = [
        'title_and_description' => [
            'title' => '',
            'desc' => ''
        ],
        'activation_online' => false
    ];

    private string $action = "save";

    public function __construct(string $action, ?array $values = null) {
        global $DIC;

        $this->lng = $DIC->language();
        $this->ctrl = $DIC->ctrl();
        $this->ui_factory = $DIC['ui.factory'];
        $this->refinery = $DIC->refinery();

        $this->action = $action;

        if ($values !== null) {
            $this->values = $values;
        }
    }

    protected function uniqueTitle(?string $v): bool {
        if ($v === null || $v === '') {
            return true;
        }

        if ($this->values['title_and_description']['title'] === $v) {
            return true;
        }

        return !ilObjIpAddressDefinition::titleExists($v);
    }

    protected function toTitleAndDescription(?array $vs): TitleAndDescription {
        if ($vs === null) {
            return new TitleAndDescription();
        }

        return new TitleAndDescription(
            $vs['title'],
            $vs['desc']
        );
    }

    public function get(ilObjectGUI $caller): StandardForm {

        $unique_title = $this->refinery->custom()->constraint(
            fn ($v) => $this->uniqueTitle($v),
            $this->lng->txt('msg_title_exists')
        );

        $trafo = $this->refinery->custom()->transformation(
            fn ($v) => $this->toTitleAndDescription($v)
        );

        $form = [
            'title_and_description' => $this->ui_factory->input()->field()->group([
                "title" => $this->ui_factory->input()->field()
                    ->text($this->lng->txt('title'))
                    ->withRequired(true)
                    ->withoutStripTags()
                    ->withMaxLength(\ilObject::TITLE_LENGTH)
                    ->withAdditionalTransformation($unique_title),
                "desc" => $this->ui_factory->input()->field()
                    ->textarea($this->lng->txt('description'))
                    ->withoutStripTags()
                    ->withMaxLimit(\ilObject::LONG_DESC_LENGTH)
            ])
            ->withValue($this->values['title_and_description'])
            ->withAdditionalTransformation($trafo)
        ];

        if ($this->action === "update") {
            $byline = $this->lng->txt('ipad_activation_online_info');
            if ($caller->getObject()->isReferenced()) {
                $tst_string = implode(", ", iterator_to_array($caller->getObject()->getTestReferences()));
                $byline = sprintf($this->lng->txt('msg_ref_by_tst'), $tst_string);
            }

            $form["activation_online"] = $this->ui_factory->input()->field()->checkbox(
                $this->lng->txt('rep_activation_online'),
                $byline
            )
            ->withValue($this->values['activation_online'])
            ->withDisabled($caller->getObject()->isReferenced());
        }

        return $this->ui_factory->input()->container()->form()->standard(
            $this->ctrl->getFormAction($caller, $this->action), $form
        )->withSubmitLabel($this->lng->txt($this->action));
    }
}