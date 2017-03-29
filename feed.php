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
 *  @copyright 2013-2017 Wienk IT
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

$affiliate = '?ac=beslist';
$products = BeslistProduct::getLoadedBeslistProducts((int)$context->language->id);
$categories = array();
foreach (BeslistProduct::getBeslistCategories() as $category) {
    $categories[$category['id_beslist_category']] = $category['name'];
}
$shop_categories = BeslistProduct::getShopCategoriesComplete((int)$context->language->id);
$mapped_categories = BeslistProduct::getMappedCategoryTree();
$default_category = $categories[Configuration::get('BESLIST_CART_CATEGORY')];
$ps_stock_management = Configuration::get('PS_STOCK_MANAGEMENT');
$stock_behaviour = Configuration::get('PS_ORDER_OUT_OF_STOCK');
$deliveryperiod_nl = Configuration::get('BESLIST_CART_DELIVERYPERIOD_NL');
$deliveryperiod_nostock_nl = Configuration::get('BESLIST_CART_DELIVERYPERIOD_NOSTOCK_NL');
$deliveryperiod_be = Configuration::get('BESLIST_CART_DELIVERYPERIOD_BE');
$deliveryperiod_nostock_be = Configuration::get('BESLIST_CART_DELIVERYPERIOD_NOSTOCK_BE');
$enabled_nl = (bool) Configuration::get('BESLIST_CART_ENABLED_NL');
$enabled_be = (bool) Configuration::get('BESLIST_CART_ENABLED_BE');
$use_long_description = (bool) Configuration::get('BESLIST_CART_USE_LONG_DESCRIPTION');
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
    $price = (float)Product::getPriceStatic($product['id_product']);
    echo "\t<product>\n";
    echo "\t\t<title><![CDATA[" . $product['name'] . "]]></title>\n";
    echo "\t\t<price>" . number_format($price, 2, ',', '') . "</price>\n";

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
                )
            )
        )
    )
    . $affiliate . "]]></productlink>\n";
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
            )
            . "]]></imagelink" . $suffix . ">\n";
        }
    }

    echo "\t\t<category>";
    if (array_key_exists('id_beslist_category', $product) && $product['id_beslist_category']) {
        echo htmlspecialchars($categories[$product['id_beslist_category']], ENT_XML1, 'UTF-8');
    } elseif (
        $product['id_category_default']
        && array_key_exists($product['id_category_default'], $mapped_categories)
    ) {
        echo htmlspecialchars($categories[$mapped_categories[$product['id_category_default']]], ENT_XML1, 'UTF-8');
    } else {
        echo htmlspecialchars($default_category, ENT_XML1, 'UTF-8');
    }
    echo "</category>\n";

    if (
        $product['id_category_default']
        && array_key_exists($product['id_category_default'], $shop_categories)
    ) {
        echo "\t\t<shop_category>";
        echo htmlspecialchars($shop_categories[$product['id_category_default']], ENT_XML1, 'UTF-8');
        echo "</shop_category>\n";
    }

    $priceExtra = 0;
    if ($price < $shippingFreePrice) {
        $priceExtra = $shippingHandling;
    }

    if ($enabled_nl) {
        $prod_deliveryperiod_nl =
            $product['delivery_code_nl'] == '' ? $deliveryperiod_nl : $product['delivery_code_nl'];
        echo "\t\t<deliveryperiod_nl>" .
            ($product['stock'] > 0 ? $prod_deliveryperiod_nl : $deliveryperiod_nostock_nl) .
            "</deliveryperiod_nl>\n";
        $shippingTotal = $carrier_nl->getDeliveryPriceByPrice($price, $country_nl->id_zone) + $priceExtra;
        echo "\t\t<shippingcost_nl>" .
            Tools::ps_round(
                $shippingTotal * (1 + ($carrier_nl_tax / 100)),
                2
            ) .
            "</shippingcost_nl>\n";
    }
    if ($enabled_be) {
        $prod_deliveryperiod_be =
            $product['delivery_code_be'] == '' ? $deliveryperiod_be : $product['delivery_code_be'];
        echo "\t\t<deliveryperiod_be>" .
            ($product['stock'] > 0 ? $prod_deliveryperiod_be : $deliveryperiod_nostock_be) .
            "</deliveryperiod_be>\n";
        $shippingTotal = $carrier_nl->getDeliveryPriceByPrice($price, $country_be->id_zone) + $priceExtra;
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
    echo "\t\t<description><![CDATA[" . ($use_long_description ? $product['description'] : $product['description_short']) . "]]></description>\n";

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

    $productFeatures = Product::getFeaturesStatic($product['id_product']);
    foreach ($productFeatures as $productFeature) {
        $name = strtolower($featuresIndexed[$productFeature['id_feature']]['name']);
        $name = preg_replace("/[^a-z0-9]/", '', $name);
        if ($name != "" && $featureValuesIndexed[$productFeature['id_feature']][$productFeature['id_feature_value']]['value'] != "") {
            echo "\t\t<" . $name . ">";
            echo "<![CDATA[" .
                $featureValuesIndexed[$productFeature['id_feature']][$productFeature['id_feature_value']]['value'] .
                "]]>";
            echo "</" . $name . ">\n";
        }
    }

    $productAttributes = Product::getAttributesParams($product['id_product'], $product['id_product_attribute']);
    foreach ($productAttributes as $productAttribute) {
        $name = strtolower($attributeGroupsIndexed[$productAttribute['id_attribute_group']]['name']);
        $name = preg_replace("/[^a-z0-9]/", '', $name);
        if ($name != "" && $attributesIndexed[$productAttribute['id_attribute_group']][$productAttribute['id_attribute']]['name'] != "") {
            echo "\t\t<" . $name . ">";
            echo "<![CDATA[" .
                $attributesIndexed[$productAttribute['id_attribute_group']][$productAttribute['id_attribute']]['name'] .
                "]]>";
            echo "</" . $name . ">\n";
        }
    }

    echo "\t\t<condition>";
    switch($product['condition']) {
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
