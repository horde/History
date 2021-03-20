<?php
/**
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @category   Horde
 * @package    History
 * @subpackage UnitTests
 */
namespace Horde\History\Sql;
use \Horde\History\TestBase as TestBase;
use \Horde_Log_Logger;
use \Horde_Log_Handler_Cli;
use \Horde_History_Sql;
use \PEAR_Config;
use \Horde_Db_Migration_Migrator;

abstract class BaseTestCase extends TestBase
{
    protected static $db;
    protected static $dir;
    protected static $migrator;
    protected static $reason;
    protected static $logger;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$logger = new Horde_Log_Logger(new Horde_Log_Handler_Cli());
        //self::$db->setLogger(self::$logger);
        self::$dir = __DIR__ . '/../../../../migration/Horde/History';
        if (!is_dir(self::$dir)) {
            error_reporting(E_ALL & ~E_DEPRECATED);
            self::$dir = PEAR_Config::singleton()
                ->get('data_dir', null, 'pear.horde.org')
                . '/Horde_History/migration';
            error_reporting(E_ALL | E_STRICT);
        }
        self::$history = new Horde_History_Sql('test_user', self::$db);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$db) {
            self::$db->disconnect();
        }
        self::$db = self::$migrator = null;
    }

    public function setUp(): void
    {
        if (!self::$db) {
            $this->markTestSkipped(self::$reason);
        }
        parent::setUp();
        self::$migrator = new Horde_Db_Migration_Migrator(
            self::$db,
            null,//self::$logger,
            array('migrationsPath' => self::$dir,
                  'schemaTableName' => 'horde_history_schema_info'));
        self::$migrator->up();
    }

    public function tearDown(): void
    {
        if (self::$migrator) {
            self::$migrator->down();
        }
    }

    public function testMigration()
    {
        self::$migrator->migrate(1);
        self::$db->insert(
            'INSERT INTO horde_histories (history_id, object_uid, history_ts, history_who, history_desc, history_action, history_extra) VALUES (?, ?, ?, ?, ?, ?, ?)',
            array(1, 'test_uid', time(), 'me', '', 'test_action', null)
        );
        self::$migrator->up();
        self::$history->log('test_uid2', array('who' => 'me', 'action' => 'test_action'));
    }
}
