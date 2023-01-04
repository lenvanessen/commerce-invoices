<?php
/**
 * Commerce Invoices plugin for Craft CMS 3.x
 *
 * A pdf of an orders does not equal an invoice, invoices should be: Immutable, sequential in order.  Commerce Invoices allows you to create moment-in-time snapshots of a order to create a invoice or credit invoice
 *
 * @link      wndr.digital
 * @copyright Copyright (c) 2021 Len van Essen
 */
namespace lenvanessen\commerce\invoices\elements\db;

use craft\elements\db\ElementQuery;
use craft\helpers\Db;

/**
 * @author    Len van Essen
 * @package   CommerceInvoices
 * @since     1.0.0
 */
class InvoiceElementsQuery extends ElementQuery
{
    public $orderId;
    public $invoiceNumber;
//    public mixed $dateCreated;
    public $type;
    public $externalId;
    public $sent;

    public function sent($value)
    {
        $this->sent = $value;

        return $this;
    }

    public function orderId($value)
    {
        $this->orderId = $value;

        return $this;
    }

    public function type($value)
    {
        $this->type = $value;

        return $this;
    }

    public function invoiceNumber($value)
    {
        $this->invoiceNumber = $value;

        return $this;
    }

//    public function dateCreated(mixed $value): \craft\elements\db\ElementQuery
//    {
//        $this->dateCreated = $value;
//
//        return $this;
//    }

    public function externalId($value)
    {
        $this->externalId = $value;

        return $this;
    }

    protected function beforePrepare(): bool
    {
        // join in the products table
        $this->joinElementTable('commerceinvoices_invoice');

        $this->query->select([
            'commerceinvoices_invoice.orderId',
            'commerceinvoices_invoice.type',
            'commerceinvoices_invoice.sent',
            'commerceinvoices_invoice.invoiceId',
            'commerceinvoices_invoice.externalId',
            'commerceinvoices_invoice.restock',
            'commerceinvoices_invoice.email',
            'commerceinvoices_invoice.invoiceNumber',
            'commerceinvoices_invoice.dateCreated',
            'commerceinvoices_invoice.billingAddressSnapshot',
            'commerceinvoices_invoice.shippingAddressSnapshot',
        ]);

        if ($this->orderId) {
            $this->subQuery->andWhere(Db::parseParam('commerceinvoices_invoice.orderId', $this->orderId));
        }

        if($this->sent) {
            $this->subQuery->andWhere(Db::parseParam('commerceinvoices_invoice.sent', $this->sent));
        }

        if ($this->externalId) {
            $this->subQuery->andWhere(Db::parseParam('commerceinvoices_invoice.externalId', $this->externalId));
        }

        if ($this->type) {
            $this->subQuery->andWhere(Db::parseParam('commerceinvoices_invoice.type', $this->type));
        }

        return parent::beforePrepare();
    }
}