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

final class Subnet
{
    private IpAddress $ip_address;
    private int $mask;

    public function __construct(IpAddress $address, int $mask) {

        if (!self::isValid($address, $mask)) {
            throw new InvalidArgumentException("Subnet is invalid.");
        }

        $this->ip_address = $address;
        $this->mask = $mask;
    }

    /**
     * @return string
     */
    public function getIpAddress(): IpAddress
    {
        return $this->ip_address;
    }

    public function setIpAddress(IpAddress $ip_address): Subnet
    {
        if (!self::isValid($ip_address, $this->mask)) {
            throw new InvalidArgumentException("Invalid IP address for given subnet mask");
        }

        $this->ip_address = $ip_address;
        return $this;
    }

    public function getMask(): int
    {
        return $this->mask;
    }

    public function setMask(int $mask): Subnet
    {
        if (!self::isValid($this->ip_address, $mask)) {
            throw new InvalidArgumentException("Invalid subnet mask for given IP address.");
        }

        $this->mask = $mask;
        return $this;
    }

    public function isAddressInSubnet(IpAddress $ip): bool
    {
        if (
            !($ip->isIpv4() && $this->ip_address->isIpv4()) &&
            !($ip->isIpv6() && $this->ip_address->isIpv6())
        ) return false;

        $ip = $ip->toBin();

        $mask = str_repeat("\xFF", $this->mask >> 3);
        if ($this->mask & 7) {
            $mask .= chr(0xFF << (8 - ($this->mask & 7)));
        }

        $mask = str_pad($mask, strlen($ip), "\x00");

        return ($ip & $mask) == ($this->ip_address->toBin() & $mask);
    }

    public function toString(): string
    {
        return $this->getIpAddress()->toString() . "/" . $this->getMask();
    }

    public static function isStringValid(string $ip_cidr): bool
    {
        $components = explode("/", $ip_cidr);

        if (count($components) !== 2) return false;

        [ $addr, $mask ] = $components;

        if (!IpAddress::isValid($addr)) return false;

        return self::isValid(new IpAddress($addr), (int) $mask);
    }

    public static function isValid(IpAddress $ip_address, int $mask): bool
    {
        return ($ip_address->isIpv4() && $mask <= 32) || ($ip_address->isIpv6() && $mask <= 128);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
