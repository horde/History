<?php
/**
 * Copyright 2014-2017 Horde LLC (http://www.horde.org/)
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @package    History
 * @subpackage UnitTests
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
namespace Horde\History\Sql;
use Horde_History_Sql_Base as Base;

class Oci8Test extends Base
{
    public static function setUpBeforeClass(): void
    {
        if (!extension_loaded('oci8')) {
            self::$reason = 'No oci8 extension';
            return;
        }
        $config = self::getConfig('HISTORY_SQL_OCI8_TEST_CONFIG', __DIR__ . '/..');
        if ($config && !empty($config['history']['sql']['oci8'])) {
            self::$db = new Horde_Db_Adapter_Oci8($config['history']['sql']['oci8']);
            parent::setUpBeforeClass();
        } else {
            self::$reason = 'No oci8 configuration';
        }
    }
}
