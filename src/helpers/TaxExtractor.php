<?php
/**
 * Commerce Invoices plugin for Craft CMS 3.x
 *
 * A pdf of an orders does not equal an invoice, invoices should be: Immutable, sequential in order.  Commerce Invoices allows you to create moment-in-time snapshots of a order to create a invoice or credit invoice
 *
 * @link      wndr.digital
 * @author    jellingsen
 * @copyright Copyright (c) 2021 Len van Essen
 */

namespace lenvanessen\commerce\invoices\helpers;

use craft\commerce\models\LineItem;

class TaxExtractor
{
    /**
     * Order Line from Constructor
     *
     * @var LineItem
     */
    private $line;

    /**
     * Tax included in the base price in
     * fractional denomination
     *
     * @var float|int
     */
    private $included;

    /**
     * Tax not included in the base price in
     * fractional denomination
     *
     * @var float|int
     */
    private $excluded;

    public function __construct(LineItem $line)
    {
        $this->line = $line;
        $this->included = $line->getTaxIncluded();
        $this->excluded = $line->getTax();
    }

    /**
     * Returns the total tax for the order line in
     * float
     *
     * @return float
     */
    public function getTaxTotal() : float
    {
        return $this->included + $this->excluded;
    }

    /**
     * Returns the tax rate in percent
     *
     * @return int
     */
    public function getTaxRate() : int
    {
        return (int)round(($this->getTaxTotal()/$this->getTotalNet())*100);
    }

    /**
     * Returns the tax for one unit from the
     * order line
     *
     * @return float
     */
    public function getTaxUnit() : float
    {
        return $this->getTaxTotal()/$this->line->qty;
    }

    /**
     * Returns the gross price for the
     * order line
     *
     * @return float
     */
    public function getTotalGross() : float
    {
        return $this->getTotalNet()+$this->getTaxTotal();
    }

    /**
     * Returns the gross price for one
     * unit from the order line
     *
     * @return int
     */
    public function getUnitGross() : int
    {
        return $this->getTotalGross()/$this->line->qty;
    }

    /**
     * Returns the net price for the
     * order line
     *
     * @return float
     */
    public function getTotalNet() : float
    {
        return $this->line->getTotal()-$this->getTaxTotal();
    }

    /**
     * Returns the net price for one
     * unit from the order line
     *
     * @return float
     */
    public function getUnitNet() : float
    {
        return $this->getTotalNet()/$this->line->qty;
    }

    public function debug() : array
    {
        return [
            'tax_included' => $this->included,
            'tax_excluded' => $this->excluded,
            'tax_rate' => $this->getTaxRate(),
            'line_total' => $this->line->getTotal(),
            'line_tax' => $this->getTaxUnit(),
            'line_net' => $this->getTotalNet(),
            'line_gross' => $this->getTotalGross()
        ];
    }
}