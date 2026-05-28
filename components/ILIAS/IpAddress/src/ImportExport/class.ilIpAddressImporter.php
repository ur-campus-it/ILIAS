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

use ILIAS\IpAddress\Objects\IpAddressRange;
use SimpleXMLElement;

class ilIpAddressImporter extends ilXmlImporter
{
    private ilIpAddressParser $parser;
    private int $root_ref_id;

    public function init(): void
    {
        global $DIC;
        $this->root_ref_id = ilObjIpAddressAdministration::getRootRefId();
    }

    public function importXmlRepresentation(
        string $a_entity,
        string $a_id,
        string $a_xml,
        ilImportMapping $a_mapping
    ): void {

        if ($a_entity !== "ipad") {
            throw new ilException("Unsupported entity " . $a_entity);
        }

        $this->parser = new ilIpAddressParser();
        $this->parser->setXMLContent($a_xml);
        $this->parser->startParsing();

        $this->validateIpRanges($this->parser->getRanges());

        $obj = new ilObjIpAddressDefinition();

        $title = $this->parser->getTitle();
        $i = 1;
        while (ilObjIpAddressDefinition::titleExists($title)) {
            $title = $this->parser->getTitle() . "(" . strval($i) . ")";
            $i = $i + 1;
        }

        $obj->setTitle($title);
        $obj_id = $obj->create();

        // Horrible hack that actually sets the description
        // TODO this is subpar
        $obj->setDescription($this->parser->getDescription() ?? "");
        $obj->setType($a_entity);
        $obj->update();

        $ref_id = $obj->createReference();
        $obj->setRefId($ref_id);
        $obj->putInTree($this->root_ref_id);
        $obj->setPermissions($this->root_ref_id);

        foreach($this->parser->getRanges() as $range) {
            $obj->getRanges()->create($range, $ref_id);
        }

        $a_mapping->addMapping(
            'components/ILIAS/IpAddress', $a_entity, $a_id, strval($obj_id)
        );
    }

    private function validateIpRanges(array $ranges) {
        foreach($ranges as $range) {
            if (!IpAddressRange::isValid($range->getFromAddress(), $range->getToAddress(true))) throw new ilException("Invalid IP address range");
        }
    }
}