<?php
/**
 * Commerce Invoices plugin for Craft CMS 3.x
 *
 * A pdf of an orders does not equal an invoice, invoices should be: Immutable, sequential in order.  Commerce Invoices allows you to create moment-in-time snapshots of a order to create a invoice or credit invoice
 *
 * @link      wndr.digital
 * @copyright Copyright (c) 2021 Len van Essen
 */

namespace lenvanessen\commerce\invoices\models;

use test\test\Test;

use Craft;
use craft\base\Model;

/**
 * Commerce Invoices Settings Model
 *
 * This is a model used to define the plugin's settings.
 *
 * Models are containers for data. Just about every time information is passed
 * between services, controllers, and templates in Craft, it’s passed via a model.
 *
 * https://craftcms.com/docs/plugins/models
 *
 * @author    Len van Essen
 * @package   CommerceInvoices
 * @since     1.0.0
 */
class Settings extends Model
{

    /**
     * @var string The Invoice Number Format
     */
    public $invoiceNumberFormat = "{{object.dateCompleted|date('Y')}}-{{'%05d'|format(object.invoiceId) }}";

    /**
     * Automatically generate an invoice when the order hits a certain status
     *
     * @var string
     */
    public $automaticallyCreateOrderStatusId = 0;

    /**
     * @var int
     */
    public $invoiceEmailId = 0;

    /**
     * @var int
     */
    public $creditEmailId = 0;

    /**
     * @var string
     */
    public $pdfPath;

    /**
     * Returns the validation rules for attributes.
     *
     * Validation rules are used by [[validate()]] to check if attribute values are valid.
     * Child classes may override this method to declare different validation rules.
     *
     * More info: http://www.yiiframework.com/doc-2.0/guide-input-validation.html
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            ['automaticallyCreateOrderStatusId', 'integer'],
            ['invoiceNumberFormat', 'string'],
            ['invoiceEmailId', 'integer'],
            ['creditEmailId', 'integer'],
        ];
    }
}
