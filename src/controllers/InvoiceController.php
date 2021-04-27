<?php
/**
 * Commerce Invoices plugin for Craft CMS 3.x
 *
 * A pdf of an orders does not equal an invoice, invoices should be: Immutable, sequential in order.  Commerce Invoices allows you to create moment-in-time snapshots of a order to create a invoice or credit invoice
 *
 * @link      wndr.digital
 * @copyright Copyright (c) 2021 Len van Essen
 */
namespace lenvanessen\commerce\invoices\controllers;

use Craft;
use craft\web\Controller;
use lenvanessen\commerce\invoices\assetbundles\invoicescpsection\InvoicesCPSectionAsset;
use lenvanessen\commerce\invoices\CommerceInvoices;
use lenvanessen\commerce\invoices\elements\Invoice;
use lenvanessen\commerce\invoices\records\InvoiceRow;
use craft\commerce\Plugin as Commerce;
use yii\web\UnauthorizedHttpException;

/**
 * @author    Len van Essen
 * @package   CommerceInvoices
 * @since     1.0.0
 */
class InvoiceController extends Controller
{
    /**
     * @param $invoiceId
     * @return \yii\web\Response
     * @throws UnauthorizedHttpException
     * @throws \Throwable
     * @throws \craft\errors\ElementNotFoundException
     * @throws \craft\errors\MissingComponentException
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\StaleObjectException
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionEdit($invoiceId)
    {
        if(! Craft::$app->getUser()->getIdentity()->can('accessCp')) {
            throw new UnauthorizedHttpException('Not allowed');
        }

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

        $invoice->sent = (bool)$request->getBodyParam('send');
        $invoice->restock = (bool)$request->getBodyParam('restock');
        Craft::$app->getElements()->saveElement($invoice);

        // If we have a e-mail for this specific order, send it
        $mailSettingName = "{$invoice->type}EmailId";
        $mailId = CommerceInvoices::getInstance()->getSettings()->{$mailSettingName};
        if($mailId !== 0 && $invoice->sent === true) {
            $emailService = Commerce::getInstance()->getEmails();
            $mail = $emailService->getEmailById((int)$mailId);

            if($mail) {
                $emailService->sendEmail($mail, $invoice->order(), null, ['invoiceId' => $invoice->id]);
            }
        }

        Craft::$app->getSession()->setNotice(sprintf("Updated invoice %s", $invoice->invoiceNumber));

        return $this->redirectToPostedUrl();
    }

    /**
     * @param $invoiceId
     * @return false
     * @throws UnauthorizedHttpException
     * @throws \yii\base\Exception
     */
    public function actionDownload($invoiceId)
    {
        if(! Craft::$app->getUser()->getIdentity()->can('accessCp')) {
            throw new UnauthorizedHttpException('Not allowed');
        }

        $invoice = Invoice::find()->uid($invoiceId)->one();

        $renderedPdf = Commerce::getInstance()->getPdfs()->renderPdfForOrder(
            $invoice->order(),
            '',
            CommerceInvoices::getInstance()->getSettings()->pdfPath,
            [
                'invoice' => $invoice
            ]
        );

        return Craft::$app->getResponse()->sendContentAsFile($renderedPdf, $invoice->invoiceNumber . '.pdf', [
            'mimeType' => 'application/pdf'
        ]);
    }

    /**
     * @return \yii\web\Response
     * @throws UnauthorizedHttpException
     */
    public function actionTest()
    {
        if(! Craft::$app->getUser()->getIdentity()->can('accessCp')) {
            throw new UnauthorizedHttpException('Not allowed');
        }

        $invoiceId = Craft::$app->getRequest()->get('invoiceId');

        return $this->renderTemplate(
            CommerceInvoices::getInstance()->getSettings()->pdfPath,
            ['invoice' => Invoice::findOne($invoiceId)]
        );
    }
}