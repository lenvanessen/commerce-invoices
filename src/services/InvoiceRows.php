<?php
/**
 * Commerce Invoices plugin for Craft CMS 3.x
 *
 * A pdf of an orders does not equal an invoice, invoices should be: Immutable, sequential in order.  Commerce Invoices allows you to create moment-in-time snapshots of a order to create a invoice or credit invoice
 *
 * @link      wndr.digital
 * @copyright Copyright (c) 2021 Len van Essen
 */

namespace lenvanessen\commerce\invoices\services;

use Craft;
use craft\base\Component;
use craft\commerce\base\ShippingMethodInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use lenvanessen\commerce\invoices\elements\Invoice;
use lenvanessen\commerce\invoices\helpers\TaxExtractor;
use lenvanessen\commerce\invoices\records\InvoiceRow;
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

        foreach($order->lineItems as $lineItem) {
            $this->createFromLineItem($lineItem, $invoice);
        }

        // Process shipping
        $this->createFromShipping($order, $invoice);

        // Parse global order adjusters, taxes, discounts
        foreach ($order->getAdjustmentsByType('tax') as $adjustment) {
            if($adjustment->lineItemId) continue; //  Only non-line-item taxes here
            if($adjustment->amount == 0) continue; // Only actual values
            $row = new InvoiceRow();
            $row->invoiceId = $invoice->id;
            $row->qty = $invoice->isCredit ? -1 : 1;
            $row->description = $adjustment->name;
            $row->tax = $adjustment->amount;
            $row->price = $adjustment->included ? -$adjustment->amount : 0;

            $row->save();
        }

        return true;
    }

    /**
     * @param Order $order
     * @param Invoice $invoice
     * @return false
     */
    public function createFromShipping(Order $order, Invoice $invoice)
    {
        if(($shipping = $order->getTotalShippingCost()) == 0) {
            return false;
        }

        $row = new InvoiceRow();
        $row->invoiceId = $invoice->id;
        $row->qty = $invoice->isCredit ? -1 : 1;
        $row->description = Craft::t(
            'commerce-invoices',
            sprintf('Shipping costs %d', $order->id)
        );

        $row->tax = 0;
        $row->price = $shipping;

        foreach($order->getAdjustmentsByType('tax') as $adjuster) {
            if($source = $adjuster->getSourceSnapshot() && isset($source['taxable']) && $source['taxable'] === 'order_total_shipping') {
                $row->tax = $adjuster->amount;
                $row->price = $adjuster->included
                    ? $shipping - $adjuster->amount
                    : $row->price;
                break;
            }
        }

        return $row->save();
    }

    /**
     * @param LineItem $lineItem
     * @param Invoice $invoice
     * @return InvoiceRow
     */
    public function createFromLineItem(LineItem $lineItem, Invoice $invoice)
    {
        $row = new InvoiceRow();
        $row->lineItemId = $lineItem->id;
        $row->invoiceId = $invoice->id;
        $row->qty = $invoice->isCredit
            ? 0 - $lineItem->qty
            : $lineItem->qty;
        $row->description = $lineItem->description;

        $tax = new TaxExtractor($lineItem);

        $row->price = $tax->getUnitNet(); // plus discount(), but array filter discount on price value first
        $row->tax = $tax->getTaxUnit();

        $row->taxCategoryId = $lineItem->taxCategoryId;

        return $row->save();
    }

    /**
     * Populate the model based on shipping method
     *
     * @param ShippingMethodInterface $method
     * @param Order                   $order
     */
    public function shipping(ShippingMethodInterface $method, Order $order)
    {
        $tax_excluded = 0;
        $tax_included = 0;
        $shipping_base_price = 0;
        foreach ($order->getAdjustments() as $adjustment) {
            if($adjustment->type == 'shipping' && $adjustment->lineItemId == null) {
                $shipping_base_price += $adjustment->amount;
            }
            if(isset($adjustment->sourceSnapshot['taxable']) && $adjustment->sourceSnapshot['taxable'] == 'order_total_shipping') {
                if($adjustment->included == "1") $tax_included+=$adjustment->amount;
                else $tax_excluded+=$adjustment->amount;
            }
        }
        $this->unit_price = (int) (($shipping_base_price+$tax_excluded)*100);
        $this->quantity = 1;
        $this->name = $method->getName();
        $this->total_amount = (int) (($shipping_base_price+$tax_excluded)*100*$this->quantity);
        $this->total_tax_amount = (int) (($tax_included+$tax_excluded)*100);
        $this->tax_rate = 0;
        if($shipping_base_price-$tax_included > 0) $this->tax_rate = (int) round((($tax_excluded+$tax_included)/($shipping_base_price-$tax_included))*10000);
    }


    public function getAllRowsByInvoiceId(int $invoiceId)
    {
        return InvoiceRow::find()->where(['invoiceId' => $invoiceId])->all();
    }
}
