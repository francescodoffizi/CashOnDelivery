<?php
/**
 * IDEALIAGroup srl
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@idealiagroup.com so we can send you a copy immediately.
 *
 * @category   MSP
 * @package    MSP_CashOnDelivery
 * @copyright  Copyright (c) 2016 IDEALIAGroup srl (http://www.idealiagroup.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace MSP\CashOnDelivery\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Tax\Api\TaxCalculationInterface;
use Magento\Tax\Helper\Data as TaxHelper;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use MSP\CashOnDelivery\Api\CashondeliveryInterface;
use MSP\CashOnDelivery\Api\CashondeliveryTableInterface;

class Cashondelivery implements CashondeliveryInterface
{
    protected $scopeConfigInterface;
    protected $cashondeliveryDetailsInterface;
    protected $objectManagerInterface;
    protected $cashondeliveryTableInterface;
    protected $taxHelper;
    protected $taxCalculation;
		protected $priceCurrencyInterface;

    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        ObjectManagerInterface $objectManagerInterface,
        CashondeliveryTableInterface $cashondeliveryTableInterface,
        TaxHelper $taxHelper,
        TaxCalculationInterface $taxCalculationInterface,
				PriceCurrencyInterface $priceCurrencyInterface
    ) {
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->objectManagerInterface = $objectManagerInterface;
        $this->cashondeliveryTableInterface = $cashondeliveryTableInterface;
        $this->taxHelper = $taxHelper;
        $this->taxCalculation = $taxCalculationInterface;
				$this->priceCurrencyInterface = $priceCurrencyInterface;
    }

    /**
     * Get totals to be used for cash on delivery
     * @return array
     */
    public function getUsedTotals()
    {
        $totals = preg_split('/\s*,\s*/', $this->scopeConfigInterface->getValue(
            'payment/msp_cashondelivery/used_totals',
            ScopeInterface::SCOPE_STORE
        ));

        $totals = array_unique($totals);

        // Exclude unwanted totals to avoid recusrsions
        $return = [];
        foreach ($totals as $total) {
            if (in_array($total, ['grand_total', 'msp_cashondelivery', 'msp_cashondelivery_tax'])) {
                continue;
            }

            $return[] = $total;
        }

        return $totals;
    }

    /**
     * Get calculation base
     * @param array $totals
     * @return double
     */
    public function getCalcBase(array $totals)
    {
        $usedTotals = $this->getUsedTotals();

        $calcBase = 0;
        foreach ($totals as $totalCode => $total) {
            if (!in_array($totalCode, $usedTotals)) {
                continue;
            }

            $calcBase += $total;
        }

        if (isset($totals['msp_cashondelivery_tax'])) {
            $calcBase -= $totals['msp_cashondelivery_tax'];
        }

        return $calcBase;
    }

    /**
     * Get base amount
     * @param array $totals
     * @param string $country
     * @param string $region
     * @return double
     */
    public function getBaseAmount(array $totals, $country, $region)
    {
        $calcBase = $this->getCalcBase($totals);
        return $this->cashondeliveryTableInterface->getFee($calcBase, $country, $region);
    }

		/**
     * Calculate rated tax amount based on price and tax rate.
     * If you are using price including tax $priceIncludeTax should be true.
     *
     * @param   float $price
     * @param   float $taxRate
     * @param   boolean $priceIncludeTax
     * @param   boolean $round
     * @return  float
     */
	public function calcTaxAmount($price, $taxRate, $priceIncludeTax = false, $round = true)
    {
        $taxRate = $taxRate / 100;

        if ($priceIncludeTax) {
            $amount = $price * (1 - 1 / (1 + $taxRate));
        } else {
            $amount = $price * $taxRate;
        }

        if ($round) {
            return $this->round($amount);
        }

        return $amount;
    }

		/**
     * Round tax amount
     *
     * @param   float $price
     * @return  float
     */
    public function round($price)
    {
        return $this->priceCurrencyInterface->round($price);
    }

    /**
     * Get base tax amount
     * @param double $amount
     * @return double
     */
    public function getBaseTaxAmount($amount)
    {
        $rate = $this->getShippingTaxRate();

 				$priceIncludeTax=$this->taxHelper->shippingPriceIncludesTax(null);
				//error_log('getShippingTaxRate='.$rate.'; $amount='.$amount.'; return='.$this->calcTaxAmount($amount, $rate, $priceIncludeTax));
        //return $this->calcTaxAmount($amount, $rate, $priceIncludeTax);
				return $amount * ($rate / 100);
    }

    /**
     * Get cart information
     * @return \MSP\CashOnDelivery\Api\CashondeliveryCartInterface
     */
    public function getCartInformation()
    {
        return $this->objectManagerInterface->get('MSP\CashOnDelivery\Api\CashondeliveryCartInterface');
    }

    /**
     * @return \Magento\Tax\Api\Data\TaxRateInterface
     */
    protected function getShippingTaxRate()
    {
        $id = $this->taxHelper->getShippingTaxClass(null);
        return $this->taxCalculation->getCalculatedRate($id);
    }
}
