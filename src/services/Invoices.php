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
use craft\commerce\elements\Order;
use lenvanessen\commerce\invoices\CommerceInvoices;
use lenvanessen\commerce\invoices\elements\Invoice;
use putyourlightson\logtofile\LogToFile;

/**
 * @author    Len van Essen
 * @package   CommerceInvoices
 * @since     1.0.0
 */
class Invoices extends Component
{
    public function createFromOrder(Order $order, $type = 'invoice'): bool
    {
        if(! $order->isCompleted) {
            LogToFile::log(sprintf("Order %d was skipped, because it has not yet been completed", $order->id), 'commerce-invoices', 'error');

            return false;
        }

        if(Invoice::find()->orderId($order->id)->type($type)->exists()) {
            LogToFile::log(sprintf("Order %d was skipped, because an invoice already exists", $order->id), 'commerce-invoices', 'error');

            return false;
        }

        $invoice = new Invoice;
        $invoice->orderId = $order->id;

        $invoice->invoiceId = (Invoice::find()->orderBy('invoiceId desc')->type($type)->one()->invoiceId ?? 0)+1;
        $invoice->invoiceNumber = Craft::$app->getView()->renderObjectTemplate(CommerceInvoices::getInstance()->getSettings()->invoiceNumberFormat, $invoice);
        $invoice->billingAddressSnapshot = $order->billingAddress->toArray();
        $invoice->shippingAddressSnapshot = $order->shippingAddress->toArray();
        $invoice->email = $order->email;
        $invoice->type = $type;
        $invoice->sent = !$invoice->getIsCredit(); // Send invoices by default

        if(! Craft::$app->getElements()->saveElement($invoice)) {

            foreach($invoice->getErrors() as $error) {
                LogToFile::log(sprintf("Could not save order %d because: %s", $order->id, $error), 'commerce-invoices', 'error');
            }

            return false;
        }

        return CommerceInvoices::getInstance()->invoiceRows->createFromOrder($order, $invoice);
    }
}
