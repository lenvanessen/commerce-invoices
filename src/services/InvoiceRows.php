<?php
/**
 * Commerce Invoices plugin for Craft CMS 3.x
 *
 * A pdf of an orders does not equal an invoice, invoices should be: Immutable, sequential in order.  Commerce Invoices allows you to create moment-in-time snapshots of a order to create a invoice or credit invoice
 *
 * @link      wndr.digital
 * @copyright Copyright (c) 2021 Len van Essen
 */

namespace lenvanessen\commerceinvoices\services;

use Craft;
use craft\base\Component;
use craft\commerce\elements\Order;
use lenvanessen\commerceinvoices\elements\Invoice;
use lenvanessen\commerceinvoices\records\InvoiceRow;
use yii\base\BaseObject;

/**
 * @author    Len van Essen
 * @package   CommerceInvoices
 * @since     1.0.0
 */
class InvoiceRows extends Component
{
    public function createFromOrder(Order $order, Invoice $invoice): bool
    {
        InvoiceRow::deleteAll("invoiceId = {$invoice->id}");

        $lineItemTax = 0;

        foreach($order->lineItems as $lineItem) {
            $row = new InvoiceRow();
            $row->lineItemId = $lineItem->id;
            $row->invoiceId = $invoice->id;
            $row->qty = $invoice->isCredit
                ? 0 - $lineItem->qty
                : $lineItem->qty;
            $row->description = $lineItem->description;

            $tax = ($lineItem->getTax() ?: $lineItem->getTaxIncluded()) / $lineItem->qty;

            $row->price = $lineItem->salePrice - $tax;
            $row->tax = $tax;
            $row->taxCategoryId = $lineItem->taxCategoryId;

            $lineItemTax += $tax;

            $row->save();
        }

        if(($shipping = $order->getTotalShippingCost()) > 0) {
            // Calculate the residual tax (shipping, etc)
            $shippingTax = ($order->getTotalTax() ?: $order->getTotalTaxIncluded()) - $lineItemTax;

            $row = new InvoiceRow();
            $row->invoiceId = $invoice->id;
            $row->qty = $invoice->isCredit ? -1 : 1;
            $row->description = Craft::t(
                'commerce-invoices',
                sprintf('Shipping costs %d', $order->id)
            );
            $row->tax = $shippingTax;
            $row->price = $shipping - $shippingTax;

            $row->save();
        }

        return true;
    }

    public function getAllRowsByInvoiceId(int $invoiceId)
    {
        return InvoiceRow::find()->where(['invoiceId' => $invoiceId])->all();
    }
}
