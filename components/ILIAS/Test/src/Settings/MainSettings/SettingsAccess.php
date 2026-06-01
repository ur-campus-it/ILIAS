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

namespace ILIAS\Test\Settings\MainSettings;

use ILIAS\Test\ExportImport\Exportable;
use ILIAS\Test\Settings\TestSettings;
use ILIAS\Test\Logging\AdditionalInformationGenerator;
use ILIAS\UI\Component\Input\Field\Factory as FieldFactory;
use ILIAS\UI\Component\Input\Container\Form\FormInput;
use ILIAS\UI\Component\Input\Field\OptionalGroup;
use ILIAS\UI\Component\Input\Field\Group;
use ILIAS\Refinery\Factory as Refinery;
use ILIAS\IpAddress\Components\ilIpAddressInputFieldGUI;

class SettingsAccess extends TestSettings implements Exportable
{
    private const MAX_PASSWORD_LENGTH = 20;

    public function __construct(
        protected bool $start_time_enabled = false,
        protected ?\DateTimeImmutable $start_time = null,
        protected bool $end_time_enabled = false,
        protected ?\DateTimeImmutable $end_time = null,
        protected bool $password_enabled = false,
        protected ?string $password = null,
        protected ?string $ip_ranges = null,
        protected bool $fixed_participants = false
    ) {
        parent::__construct();
    }

    public function toForm(
        \ilLanguage $lng,
        FieldFactory $f,
        Refinery $refinery,
        ?array $environment = null
    ): FormInput {
        $inputs['access_window'] = $this->getInputAccessWindow($lng, $f, $refinery, $environment);
        $inputs['test_password'] = $this->getInputPassword($lng, $f, $refinery);
        $inputs['ip_ranges'] = $this->getInputIpRanges($lng, $f, $refinery);

        $inputs['fixed_participants_enabled'] = $f->checkbox(
            $lng->txt('participants_invitation'),
            $lng->txt('participants_invitation_description')
        )->withValue($this->getFixedParticipants());
        if ($environment['participant_data_exists']) {
            $inputs['fixed_participants_enabled'] = $inputs['fixed_participants_enabled']
                ->withDisabled(true);
        }

        return $f->section($inputs, $lng->txt('tst_settings_header_execution'));
    }

    private function getInputAccessWindow(
        \ilLanguage $lng,
        FieldFactory $f,
        Refinery $refinery,
        ?array $environment = null
    ): Group {
        $constraint = $refinery->custom()->constraint(
            static fn(array $vs) =>
                $vs['start_time'] === null || $vs['end_time'] === null || $vs['start_time'] < $vs['end_time'],
            $lng->txt('duration_end_must_not_be_earlier_than_start')
        );

        $trafo = $refinery->custom()->transformation(
            static function (array $vs): array {
                $vs['start_time_enabled'] = $vs['start_time'] !== null;
                $vs['end_time_enabled'] = $vs['end_time'] !== null;
                return $vs;
            }
        );

        return $f->group($this->getSubInputsAccessWindow($lng, $f, $environment))
            ->withAdditionalTransformation($constraint)
            ->withAdditionalTransformation($trafo);
    }

    private function getSubInputsAccessWindow(
        \ilLanguage $lng,
        FieldFactory $f,
        array $environment
    ): array {
        $sub_inputs_access_window['start_time'] = $f->dateTime(
            $lng->txt('tst_starting_time'),
            $lng->txt('tst_starting_time_desc')
        )->withTimezone($environment['user_time_zone'])
            ->withFormat($environment['user_date_format'])
            ->withUseTime(true);
        if ($this->getStartTime() !== null) {
            $sub_inputs_access_window['start_time'] = $sub_inputs_access_window['start_time']
                ->withValue($this->getStartTime()->setTimezone(new \DateTimeZone($environment['user_time_zone'])));
        }
        if ($environment['participant_data_exists']) {
            $sub_inputs_access_window['start_time'] = $sub_inputs_access_window['start_time']->withDisabled(true);
        }

        $sub_inputs_access_window['end_time'] = $f->dateTime(
            $lng->txt('tst_ending_time'),
            $lng->txt('tst_ending_time_desc')
        )->withTimezone($environment['user_time_zone'])
            ->withFormat($environment['user_date_format'])
            ->withUseTime(true);
        if ($this->getEndTime() !== null) {
            $sub_inputs_access_window['end_time'] = $sub_inputs_access_window['end_time']
                ->withValue($this->getEndTime()->setTimezone(new \DateTimeZone($environment['user_time_zone'])));
        }

        return $sub_inputs_access_window;
    }

    private function getInputPassword(
        \ilLanguage $lng,
        FieldFactory $f,
        Refinery $refinery
    ): OptionalGroup {
        $trafo = $refinery->custom()->transformation(
            static function (?array $vs): array {
                if ($vs === null) {
                    return [
                        'password_enabled' => false,
                        'password_value' => null
                    ];
                }

                $vs['password_enabled'] = true;
                return $vs;
            }
        );

        $sub_inputs_password['password_value'] = $f->text($lng->txt('tst_password_enter'))
            ->withRequired(true)->withMaxLength(self::MAX_PASSWORD_LENGTH);

        $password_input = $f->optionalGroup(
            $sub_inputs_password,
            $lng->txt('tst_password'),
            $lng->txt('tst_password_details')
        )->withValue(null)
            ->withAdditionalTransformation($trafo);

        if (!$this->getPasswordEnabled()) {
            return $password_input;
        }

        return $password_input->withValue(
            ['password_value' => $this->getPassword()]
        );
    }

    private function getInputIpRanges(
        \ilLanguage $lng,
        FieldFactory $f,
        Refinery $refinery
    ): FormInput {

        $input = new ilIpAddressInputFieldGUI();

        if ($this->isIpRangeEnabled()) {
            $input = $input->get($this->getIpRanges());
        } else {
            $input = $input ->get();
        }

        $trafo = $refinery->custom()->transformation(
            static function (?array $vs): array {
                if ($vs === null) return [ 'ip_ranges' => null ];
                return [ 'ip_ranges' => implode(',', $vs['ip_ranges'])];
            }
        );

        return $f->optionalGroup(
            [
                'ip_ranges' => $input
            ],
            $lng->txt('ip_range_label'),
            $lng->txt('ip_range_info')
        )->withAdditionalTransformation($trafo);
    }

    public function toStorage(): array
    {
        return [
            'starting_time_enabled' => ['integer', (int) $this->getStartTimeEnabled()],
            'starting_time' => ['integer', $this->getStartTime() !== null ? $this->getStartTime()->getTimestamp() : 0],
            'ending_time_enabled' => ['integer', (int) $this->getEndTimeEnabled()],
            'ending_time' => ['integer', $this->getEndTime() !== null ? $this->getEndTime()->getTimestamp() : 0],
            'password_enabled' => ['integer', (int) $this->getPasswordEnabled()],
            'password' => ['text', $this->getPassword()],
            'ip_ranges' => ['text', $this->getIpRanges()],
            'fixed_participants' => ['integer', (int) $this->getFixedParticipants()]
        ];
    }

    public function toLog(AdditionalInformationGenerator $additional_info): array
    {
        $starting_time = $additional_info->getNoneTag();
        if (($st_immutable = $this->getStartTime()) instanceof \DateTimeImmutable) {
            $starting_time = $st_immutable->format(AdditionalInformationGenerator::DATE_STORAGE_FORMAT);
        }

        $end_time = $additional_info->getNoneTag();
        if (($et_immutable = $this->getEndTime()) instanceof \DateTimeImmutable) {
            $end_time = $et_immutable->format(AdditionalInformationGenerator::DATE_STORAGE_FORMAT);
        }

        return [
            AdditionalInformationGenerator::KEY_TEST_START_TIME => $starting_time,
            AdditionalInformationGenerator::KEY_TEST_END_TIME => $end_time,
            AdditionalInformationGenerator::KEY_TEST_PASSWORD => $this->getPassword() ?? $additional_info->getNoneTag(),
            AdditionalInformationGenerator::KEY_TEST_IP_RANGES => $this->isIpRangeEnabled()
                ? $this->getIpRanges()
                : $additional_info->getEnabledDisabledTagForBool(false),
            AdditionalInformationGenerator::KEY_TEST_FIXED_PARTICIPANTS => $additional_info
                ->getEnabledDisabledTagForBool($this->getFixedParticipants())
        ];
    }

    public function getStartTimeEnabled(): bool
    {
        return $this->start_time_enabled;
    }
    public function withStartTimeEnabled(bool $start_time_enabled): self
    {
        $clone = clone $this;
        $clone->start_time_enabled = $start_time_enabled;
        return $clone;
    }

    public function getStartTime(): ?\DateTimeImmutable
    {
        return $this->start_time;
    }
    public function withStartTime(?\DateTimeImmutable $start_time): self
    {
        $clone = clone $this;
        $clone->start_time = $start_time;
        return $clone;
    }

    public function getEndTimeEnabled(): bool
    {
        return $this->end_time_enabled;
    }
    public function withEndTimeEnabled(bool $end_time_enabled): self
    {
        $clone = clone $this;
        $clone->end_time_enabled = $end_time_enabled;
        return $clone;
    }

    public function getEndTime(): ?\DateTimeImmutable
    {
        return $this->end_time;
    }
    public function withEndTime(?\DateTimeImmutable $end_time): self
    {
        $clone = clone $this;
        $clone->end_time = $end_time;
        return $clone;
    }

    public function getPasswordEnabled(): bool
    {
        return $this->password_enabled;
    }
    public function withPasswordEnabled(bool $password_enabled): self
    {
        $clone = clone $this;
        $clone->password_enabled = $password_enabled;
        return $clone;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }
    public function withPassword(?string $password): self
    {
        $clone = clone $this;
        $clone->password = $password;
        return $clone;
    }

    public function getIpRanges(): ?string
    {
        return $this->ip_ranges;
    }
    public function withIpRanges(?string $ip_ranges): self
    {
        $clone = clone $this;
        $clone->ip_ranges = $ip_ranges;
        return $clone;
    }

    public function isIpRangeEnabled(): ?bool
    {
        return $this->ip_ranges !== null;
    }

    public function getFixedParticipants(): bool
    {
        return $this->fixed_participants;
    }
    public function withFixedParticipants(bool $fixed_participants): self
    {
        $clone = clone $this;
        $clone->fixed_participants = $fixed_participants;
        return $clone;
    }

    public function toExport(): array
    {
        return [
            'starting_time_enabled' => $this->getStartTimeEnabled(),
            'starting_time' => $this->getStartTime()?->format(\DateTimeInterface::ATOM),
            'ending_time_enabled' => $this->getEndTimeEnabled(),
            'ending_time' => $this->getEndTime()?->format(\DateTimeInterface::ATOM),
            'password_enabled' => $this->getPasswordEnabled(),
            'password' => $this->getPassword(),
            'ip_ranges' => $this->getIpRanges(),
            'fixed_participants' => $this->getFixedParticipants()
        ];
    }

    public static function fromExport(array $data): static
    {
        return new self(
            (bool) $data['starting_time_enabled'],
            $data['starting_time'] !== null ? new \DateTimeImmutable($data['starting_time']) : null,
            (bool) $data['ending_time_enabled'],
            $data['ending_time'] !== null ? new \DateTimeImmutable($data['ending_time']) : null,
            (bool) $data['password_enabled'],
            $data['password'],
            $data['ip_ranges'],
            (bool) $data['fixed_participants'],
        );
    }
}
