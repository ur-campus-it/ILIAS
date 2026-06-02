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

namespace ILIAS\Data\Ip;

use InvalidArgumentException;
use ilXmlWriter;

final class IpAddressRange
{
    private ?int $obj_id = null;
    private ?int $ref_id = null;
    private IpAddress $from_address;
    private ?IpAddress $to_address = null;

    public function __construct(
        IpAddress $from_address,
        ?IpAddress $to_address = null
    ) {
        $this->setFromAddress($from_address);
        $this->setToAddress($to_address);
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->obj_id;
    }

    /**
     * @param int $obj_id
     * @return IpAddressRange
     */
    public function withId(int $obj_id): IpAddressRange
    {
        $this->obj_id = $obj_id;
        return $this;
    }

    /**
     * @return int
     */
    public function getRefId(): ?int
    {
        return $this->ref_id;
    }

    /**
     * @param int $ref_id
     * @return IpAddressRange
     */
    public function withRefId(int $ref_id): IpAddressRange
    {
        $this->ref_id = $ref_id;
        return $this;
    }

    /**
     * @return string
     */
    public function getFromAddress(): IpAddress
    {
        return $this->from_address;
    }

    /**
     * @param string $from_address
     * @return IpAddressRange
     */
    public function setFromAddress(IpAddress $from_address): IpAddressRange
    {
        if ($this->to_address === null) {
            $this->from_address = $from_address;
            return $this;
        }

        if (!self::isValid($from_address, $this->to_address)) {
            throw new InvalidArgumentException("Invalid IP address range");
        }

        $this->from_address = $from_address;
        return $this;
    }

    /**
     * @return string
     */
    public function getToAddress(bool $allow_null = false): ?IpAddress
    {
        if ($this->to_address === null && !$allow_null) {
            return $this->from_address;
        }

        return $this->to_address;
    }

    /**
     * @param string $from_address
     * @return IpAddressRange
     */
    public function setToAddress(?IpAddress $to_address): IpAddressRange
    {
        if ($to_address === null) {
            $this->to_address = null;
            return $this;
        }

        if (!self::isValid($this->from_address, $to_address)) {
            throw new InvalidArgumentException("Invalid IP address range");
        }

        $this->to_address = $to_address;
        return $this;
    }

    public function isAddressWithinRange(IpAddress $ip): bool {
        if ($ip->isIpv4() && $this->getFromAddress()->isIpv4() && $this->getToAddress()->isIpv4()) {
            return $this->getFromAddress()->toLong() <= $ip->toLong() && $ip->toLong() <= $this->getToAddress()->toLong();
        }

        if ($ip->isIpv6() && $this->getFromAddress()->isIpv6() && $this->getToAddress()->isIpv6()) {
            return $this->getFromAddress()->toHex() <= $ip->toHex() && $ip->toHex() <= $this->getToAddress()->toHex();
        }

        return false;
    }

    public static function isValid(IpAddress $from_address, IpAddress $to_address): bool
    {
        if ($from_address->isIpv4() && $to_address->isIpv4()) {
            return $from_address->toLong() <= $to_address->toLong();
        }
    
        if ($from_address->isIpv6() && $to_address->isIpv6()) {
            return $from_address->toHex() <= $to_address->toHex();
        }
    
        return false;
    }

    public function toXml(
        ilXmlWriter $a_xml_writer = new ilXmlWriter(),
        bool $dump_mem = true
    ): ?string
    {
        $a_xml_writer->xmlStartTag("IpRange", [ "id" => $this->getId()]);

        $a_xml_writer->xmlElement("From", null, $this->getFromAddress()->toString());

        if ($this->to_address !== null) {
            $a_xml_writer->xmlElement("To", null, $this->getToAddress()->toString());
        }

        $a_xml_writer->xmlEndTag("IpRange");

        if ($dump_mem) {
            return $a_xml_writer->xmlDumpMem(false);
        } else {
            return null;
        }
    }

    public function toString(): string
    {
        if ($this->to_address === null) {
            return $this->getFromAddress()->toString();
        }

        return $this->getFromAddress()->toString() . " - " . $this->getToAddress()->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
