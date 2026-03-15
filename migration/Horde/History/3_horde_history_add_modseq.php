<?php

class HordeHistoryAddModSeq extends Horde_Db_Migration_Base
{
    public function up()
    {
        $t = $this->createTable('horde_histories_modseq', ['autoincrementKey' => false]);
        $t->column('history_modseq', 'integer', ['null' => false, 'default' => 0]);
        $t->column('history_modseqempty', 'integer', ['null' => false, 'default' => 0]);
        $t->end();
        $this->addColumn('horde_histories', 'history_modseq', 'integer', ['default' => 0, 'null' => false]);

        $rows = $this->select('SELECT history_id FROM horde_histories ORDER BY history_ts ASC');
        $seq = 1;

        $this->beginDbTransaction();
        $empty = true;
        foreach ($rows as $row) {
            $empty = false;
            $this->update(
                'UPDATE horde_histories SET history_modseq = ? WHERE history_id = ?',
                [$seq++, $row['history_id']]
            );
        }
        if (!$empty) {
            $this->insert('INSERT INTO horde_histories_modseq (history_modseq) VALUES(?)', [$seq - 1]);
        }
        $this->commitDbTransaction();

        // Add the index after the new values are set for performance reasons.
        $this->addIndex('horde_histories', ['history_modseq']);

        // ...same with the autoincrement for this field.
        $this->changeColumn('horde_histories_modseq', 'history_modseq', 'autoincrementKey');
    }

    public function down()
    {
        $this->dropTable('horde_histories_modseq');
        $this->removeColumn('horde_histories', 'history_modseq');
    }

}
