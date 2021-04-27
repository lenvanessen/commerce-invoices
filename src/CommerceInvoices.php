<?php
/**
 * Commerce Invoices plugin for Craft CMS 3.x
 *
 * A pdf of an orders does not equal an invoice, invoices should be: Immutable, sequential in order.  Commerce Invoices allows you to create moment-in-time snapshots of a order to create a invoice or credit invoice
 *
 * @link      wndr.digital
 * @copyright Copyright (c) 2021 Len van Essen
 */

namespace lenvanessen\commerce\invoices;


use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\commerce\elements\Order;
use craft\commerce\events\EmailEvent;
use craft\commerce\events\MailEvent;
use craft\commerce\events\OrderStatusEvent;
use craft\commerce\models\Pdf;
use craft\commerce\services\Emails;
use craft\commerce\services\OrderHistories;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\ArrayHelper;
use craft\commerce\Plugin as Commerce;

use craft\helpers\Assets;
use craft\web\UrlManager;
use lenvanessen\commerce\invoices\actions\CreateCreditInvoice;
use lenvanessen\commerce\invoices\actions\CreateInvoice;
use lenvanessen\commerce\invoices\elements\Invoice;
use lenvanessen\commerce\invoices\models\Settings;
use lenvanessen\commerce\invoices\services\InvoiceRows;
use lenvanessen\commerce\invoices\services\Invoices;
use modules\sitemodule\jobs\ExactOrderExport;
use yii\base\BaseObject;
use yii\base\Event;

/**
 * Class CommerceInvoices
 *
 * @author    Len van Essen
 * @package   CommerceInvoices
 * @since     1.0.0
 *
 */
class CommerceInvoices extends Plugin
{
    /**
     * @var CommerceInvoices
     */
    public static $plugin;

    /**
     * @var string
     */
    public $schemaVersion = '1.0.1';

    /**
     * @var bool
     */
    public $hasCpSettings = true;

    /**
     * @var bool
     */
    public $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->_registerOrderActions();
        $this->_registerComponents();
        $this->_registerRoutes();
        $this->_creatInvoiceOnOrderStatusChange();
        $this->_attachPdfsToEmails();
    }

    private function _attachPdfsToEmails()
    {
        Event::on(
            Emails::class,
            Emails::EVENT_BEFORE_SEND_MAIL,
            function(MailEvent $event) {
                if(isset($event->orderData['invoiceId'])) {
                    $this->emails->attachInvoiceToMail($event);
                }
            }
        );
    }

    private function _registerComponents(): void
    {
        $this->setComponents([
            'invoices' => [
                'class' => Invoices::class,
            ],
            'invoiceRows' => [
                'class' => InvoiceRows::class
            ],
            'emails' => [
                'class' => Emails::class
            ]
        ]);
    }

    private function _creatInvoiceOnOrderStatusChange()
    {
        Event::on(
            OrderHistories::class,
            OrderHistories::EVENT_ORDER_STATUS_CHANGE,
            function (OrderStatusEvent $event) {
                // @var Order $order
                $order = $event->order;

                if($order->orderStatusId === (int)$this->getSettings()->automaticallyCreateOrderStatusId) {
                    $this->invoices->createFromOrder($order);
                }
            }
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function createSettingsModel()
    {
        return new Settings();
    }

    /**
     * {@inheritDoc}
     */
    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'commerce-invoices/settings',
            [
                'settings' => $this->getSettings(),
                'orderStatuses' => ArrayHelper::map(
                    Commerce::getInstance()->orderStatuses->getAllOrderStatuses(),
                    'id',
                    'name'
                ),
                'emails' => ArrayHelper::map(
                    Commerce::getInstance()->emails->getAllEmails(),
                    'id',
                    'name'
                )
            ]
        );
    }

    public function getCpNavItem()
    {
        $parent = parent::getCpNavItem();
        $parent['label'] = Craft::t('commerce-invoices', 'Invoices');

        $subnav = [
            'commerceInvoices' => [
                'label' => Craft::t('commerce-invoices', 'Invoices'),
                'url'   => 'commerce-invoices'
            ]
        ];

        if(Craft::$app->getConfig()->general->allowAdminChanges) {
            $subnav['invoiceSettings'] = [
                'label' => Craft::t('commerce-invoices', 'Settings'),
                'url'   => 'settings/plugins/commerce-invoices'
            ];
        }
        return array_merge($parent,[
            'subnav' => $subnav
        ]);
    }

    private function _registerOrderActions()
    {
        Event::on(
            Order::class,
            Element::EVENT_REGISTER_ACTIONS,
            function (RegisterElementActionsEvent $event) {
                $event->actions[] = CreateInvoice::class;
                $event->actions[] = CreateCreditInvoice::class;
            });
    }

    private function _registerRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['commerce-invoices/<invoiceId:\d+>'] = 'commerce-invoices/invoice/edit';
                $event->rules['commerce-invoices/download/<invoiceId:{uid}>'] = 'commerce-invoices/invoice/download';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                if (getenv('ENVIRONMENT') !== 'production') {
                    $event->rules['commerce-invoices/style-pdf'] = 'commerce-invoices/invoice/test';
                }
            }
        );
    }
}
