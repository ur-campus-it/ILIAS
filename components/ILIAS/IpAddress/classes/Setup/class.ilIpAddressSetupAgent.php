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

namespace ILIAS\IpAddress\Setup;

use ILIAS\Refinery\Transformation;
use ILIAS\Setup;
use ILIAS\Setup\Config;
use ILIAS\Setup\Metrics;
use ILIAS\Setup\Migration;
use ILIAS\Setup\Objective;
use ilOrgUnitOperation;
use ilOrgUnitOperationContext;

/**
 * @author Bastian Meissner <bastian.meissner@ur.de>
 */
final class ilIpAddressSetupAgent implements Setup\Agent
{
    public function hasConfig(): bool
    {
        return false;
    }

    public function getArrayToConfigTransformation(): Transformation
    {
        throw new \LogicException(
            self::class . " has no config."
        );
    }

    public function getInstallObjective(?Config $config = null): Objective
    {
        // Add object to object_data and object_reference, create read/write/edit-permissions rbac operations in rbac_ta
        return new \ilTreeAdminNodeAddedObjective('ipaa', '__IpAddressAdministration');
    }

    public function getBuildObjective(): Objective
    {
        // Do nothing
        return new Objective\NullObjective();
    }

    public function getStatusObjective(Metrics\Storage $storage): Objective
    {
        // uuhm, run the database update steps, i guess?
        return new \ilDatabaseUpdateStepsMetricsCollectedObjective($storage, new ilIpAddressDBUpdateSteps());
    }

    public function getMigrations(): array
    {
        return [];
    }

    public function getNamedObjectives(?Config $config = null): array
    {
        return [];
    }


    public function getUpdateObjective(?Setup\Config $config = null): Setup\Objective
    {
        // in case of an update, make sure that the steps also mentioned above get executed?
        return new Setup\ObjectiveCollection(
            'IP Address definitions',
            true,
            new \ilTreeAdminNodeAddedObjective('ipaa', '__IpAddressAdministration'),
            new \ilDatabaseUpdateStepsExecutedObjective(new ilIpAddressDBUpdateSteps()),
            ...$this->getOrgUnitObjectives()
        );
    }

    protected function getOrgUnitObjectives(): array
    {
        return [];
    }
}
