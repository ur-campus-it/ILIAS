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

use ILIAS\Database\PDO\FieldDefinition\ForeignKeyConstraints;

final class ilIpAddressDBUpdateSteps implements \ilDatabaseUpdateSteps
{
    protected \ilDBInterface $db;

    public function prepare(\ilDBInterface $db): void
    {
        $this->db = $db;
    }

    private function useTransaction(callable $updateStep): void
    {
        try {
            if ($this->db->supportsTransactions()) {
                $this->db->beginTransaction();
            }
            $updateStep($this->db);

            if ($this->db->supportsTransactions()) {
                $this->db->commit();
            }
        } catch (\Exception $exception) {
            if ($this->db->supportsTransactions()) {
                $this->db->rollback();
            }
            throw $exception;
        }
    }

    public function step_1(): void
    {
        $this->useTransaction(function (\ilDBInterface $db) {
            $ipaaTableName = 'ipaa_data';

            if (!$db->tableExists($ipaaTableName)) {
                $db->createTable($ipaaTableName, [
                    'range_id' => ['type' => 'integer', 'notnull' => true],
                    'definition_id' => ['type' => 'integer', 'notnull' => true],
                    'ip_range_from' => ['type' => 'text', 'length' => 255, 'notnull' => true],
                    'ip_range_to' => ['type' => 'text', 'length' => 255 ],
                ]);

                $db->createSequence($ipaaTableName);

                $db->addForeignKey(
                    'ipaa_data_fkey',
                    ['definition_id'],
                    $ipaaTableName,
                    ['ref_id'],
                    'object_reference',
                    ForeignKeyConstraints::CASCADE,
                    ForeignKeyConstraints::CASCADE,
                );

                $db->addPrimaryKey($ipaaTableName, ['range_id']);
            }
        });
    }
}
