<?php

/**
 * Commerce Invoices plugin for Craft CMS 3.x
 *
 * A pdf of an orders does not equal an invoice, invoices should be: Immutable, sequential in order.  Commerce Invoices allows you to create moment-in-time snapshots of a order to create a invoice or credit invoice
 *
 * @link      wndr.digital
 * @copyright Copyright (c) 2021 Len van Essen
 */

namespace lenvanessen\commerce\invoices\helpers;

use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\records\LineItem;

class Stock
{
    /**
     * @param LineItem $lineItem
     * @author yoannisj
     * @return bool
     */
    public static function isRestockableLineItem(LineItem $lineItem) : bool
    {
        if ($lineItem->qty == 0) {
            return false;
        }


        if(! $purchasable = Variant::findOne($lineItem->purchasableId)) {
            return false;
        }

        if ($purchasable->canGetProperty('hasUnlimitedStock')
            && $purchasable->hasUnlimitedStock
        ) {
            return false;
        }


        if ($purchasable->canGetProperty('stock')) {
            return true;
        }

        return false;
    }
}