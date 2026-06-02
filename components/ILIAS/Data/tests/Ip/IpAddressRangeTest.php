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
use ILIAS\Data\Ip\IpAddressRange;
use ILIAS\Data\Ip\IpAddress;
use InvalidArgumentException;

/**
 * Unit tests for IP address range object
 * @author  Bastian Meissner <bastian.meissner@ur.de>
 * @ingroup IpAddress
 */

#[CoversClass(IpAddressRange::class)]
class IpAddressRangeTest extends TestCase
{

    public function testConstruct(): void
    {
        $obj = new IpAddressRange(
            new IpAddress("127.0.0.1")
        );

        $this->assertInstanceOf(IpAddressRange::class, $obj);
    
        $obj = new IpAddressRange(
            new IpAddress("127.0.0.1"),
            new IpAddress("127.0.0.5")
        );

        $this->assertInstanceOf(IpAddressRange::class, $obj);

        $obj = new IpAddressRange(
            new IpAddress("::1"),
            new IpAddress("::5")
        );

        $this->assertInstanceOf(IpAddressRange::class, $obj);

        $this->expectException(InvalidArgumentException::class);
        $obj = new IpAddressRange(
            new IpAddress("::5"),
            new IpAddress("::1")
        );

        $this->expectException(InvalidArgumentException::class);
        $obj = new IpAddressRange(
            new IpAddress("127.0.0.5"),
            new IpAddress("127.0.0.1")
        );

        $this->expectException(InvalidArgumentException::class);
        $obj = new IpAddressRange(
            new IpAddress("127.0.0.1"),
            new IpAddress("::5")
        );
    }

    public function testSetFromAddress(): void
    {
        $obj = new IpAddressRange(
            new IpAddress("127.0.0.1"),
            new IpAddress("127.0.0.5")
        );

        $obj->setFromAddress(new IpAddress("127.0.0.2"));
        $this->assertEquals($obj->getFromAddress()->toString(), "127.0.0.2");

        $this->expectException(InvalidArgumentException::class);
        $obj->setFromAddress(new IpAddress("127.0.0.6"));

        $this->expectException(InvalidArgumentException::class);
        $obj->setFromAddress(new IpAddress("::1"));

        $obj = new IpAddressRange(
            new IpAddress("::1"),
            new IpAddress("::5")
        );

        $obj->setFromAddress(new IpAddress("::2"));
        $this->assertEquals($obj->getFromAddress()->toString(), "::2");

        $this->expectException(InvalidArgumentException::class);
        $obj->setFromAddress(new IpAddress("::6"));

        $this->expectException(InvalidArgumentException::class);
        $obj->setFromAddress(new IpAddress("127.0.0.1"));

        $obj = new IpAddressRange(
            new IpAddress("::1")
        );

        $obj->setFromAddress(new IpAddress("::2"));
        $this->assertEquals($obj->getFromAddress()->toString(), "::2");

        $obj->setFromAddress(new IpAddress("127.0.0.2"));
        $this->assertEquals($obj->getFromAddress()->toString(), "127.0.0.2");
    }

    public function testSetToAddress(): void
    {
        $obj = new IpAddressRange(
            new IpAddress("127.0.0.2")
        );

        $obj->setToAddress(new IpAddress("127.0.0.5"));
        $this->assertEquals($obj->getToAddress()->toString(), "127.0.0.5");

        $obj->setToAddress(null);
        $this->assertEquals($obj->getToAddress()->toString(), "127.0.0.2");

        $this->expectException(InvalidArgumentException::class);
        $obj->setToAddress(new IpAddress("127.0.0.1"));

        $this->expectException(InvalidArgumentException::class);
        $obj->setToAddress(new IpAddress("::1"));

        $obj = new IpAddressRange(
            new IpAddress("::2")
        );

        $obj->setToAddress(new IpAddress("::5"));
        $this->assertEquals($obj->getToAddress()->toString(), "::5");

        $obj->setToAddress(null);
        $this->assertEquals($obj->getToAddress()->toString(), "::2");

        $this->expectException(InvalidArgumentException::class);
        $obj->setToAddress(new IpAddress("::1"));

        $this->expectException(InvalidArgumentException::class);
        $obj->setToAddress(new IpAddress("127.0.0.1"));
    }

    public function testGetToAddress(): void
    {
        $obj = new IpAddressRange(
            new IpAddress("127.0.0.1")
        );

        $this->assertEquals($obj->getToAddress()->toString(), "127.0.0.1");
        $this->assertNull($obj->getToAddress(true));

        $obj->setToAddress(new IpAddress("127.0.0.5"));
        $this->assertEquals($obj->getToAddress()->toString(), "127.0.0.5");
        $this->assertEquals($obj->getToAddress(true)->toString(), "127.0.0.5");
    }

    public function testIsAddressWithinRange(): void
    {
        $obj = new IpAddressRange(
            new IpAddress("127.0.0.1")
        );

        $this->assertTrue($obj->isAddressWithinRange(new IpAddress("127.0.0.1")));
        $this->assertFalse($obj->isAddressWithinRange(new IpAddress("127.0.0.2")));
        $this->assertFalse($obj->isAddressWithinRange(new IpAddress("::1")));

        $obj->setToAddress(new IpAddress("127.0.0.5"));
        $this->assertTrue($obj->isAddressWithinRange(new IpAddress("127.0.0.3")));
        $this->assertFalse($obj->isAddressWithinRange(new IpAddress("127.0.0.7")));
        $this->assertFalse($obj->isAddressWithinRange(new IpAddress("::1")));

        $obj = new IpAddressRange(
            new IpAddress("::1")
        );

        $this->assertTrue($obj->isAddressWithinRange(new IpAddress("::1")));
        $this->assertFalse($obj->isAddressWithinRange(new IpAddress("::2")));
        $this->assertFalse($obj->isAddressWithinRange(new IpAddress("127.0.0.1")));

        $obj->setToAddress(new IpAddress("::5"));
        $this->assertTrue($obj->isAddressWithinRange(new IpAddress("::3")));
        $this->assertFalse($obj->isAddressWithinRange(new IpAddress("::7")));
        $this->assertFalse($obj->isAddressWithinRange(new IpAddress("127.0.0.1")));
    }

    public function testIsValid(): void
    {
        $this->assertTrue(IpAddressRange::isValid(
            new IpAddress("127.0.0.1"),
            new IpAddress("127.0.0.5")
        ));

        $this->assertTrue(IpAddressRange::isValid(
            new IpAddress("::1"),
            new IpAddress("::5")
        ));

        $this->assertFalse(IpAddressRange::isValid(
            new IpAddress("127.0.0.5"),
            new IpAddress("127.0.0.1")
        ));

        $this->assertFalse(IpAddressRange::isValid(
            new IpAddress("::5"),
            new IpAddress("::1")
        ));

        $this->assertFalse(IpAddressRange::isValid(
            new IpAddress("127.0.0.1"),
            new IpAddress("::5")
        ));
    }

    public function testToXml(): void
    {
        $obj = new IpAddressRange(
            new IpAddress("127.0.0.1"),
            new IpAddress("127.0.0.5")
        );

        $this->assertEquals($obj->toXml(), "<IpRange><From>127.0.0.1</From><To>127.0.0.5</To></IpRange>");
    }

    public function testToString(): void
    {
        $obj = new IpAddressRange(
            new IpAddress("127.0.0.1"),
            new IpAddress("127.0.0.5")
        );

        $this->assertEquals($obj->toString(), "127.0.0.1 - 127.0.0.5");

        $obj = new IpAddressRange(
            new IpAddress("127.0.0.1")
        );
        
        $this->assertEquals($obj->toString(), "127.0.0.1");
    }
}

