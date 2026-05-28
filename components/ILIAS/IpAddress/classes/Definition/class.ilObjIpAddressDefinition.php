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

use ILIAS\IpAddress\Objects\IpAddress;
use ILIAS\IpAddress\Objects\IpAddressRangeRepository;

final class ilObjIpAddressDefinition extends ilObject2
{
    public const string TYPE = 'ipad';

    private ?IpAddressRangeRepository $ranges = null;

    public function __construct(int $id = 0, bool $a_call_by_reference = true)
    {
        parent::__construct($id, $a_call_by_reference);
    }

    protected function initType(): void
    {
        $this->setType(self::TYPE);
    }

    public function getRanges(): IpAddressRangeRepository
    {
        if ($this->ranges === null) {
            if (!isset($this->ref_id)) {
                $message = "ilObject::read(): No ref_id given! (" . $this->type . ")";
                $this->error->raiseError($message, $this->error->WARNING);
            }
            $this->ranges = new IpAddressRangeRepository($this->ref_id);
        }
        return $this->ranges;
    }

    public function matchesAddress(string $ip): bool
    {
        if ($this->ranges === null) {
            $this->getRanges();
        }

        return $this->ranges->matchesAddress($ip);
    }

    public static function titleExists(string $title): bool
    {
        global $DIC;
        $db = $DIC->database();

        return $db->query("SELECT title FROM object_data WHERE type = 'ipad' AND title =" . $db->quote($title, 'text'))->numRows() > 0;
    }

    public static function search(string $title, bool $partial = true, bool $online_only = true, bool $in_trash = false): Generator
    {
        global $DIC;
        $db = $DIC->database();
        $query = "SELECT o.title, r.ref_id FROM object_data o LEFT JOIN object_reference r on o.obj_id = r.obj_id WHERE o.type = 'ipad'";
        if ($partial) {
            $query = $query . " AND" . $db->like('o.title', 'text', '%' . $title . '%');
        } else {
            $query = $query . " AND o.title =" . $db->quote($title, 'text');
        }
        if ($online_only) {
            $query = $query . " AND o.offline = 0";
        }
        $res = $db->query($query);

        while ($row = $res->fetchAssoc()) {
            if (ilObject::_isInTrash($row['ref_id']) && !$in_trash) continue;
            yield ilObjectFactory::getInstanceByRefId($row['ref_id']);
        }
    }

    public function toXml(): string
    {
        if ($this->ranges === null) {
            $this->getRanges();
        }

        $a_xml_writer = new ilXmlWriter();

        $a_xml_writer->xmlStartTag("IpAddressDefinition");

        $a_xml_writer->xmlElement("Id", null, $this->getId());
        $a_xml_writer->xmlElement("Title", null, $this->getTitle());
        $a_xml_writer->xmlElement("Description", null, $this->getDescription());

        foreach($this->ranges->findAll() as $id => $range) {
            $range->toXml($a_xml_writer, false);
        }

        $a_xml_writer->xmlEndTag("IpAddressDefinition");

        return $a_xml_writer->xmlDumpMem(false);
    }

    public static function _exists(int $id, bool $reference = false, ?string $type = null): bool
    {
        return parent::_exists($id, $reference, self::TYPE);
    }
}
