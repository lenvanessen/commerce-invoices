<?php
/**
 * Commerce Invoices plugin for Craft CMS 3.x
 *
 * A pdf of an orders does not equal an invoice, invoices should be: Immutable, sequential in order.  Commerce Invoices allows you to create moment-in-time snapshots of a order to create a invoice or credit invoice
 *
 * @link      wndr.digital
 * @copyright Copyright (c) 2021 Len van Essen
 */

namespace lenvanessen\commerce\invoices\variables;

use lenvanessen\commerce\invoices\elements\Invoice;

/**
 * @author    Len van Essen
 * @package   CommerceInvoices
 * @since     1.0.0
 */
class InvoiceVariable
{
    public function find()
    {
        return Invoice::find();
    }
}
