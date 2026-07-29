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

namespace ILIAS\Test\Setup;

use ILIAS\Database\PDO\FieldDefinition\ForeignKeyConstraints;

class Test11DBUpdateSteps implements \ilDatabaseUpdateSteps
{
    use TestSettingsSetup;

    protected \ilDBInterface $db;

    public function prepare(\ilDBInterface $db): void
    {
        $this->db = $db;
    }

    public function step_1(): void
    {
        if ($this->db->tableColumnExists('tst_tests', 'mailnotification')) {
            $this->db->dropTableColumn('tst_tests', 'mailnotification');
        }
        if ($this->db->tableColumnExists('tst_tests', 'mailnottype')) {
            $this->db->dropTableColumn('tst_tests', 'mailnottype');
        }
    }

    public function step_2(): void
    {
        // 1. Create table schema
        if (!$this->db->tableExists('tst_test_settings')) {
            // Create table and sequence table
            $this->db->createTable('tst_test_settings', ['id' => ['type' => \ilDBConstants::T_INTEGER]]);
            $this->db->createSequence('tst_test_settings');
            $this->db->addPrimaryKey('tst_test_settings', ['id']);

            // Create table columns
            foreach (self::SETTINGS_COLUMNS as $key => $value) {
                [$column_def] = $value;

                // No columns should be nullable, except those with NULL by default
                if (!isset($column_def['notnull'])) {
                    $column_def['notnull'] = !$this->columnIsNullable($column_def);
                }

                $this->db->addTableColumn('tst_test_settings', $key, $column_def);
            }
        }

        // 2. Create a foreign key column in tst_tests
        if (!$this->db->tableColumnExists('tst_tests', 'settings_id')) {
            $this->db->addTableColumn(
                'tst_tests',
                'settings_id',
                ['type' => \ilDBConstants::T_INTEGER, 'default' => null, 'notnull' => false],
            );
            $this->db->addForeignKey(
                'test_settings_fkey',
                ['settings_id'],
                'tst_tests',
                ['id'],
                'tst_test_settings',
                ForeignKeyConstraints::NO_ACTION,
                ForeignKeyConstraints::RESTRICT,
            );
        }

        // 3. Create a foreign key column and new columns in tst_test_defaults
        if (!$this->db->tableColumnExists('tst_test_defaults', 'settings_id')) {
            $this->db->addTableColumn(
                'tst_test_defaults',
                'settings_id',
                ['type' => \ilDBConstants::T_INTEGER, 'default' => null, 'notnull' => false],
            );
            $this->db->addForeignKey(
                'test_default_fkey',
                ['settings_id'],
                'tst_test_defaults',
                ['id'],
                'tst_test_settings',
                ForeignKeyConstraints::NO_ACTION,
                ForeignKeyConstraints::RESTRICT
            );
        }
        if (!$this->db->tableColumnExists('tst_test_defaults', 'description')) {
            $this->db->addTableColumn(
                'tst_test_defaults',
                'description',
                ['type' => \ilDBConstants::T_TEXT, 'length' => 4000, 'default' => null],
            );
        }
        if (!$this->db->tableColumnExists('tst_test_defaults', 'author')) {
            $this->db->addTableColumn(
                'tst_test_defaults',
                'author',
                ['type' => \ilDBConstants::T_TEXT, 'length' => 255, 'default' => null],
            );
        }

        // 4. Create tst_defaults_marks table to store marks reference
        if (!$this->db->tableExists('tst_defaults_marks')) {
            $this->db->createTable(
                'tst_defaults_marks',
                [
                    'defaults_id' => ['type' => \ilDBConstants::T_INTEGER],
                    'mark_id' => ['type' => \ilDBConstants::T_INTEGER],
                ],
            );
            $this->db->addPrimaryKey('tst_defaults_marks', ['defaults_id', 'mark_id']);

            $this->db->addForeignKey(
                'test_default_fkey2',
                ['defaults_id '],
                'tst_defaults_marks',
                ['test_defaults_id'],
                'tst_test_defaults',
                ForeignKeyConstraints::NO_ACTION,
                ForeignKeyConstraints::RESTRICT
            );

            $this->db->addForeignKey(
                'test_mark_fkey',
                ['mark_id '],
                'tst_defaults_marks',
                ['mark_id'],
                'tst_mark',
                ForeignKeyConstraints::NO_ACTION,
                ForeignKeyConstraints::CASCADE
            );
        }
    }

    public function step_3(): void
    {
        $columns = ['reporting_date', 'starting_time', 'ending_time'];
        foreach ($columns as $column) {
            $this->db->modifyTableColumn('tst_test_settings', $column, ['length' => 8]);
        }
    }

    public function step_4(): void
    {
        $table = 'qpl_a_cloze';
        $column = 'answertext';
        if ($this->db->tableExists($table) && $this->db->tableColumnExists($table, $column)) {
            $this->db->manipulate("UPDATE {$table} SET {$column} = '' WHERE {$column} IS NULL");
            $this->db->modifyTableColumn($table, $column, ['default' => '', 'notnull' => true]);
        }
    }

    public function step_5(): void
    {
        $this->db->manipulate(
            'DELETE FROM settings WHERE module="assessment" AND keyword="export_essay_qst_with_html"'
        );
    }

    public function step_6(): void
    {
        $this->db->manipulate(
            'UPDATE tst_rnd_quest_set_qpls SET pool_path = "" WHERE pool_path IS NULL'
        );
        $this->db->modifyTableColumn(
            'tst_rnd_quest_set_qpls',
            'pool_path',
            [
                'default' => '',
                'notnull' => true
            ]
        );
    }

    public function step_7(): void
    {
        $this->db->manipulate(
            'UPDATE tst_rnd_quest_set_qpls SET pool_title = "" WHERE pool_title IS NULL'
        );
        $this->db->modifyTableColumn(
            'tst_rnd_quest_set_qpls',
            'pool_title',
            [
                'default' => '',
                'notnull' => true
            ]
        );
    }

    public function step_8(): void
    {
        if ($this->db->tableColumnExists('tst_test_settings', 'ip_ranges')) {
            return;
        }

        // 1. Create new column for IP ranges
        $this->db->addTableColumn('tst_test_settings', 'ip_ranges', ['type' => \ilDBConstants::T_TEXT, 'length' => 4000, 'default' => null]);

        // 2. Add ip_range_from to ip_ranges if both ip_range_from and ip_range_to are identical
        $this->db->manipulate(
            'UPDATE tst_test_settings SET ip_ranges = ip_range_from, ip_range_from = NULL, ip_range_to = NULL WHERE ip_range_from == ip_range_to AND ip_range_from IS NOT NULL AND ip_range_to IS NOT NULL'
        );

        // 3. Add ip_range_from to ip_ranges if ip_range_to is empty
        $this->db->manipulate(
            'UPDATE tst_test_settings SET ip_ranges = ip_range_from, ip_range_from = NULL, ip_range_to = NULL WHERE ip_range_from IS NOT NULL AND ip_range_to IS NULL'
        );

        // 4. Add ip_range_to to ip_ranges if ip_range_from is empty
        $this->db->manipulate(
            'UPDATE tst_test_settings SET ip_ranges = ip_range_to, ip_range_from = NULL, ip_range_to = NULL WHERE ip_range_from IS NULL AND ip_range_to IS NOT NULL'
        );
    }

    public function step_9(): void
    {
        if ($this->db->tableColumnExists('tst_invited_user', 'ip_ranges')) {
            return;
        }

        // 1. Create new column for IP ranges
        $this->db->addTableColumn('tst_invited_user', 'ip_ranges', ['type' => \ilDBConstants::T_TEXT, 'length' => 4000, 'default' => null]);

        // 2. Add preexisting IP ranges into column using concat
        $this->db->manipulate(
            'UPDATE tst_invited_user SET ip_ranges = concat(ip_range_from, "-", ip_range_to) WHERE ip_range_from != ip_range_to AND ip_range_from IS NOT NULL AND ip_range_to IS NOT NULL'
        );

        // 3. Add preexisting IP restrictions into column, handle edge cases where only ip_range_from is set
        $this->db->manipulate(
            'UPDATE tst_invited_user SET ip_ranges = ip_range_from WHERE ip_range_from == ip_range_to OR (ip_range_from IS NOT NULL AND ip_range_to IS NULL)'
        );

        // 4. Handle further edge cases where only ip_range_to is set
        $this->db->manipulate(
            'UPDATE tst_invited_user SET ip_ranges = ip_range_to WHERE ip_range_to IS NOT NULL AND ip_range_from IS NULL'
        );
    }

    public function step_10(): void
    {
        // 1. Ensure that a new column for IP ranges is present
        if (!$this->db->tableColumnExists('tst_test_settings', 'ip_ranges')) {
            $this->db->addTableColumn('tst_test_settings', 'ip_ranges', ['type' => \ilDBConstants::T_TEXT, 'length' => 4000, 'default' => null]);
        }

        if (!$this->db->tableColumnExists('tst_test_settings', 'ip_ranges_from') && !$this->db->tableColumnExists('tst_test_settings', 'ip_ranges_to')) {
            return;
        }

        // 2. Fetch all preexisting IP ranges
        $items = [];
        $res = $this->db->query("SELECT ip_range_from, ip_range_to FROM tst_test_settings WHERE ip_range_from != ip_range_to AND ip_range_from IS NOT NULL AND ip_range_to IS NOT NULL");

        while ($row = $this->db->fetchAssoc($res)) {
            $this->items[] = [
                'ip_range_from' => $row['ip_range_from'],
                'ip_range_to' => $row['ip_range_to']
            ];
        }

        // 3. Check if IP range definition already exists

        foreach ($items as $item) {
            $res = $this->db->query("SELECT definition_id FROM ipaa_data WHERE ip_range_from = " . $item['ip_range_from'] . " AND ip_range_to = " . $item['ip_range_to'] . " LIMIT 1");

            if ($this->db->numRows($res) > 0) {
                // 3a. IP range already exists, add definition_id to tst_test_settings

                $definition_id = $this->db->fetchObject($res)->definition_id;

                $this->db->manipulate(
                    'UPDATE tst_test_settings SET ip_ranges = ' . $this->db->quote('ref_' . $definition_id) . ' WHERE ip_range_from = ' . $this->db->quote($item['ip_range_from']) . ' AND ip_range_to = ' . $this->db->quote($item['ip_range_to'])
                );

            } else {
                // 3b. IP range does not exist, add new entry to ipaa_data and add ref_id to tst_test_settings

                $id = uniqid('ipad_');

                $obj_id = $this->db->nextId('object_data');
                $ref_id = $this->db->nextId('object_reference');

                $this->db->insert('object_data', [
                    'obj_id' => ['integer', $obj_id],
                    'type' => ['text', 'ipad'],
                    'title' => ['text', $id],
                    'description' => ['text', 'This entry was auto-generated by Test12DBUpdateSteps.'],
                ]);

                $this->db->insert('object_reference', [
                    'ref_id' => ['integer', $ref_id],
                    'obj_id' => ['integer', $obj_id]
                ]);

                $this->db->insert('ipaa_data', [
                    'definition_id' => ['integer', $ref_id],
                    'ip_range_from' => ['text', $item['ip_range_from']],
                    'ip_range_to' => ['text', $item['ip_range_to']]
                ]);

                $this->db->manipulate(
                    'UPDATE tst_test_settings SET ip_ranges = ' . $this->db->quote('ref_' . $ref_id) . ' WHERE ip_range_from = ' . $this->db->quote($item['ip_range_from']) . ' AND ip_range_to = ' . $this->db->quote($item['ip_range_to'])
                );
            }

        }
    }

    public function step_11(): void
    {
        if (!$this->db->tableColumnExists('tst_invited_user', 'ip_ranges')) {
            $this->db->addTableColumn('tst_invited_user', 'ip_ranges', ['type' => \ilDBConstants::T_TEXT, 'length' => 4000, 'default' => null]);
        }

        if (!$this->db->tableColumnExists('tst_test_settings', 'ip_ranges_from') && !$this->db->tableColumnExists('tst_test_settings', 'ip_ranges_to')) {
            return;
        }

        $this->db->manipulate(
            'UPDATE tst_invited_user SET ip_ranges = ip_range_from, ip_range_from = NULL, ip_range_to = NULL WHERE ip_range_from == ip_range_to AND ip_range_from IS NOT NULL AND ip_range_to IS NOT NULL'
        );

        $this->db->manipulate(
            'UPDATE tst_invited_user SET ip_ranges = ip_range_from, ip_range_from = NULL, ip_range_to = NULL WHERE ip_range_from IS NOT NULL AND ip_range_to IS NULL'
        );

        $this->db->manipulate(
            'UPDATE tst_invited_user SET ip_ranges = ip_range_to, ip_range_from = NULL, ip_range_to = NULL WHERE ip_range_from IS NULL AND ip_range_to IS NOT NULL'
        );
    }

    public function step_12(): void
    {
        // 1. Ensure that a new column for IP ranges is present
        if (!$this->db->tableColumnExists('tst_invited_user', 'ip_ranges')) {
            $this->db->addTableColumn('tst_invited_user', 'ip_ranges', ['type' => \ilDBConstants::T_TEXT, 'length' => 4000, 'default' => null]);
        }

        if (!$this->db->tableColumnExists('tst_test_settings', 'ip_ranges_from') && !$this->db->tableColumnExists('tst_test_settings', 'ip_ranges_to')) {
            return;
        }

        // 2. Fetch all preexisting IP ranges
        $items = [];
        $res = $this->db->query("SELECT ip_range_from, ip_range_to FROM tst_invited_user WHERE ip_range_from IS NOT NULL AND ip_range_to IS NOT NULL");

        while ($row = $this->db->fetchAssoc($res)) {
            $this->items[] = [
                'ip_range_from' => $row['ip_range_from'],
                'ip_range_to' => $row['ip_range_to']
            ];
        }

        // 3. Check if IP range definition already exists

        foreach ($items as $item) {
            $res = $this->db->query("SELECT definition_id FROM ipaa_data WHERE ip_range_from = " . $item['ip_range_from'] . " AND ip_range_to = " . $item['ip_range_to'] . " LIMIT 1");

            if ($this->db->numRows($res) > 0) {
                // 3a. IP range already exists, add definition_id to tst_invited_user

                $definition_id = $this->db->fetchObject($res)->definition_id;

                $this->db->manipulate(
                    'UPDATE tst_invited_user SET ip_ranges = ' . $this->db->quote('ref_' . $definition_id) . ' WHERE ip_range_from = ' . $this->db->quote($item['ip_range_from']) . ' AND ip_range_to = ' . $this->db->quote($item['ip_range_to'])
                );

            } else {
                // 3b. IP range does not exist, add new entry to ipaa_data and add ref_id to tst_test_settings

                $id = uniqid('ipad_');

                $obj_id = $this->db->nextId('object_data');
                $ref_id = $this->db->nextId('object_reference');

                $this->db->insert('object_data', [
                    'obj_id' => ['integer', $obj_id],
                    'type' => ['text', 'ipad'],
                    'title' => ['text', $id],
                    'description' => ['text', 'This entry was auto-generated by Test12DBUpdateSteps.'],
                ]);

                $this->db->insert('object_reference', [
                    'ref_id' => ['integer', $ref_id],
                    'obj_id' => ['integer', $obj_id]
                ]);

                $this->db->insert('ipaa_data', [
                    'definition_id' => ['integer', $ref_id],
                    'ip_range_from' => ['text', $item['ip_range_from']],
                    'ip_range_to' => ['text', $item['ip_range_to']]
                ]);

                $this->db->manipulate(
                    'UPDATE tst_test_settings SET ip_ranges = ' . $this->db->quote('ref_' . $ref_id) . ' WHERE ip_range_from = ' . $this->db->quote($item['ip_range_from']) . ' AND ip_range_to = ' . $this->db->quote($item['ip_range_to'])
                );
            }

        }

        // 5. Remove columns ip_range_from and ip_range_to
        $this->db->dropTableColumn('tst_test_settings', 'ip_range_from');
        $this->db->dropTableColumn('tst_test_settings', 'ip_range_to');
    }
}
