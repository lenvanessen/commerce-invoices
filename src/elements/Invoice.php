<?php
/**
 * Commerce Invoices plugin for Craft CMS 3.x
 *
 * A pdf of an orders does not equal an invoice, invoices should be: Immutable, sequential in order.  Commerce Invoices allows you to create moment-in-time snapshots of a order to create a invoice or credit invoice
 *
 * @link      wndr.digital
 * @copyright Copyright (c) 2021 Len van Essen
 */
namespace lenvanessen\commerce\invoices\elements;

use Craft;
use craft\base\Element;
use craft\commerce\models\LineItem;
use craft\commerce\Plugin;
use craft\helpers\Json;
use craft\helpers\Template;
use craft\elements\Asset;
use craft\elements\db\ElementQueryInterface;
use craft\commerce\elements\Order;

use craft\helpers\UrlHelper;
use lenvanessen\commerce\invoices\CommerceInvoices;
use lenvanessen\commerce\invoices\records\Invoice as InvoiceRecord;
use lenvanessen\commerce\invoices\elements\db\InvoiceElementsQuery;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * @author    Len van Essen
 * @package   CommerceInvoices
 * @since     1.0.0
 */
class Invoice extends Element
{
    const TYPE_CREDIT = 'credit';
    const TYPE_INVOICE = 'invoice';

    /**
     * Order ID in Craft Commerce
     *
     * @var int
     */
    public $orderId;

    /**
     * The sequential invoice ID
     *
     * @var int
     */
    public $invoiceId;

    /**
     * @var int Invoice Number
     */
    public $invoiceNumber;

    /**
     * Snapshot of the billing address
     *
     * @var array
     */
    public $billingAddressSnapshot;

    /**
     * Snapshot of the shipping address
     *
     * @var array
     */
    public $shippingAddressSnapshot;

    /**
     * The e-mail for sending the invoice to
     *
     * @var string
     */
    public $email;

    /**
     * The type of invoice (credit|invoice)
     * @var string
     */
    public $type;

    /**
     * Was the invoice sent
     * @var bool
     */
    public $sent;

    /**
     * Decides if the stock should be reset
     *
     * @var bool
     */
    public $restock;

    /**
     * @var string
     */
    public $externalId;

    /**
     * @var array
     */
    private $_rows;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('commerce-invoices', 'Invoice');
    }

    /**
     * @inheritdoc
     */
    public static function pluralDisplayName(): string
    {
        return Craft::t('commerce-invoices', 'Invoices');
    }

    /**
     * @inheritdoc
     */
    public static function hasContent(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasTitles(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasUris(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function isLocalized(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function hasStatuses(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public static function find(): ElementQueryInterface
    {
        return new InvoiceElementsQuery(static::class);
    }

    public function billingAddress()
    {
        return Json::decodeIfJson($this->billingAddressSnapshot);
    }

    public function shippingAddress()
    {
        return Json::decodeIfJson($this->shippingAddressSnapshot);
    }

    public function getPdfUrl()
    {
        return UrlHelper::cpUrl('commerce-invoices/download/'.$this->uid);
    }

    /**
     * @return LineItem[]
     */
    public function getRows(): array
    {
        if ($this->_rows === null) {
            $rows = $this->id ? CommerceInvoices::getInstance()->invoiceRows->getAllRowsByInvoiceId($this->id) : [];

            $this->_rows = $rows;
        }

        return $this->_rows;
    }

    public function getEditable(): bool
    {
        if(!$this->getIsCredit()) {
            return false;
        }

        if($this->sent) {
            return false;
        }

        return true;
    }

    public function order(): Order
    {
        return Order::findOne($this->orderId);
    }

    public function getIsCredit(): bool
    {
        return $this->type === self::TYPE_CREDIT;
    }

    /**
     * @return float
     */
    public function subTotal() : float
    {
        return $this->_rows
            ? array_reduce($this->_rows, fn($amount, $current) => $amount += $current->subTotal())
            : 0;
    }

    /**
     * @return float
     */
    public function totalTax() : float
    {
        return $this->_rows
            ? array_reduce($this->_rows, fn($amount, $current) => $amount += $current->subTotalTax())
            : 0;
    }

    /**
     * @return float
     */
    public function total(): float
    {
        return $this->totalTax() + $this->subTotal();
    }

    /**
     * @inheritdoc
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => 'invoices',
                'label' => Craft::t('commerce-invoices', 'Invoices'),
                'criteria' => [
                    'type' => self::TYPE_INVOICE
                ],
                'hasThumbs' => false
            ],
            [
                'key' => 'credit',
                'label' => Craft::t('commerce-invoices', 'Credit invoices'),
                'criteria' => [
                    'type' => self::TYPE_CREDIT
                ],
                'hasThumbs' => false
            ],
        ];

        return $sources;
    }

    /**
     * @inheritdoc
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        return $actions;
    }

    /**
     * @inheritdoc
     */
    protected static function defineTableAttributes(): array
    {
        $attributes = [
            'invoiceNumber' => ['label' => Craft::t('commerce-invoices', 'Invoice Number')],
            'orderId' => ['label' => Craft::t('commerce', 'Order ID')],
            'dateCreated' => ['label' => Craft::t('commerce-invoices', 'Invoice Date')],
        ];

        return $attributes;
    }

    protected static function defineSearchableAttributes(): array
    {
        return ['orderId', 'invoiceNumber', 'email'];
    }

    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'orderId':
                $order = Order::find()
                    ->id($this->orderId)
                    ->one();

                if ($order) {
                    return Template::raw("<a href='{$order->getCpEditUrl()}'>{$this->orderId}</a>");
                }

                return '-';
            case 'dateCreated':
                if ($this->dateCreated) {
                    return Craft::$app->formatter->asDate($this->dateCreated);
                }
        }

        return parent::tableAttributeHtml($attribute);
    }

    /**
     * @inheritdoc
     */
    public function extraFields(): array
    {
        $names = parent::extraFields();

        $names[] = 'orderId';
        $names[] = 'invoiceNumber';
        $names[] = 'dateCreated';

        return $names;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = [];

        $rules[] = [
            [
                'orderId',
                'invoiceId',
            ],
            'number',
            'integerOnly' => true
        ];

        return $rules;
    }

    /**
     * @inheritdoc
     */
    public function getUiLabel(): string
    {
        return (string) $this->invoiceNumber;
    }

    /**
     * @inheritdoc
     */
    public function getIsEditable(): bool
    {
        return false;
    }

    public function getCpEditUrl()
    {
        return 'commerce-invoices/'.$this->id;
    }

    /**
     * @inheritdoc
     */
    public function getFieldLayout()
    {
        return Craft::$app->fields->getLayoutByType(Invoice::class);
    }

    /**
     * @inheritdoc
     */
    public function getGroup()
    {
        if ($this->groupId === null) {
            throw new InvalidConfigException('Tag is missing its group ID');
        }

        if (($group = Craft::$app->getTags()->getTagGroupById($this->groupId)) === null) {
            throw new InvalidConfigException('Invalid tag group ID: '.$this->groupId);
        }

        return $group;
    }

    /**
     * @inheritdoc
     */
    public function getEditorHtml(): string
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function afterSave(bool $isNew)
    {
        if (!$isNew) {
            $record = InvoiceRecord::findOne($this->id);

            if (!$record) {
                throw new Exception('Invalid Invoice Element ID: ' . $this->id);
            }
        } else {
            $record = new InvoiceRecord();
            $record->id = $this->id;
        }

        $record->orderId = $this->orderId;
        $record->invoiceNumber = $this->invoiceNumber;
        $record->invoiceId = $this->invoiceId;
        $record->billingAddressSnapshot = $this->billingAddressSnapshot;
        $record->shippingAddressSnapshot = $this->shippingAddressSnapshot;
        $record->email = $this->email;
        $record->type = $this->type;
        $record->sent = $this->sent;
        $record->restock = $this->restock;

        $record->save(false);

        $this->id = $record->id;

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function beforeDelete(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function afterDelete()
    {
    }

    /**
     * @inheritdoc
     */
    public function beforeMoveInStructure(int $structureId): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function afterMoveInStructure(int $structureId)
    {
    }
}