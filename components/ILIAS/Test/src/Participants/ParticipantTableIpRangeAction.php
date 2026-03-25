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
use ILIAS\Refinery\Factory as Refinery;

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
        $validate_ip_ranges = $this->refinery->custom()->constraint(
            function(?array $vs): bool {
                if ($vs === null) {
                    return true;
                }

                foreach ($vs as $v) {
                    if (str_contains($v, "-")) {
                        $res = $this->checkIpRangeValidity($v);
                        if (!$res) return false;
                    }
                }

                return true;
            },
            $this->lng->txt('invalid_ip_range')
        );

        $validate_ip_subnets = $this->refinery->custom()->constraint(
            function(?array $vs): bool {
                if ($vs === null) {
                    return true;
                }

                foreach ($vs as $v) {
                    if (str_contains($v, "/")) {
                        $res = $this->checkIpSubnetValidity($v);
                        if (!$res) return false;
                    }
                }

                return true;
            },
            $this->lng->txt('invalid_ip_subnet')
        );

        $validate_ip_addresses = $this->refinery->custom()->constraint(
            function (?array $vs): bool {
                if ($vs === null) {
                    return true;
                }

                foreach($vs as $v) {
                    if ((str_contains($v, "/")) || (str_contains($v, "-"))) continue;

                    $res = filter_var($v, FILTER_VALIDATE_IP) !== false;    
                    if (!$res) return false;
                }
                
                return true;
            },
            $this->lng->txt('invalid_ip')
        );
        $ip_range_group_trafo = $this->refinery->custom()->transformation(
            static function (?array $vs): array {
                if ($vs === null) {
                    $vs = [
                        'ip_ranges' => null
                    ];
                }
                $vs['ip_ranges'] = implode(",", $vs['ip_ranges']);

                return $vs;
            }
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
                    'ip_ranges' => $this->ui_factory->input()->field()->tag(
                        $this->lng->txt('ip_ranges'),
                        [],
                        $this->lng->txt('ip_ranges_label')
                    )   
                        ->withAdditionalTransformation($validate_ip_ranges)
                        ->withAdditionalTransformation($validate_ip_subnets)
                        ->withAdditionalTransformation($validate_ip_addresses)
                ])->withValue(
                    $this->isUniqueClientIp($selected_participants)
                    ? [
                        'ip_ranges' => explode(",", $selected_participants[0]->getClientIpRanges() ?? '')
                    ]
                    : [
                        'ip_ranges' => []
                    ]
                )
                    ->withAdditionalTransformation($ip_range_group_trafo)
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

    private function checkIpRangeValidity(string $range): bool
    {
        $v = explode("-", $range);
        if (sizeof($v) !== 2) return false;

        list($start, $end) = $v;

        if (filter_var($start, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
           && filter_var($end, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return ip2long($start) <= ip2long($end);
        }

        if (filter_var($start, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
           && filter_var($end, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return bin2hex(inet_pton($start)) <= bin2hex(inet_pton($end));
        }
        return false;
    }

    private function checkIpSubnetValidity(string $subnet): bool
    {
        $v = explode("/", $subnet);
        if (sizeof($v) !== 2) return false;

        list($address, $mask) = $v;

        $is_ipv4 = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $is_ipv6 = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

        if (!$is_ipv4 && !$is_ipv6) return false;

        return (($is_ipv4 && $mask <= 32) || ($is_ipv6 && $mask <= 128));
    }
    public function getSelectionErrorMessage(): ?string
    {
        return null;
    }
}
