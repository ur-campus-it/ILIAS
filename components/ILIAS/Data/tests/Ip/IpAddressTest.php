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

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use ILIAS\Data\Ip\IpAddress;

/**
 * Unit tests for IP address object
 * @author  Bastian Meissner <bastian.meissner@ur.de>
 * @ingroup IpAddress
 */

#[CoversClass(IpAddress::class)]
class IpAddressTest extends TestCase
{

    public function testConstruct(): void
    {
        $obj = new IpAddress("127.0.0.1");
        $this->assertInstanceOf(IpAddress::class, $obj);
    
        $obj = new IpAddress("::1");
        $this->assertInstanceOf(IpAddress::class, $obj);

        $this->expectException(InvalidArgumentException::class);
        $obj = new IpAddress("Patrick that's a pickle");
    }

    public function testSet(): void
    {
        $obj = new IpAddress("127.0.0.1");
        
        $obj->set("127.0.0.2");
        $this->assertEquals($obj->toString(), "127.0.0.2");

        $obj->set("::1");
        $this->assertEquals($obj->toString(), "::1");

        $this->expectException(InvalidArgumentException::class);
        $obj->set("Patrick that's a pickle");
    }

    public function testIsIpv4(): void
    {
        $obj = new IpAddress("127.0.0.1");
        $this->assertTrue($obj->isIpv4());
        $this->assertFalse($obj->isIpv6());
    }

    public function testIsIpv6(): void
    {
        $obj = new IpAddress("::1");
        $this->assertTrue($obj->isIpv6());
        $this->assertFalse($obj->isIpv4());
    }

    public function testToLong(): void
    {
        $obj = new IpAddress("127.0.0.1");
        $this->assertEquals($obj->toLong(), "2130706433");
    }

    public function testToBin(): void
    {
        $obj = new IpAddress("127.0.0.1");
        $this->assertEquals($obj->toHex(), "7f000001");
    }

    public function testIsValid(): void
    {
        $this->assertTrue(IpAddress::isValid("127.0.0.1"));
        $this->assertTrue(IpAddress::isValid("::1"));
        $this->assertFalse(IpAddress::isValid("Patrick that's a pickle"));
    }

    public function testToString(): void
    {
        $obj = new IpAddress("127.0.0.1");
        $this->assertEquals($obj->toString(), "127.0.0.1");
    }
}

