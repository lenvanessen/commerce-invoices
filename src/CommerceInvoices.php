<?php
/**
 * Commerce Invoices plugin for Craft CMS 3.x
 *
 * A pdf of an orders does not equal an invoice, invoices should be: Immutable, sequential in order.  Commerce Invoices allows you to create moment-in-time snapshots of a order to create a invoice or credit invoice
 *
 * @link      wndr.digital
 * @copyright Copyright (c) 2021 Len van Essen
 */

namespace lenvanessen\commerceinvoices;


use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\commerce\elements\Order;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\ArrayHelper;
use craft\commerce\Plugin as Commerce;

use craft\web\UrlManager;
use lenvanessen\commerceinvoices\actions\CreateCreditInvoice;
use lenvanessen\commerceinvoices\actions\CreateInvoice;
use lenvanessen\commerceinvoices\elements\Invoice;
use lenvanessen\commerceinvoices\models\Settings;
use lenvanessen\commerceinvoices\services\InvoiceRows;
use lenvanessen\commerceinvoices\services\Invoices;
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
    public $schemaVersion = '1.0.0';

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
    }

    private function _registerComponents(): void
    {
        $this->setComponents([
            'invoices' => [
                'class' => Invoices::class,
            ],
            'invoiceRows' => [
                'class' => InvoiceRows::class
            ]
        ]);
    }

    private function _creatInvoiceOnOrderStatusChange()
    {
        // Todo implement
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
                )
            ]
        );
    }

    public function getCpNavItem()
    {
        $parent = parent::getCpNavItem();
        $parent['label'] = Craft::t('commerce-invoices', 'Invoices');

        return array_merge($parent,[
            'subnav' => [
                'sectionName' => [
                    'label' => Craft::t('commerce-invoices', 'Invoices'),
                    'url'   => 'commerce-invoices'
                ],
                'settings' => [
                    'label' => Craft::t('commerce-invoices', 'Settings'),
                    'url'   => 'settings/plugins/commerce-invoices'
                ]
            ]
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
            }
        );
    }
}
