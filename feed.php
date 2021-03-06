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
 *  @author    Mark Wienk
 *  @copyright 2013-2018 Wienk IT
 *  @license   LICENSE.txt
 */

include(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/../../init.php');
require_once(dirname(__FILE__).'/classes/BeslistProduct.php');
if (!Module::getInstanceByName('beslistcart')->active) {
    exit;
}

$context = Context::getContext();
$link = $context->link;
$cookie = $context->cookie;

$limit = Tools::getValue('limit');
if ($limit) {
    $products = BeslistProduct::getLoadedBeslistProducts((int)$context->language->id, $limit);
} else {
    $products = BeslistProduct::getLoadedBeslistProducts((int)$context->language->id);
}
$shop_categories = BeslistProduct::getShopCategoriesComplete((int)$context->language->id);
$ps_stock_management = Configuration::get('PS_STOCK_MANAGEMENT');
$stock_behaviour = Configuration::get('PS_ORDER_OUT_OF_STOCK');
$deliveryperiod_nl = Configuration::get('BESLIST_CART_DELIVERYPERIOD_NL');
$deliveryperiod_nostock_nl = Configuration::get('BESLIST_CART_DELIVERYPERIOD_NOSTOCK_NL');
$deliveryperiod_be = Configuration::get('BESLIST_CART_DELIVERYPERIOD_BE');
$deliveryperiod_nostock_be = Configuration::get('BESLIST_CART_DELIVERYPERIOD_NOSTOCK_BE');
$enabled_nl = (bool) Configuration::get('BESLIST_CART_ENABLED_NL');
$enabled_be = (bool) Configuration::get('BESLIST_CART_ENABLED_BE');
$use_long_description = (bool) Configuration::get('BESLIST_CART_USE_LONG_DESCRIPTION');
$use_attributes_in_title = (bool) Configuration::get('BESLIST_CART_ATTRIBUTES_IN_TITLE');
$attribute_size = (int) Configuration::get('BESLIST_CART_ATTRIBUTE_SIZE');
$attribute_color = (int) Configuration::get('BESLIST_CART_ATTRIBUTE_COLOR');
$country_nl = new Country(Country::getByIso('NL'));
$country_be = new Country(Country::getByIso('BE'));
$address_nl = new Address();
$address_nl->id_country = $country_nl->id;
$address_nl->id_state = 0;
$address_nl->postcode = 0;
$address_be = new Address();
$address_be->id_country = $country_be->id;
$address_be->id_state = 0;
$address_be->postcode = 0;
$carrier_nl = Carrier::getCarrierByReference(Configuration::get('BESLIST_CART_CARRIER_NL'));
$carrier_be = Carrier::getCarrierByReference(Configuration::get('BESLIST_CART_CARRIER_BE'));
$shipping_method_nl = $carrier_nl->getShippingMethod();
$shipping_method_be = $carrier_be->getShippingMethod();
$carrier_nl_tax = $carrier_nl->getTaxesRate($address_nl);
$carrier_be_tax = $carrier_be->getTaxesRate($address_be);
$shippingFreePrice = Configuration::get('PS_SHIPPING_FREE_PRICE');
$shippingHandling = Configuration::get('PS_SHIPPING_HANDLING');

$featureValuesIndexed = array();
$featuresIndexed = array();
$features = Feature::getFeatures($context->language->id);
foreach ($features as $feature) {
    $id_feature = $feature['id_feature'];
    $featureValuesIndexed[$id_feature] = array();
    $featureValues = FeatureValue::getFeatureValuesWithLang($context->language->id, $id_feature);
    foreach ($featureValues as $featureValue) {
        $featureValuesIndexed[$id_feature][$featureValue['id_feature_value']] = $featureValue;
    }
    $featuresIndexed[$id_feature] = $feature;
}
unset($features);
unset($featureValues);

$attributesIndexed = array();
$attributeGroupsIndexed = array();
$attributeGroups = AttributeGroup::getAttributesGroups($context->language->id);

foreach ($attributeGroups as $attributeGroup) {
    $id_attribute_group = $attributeGroup['id_attribute_group'];
    $attributesIndexed[$id_attribute_group] = array();
    $attributeValues = AttributeGroup::getAttributes($context->language->id, $id_attribute_group);
    foreach ($attributeValues as $attributeValue) {
        $attributesIndexed[$id_attribute_group][$attributeValue['id_attribute']] = $attributeValue;
    }
    $attributeGroupsIndexed[$id_attribute_group] = $attributeGroup;
}
unset($attributeGroups);
unset($attributeValues);

header("Content-Type:text/xml; charset=utf-8");
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
?>
<productfeed type="beslist" date="<?php echo date('Y-m-d H:i:s'); ?>">
    <?php
    foreach ($products as $product) {
        echo "\t<product>\n";
        $price = (float)BeslistProduct::getPriceStatic(
            $product['id_product'],
            $product['id_product_attribute'],
            $context
        );
        echo "\t\t<price>" . number_format($price, 2, ',', '') . "</price>\n";
        $price_old = (float)BeslistProduct::getPriceStatic(
            $product['id_product'],
            $product['id_product_attribute'],
            $context,
            false
        );

        if ($price_old != $price) {
            echo "\t\t<price_old>" .
                number_format($price_old, 2, ',', '') .
                "</price_old>\n";
        }

        if ($product['id_product_attribute']) {
            echo "\t\t<code>" . $product['id_product_attribute'] . "-" . $product['id_product'] . "</code>\n";
        } else {
            echo "\t\t<code>" . $product['id_product'] . "</code>\n";
        }

        if (isset($product['attribute_reference'])) {
            echo "\t\t<sku>" . $product['attribute_reference'] . "</sku>\n";
        } elseif (isset($product['reference'])) {
            echo "\t\t<sku>" . $product['reference'] . "</sku>\n";
        }

        if (isset($product['size'])) {
            if (isset($product['variant'])) {
                echo "\t\t<variantcode>" . $product['id_product'] . "-" . $product['variant'] . "</variantcode>\n";
            } else {
                echo "\t\t<variantcode>" . $product['id_product'] . "</variantcode>\n";
            }
            echo "\t\t<size>" . $product['size'] . "</size>\n";
        }

        if (isset($product['color'])) {
            echo "\t\t<modelcode>" . $product['id_product'] . "</modelcode>\n"; // Grouping id
            echo "\t\t<color>" . $product['color'] . "</color>\n";
        }

        echo "\t\t<productlink><![CDATA[" . str_replace(
            '&amp;',
            '&',
            htmlspecialchars(
                $link->getProductLink(
                    $product['id_product'],
                    $product['link_rewrite'],
                    Category::getLinkRewrite(
                        (int)($product['id_category_default']),
                        $cookie->id_lang
                    ),
                    null,
                    null,
                    null,
                    $product['id_product_attribute'],
                    false,
                    false,
                    false,
                    array('ac' => 'beslist')
                )
            )
        ) . "]]></productlink>\n";
        $hasAttributeImage = array_key_exists('attribute_image', $product) && $product['attribute_image'];
        $images = Image::getImages((int)$context->language->id, $product['id_product']);
        if (is_array($images) and sizeof($images)) {
            $extraImageCounter = 1;
            foreach ($images as $idx => $image) {
                $isPrimary = false;
                if ($hasAttributeImage && $image['id_image'] == $product['attribute_image']) {
                    $isPrimary = true;
                } elseif (!$hasAttributeImage && $idx == 0) {
                    $isPrimary = true;
                }
                $imageObj = new Image($image['id_image']);
                $suffix = $isPrimary ? "" : "_" . $extraImageCounter++;
                echo "\t\t<imagelink" . $suffix . "><![CDATA[" . $link->getImageLink(
                    $product['link_rewrite'],
                    $image['id_image']
                ) . "]]></imagelink" . $suffix . ">\n";
            }
        }

        if ($product['id_category_default'] && array_key_exists($product['id_category_default'], $shop_categories)) {
            echo "\t\t<shop_category>";
            echo htmlspecialchars($shop_categories[$product['id_category_default']], ENT_XML1, 'UTF-8');
            echo "</shop_category>\n";
        }

        $priceExtra = 0;
        if ($price < $shippingFreePrice) {
            $priceExtra = $shippingHandling;
        }

        if ($enabled_nl) {
            if (isset($product['delivery_code_nl']) && $product['delivery_code_nl'] != '') {
                $prod_deliveryperiod_nl = $product['delivery_code_nl'];
            } elseif (isset($product['available_now']) && $product['available_now'] != '') {
                $prod_deliveryperiod_nl = $product['available_now'];
            } else {
                $prod_deliveryperiod_nl = $deliveryperiod_nl;
            }
            echo "\t\t<deliveryperiod_nl>" .
                ($product['stock'] > 0 ? $prod_deliveryperiod_nl : $deliveryperiod_nostock_nl) .
                "</deliveryperiod_nl>\n";

            if ($shippingFreePrice > 0 && $price >= $shippingFreePrice) {
                $shippingTotal = 0;
            } elseif ($shipping_method_nl == Carrier::SHIPPING_METHOD_WEIGHT) {
                if (!isset($product['attribute_weight']) || is_null($product['attribute_weight'])) {
                    $shippingTotal = $carrier_nl->getDeliveryPriceByWeight(
                        $product['weight'],
                        $country_nl->id_zone
                    );
                } else {
                    $shippingTotal = $carrier_nl->getDeliveryPriceByWeight(
                        $product['weight'] + $product['attribute_weight'],
                        $country_nl->id_zone
                    );
                }
            } else {
                $shippingTotal = $carrier_nl->getDeliveryPriceByPrice($price, $country_nl->id_zone) + $priceExtra;
            }

            echo "\t\t<shippingcost_nl>" .
                Tools::ps_round(
                    $shippingTotal * (1 + ($carrier_nl_tax / 100)),
                    2
                ) .
                "</shippingcost_nl>\n";
        }
        if ($enabled_be) {
            if (isset($product['delivery_code_be']) && $product['delivery_code_be'] != '') {
                $prod_deliveryperiod_be = $product['delivery_code_be'];
            } elseif (isset($product['available_now']) && $product['available_now'] != '') {
                $prod_deliveryperiod_be = $product['available_now'];
            } else {
                $prod_deliveryperiod_be = $deliveryperiod_be;
            }

            echo "\t\t<deliveryperiod_be>" .
                ($product['stock'] > 0 ? $prod_deliveryperiod_be : $deliveryperiod_nostock_be) .
                "</deliveryperiod_be>\n";

            if ($shippingFreePrice > 0 && $price >= $shippingFreePrice) {
                  $shippingTotal = 0;
            } elseif ($shipping_method_be == Carrier::SHIPPING_METHOD_WEIGHT) {
                if (!isset($product['attribute_weight']) || is_null($product['attribute_weight'])) {
                    $shippingTotal = $carrier_be->getDeliveryPriceByWeight(
                        $product['weight'],
                        $country_be->id_zone
                    );
                } else {
                    $shippingTotal = $carrier_be->getDeliveryPriceByWeight(
                        $product['weight'] + $product['attribute_weight'],
                        $country_be->id_zone
                    );
                }
            } else {
                $shippingTotal = $carrier_be->getDeliveryPriceByPrice($price, $country_be->id_zone) + $priceExtra;
            }
            echo "\t\t<shippingcost_be>" .
                Tools::ps_round(
                    $shippingTotal * (1 + ($carrier_be_tax / 100)),
                    2
                ) . "</shippingcost_be>\n";
        }
        if (isset($product['attrean'])) {
            echo "\t\t<eancode>" . $product['attrean'] . "</eancode>\n";
        } else {
            echo "\t\t<eancode>" . $product['ean13'] . "</eancode>\n";
        }
        $description = $use_long_description ? $product['description'] : $product['description_short'];
        $description = str_replace(array('<br>', '<br />', '<p>', '</p>'), '\\\\n', $description);
        echo "\t\t<description><![CDATA[" . trim($description, "\\\\n"). "]]></description>\n";

        $display = 1;
        if ($product['published'] == 0) {
            $display = 0;
        } elseif ($product['stock'] > 0 || !$ps_stock_management) {
            $display = 1;
        } elseif ($product['out_of_stock_behaviour'] == 1) {
            $display = 1;
        } elseif ($product['out_of_stock_behaviour'] == 2 && $stock_behaviour == 1) {
            $display = 1;
        } else {
            $display = 0;
        }
        echo "\t\t<display>" . $display . "</display>\n";
        if (isset($product['manufacturer_name'])) {
            echo "\t\t<brand><![CDATA[" . $product['manufacturer_name'] . "]]></brand>\n";
        }

        $featureField = "<features_combined><![CDATA[";
        $prFeatures = Product::getFeaturesStatic($product['id_product']);
        foreach ($prFeatures as $prFeature) {
            $rawName = $featuresIndexed[$prFeature['id_feature']]['name'];
            $name = Tools::strtolower($rawName);
            $name = preg_replace("/[^a-z0-9]/", '', $name);
            if ($name != "" &&
                array_key_exists($prFeature['id_feature'], $featuresIndexed) &&
                array_key_exists($prFeature['id_feature_value'], $featureValuesIndexed[$prFeature['id_feature']]) &&
                $featureValuesIndexed[$prFeature['id_feature']][$prFeature['id_feature_value']]['value'] != ""
            ) {
                $value = $featureValuesIndexed[$prFeature['id_feature']][$prFeature['id_feature_value']]['value'];
                echo "\t\t<" . $name . "><![CDATA[" . $value . "]]></" . $name . ">\n";
                $featureField .= $rawName . ": " .$value . "\\\\n";
            }
        }

        $nameSuffix = "";
        $attributes = Product::getAttributesParams($product['id_product'], $product['id_product_attribute']);
        foreach ($attributes as $attribute) {
            $rawName = $attributeGroupsIndexed[$attribute['id_attribute_group']]['name'];
            $name = Tools::strtolower($rawName);
            $name = preg_replace("/[^a-z0-9]/", '', $name);
            if ($name != "" &&
                array_key_exists($attribute['id_attribute_group'], $attributesIndexed) &&
                array_key_exists($attribute['id_attribute'], $attributesIndexed[$attribute['id_attribute_group']]) &&
                $attributesIndexed[$attribute['id_attribute_group']][$attribute['id_attribute']]['name'] != ""
            ) {
                $value = $attributesIndexed[$attribute['id_attribute_group']][$attribute['id_attribute']]['name'];
                echo "\t\t<" . $name . "><![CDATA[" . $value . "]]></" . $name . ">\n";
                $featureField .= $rawName . ": " .$value . "\\\\n";
                if ((isset($product['color']) && $attribute['id_attribute_group'] == $attribute_color) ||
                    (isset($product['size']) && $attribute['id_attribute_group'] == $attribute_size)
                ) {
                    continue;
                }
                $nameSuffix .= $use_attributes_in_title ? ", " . $value : "";
            }
        }
        $featureField .= "]]></features_combined>";
        echo $featureField;

        echo "\t\t<title><![CDATA[" . $product['name'] . $nameSuffix . "]]></title>\n";

        echo "\t\t<condition>";
        switch ($product['condition']) {
            case 'refurbished':
                echo 'Refurbished';
                break;
            case 'used':
                echo 'Gebruikt';
                break;
            default:
                echo 'Nieuw';
                break;
        }
        echo "</condition>\n";
        echo "\t</product>\n";
    }
    ?>
</productfeed>
