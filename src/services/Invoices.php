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
use lenvanessen\commerceinvoices\CommerceInvoices;
use lenvanessen\commerceinvoices\elements\Invoice;

/**
 * @author    Len van Essen
 * @package   CommerceInvoices
 * @since     1.0.0
 */
class Invoices extends Component
{
    public function createFromOrder(Order $order, $type = 'invoice'): bool
    {
        $sessionService = Craft::$app->getSession();
        $sessionService->setNotice(Craft::t('commerce-invoices', sprintf('Invoice created successfully for order: #%d', $order->id)));

        if(! $order->isCompleted) {
            $sessionService->setError(
                Craft::t(
                    'commerce-invoices',
                    sprintf("Order %d was skipped, because it has not yet been completed", $order->id)
                )
            );

            return false;
        }

        if(Invoice::find()->orderId($order->id)->type($type)->exists()) {
            $sessionService->setError(
                Craft::t(
                    'commerce-invoices',
                    sprintf("Order %d was skipped, because an invoice already exists", $order->id)
                )
            );

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

        if(! Craft::$app->getElements()->saveElement($invoice)) {
            // TODO doesn't support multiple errors
            foreach($invoice->getErrors() as $error) {
                $sessionService->setError(
                    sprintf("Could not save order %d because: %s", $order->id, $error)
                );
            }

            return false;
        }

        CommerceInvoices::getInstance()->invoiceRows->createFromOrder($order, $invoice);

        $sessionService->setNotice(Craft::t('commerce-invoices', sprintf('Invoice created successfully for order: #%d', $order->id)));

        return true;
    }
}
