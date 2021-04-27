<?php

namespace lenvanessen\commerce\invoices\migrations;

use Craft;
use craft\db\Migration;
use lenvanessen\commerce\invoices\records\Invoice;

/**
 * m210427_082002_addExternalId migration.
 */
class m210427_082002_addExternalId extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $tableName = Invoice::tableName();

        if(!$this->db->columnExists($tableName, 'externalId')) {
            $this->addColumn($tableName, 'externalId', $this->string()->after('id'));
            $this->createIndex(null, $tableName, 'externalId', false);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m210427_082002_addExternalId cannot be reverted.\n";
        return false;
    }
}
