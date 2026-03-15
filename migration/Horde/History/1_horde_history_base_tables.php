<?php

class HordeHistoryBaseTables extends Horde_Db_Migration_Base
{
    public function up()
    {
        if (!in_array('horde_histories', $this->tables())) {
            $t = $this->createTable('horde_histories', ['autoincrementKey' => ['history_id']]);
            $t->column('history_id', 'integer', ['null' => false, 'unsigned' => true]);
            $t->column('object_uid', 'string', ['limit' => 255, 'null' => false]);
            $t->column('history_action', 'string', ['limit' => 32, 'null' => false]);
            $t->column('history_ts', 'bigint', ['null' => false]);
            $t->column('history_desc', 'text');
            $t->column('history_who', 'string', ['limit' => 255]);
            $t->column('history_extra', 'text');
            $t->end();
            $this->addIndex('horde_histories', ['history_action']);
            $this->addIndex('horde_histories', ['history_ts']);
            $this->addIndex('horde_histories', ['object_uid']);
        }
    }

    public function down()
    {
        $this->dropTable('horde_histories');
    }
}
