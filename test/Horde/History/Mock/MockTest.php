<?php
/**
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * @author     Michael J Rubinsky <mrubinsk@horde.org>
 * @category   Horde
 * @package    History
 * @subpackage UnitTests
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
namespace Horde\History\Mock;
use Horde\History\TestBase as TestBase;
use \Horde_History_Mock;
use \Horde_Cache;
use \Horde_Cache_Storage_Mock;

class MockTest extends TestBase
{
    protected function setUp(): void
    {
        self::$history = new Horde_History_Mock('test');
    }

    protected function tearDown(): void
    {
        self::$history = null;
    }

    public function testCaching()
    {
        if (!class_exists('Horde_Cache_Storage_Mock')) {
            $this->markTestSkipped('Horde_Cache is not installed');
        }

        self::$history->setCache(new Horde_Cache(new Horde_Cache_Storage_Mock()));
        self::$history->log('appone:test_uid', array('who' => 'me', 'ts' => 1000, 'action' => 'test_action'));
        self::$history->getHistory('appone:test_uid');
        $count = self::$history->getCount;
        $this->assertGreaterThan(0, $count);
        self::$history->getHistory('appone:test_uid');
        $this->assertEquals($count, self::$history->getCount);
        self::$history->removeByNames(array('appone:test_uid'));
        $this->assertEquals(0, count(self::$history->getHistory('appone:test_uid')));
        $this->assertGreaterThan($count, self::$history->getCount);
    }
}
