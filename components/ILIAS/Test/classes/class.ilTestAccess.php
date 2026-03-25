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

use ILIAS\Test\Participants\ParticipantRepository;
use ILIAS\Test\Participants\Participant;
use ILIAS\Test\Settings\SettingsNotFoundException;
use ILIAS\Test\TestDIC;
use ILIAS\Test\Access\ParticipantAccess;
use ILIAS\Test\Settings\MainSettings\MainSettingsDatabaseRepository;
use ILIAS\Test\Settings\MainSettings\SettingsAccess;

/**
 * Class ilTestAccess
 *
 * @author    Björn Heyser <info@bjoernheyser.de>
 * @version    $Id$
 *
 * @package components\ILIAS/Test
 */
class ilTestAccess
{
    protected ilAccessHandler $access;
    protected ilDBInterface $db;
    protected ilLanguage $lng;
    protected MainSettingsDatabaseRepository $main_settings_repository;

    protected ilTestParticipantAccessFilterFactory $participant_access_filter;
    protected ParticipantRepository $participant_repository;

    public function __construct(
        protected int $ref_id
    ) {
        /** @var ILIAS\DI\Container $DIC */
        global $DIC;
        $this->db = $DIC['ilDB'];
        $this->lng = $DIC['lng'];
        $this->participant_access_filter = new ilTestParticipantAccessFilterFactory($DIC['ilAccess']);
        $this->participant_repository = TestDIC::dic()['participant.repository'];
        $this->access = $DIC->access();
        $this->main_settings_repository = TestDIC::dic()['settings.main.repository'];
    }

    public function getAccess(): ilAccessHandler
    {
        return $this->access;
    }

    public function setAccess(ilAccessHandler $access)
    {
        $this->access = $access;
    }

    public function getRefId(): int
    {
        return $this->ref_id;
    }

    /**
     * @return bool
     */
    public function checkCorrectionsAccess(): bool
    {
        return $this->getAccess()->checkAccess('write', '', $this->getRefId());
    }

    /**
     * @return bool
     */
    public function checkScoreParticipantsAccess(): bool
    {
        if (!$this->getAccess()->checkAccess('read', '', $this->getRefId())) {
            return false;
        }
        return
            $this->getAccess()->checkAccess('write', '', $this->getRefId())
            || $this->getAccess()->checkPositionAccess(ilOrgUnitOperation::OP_SCORE_PARTICIPANTS, $this->getRefId())
        ;
    }

    public function checkScoreParticipantsAccessAnon(): bool
    {
        return $this->getAccess()->checkAccess('score_anon', '', $this->getRefId());
    }

    /**
     * @return bool
     */
    public function checkManageParticipantsAccess(): bool
    {
        if ($this->getAccess()->checkAccess('write', '', $this->getRefId())) {
            return true;
        }

        if (!$this->getAccess()->checkAccess('read', '', $this->getRefId())) {
            return false;
        }

        if ($this->getAccess()->checkPositionAccess(ilOrgUnitOperation::OP_MANAGE_PARTICIPANTS, $this->getRefId())) {
            return true;
        }

        return false;
    }

    public function checkParticipantsResultsAccess(): bool
    {
        if ($this->getAccess()->checkAccess('write', '', $this->getRefId())) {
            return true;
        }

        if ($this->getAccess()->checkAccess('tst_results', '', $this->getRefId())) {
            return true;
        }

        if (!$this->getAccess()->checkAccess('read', '', $this->getRefId())) {
            return false;
        }

        if ($this->getAccess()->checkPositionAccess(ilOrgUnitOperation::OP_MANAGE_PARTICIPANTS, $this->getRefId())) {
            return true;
        }

        if ($this->getAccess()->checkPositionAccess(ilOrgUnitOperation::OP_ACCESS_RESULTS, $this->getRefId())) {
            return true;
        }

        return false;
    }

    public function checkOtherParticipantsLearningProgressAccess(): bool
    {
        if ($this->getAccess()->checkAccess('write', '', $this->getRefId())) {
            return true;
        }

        if ($this->getAccess()->checkRbacOrPositionPermissionAccess(
            'read_learning_progress',
            ilOrgUnitOperation::OP_READ_LEARNING_PROGRESS,
            $this->getRefId()
        )) {
            return true;
        }

        return false;
    }

    protected function checkAccessForActiveId(Closure $access_filter, int $active_id, int $test_id): bool
    {
        $participantData = new ilTestParticipantData($this->db, $this->lng);
        $participantData->setActiveIdsFilter([$active_id]);
        $participantData->setParticipantAccessFilter($access_filter);
        $participantData->load($test_id);

        return in_array($active_id, $participantData->getActiveIds());
    }

    public function checkResultsAccessForActiveId(int $active_id, int $test_id): bool
    {
        $access_filter = $this->participant_access_filter->getAccessResultsUserFilter($this->getRefId());
        return $this->checkAccessForActiveId($access_filter, $active_id, $test_id);
    }

    public function checkScoreParticipantsAccessForActiveId(int $active_id, int $test_id): bool
    {
        $access_filter = $this->participant_access_filter->getScoreParticipantsUserFilter($this->getRefId());
        return $this->checkAccessForActiveId($access_filter, $active_id, $test_id);
    }

    public function isParticipantAllowed(int $obj_id, int $user_id): ParticipantAccess
    {
        try {
            $access_settings = $this->main_settings_repository->getForObjFi($obj_id)
                ->getAccessSettings();
        } catch (SettingsNotFoundException $e) {
            return ParticipantAccess::MISSING_SETTINGS;
        } catch (\Exception $e) {
            return ParticipantAccess::BROKEN_TEST;
        }

        $participant = $this->participant_repository->getParticipantByUserId(
            ilObjTest::_getTestIDFromObjectID(
                ilObjTest::_lookupObjId($this->getRefId())
            ),
            $user_id
        );

        if ($access_settings->getFixedParticipants()
            && ($participant === null || !$participant->isInvitedParticipant())) {
            return ParticipantAccess::NOT_INVITED;
        }

        $ip = $_SERVER['REMOTE_ADDR'];

        $allowed_individual = $this->isParticipantExplicitelyAllowedByIndividualIPRange($participant, $ip);
        if ($allowed_individual === false) {
            return ParticipantAccess::INDIVIDUAL_CLIENT_IP_MISMATCH;
        }


        if ($allowed_individual === true
            || !$access_settings->isIpRangeEnabled()) {
            return ParticipantAccess::ALLOWED;
        }

        if (!$this->isIpAllowedToAccessTest($ip, $access_settings)) {
            return ParticipantAccess::TEST_LEVEL_CLIENT_IP_MISMATCH;
        }

        return ParticipantAccess::ALLOWED;
    }

    private function isParticipantExplicitelyAllowedByIndividualIPRange(
        ?Participant $participant,
        string $ip
    ): ?bool {
        $ranges = $participant?->getClientIpRanges();

        if ($ranges === null) {
            return null;
        }

        return $this->handleIpRanges($ip, $ranges);
    }

    private function isIpAllowedToAccessTest(
        string $ip,
        SettingsAccess $access_settings
    ): bool {
        if (!$access_settings->isIpRangeEnabled()) {
            return true;
        }

        $ranges = $access_settings->getIpRanges();
        return $this->handleIpRanges($ip, $ranges);
    }

    private function handleIpRanges(string $ip, string $ranges): bool
    {
        foreach(explode(',', $ranges) as $v) {
            if (str_contains($v, '-')) {
                list($start, $end) = explode('-', $v);
                if ($this->isIpTypeOf(FILTER_FLAG_IPV4, [$ip, $start, $end])) {
                    return $this->isIpv4Between($ip, $start, $end);
                }

                if ($this->isIpTypeOf(FILTER_FLAG_IPV6, [$ip, $start, $end])) {
                    return $this->isIpv6Between($ip, $start, $end);
                }
            }

            if (str_contains($v, '/')) {
                list($addr, $mask) = explode('/', $v);
                if ($this->isIpTypeOf(FILTER_FLAG_IPV4, [$ip, $addr])) {
                    return $this->isIpInSubnet($ip, $v);
                }

                if ($this->isIpTypeOf(FILTER_FLAG_IPV6, [$ip, $addr])) {
                    return $this->isIpInSubnet($ip, $v);
                }
            }

            if ($this->isIpTypeOf(FILTER_FLAG_IPV4, [$ip, $v])) {
                return ($ip === $v);
            }

            if ($this->isIpTypeOf(FILTER_FLAG_IPV6, [$ip, $v])) {
                return ($ip === $v);
            }
        }

        return false;
    }

    private function isIpInSubnet(string $ip, string $subnet): bool
    {
        list($address, $prefix) = explode('/', $subnet);
        $address = inet_pton($address);
        $ip = inet_pton($ip);

        $mask = str_repeat("\xFF", $prefix >> 3);
        if ($prefix & 7) $mask .= chr(0xFF << (8 - ($prefix & 7)));
        $mask = str_pad($mask, strlen($ip), "\x00");

        return ($ip & $mask) == ($address & $mask);
    }

    private function isIpTypeOf(int $ip_type_flag, array $ips): bool
    {
        return array_all($ips, function(?string $v) use ($ip_type_flag): bool {
            return filter_var($v, FILTER_VALIDATE_IP, $ip_type_flag) !== false;
        });
    }

    private function isIpv4Between(string $ip, string $range_start, string $range_end): bool
    {
        return ip2long($range_start) <= ip2long($ip)
            && ip2long($ip) <= ip2long($range_end);
    }

    private function isIpv6Between(string $ip, string $range_start, string $range_end): bool
    {
        return bin2hex(inet_pton($range_start)) <= bin2hex(inet_pton($ip))
            && bin2hex(inet_pton($ip)) <= bin2hex(inet_pton($range_end));
    }
}
