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

require_once(dirname(__FILE__).'/BeslistShippingHelper.php');
require_once _PS_MODULE_DIR_ . 'beslistcart/libraries/autoload.php';

use Wienkit\BeslistShopitemClient\Entities\PriceField;
use Wienkit\BeslistShopitemClient\Entities\ShippingField;
use Wienkit\BeslistShopitemClient\Entities\ShopItem;
use Wienkit\BeslistShopitemClient\Entities\StockField;
use Wienkit\BeslistShopitemClient\Entities\ValueField;

class ShopItemFactory
{
    /**
     * @var BeslistShippingHelper
     */
    protected $shippingHelper;

    public function __construct()
    {
        $this->shippingHelper = new BeslistShippingHelper();
    }

    /**
     * Returns the ShopItem for Beslist.
     *
     * @param BeslistProduct $beslistProduct
     * @param bool $quantityOverride
     * @return ShopItem
     */
    public function getShopItem(BeslistProduct $beslistProduct, $quantityOverride = false)
    {
        $context = Context::getContext();

        $price = $this->getPrice($beslistProduct);
        $productData = $this->getProductInformation($beslistProduct, $context, $quantityOverride);

        $shipping = [];
        foreach (array('nl', 'be') as $country) {
            $shipping[] = $this->getShippingField($productData, $country, $price);
        }

        $priceField = $this->getPriceField($beslistProduct, $context, $price);
        $stock = $this->getStockField($productData);
        return new ShopItem($priceField, $shipping, $stock);
    }

    /**
     * @param BeslistProduct $beslistProduct
     * @return float
     */
    private function getPrice(BeslistProduct $beslistProduct)
    {
        $price = BeslistProduct::getPriceStatic(
            $beslistProduct->id_product,
            $beslistProduct->id_product_attribute
        );
        return $price;
    }

    /**
     * @param BeslistProduct $beslistProduct
     * @param $context
     * @param $price
     * @return PriceField
     */
    private function getPriceField(BeslistProduct $beslistProduct, $context, $price)
    {
        $price_old = (float)BeslistProduct::getPriceStatic(
            $beslistProduct->id_product,
            $beslistProduct->id_product_attribute,
            $context,
            false
        );

        if ($price_old != $price) {
            return new PriceField(new ValueField($price), new ValueField($price_old));
        } else {
            return new PriceField(new ValueField($price), new ValueField($price));
        }
    }

    /**
     * @param BeslistProduct $beslistProduct
     * @param $context
     * @param bool $quantityOverride
     * @return array|false|mysqli_result|null|PDOStatement|resource
     */
    private function getProductInformation(BeslistProduct $beslistProduct, $context, $quantityOverride = false)
    {
        $weightsResult = $beslistProduct->getProductWeights($context->language->id);
        $productData = $weightsResult[0];
        if ($quantityOverride !== false) {
            $productData['stock'] = $quantityOverride;
        } else {
            $productData['stock'] = StockAvailable::getQuantityAvailableByProduct(
                $beslistProduct->id_product,
                $beslistProduct->id_product_attribute
            );
        }
        $productData['delivery_code_nl'] = $beslistProduct->delivery_code_nl;
        $productData['delivery_code_be'] = $beslistProduct->delivery_code_be;
        return $productData;
    }

    /**
     * @param $productData
     * @param $country
     * @param $price
     * @return ShippingField
     */
    private function getShippingField($productData, $country, $price)
    {
        $deliveryPeriod = $this->shippingHelper->getProductDeliveryPeriodForCountry($productData, $country);
        $shippingPrice = $this->shippingHelper->getProductShippingCostPerCountry($productData, $price, $country);
        return new ShippingField(
            $country,
            new ValueField($shippingPrice),
            new ValueField($deliveryPeriod)
        );
    }

    /**
     * @param $productData
     * @return StockField
     */
    private function getStockField($productData)
    {
        return new StockField(new ValueField($productData['stock']));
    }
}
