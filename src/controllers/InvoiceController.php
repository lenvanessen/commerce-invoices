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
use craft\commerce\elements\Variant;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\web\Controller;
use craft\commerce\elements\Order;
use lenvanessen\commerce\invoices\assetbundles\invoicescpsection\InvoicesCPSectionAsset;
use lenvanessen\commerce\invoices\CommerceInvoices;
use lenvanessen\commerce\invoices\elements\Invoice;
use lenvanessen\commerce\invoices\helpers\Stock;
use lenvanessen\commerce\invoices\models\FakePdf;
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

        if($invoice->sent && $invoice->restock) {
            foreach($invoice->rows as $row) {
                $lineItem = $row->lineItem;
                if(!$lineItem || !Stock::isRestockableLineItem($lineItem)) continue;
                $purchasable = Variant::findOne($lineItem->purchasableId);
                $purchasable->stock = $purchasable->stock += abs($row->qty);

                Craft::$app->getElements()->saveElement($purchasable);
            }
        }

        if($invoice->sent && $invoice->type === 'credit') {
            $order = $invoice->order();
            $order->orderStatusId = Plugin::getInstance()->orderStatuses->getOrderStatusByHandle('refunded')->id;
            Craft::$app->getElements()->saveElement($order);
        }

        CommerceInvoices::getInstance()->emails->sendInvoiceEmails($invoice);

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
        if(! $currentUser = Craft::$app->getUser()->getIdentity()) {
            throw new UnauthorizedHttpException('Not allowed');
        }

        $invoice = Invoice::find()->uid($invoiceId)->one();
        if(!$currentUser->can('accessCp') && $invoice->order()->user && $invoice->order()->user->id !== $currentUser->id) {
            throw new UnauthorizedHttpException('Not allowed');
        }

        $renderedPdf = Commerce::getInstance()->getPdfs()->renderPdfForOrder(
            $invoice->order(),
            '',
            CommerceInvoices::getInstance()->getSettings()->pdfPath,
            [
                'invoice' => $invoice
            ],
            new FakePdf()
        );

        return Craft::$app->getResponse()->sendContentAsFile($renderedPdf, $invoice->invoiceNumber . '.pdf', [
            'mimeType' => 'application/pdf'
        ]);
    }

    /**
     *
     */
    public function actionCreate()
    {
        $orderId = $this->request->getParam('orderId');
        $order = Order::findOne($orderId);

        $invoice = CommerceInvoices::getInstance()->invoices->createFromOrder($order, $this->request->getParam('type'));

        return $this->redirect($invoice->getCpEditUrl());
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

    public function actionSend()
    {

        if(! Craft::$app->getUser()->getIdentity()->can('accessCp')) {
            throw new UnauthorizedHttpException('Not allowed');
        }

        $orderId = Craft::$app->getRequest()->get('orderId');
        $order = Order::find()->id($orderId)->one();

        CommerceInvoices::getInstance()->invoices->createFromorder($order);
    }
}