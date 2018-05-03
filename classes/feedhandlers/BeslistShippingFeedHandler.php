<?php
/**
 * NOTICE OF LICENSE
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    Mark Wienk
 * @copyright 2013-2018 Wienk IT
 * @license   LICENSE.txt
 */

class BeslistShippingFeedHandler
{
    /** @var array */
    private $shipping_method = array();

    /** @var array  */
    private $carrier = array();

    /** @var array  */
    private $carrier_tax = array();

    /** @var array  */
    private $country = array();

    /** @var array */
    private $enabled = array();

    /** @var array */
    private $deliveryperiod = array();

    /** @var array */
    private $deliveryperiod_nostock = array();

    /** @var int */
    private $shippingHandling;

    /** @var int */
    private $shippingFreePrice;

    public function __construct()
    {
        $this->shippingFreePrice = (int) Configuration::get('PS_SHIPPING_FREE_PRICE');
        $this->shippingHandling = (int) Configuration::get('PS_SHIPPING_HANDLING');

        $this->deliveryperiod['nl'] = Configuration::get('BESLIST_CART_DELIVERYPERIOD_NL');
        $this->deliveryperiod_nostock['nl'] = Configuration::get('BESLIST_CART_DELIVERYPERIOD_NOSTOCK_NL');
        $this->deliveryperiod['be'] = Configuration::get('BESLIST_CART_DELIVERYPERIOD_BE');
        $this->deliveryperiod_nostock['be'] = Configuration::get('BESLIST_CART_DELIVERYPERIOD_NOSTOCK_BE');

        $this->enabled['nl'] = (bool) Configuration::get('BESLIST_CART_ENABLED_NL');
        $this->enabled['be'] = (bool) Configuration::get('BESLIST_CART_ENABLED_BE');

        $this->country['nl'] = new Country(Country::getByIso('NL'));
        $this->country['be'] = new Country(Country::getByIso('BE'));

        $this->carrier['nl'] = Carrier::getCarrierByReference(Configuration::get('BESLIST_CART_CARRIER_NL'));
        $this->carrier['be'] = Carrier::getCarrierByReference(Configuration::get('BESLIST_CART_CARRIER_BE'));

        $this->shipping_method['nl'] = $this->carrier['nl']->getShippingMethod();
        $this->shipping_method['be'] = $this->carrier['be']->getShippingMethod();

        $this->carrier_tax['nl'] = $this->carrier['nl']->getTaxesRate(
            $this->getDefaultAddress($this->country['nl']->id)
        );
        $this->carrier_tax['be'] = $this->carrier['be']->getTaxesRate(
            $this->getDefaultAddress($this->country['be']->id)
        );
    }

    /**
     * Handle the product.
     *
     * Adds shippingcost and delivery periods.
     *
     * @param $product
     * @param $price
     * @return string
     */
    public function handle($product, $price)
    {
        $result = "";
        $countries = array('nl', 'be');
        foreach ($countries as $country) {
            if ($this->enabled[$country]) {
                $result .= $this->getProductDeliveryPeriodForCountry($product, $country);
                $result .= $this->getProductShippingCostPerCountry($product, $price, $country);
            }
        }
        return trim($result, PHP_EOL);
    }

    /**
     * @param $country_id
     * @return Address
     */
    private function getDefaultAddress($country_id)
    {
        $address = new Address();
        $address->id_country = $country_id;
        $address->id_state = 0;
        $address->postcode = 0;
        return $address;
    }

    /**
     * @param $product
     * @param $country
     * @return string
     */
    private function getProductDeliveryPeriodForCountry($product, $country)
    {
        if ($product['stock'] < 1) {
            $deliveryperiod = $this->deliveryperiod_nostock[$country];
        } elseif (!empty($product["delivery_code_{$country}"])) {
            $deliveryperiod = $product["delivery_code_{$country}"];
        } elseif (!empty($product['available_now'])) {
            $deliveryperiod = $product['available_now'];
        } else {
            $deliveryperiod = $this->deliveryperiod[$country];
        }
        return "<deliveryperiod_{$country}>{$deliveryperiod}</deliveryperiod_{$country}>" . PHP_EOL;
    }

    /**
     * @param $product
     * @param $price
     * @param $country
     * @return string
     */
    private function getProductShippingCostPerCountry($product, $price, $country)
    {
        if ($this->shippingFreePrice > 0 && $price >= $this->shippingFreePrice) {
            $shippingTotal = 0;
        } elseif ($this->shipping_method[$country] == Carrier::SHIPPING_METHOD_WEIGHT) {
            if (!isset($product['attribute_weight']) || is_null($product['attribute_weight'])) {
                $shippingTotal = $this->carrier[$country]->getDeliveryPriceByWeight(
                    $product['weight'],
                    $this->country[$country]->id_zone
                );
            } else {
                $shippingTotal = $this->carrier[$country]->getDeliveryPriceByWeight(
                    $product['weight'] + $product['attribute_weight'],
                    $this->country[$country]->id_zone
                );
            }
        } else {
            /** @var int $shippingTotal */
            $shippingTotal = $this->carrier[$country]->getDeliveryPriceByPrice(
                $price,
                $this->country[$country]->id_zone
            );
            if ($price < $this->shippingFreePrice) {
                $shippingTotal += $this->shippingHandling;
            }
        }
        $shipping_cost = Tools::ps_round($shippingTotal * (1 + ($this->carrier_tax[$country] / 100)), 2);

        return "<shippingcost_{$country}>{$shipping_cost}</shippingcost_{$country}>" . PHP_EOL;
    }
}
