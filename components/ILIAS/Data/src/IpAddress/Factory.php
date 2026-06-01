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

namespace ILIAS\Data\IpAddress;

class Factory
{
    public function address(string $addr): IpAddress
    {
        return new IpAddress($addr);
    }

    public function range(IPAddress $from, ?IpAddress $to = null): IpAddressRange
    {
        return new IpAddressRange($from, $to);
    }

    public function subnet(string $cidr): IpAddressSubnet
    {
        return new IpAddressSubnet($cidr);
    }
}
