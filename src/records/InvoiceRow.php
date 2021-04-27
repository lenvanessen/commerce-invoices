<?php
/**
 * Commerce Invoices plugin for Craft CMS 3.x
 *
 * A pdf of an orders does not equal an invoice, invoices should be: Immutable, sequential in order.  Commerce Invoices allows you to create moment-in-time snapshots of a order to create a invoice or credit invoice
 *
 * @link      wndr.digital
 * @copyright Copyright (c) 2021 Len van Essen
 */

namespace lenvanessen\commerce\invoices\records;

use craft\commerce\records\LineItem;
use lenvanessen\commerce\invoices\CommerceInvoices;

use Craft;
use craft\db\ActiveRecord;

/**
 * @author    Len van Essen
 * @package   CommerceInvoices
 * @since     1.0.0
 */
class InvoiceRow extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%commerceinvoices_invoicerow}}';
    }

    public function subTotal()
    {
        return $this->qty * $this->price;
    }

    public function subTotalTax()
    {
        return $this->qty * $this->tax;
    }

    public function getLineItem()
    {
        if(! $this->lineItemId) {
            return false;
        }

        return LineItem::findOne($this->lineItemId);
    }
}
