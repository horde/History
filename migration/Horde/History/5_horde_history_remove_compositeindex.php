<?php

class HordeHistoryRemoveCompositeIndex extends Horde_Db_Migration_Base
{
    public function up()
    {
        // Older installs may have indexes differently named.
        $indexes = $this->indexes('horde_histories');
        foreach ($indexes as $idx) {
            if ($idx->columns == ['history_modseq']
                || $idx->columns == ['object_uid']) {
                $this->removeIndex('horde_histories', ['name' => $idx->name]);
            }
        }
    }

    public function down()
    {
        $this->addIndex('horde_histories', 'history_modseq');
        $this->addIndex('horde_histories', 'object_uid');
    }

}
