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
use ILIAS\IpAddress\Objects\IpAddress;
use ILIAS\IpAddress\Objects\IpAddressSubnet;
use ILIAS\IpAddress\Objects\IpAddressRange;
use ILIAS\IpAddress\Component\ilIpAddressInputFieldGUI;

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

        return $this->isWithinRanges($ip, $ranges);
    }

    private function isIpAllowedToAccessTest(
        string $ip,
        SettingsAccess $access_settings
    ): bool {
        if (!$access_settings->isIpRangeEnabled()) {
            return true;
        }

        $ranges = $access_settings->getIpRanges();
        return $this->isWithinRanges($ip, $ranges);
    }

    private function resolveIpAddressReference(string $reference, string $ip): bool
    {
        $obj = ilObjectFactory::getInstanceByRefId(
            (int) str_replace('ref_', '', $reference),
            false
        );

        return $obj->matchesAddress($ip);
    }

    private function isWithinRanges(string $ip, string $ranges): bool
    {
        foreach(explode(',', $ranges) as $v) {

            if (str_starts_with($v, 'ref_')) {
                if ($this->resolveIpAddressReference($v, $ip)) return true;
            }

            if (IpAddressSubnet::isStringValid($v)) {
                [ $addr, $mask ] = explode("/", $v);
                $ip_obj = new IpAddress($ip);
                if (new IpAddressSubnet(new IpAddress($addr), intval($mask))->isAddressInSubnet($ip_obj)) return true;
            }

            if (IpAddress::isValid($v)) {
                $v_obj = new IpAddress($v);
                $range = new IpAddressRange(new IpAddress($ip));
                if ($range->isAddressWithinRange($v_obj)) return true;
            }
        }

        return false;
    }
}
