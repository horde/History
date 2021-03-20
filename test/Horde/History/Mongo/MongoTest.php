<?php
/**
 * Copyright 2014-2017 Horde LLC (http://www.horde.org/)
 *
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    History
 * @subpackage UnitTests
 */
namespace Horde\History\Mongo;
use \Horde\History\TestBase as TestBase;
use \Horde_History_Mongo;
use \Horde_Test_Factory_Mongo;

/**
 * MongoDB History tests.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    History
 * @subpackage UnitTests
 */
class MongoTest extends TestBase
{
    private $_dbname = 'horde_history_mongodbtest';
    private $_mongo;

    public function setUp(): void
    {
        if (($config = self::getConfig('HISTORY_MONGO_TEST_CONFIG', __DIR__ . '/..')) &&
            isset($config['history']['mongo'])) {
            $factory = new Horde_Test_Factory_Mongo();
            $this->_mongo = $factory->create(array(
                'config' => $config['history']['mongo'],
                'dbname' => $this->_dbname
            ));
        }

        if (empty($this->_mongo)) {
            $this->markTestSkipped('MongoDB not available.');
        }

        self::$history = new Horde_History_Mongo('test', array(
            'mongo_db' => $this->_mongo
        ));
    }

    public function tearDown(): void
    {
        if (!empty($this->_mongo)) {
            $this->_mongo->selectDB(null)->drop();
        }
    }

}
