<?php
/**
 * Commerce Invoices plugin for Craft CMS 3.x
 *
 * A pdf of an orders does not equal an invoice, invoices should be: Immutable, sequential in order.  Commerce Invoices allows you to create moment-in-time snapshots of a order to create a invoice or credit invoice
 *
 * @link      wndr.digital
 * @copyright Copyright (c) 2021 Len van Essen
 */

namespace lenvanessen\commerceinvoices\assetbundles\invoicescpsection;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * @author    Len van Essen
 * @package   CommerceInvoices
 * @since     1.0.0
 */
class InvoicesCPSectionAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->sourcePath = "@lenvanessen/commerceinvoices/assetbundles/invoicescpsection/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/Invoices.js',
        ];

        $this->css = [
            'css/Invoices.css',
        ];

        parent::init();
    }
}
