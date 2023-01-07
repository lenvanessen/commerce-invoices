<?php

namespace lenvanessen\commerce\invoices\models;

use craft\commerce\elements\Order;
use craft\commerce\models\Pdf;
use lenvanessen\commerce\invoices\CommerceInvoices;

class FakePdf extends Pdf
{
    public function __construct($config = [])
    {
        $this->templatePath = CommerceInvoices::getInstance()->getSettings()->pdfPath;
        parent::__construct($config);
    }

    public function getRenderLanguage(Order $order = null): string
    {
        return \Craft::$app->getSites()->getPrimarySite()->language;
    }
}