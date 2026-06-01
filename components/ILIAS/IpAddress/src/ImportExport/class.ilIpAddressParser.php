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

use ILIAS\Data\IpAddress\IpAddressRange;

class ilIpAddressParser extends ilSaxParser
{
    private ?int $id = null;
    private string $title = "";
    private ?string $description = null;

    private array $ranges = [];
    private ?IpAddressRange $range = null;

    private string $current_element = '';

    public function __construct(
        ?string $path_to_file = '',
        ?bool $throw_exception = false
    ) {
        parent::__construct($path_to_file);
        $this->df = new \ILIAS\Data\Factory();
        $this->setThrowException($throw_exception);
    }

    public function startParsing(): void
    {
        parent::startParsing();
    }

    public function setHandlers($a_xml_parser): void
    {
        xml_set_element_handler($a_xml_parser, $this->handlerBeginTag(...), $this->handlerEndTag(...));
        xml_set_character_data_handler($a_xml_parser, $this->handleCharacterData(...));
    }

    public function handlerBeginTag($a_xml_parser, string $a_name, array $a_attribs): void
    {
        // We don't store anything of relevance in the xml tags themselves, so we can keep
        // it simple here.
        $this->current_element = strtolower($a_name);

        if ($this->current_element == 'iprange') {
            $this->ipRangeBeginTag();
        }
    }

    public function handlerEndTag($a_xml_parser, string $a_name): void
    {
        // We don't store anything of relevance in the xml tags themselves, so we can keep
        // it simple here.
        if ($this->current_element == 'iprange') {
            $this->range = null;
        }

        $this->current_element = '';
    }

    public function handleCharacterData($a_xml_parser, string $a_data): void
    {
        switch($this->current_element) {
            case "id":
                $this->id = (int) $a_data;
                break;
            case "title":
                $this->title = $this->title . $a_data;
                break;
            case "description":
                $this->description = $a_data;
                break;
            case "from":
                $addr = $this->df->ip()->address($a_data);
                $this->range->setFromAddress($addr);
                break;
            case "to":
                $addr = $this->df->ip()->address($a_data);
                $this->range->setToAddress($addr);
                break;
            default:
                break;
        }
    }

    private function ipRangeBeginTag(): void {
        $from_address = $this->df->ip()->address("::");
        $this->range = $this->ranges[] = $this->df->ip()->range($from_address);
    }

    public function getTitle(): ?string {
        return $this->title;
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getDescription(): ?string {
        return $this->description;
    }

    public function getRanges(): array {
        return $this->ranges;
    }
}