<?php
/**
 * Commerce Invoices plugin for Craft CMS 3.x
 *
 * A pdf of an orders does not equal an invoice, invoices should be: Immutable, sequential in order.  Commerce Invoices allows you to create moment-in-time snapshots of a order to create a invoice or credit invoice
 *
 * @link      wndr.digital
 * @copyright Copyright (c) 2021 Len van Essen
 */

namespace lenvanessen\commerce\invoices\services;

use craft\commerce\events\MailEvent;
use craft\helpers\Assets;
use craft\commerce\Plugin as Commerce;
use lenvanessen\commerce\invoices\CommerceInvoices;
use lenvanessen\commerce\invoices\elements\Invoice;
use lenvanessen\commerce\invoices\models\FakePdf;
use putyourlightson\logtofile\LogToFile;

/**
* @author    Len van Essen
* @package   CommerceInvoices
* @since     1.0.0
*/
class Emails
{
    /**
     * Attaches a invoice to it's designated email
     *
     * @param MailEvent $event
     * @return false
     */
    public function attachInvoiceToMail(MailEvent $event, Invoice $invoice)
    {
        $renderedPdf = Commerce::getInstance()->getPdfs()->renderPdfForOrder(
            $event->order,
            'email',
            CommerceInvoices::getInstance()->getSettings()->pdfPath,
            [
                'invoice' => $invoice
            ],
            new FakePdf()
        );

        $tempPath = Assets::tempFilePath('pdf');

        file_put_contents($tempPath, $renderedPdf);

        // Attachment information
        $options = ['fileName' => $invoice->invoiceNumber . '.pdf', 'contentType' => 'application/pdf'];
        $event->craftEmail->attach($tempPath, $options);
    }

    /**
     * @param Invoice $invoice
     * @throws \Throwable
     * @throws \yii\base\Exception
     * @throws \yii\base\InvalidConfigException
     */
    public function sendInvoiceEmails(Invoice $invoice)
    {
        // If we have a e-mail for this specific order, send it
        $mailSettingName = "{$invoice->type}EmailId";
        $mailId = CommerceInvoices::getInstance()->getSettings()->{$mailSettingName};

        LogToFile::log(sprintf("Sending emails for invoice %d", $invoice->id), 'commerce-invoices');
        if($mailId !== 0 && $invoice->sent == true) {
            $emailService = Commerce::getInstance()->getEmails();
            $mail = $emailService->getEmailById((int)$mailId);

            LogToFile::log(sprintf("send conditions passed %d", $invoice->id), 'commerce-invoices');
            if($mail) {
                LogToFile::log(sprintf("triggering emailservice to send mail for invoice %d", $invoice->id), 'commerce-invoices');
                $emailService->sendEmail($mail, $invoice->order(), null, ['invoiceId' => $invoice->id]);
            }
        }
    }
}