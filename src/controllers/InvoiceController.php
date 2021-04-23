<?php
/**
 * Commerce Invoices plugin for Craft CMS 3.x
 *
 * A pdf of an orders does not equal an invoice, invoices should be: Immutable, sequential in order.  Commerce Invoices allows you to create moment-in-time snapshots of a order to create a invoice or credit invoice
 *
 * @link      wndr.digital
 * @copyright Copyright (c) 2021 Len van Essen
 */
namespace lenvanessen\commerceinvoices\controllers;

use Craft;
use craft\web\Controller;
use lenvanessen\commerceinvoices\assetbundles\invoicescpsection\InvoicesCPSectionAsset;
use lenvanessen\commerceinvoices\CommerceInvoices;
use lenvanessen\commerceinvoices\elements\Invoice;
use lenvanessen\commerceinvoices\records\InvoiceRow;
use yii\web\UnauthorizedHttpException;

/**
 * @author    Len van Essen
 * @package   CommerceInvoices
 * @since     1.0.0
 */
class InvoiceController extends Controller
{
    public function actionEdit($invoiceId)
    {
        $request = Craft::$app->getRequest();
        $invoice = Invoice::findOne($invoiceId);

        if(! $request->isPost) {
            Craft::$app->getView()->registerAssetBundle(InvoicesCPSectionAsset::class);

            return $this->renderTemplate('commerce-invoices/invoice/edit', [
                'invoice' => $invoice,
                'rows' => InvoiceRow::find()->where(['invoiceId' => $invoice->id])->all()
            ]);
        }

        if($invoice->getEditable() === false) {
            throw new UnauthorizedHttpException('Trying to edit a non-editable invoice');
        }

        if($request->getBodyParam('reset')) {
            CommerceInvoices::getInstance()->invoiceRows->createFromOrder($invoice->order(), $invoice);
            return $this->redirectToPostedUrl();
        }

        foreach($request->getBodyParam('rows') as $rowId => $data) {
            $row = InvoiceRow::findOne($rowId);
            $qty = (int)$data['qty'];

            if($qty === 0) {
                $row->delete();
                continue;
            }

            $row->qty = $qty;
            $row->save();
        }

        $invoice->sent = $request->getBodyParam('send');
        $invoice->restock = (bool)$request->getBodyParam('restock');
        Craft::$app->getElements()->saveElement($invoice);

        // TODO restock

        Craft::$app->getSession()->setNotice(sprintf("Updated invoice %s", $invoice->invoiceNumber));

        return $this->redirectToPostedUrl();
    }
}