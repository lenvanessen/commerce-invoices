<?php

namespace lenvanessen\commerceinvoices\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use lenvanessen\commerceinvoices\CommerceInvoices;

/**
 * @author    Len van Essen
 * @package   CommerceInvoices
 * @since     1.0.0
 */
class CreateCreditInvoice extends ElementAction
{
    public static function displayName(): string
    {
        return Craft::t('commerce-invoices', 'Create Credit Invoice');
    }

    /**
     * @inheritdoc
     */
    public function performAction(ElementQueryInterface $query = null): bool
    {
        if (!$query) {
            return false;
        }

        foreach ($query->all() as $order) {
            CommerceInvoices::getInstance()->invoices->createFromOrder($order, 'credit');
        }

        return true;
    }
}
