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
use craft\commerce\events\MailEvent;
use craft\commerce\events\OrderStatusEvent;
use craft\commerce\services\Emails;
use craft\commerce\services\OrderHistories;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\ArrayHelper;
use craft\commerce\Plugin as Commerce;

use craft\helpers\UrlHelper;
use craft\web\twig\variables\CraftVariable;
use craft\web\UrlManager;
use lenvanessen\commerce\invoices\actions\CreateCreditInvoice;
use lenvanessen\commerce\invoices\actions\CreateInvoice;
use lenvanessen\commerce\invoices\elements\Invoice;
use lenvanessen\commerce\invoices\models\Settings;
use lenvanessen\commerce\invoices\services\InvoiceRows;
use lenvanessen\commerce\invoices\services\Invoices;
use lenvanessen\commerce\invoices\services\Emails as InternalMailService;
use lenvanessen\commerce\invoices\variables\InvoiceVariable;
use yii\base\Event;
use yii\base\ModelEvent;

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
    public string $schemaVersion = '1.0.1';

    /**
     * @var bool
     */
    public bool $hasCpSettings = true;

    /**
     * @var bool
     */
    public bool $hasCpSection = true;

    public function __construct($id, $parent = null, array $config = [])
    {
        $this->_registerRoutes();

        parent::__construct($id, $parent, $config);
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        $this->_registerComponents();
        $this->_registerRoutes();
        $this->_attachPdfsToEmails();
        $this->_creatInvoiceOnOrderStatusChange();
        $this->_registerVariables();
        $this->_injectOrderActions();
    }

    private function _injectOrderActions()
    {
        Craft::$app->view->hook('cp.commerce.order.edit.order-secondary-actions', function(array &$context) {
            $order = $context['order'];
            if(! $order->id || Invoice::find()->orderId($order->id)->type('credit')->exists() || ! $order->isCompleted) {
                return '';
            }

            $html = '<style>#order-secondary-actions{display:flex;}</style>';
            $html .= '<div class="spacer"></div><a href="'. UrlHelper::cpUrl('commerce-invoices/create?orderId='.$order->id).'&type=credit" type="button" class="btn submit">Credit invoice</a>';

            return $html;
        });
    }

    private function _attachPdfsToEmails()
    {
        Event::on(
            Emails::class,
            Emails::EVENT_BEFORE_SEND_MAIL,
            function(MailEvent $event) {

                if(isset($event->orderData['invoiceId']) && $invoice = Invoice::findOne($event->orderData['invoiceId'])) {
                    $this->emails->attachInvoiceToMail($event, $invoice);
                    return;
                }

                // Or add them recursively
                $invoices = Invoice::find()->orderId($event->order->id)->all();

                foreach($invoices as $invoice) {
                    $mailSettingName = "{$invoice->type}EmailId";
                    $mailId = CommerceInvoices::getInstance()->getSettings()->{$mailSettingName};

                    if($mailId === $event->commerceEmail->id && $invoice->sent == true) {
                        $this->emails->attachInvoiceToMail($event, $invoice);
                    }
                }
            }
        );
    }

    private function _registerVariables(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('invoices', InvoiceVariable::class);
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
                'class' => InternalMailService::class
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
    protected function createSettingsModel(): ?\craft\base\Model
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

    public function getCpNavItem(): ?array
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

    private function _registerRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['commerce-invoices/<invoiceId:\d+>'] = 'commerce-invoices/invoice/edit';
                $event->rules['commerce-invoices/create'] = 'commerce-invoices/invoice/create';
            }
        );

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_SITE_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['commerce-invoices/download/<invoiceId:{uid}>'] = 'commerce-invoices/invoice/download';
                if (getenv('ENVIRONMENT') !== 'production') {
                    $event->rules['commerce-invoices/style-pdf'] = 'commerce-invoices/invoice/test';
                    $event->rules['test-send'] = 'commerce-invoices/invoice/send';
                }
            }
        );
    }
}
