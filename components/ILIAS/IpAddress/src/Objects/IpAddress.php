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

namespace ILIAS\IpAddress\Objects;

use InvalidArgumentException;

final class IpAddress
{
    private string $ip_address;

    public function __construct(
        string $ip_address,
    ) {
        $this->set($ip_address);
    }

    public function set(string $ip_address): IpAddress
    {
        if (!self::isValid($ip_address)) {
            throw new InvalidArgumentException();
        }

        $this->ip_address = $ip_address;
        return $this;
    }

    public function isIpv4 (): bool {
        return filter_var($this->ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    public function isIpv6(): bool {
        return filter_var($this->ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    public function toString(): string
    {
        return $this->ip_address;
    }

    public function toLong(): int
    {
        return ip2long($this->ip_address);
    }

    public function toBin(): string
    {
        return inet_pton($this->ip_address);
    }

    public function toHex(): string
    {
        return bin2hex(inet_pton($this->ip_address));
    }

    public static function isValid(?string $ip): bool
    {
        return $ip !== null && $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP);
    }
}
