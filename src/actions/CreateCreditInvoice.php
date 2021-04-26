<?php

namespace lenvanessen\commerce\invoices\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use lenvanessen\commerce\invoices\CommerceInvoices;

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

        $sessionService = Craft::$app->getSession();
        foreach ($query->all() as $order) {
            CommerceInvoices::getInstance()->invoices->createFromOrder($order, 'credit')
                ? $sessionService->setNotice(Craft::t('commerce-invoices', sprintf('Invoice created successfully for order: #%d', $order->id)))
                : $sessionService->setError(     sprintf("Invoice for order %d was not created successfully, check logs", $order->id));
        }

        return true;
    }
}
