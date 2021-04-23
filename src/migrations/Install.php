<?php
/**
 * Commerce Invoices plugin for Craft CMS 3.x
 *
 * A pdf of an orders does not equal an invoice, invoices should be: Immutable, sequential in order.  Commerce Invoices allows you to create moment-in-time snapshots of a order to create a invoice or credit invoice
 *
 * @link      wndr.digital
 * @copyright Copyright (c) 2021 Len van Essen
 */

namespace lenvanessen\commerceinvoices\migrations;

use craft\commerce\db\Table as CommerceTable;

use Craft;
use craft\db\Migration;
use lenvanessen\commerceinvoices\db\Table;

/**
 * @author    Len van Essen
 * @package   CommerceInvoices
 * @since     1.0.0
 */
class Install extends Migration
{
    /**
     * @var string The database driver to use
     */
    public $driver;

    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        if ($this->createTables()) {
            $this->addForeignKeys();
            // Refresh the db schema caches
            Craft::$app->db->schema->refresh();
        }

        return true;
    }

   /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->driver = Craft::$app->getConfig()->getDb()->driver;
        $this->removeTables();

        return true;
    }

    /**
     * @return bool
     */
    protected function createTables()
    {
        $tablesCreated = false;

        $tableSchema = Craft::$app->db->schema->getTableSchema(Table::INVOICES);
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                Table::INVOICES,
                [
                    'id' => $this->primaryKey(),
                    'orderId' => $this->integer(),
                    'invoiceId' => $this->integer()->notNull(),
                    'type' => $this->enum('type', ['invoice', 'credit'])->defaultValue('invoice'),
                    'sent' => $this->boolean()->defaultValue(false),
                    'invoiceNumber' => $this->string(),
                    'restock' => $this->boolean()->defaultValue(false),
                    'billingAddressSnapshot' => $this->json(),
                    'shippingAddressSnapshot' => $this->json(),
                    'email' => $this->string(255)->notNull()->defaultValue(''),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid(),
                ]
            );
        }

        $tableSchema = Craft::$app->db->schema->getTableSchema(Table::INVOICE_ROWS);
        if ($tableSchema === null) {
            $tablesCreated = true;
            $this->createTable(
                Table::INVOICE_ROWS,
                [
                    'id' => $this->primaryKey(),
                    'invoiceId' => $this->integer(),
                    'lineItemId' => $this->integer(),
                    'qty' => $this->decimal(2),
                    'description' => $this->string(),
                    'price' => $this->decimal(14, 4)->notNull()->unsigned(),
                    'tax' => $this->decimal(14, 4)->notNull()->unsigned(),
                    'taxCategoryId' => $this->integer(),
                    'dateCreated' => $this->dateTime()->notNull(),
                    'dateUpdated' => $this->dateTime()->notNull(),
                    'uid' => $this->uid()
                ]
            );
        }

        return $tablesCreated;
    }

    /**
     * @return void
     */
    protected function addForeignKeys()
    {

        $this->addForeignKey(null,Table::INVOICES, 'id', '{{%elements}}', 'id');
        $this->addForeignKey(null, Table::INVOICES, 'orderId', CommerceTable::ORDERS, 'id',);
        $this->addForeignKey(null,Table::INVOICE_ROWS, 'invoiceId', Table::INVOICES, 'id');
        $this->addForeignKey(null, Table::INVOICE_ROWS, 'taxCategoryId', CommerceTable::TAXCATEGORIES, ['id']);
        $this->addForeignKey(null, Table::INVOICE_ROWS, 'lineItemId', CommerceTable::LINEITEMS, ['id'], null, null);
    }

    /**
     * @return void
     */
    protected function removeTables()
    {
        $this->dropTableIfExists(Table::INVOICE_ROWS);
        $this->dropTableIfExists(Table::INVOICES);
    }
}
