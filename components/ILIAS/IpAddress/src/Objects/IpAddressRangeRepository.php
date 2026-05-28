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

namespace ILIAS\IpAddress;

use Generator;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\Data\Range;
use ILIAS\Data\Order;

class IpAddressRangeRepository implements DataRetrieval {

    private $database;

    private const TABLE_NAME = 'ipaa_data';

    public function __construct(
        private int $definition_id
    ) {
        $container = $GLOBALS['DIC'];
        $this->db = $container->database();
    }

    public function findAll(): Generator {
        $result = $this->db->query('SELECT * FROM ' .
            $this->db->quoteIdentifier(self::TABLE_NAME) .
            ' WHERE definition_id = ' . $this->db->quote($this->definition_id, 'integer')
        );

        while ($row = $result->fetchObject()) {
            yield $row->range_id => $this->parseFromStdClass($row);
        }
    }

    public function countAll(): int {
        $result = $this->db->query('SELECT * FROM ' .
            $this->db->quoteIdentifier(self::TABLE_NAME) .
            ' WHERE definition_id = ' . $this->db->quote($this->definition_id, 'integer')
        );

        return $result->numRows();
    }

    public function findByObjectId(int $range_id): IpAddressRange {
        $result = $this->db->query('SELECT * FROM ' .
            $this->db->quoteIdentifier(self::TABLE_NAME) .
            ' WHERE definition_id  = ' . $this->db->quote($this->definition_id, 'integer') .
            ' AND range_id = ' . $this->db->quote($range_id, 'integer')
        );

        while ($row = $result->fetchObject()) {
            return $this->parseFromStdClass($row);
        }

        throw new ilException('No IpAddressRange found with range_id' . $range_id);
    }

    public function create(IpAddressRange $range, int $definition_id): IpAddressRange {

        $this->db->insert(self::TABLE_NAME, [
            'range_id' => ['integer', $this->db->nextId('ipaa_data')],
            'definition_id' => ['integer', $definition_id],
            'ip_range_from' => ['text', $range->getFromAddress()->toString()],
            'ip_range_to' => ['text', self::getToAddress($range)]
        ]);

        return $range;
    }

    public function update(IpAddressRange $range, int $id, int $definition_id): IpAddressRange {

        $this->db->update(self::TABLE_NAME, [
            'definition_id' => ['int', $definition_id],
            'ip_range_from' => ['text', $range->getFromAddress()->toString()],
            'ip_range_to' => ['text', self::getToAddress($range)]
        ], [
            'range_id' => ['integer', $id]
        ]);

        return $range;
    }

    public function deleteByObjId(int $obj_id): void {
        $this->db->manipulate('DELETE FROM ' .
            $this->db->quoteIdentifier(self::TABLE_NAME) .
            ' WHERE definition_id  = ' . $this->db->quote($this->definition_id, 'integer') .
            ' AND range_id = ' . $this->db->quote($obj_id, 'integer')
        );
    }

    public function deleteAll(): void {
        $this->db->manipulate('DELETE FROM ' .
            $this->db->quoteIdentifier(self::TABLE_NAME) .
            ' WHERE definition_id  = ' . $this->db->quote($this->definition_id, 'integer')
        );
    }

    private function parseFromStdClass($val): IpAddressRange {

        $from_address = new IpAddress($val->ip_range_from);

        $to_address = null;
        if ($val->ip_range_to !== null && $val->ip_range_to !== "") {
            $to_address = new IpAddress($val->ip_range_to);
        }

        return new IpAddressRange($from_address, $to_address);
    }

    protected static function getToAddress(IpAddressRange $range): string {
        $to_address = $range->getToAddress(true);
        if ($to_address !== null) {
            $to_address = $to_address->toString();
        }

        return $to_address ?? "";
    }

    public function getRows(
        DataRowBuilder $row_builder,
        array $visible_column_ids,
        Range $range,
        Order $order,
        mixed $additional_viewcontrol_data,
        mixed $filter_data,
        mixed $additional_parameters
    ): Generator {

        foreach($this->findAll() as $id => $range) {
            yield $row_builder->buildDataRow(
                $id,
                [
                    'ip_range_from' => $range->getFromAddress()->toString(),
                    'ip_range_to' => self::getToAddress($range)
                ]
            );
        }
    }

    public function getTotalRowCount(
        mixed $additional_viewcontrol_data,
        mixed $filter_data,
        mixed $additional_parameters
    ): ?int {
        return $this->countAll();
    }
};