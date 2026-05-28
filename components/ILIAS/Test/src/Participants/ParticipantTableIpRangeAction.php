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

namespace ILIAS\Test\Participants;

use ILIAS\Language\Language;
use ILIAS\UI\Factory as UIFactory;
use ILIAS\UI\Component\Modal\Modal;
use ILIAS\UI\Component\Table\Action\Action;
use ILIAS\UI\URLBuilder;
use ILIAS\UI\URLBuilderToken;
use Psr\Http\Message\ServerRequestInterface;
use ILIAS\Test\Settings\MainSettings\SettingsAccess;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\IpAddress\Components\ilIpAddressInputFieldGUI;

class ParticipantTableIpRangeAction implements TableAction
{
    public const ACTION_ID = 'client_ip_range';

    public function __construct(
        private readonly Language $lng,
        private readonly \ilGlobalTemplateInterface $tpl,
        private readonly UIFactory $ui_factory,
        private readonly Refinery $refinery,
        private readonly ParticipantRepository $participant_repository,
        private readonly \ilTestAccess $test_access,
    ) {
    }

    public function getActionId(): string
    {
        return self::ACTION_ID;
    }

    public function isAvailable(): bool
    {
        return $this->test_access->checkManageParticipantsAccess();
    }

    public function getTableAction(
        URLBuilder $url_builder,
        URLBuilderToken $row_id_token,
        URLBuilderToken $action_token,
        URLBuilderToken $action_type_token
    ): Action {
        return $this->ui_factory->table()->action()->standard(
            $this->lng->txt(self::ACTION_ID),
            $url_builder
                ->withParameter($action_token, self::ACTION_ID)
                ->withParameter($action_type_token, ParticipantTableActions::SHOW_ACTION),
            $row_id_token
        )->withAsync();
    }

    public function getModal(
        URLBuilder $url_builder,
        array $selected_participants,
        bool $all_participants_selected
    ): ?Modal {

        $value = $this->isUniqueClientIp($selected_participants) ? $selected_participants[0]->getClientIpRanges() : null;

        $input = new ilIpAddressInputFieldGUI()->withURLBuilder($url_builder);

        $trafo = $this->refinery->custom()->transformation(
            static function (?array $vs): array {
                if ($vs === null) return [ 'ip_ranges' => null ];
                return [ 'ip_ranges' => implode(',', $vs['ip_ranges'])];
            }
        );

        $max_one = $this->refinery->custom()->constraint(
            function(?array $vs): bool {
                if ($vs === null) {
                    return true;
                }

                return count($vs) <= 1;
            },
            $this->lng->txt('ip_max_one_element')
        );

        $participant_rows = array_map(
            fn(Participant $participant) => sprintf(
                '%s, %s',
                $participant->getLastname(),
                $participant->getFirstname()
            ),
            $selected_participants
        );

        return $this->ui_factory->modal()->roundtrip(
            $this->lng->txt('client_ip_range'),
            [
                $this->ui_factory->messageBox()->info(
                    $this->lng->txt(
                        $this->resolveInfoMessage(
                            $selected_participants,
                            $all_participants_selected
                        )
                    )
                ),
                $this->ui_factory->listing()->unordered($participant_rows)
            ],
            [
                'ip_range' => $this->ui_factory->input()->field()->group([
                    'ip_ranges' => $input->get($value)
                    ->withAdditionalTransformation($max_one),
                ])
                ->withAdditionalTransformation($trafo)
            ],
            $url_builder->buildURI()->__toString()
        )->withSubmitLabel($this->lng->txt('change'));
    }

    public function onSubmit(
        URLBuilder $url_builder,
        ServerRequestInterface $request,
        array $selected_participants,
        bool $all_participants_selected
    ): ?Modal {
        if (!$this->test_access->checkManageParticipantsAccess()) {
            $this->tpl->setOnScreenMessage(
                \ilGlobalTemplateInterface::MESSAGE_TYPE_FAILURE,
                $this->lng->txt('no_permission'),
                true
            );
            return null;
        }

        $modal = $this->getModal(
            $url_builder,
            $selected_participants,
            $all_participants_selected
        )->withRequest($request);

        $data = $modal->getData();
        if ($data === null) {
            return $modal->withOnLoad($modal->getShowSignal());
        }

        $this->participant_repository->updateIpRange(
            array_map(
                static fn(Participant $v) => $v->withClientIpRanges($data['ip_range']['ip_ranges']),
                $selected_participants
            )
        );

        $this->tpl->setOnScreenMessage(
            \ilGlobalTemplateInterface::MESSAGE_TYPE_SUCCESS,
            $this->lng->txt('ip_range_updated'),
            true
        );
        return null;
    }

    public function allowActionForRecord(Participant $record): bool
    {
        return $record->isInvitedParticipant();
    }

    /**
     * @param array $selected_participants
     */
    private function resolveInfoMessage(
        array $selected_participants,
        bool $all_participants_selected
    ): string {
        if ($all_participants_selected) {
            return 'ip_range_for_all_participants';
        }

        if (count($selected_participants) === 1) {
            return 'ip_range_for_single_participant';
        }

        return 'ip_range_for_selected_participants';
    }

    private function isUniqueClientIp(array $selected_participants): bool
    {
        return count($selected_participants) === 1
            || count(array_unique(array_map(
                fn(Participant $participant) => $participant->getClientIpFrom() . '-' . $participant->getClientIpTo(),
                $selected_participants
            ))) === 1;
    }

    public function getSelectionErrorMessage(): ?string
    {
        return null;
    }
}
