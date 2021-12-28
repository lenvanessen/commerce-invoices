<?php

namespace lenvanessen\commerce\invoices\models;

class FakePdf
{
    public function getRenderLanguage($order)
    {
        return \Craft::$app->getSites()->getPrimarySite()->language;
    }
}