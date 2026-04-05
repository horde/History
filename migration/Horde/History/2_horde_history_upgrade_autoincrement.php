<?php

class HordeHistoryUpgradeAutoIncrement extends Horde_Db_Migration_Base
{
    public function up()
    {
        $this->changeColumn('horde_histories', 'history_id', 'autoincrementKey');
        if (in_array('horde_histories_seq', $this->tables())) {
            $this->dropTable('horde_histories_seq');
        }
    }

    public function down()
    {
        $this->changeColumn('horde_histories', 'history_id', 'integer', ['null' => false, 'unsigned' => true]);
    }
}
