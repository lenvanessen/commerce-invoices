<?php

namespace craft\contentmigrations;

use craft\commerce\db\Table as CommerceTable;
use craft\db\Migration;
use craft\helpers\MigrationHelper;
use lenvanessen\commerce\invoices\db\Table;

/**
 * m220223_165606_fix_foreigh_key_issue_on_deleting_orders migration.
 */
class m220223_165606_fix_foreigh_key_issue_on_deleting_orders extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        MigrationHelper::dropForeignKeyIfExists(Table::INVOICES, ['orderId'], $this);
        $this->addForeignKey(null, Table::INVOICES, 'orderId', CommerceTable::ORDERS, 'id', 'CASCADE');
        // Place migration code here...
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m220223_165606_fix_foreigh_key_issue_on_deleting_orders cannot be reverted.\n";
        return false;
    }
}
