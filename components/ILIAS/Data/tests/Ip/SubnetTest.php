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
use ILIAS\Data\Ip\Subnet;
use ILIAS\Data\Ip\IpAddress;
use InvalidArgumentException;

/**
 * Unit tests for IP address range object
 * @author  Bastian Meissner <bastian.meissner@ur.de>
 * @ingroup IpAddress
 */

#[CoversClass(Subnet::class)]
class SubnetTest extends TestCase
{

    public function testConstruct(): void
    {
        $obj = new Subnet(new IpAddress("127.0.0.0"), 24);
        $this->assertInstanceOf(Subnet::class, $obj);

        $obj = new Subnet(new IpAddress("::"), 64);
        $this->assertInstanceOf(Subnet::class, $obj);

        $this->expectException(InvalidArgumentException::class);
        $obj = new Subnet(new IpAddress("127.0.0.0"), 64);

        $this->expectException(InvalidArgumentException::class);
        $obj = new Subnet(new IpAddress("::"), 161);
    }

    public function testSetIpAddress(): void
    {
        $obj = new Subnet(new IpAddress("::"), 64);
        $this->assertEquals($obj->getIpAddress()->toString(), "::");

        $obj->setIpAddress(new IpAddress("::1"));
        $this->assertEquals($obj->getIpAddress()->toString(), "::1");

        $this->expectException(InvalidArgumentException::class);
        $obj->setIpAddress(new IpAddress("127.0.0.1"));
    }

    public function testSetMask(): void
    {
        $obj = new Subnet(new IpAddress("127.0.0.1"), 24);
        
        $obj->setMask(32);
        $this->assertEquals($obj->getMask(), 32);

        $this->expectException(InvalidArgumentException::class);
        $obj->setMask(64);

        $obj = new Subnet(new IpAddress("::"), 64);

        $obj->setMask(32);
        $this->assertEquals($obj->getMask(), 32);

        $this->expectException(InvalidArgumentException::class);
        $obj->setMask(255);
    }

    public function testIsAddressInSubnet(): void
    {
        $obj = new Subnet(new IpAddress("127.0.0.1"), 24);

        $this->assertTrue($obj->isAddressInSubnet(new IpAddress("127.0.0.5")));
        $this->assertFalse($obj->isAddressInSubnet(new IpAddress("::1")));

        $obj = new Subnet(new IpAddress("::"), 64);

        $this->assertTrue($obj->isAddressInSubnet(new IpAddress("::5")));
        $this->assertFalse($obj->isAddressInSubnet(new IpAddress("127.0.0.5")));
    }

    public function testIsStringValid(): void
    {
        $this->assertTrue(Subnet::isStringValid("127.0.0.0/24"));
        $this->assertTrue(Subnet::isStringValid("::/64"));
        $this->assertFalse(Subnet::isStringValid("127.0.0.0/64"));
        $this->assertFalse(Subnet::isStringValid("Patrick that's a pickle"));
    }

    public function testIsValid(): void
    {
        $this->assertTrue(Subnet::isValid(new IpAddress("127.0.0.0"), 24));
        $this->assertTrue(Subnet::isValid(new IpAddress("::"), 64));
        $this->assertFalse(Subnet::isValid(new IpAddress("127.0.0.0"), 64));
        $this->assertFalse(Subnet::isValid(new IpAddress("::"), 255));
    }

    public function testToString(): void
    {
        $obj = new Subnet(new IpAddress("127.0.0.1"), 24);
        $this->assertEquals($obj->toString(), "127.0.0.1/24");

        $obj = new Subnet(new IpAddress("::1"), 64);
        $this->assertEquals($obj->toString(), "::1/64");
    }
}