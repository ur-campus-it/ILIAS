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

class ilIpAddressExporter extends ilXmlExporter
{
    public function init(): void { }

    public function getXmlRepresentation(string $a_entity, string $a_schema_version, string $a_id): string
    {
        $refs = ilObject::_getAllReferences((int) $a_id);
        if (count($refs) !== 1) {
            return "";
        }

        $obj = ilObjectFactory::getInstanceByRefId(current($refs));
        return $obj->toXml();
    }

    public function getValidSchemaVersions(string $a_entity): array
    {
        return [
            "4.1.0" => [
                "namespace" => "http://www.ilias.de/IpAddress/ipad/4_1",
                "xsd_file" => "ilias_ipad_4_1.xsd",
                "uses_dataset" => false,
                "min" => "4.1.0",
                "max" => ""
            ]
        ];
    }
}