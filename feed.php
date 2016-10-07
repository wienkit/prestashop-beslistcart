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
 *  @copyright 2013-2016 Wienk IT
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
$ps_stock_management = Configuration::get('PS_STOCK_MANAGEMENT');
$stock_behaviour = Configuration::get('PS_ORDER_OUT_OF_STOCK');
$deliveryperiod_nl = Configuration::get('BESLIST_CART_DELIVERYPERIOD_NL');
$deliveryperiod_nostock_nl = Configuration::get('BESLIST_CART_DELIVERYPERIOD_NOSTOCK_NL');
$deliveryperiod_be = Configuration::get('BESLIST_CART_DELIVERYPERIOD_BE');
$deliveryperiod_nostock_be = Configuration::get('BESLIST_CART_DELIVERYPERIOD_NOSTOCK_BE');
$enabled_nl = (bool) Configuration::get('BESLIST_CART_ENABLED_NL');
$enabled_be = (bool) Configuration::get('BESLIST_CART_ENABLED_BE');
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

header("Content-Type:text/xml; charset=utf-8");
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
?>
<productfeed type="beslist" date="<?php echo date('Y-m-d H:i:s'); ?>">
<?php
foreach ($products as $product) {
    $price = $product['override_price'];
    if ($price == 0) {
        $price = (float)Product::getPriceStatic($product['id_product']);
    }
    echo "\t<product>\n";
    echo "\t\t<title><![CDATA[" . $product['name'] . "]]></title>\n";
    echo "\t\t<price>" . number_format($price, 2, ',', '') . "</price>\n";
    if (isset($product['attribute_reference'])) {
        echo "\t\t<code><![CDATA[" . $product['attribute_reference'] . "]]></code>\n";
        echo "\t\t<sku>" . $product['attribute_reference'] . "</sku>\n";
        if (isset($product['variant'])) {
            echo "\t\t<variantcode>" . $product['reference'] . '-' . $product['variant'] . "</variantcode>\n";
        }
        echo "\t\t<modelcode>" . $product['reference'] . "</modelcode>\n"; // Grouping id
    } else {
        echo "\t\t<code><![CDATA[" . $product['reference'] . "]]></code>\n";
        echo "\t\t<sku>" . $product['reference'] . "</sku>\n";
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
    $images = Image::getImages((int)$context->language->id, $product['id_product']);
    if (is_array($images) and sizeof($images)) {
        foreach ($images as $idx => $image) {
            $imageObj = new Image($image['id_image']);
            $suffix = $idx > 0 ? "_" . $idx : "";
            echo "\t\t<imagelink" . $suffix . "><![CDATA[" . $link->getImageLink(
                    $product['link_rewrite'],
                    $image['id_image']
                )
                . "]]></imagelink" . $suffix . ">\n";
        }
    }

    echo "\t\t<category>" . htmlspecialchars($product['category_name'], ENT_XML1, 'UTF-8') . "</category>\n";
    if ($enabled_nl) {
        $prod_deliveryperiod_nl = $product['delivery_code_nl'] == '' ? $deliveryperiod_nl : $product['delivery_code_nl'];
        echo "\t\t<deliveryperiod_nl>" .
            ($product['stock'] > 0 ? $prod_deliveryperiod_nl : $deliveryperiod_nostock_nl) .
            "</deliveryperiod_nl>\n";
        echo "\t\t<shippingcost_nl>" .
            Tools::ps_round(
                $carrier_nl->getDeliveryPriceByPrice($price, $country_nl->id_zone) * (1 + ($carrier_nl_tax / 100)),
                2
            ) .
            "</shippingcost_nl>\n";
    }
    if ($enabled_be) {
        $prod_deliveryperiod_be = $product['delivery_code_be'] == '' ? $deliveryperiod_be : $product['delivery_code_be'];
        echo "\t\t<deliveryperiod_be>" .
            ($product['stock'] > 0 ? $prod_deliveryperiod_be : $deliveryperiod_nostock_be) .
            "</deliveryperiod_be>\n";
        echo "\t\t<shippingcost_be>" .
            Tools::ps_round(
                $carrier_be->getDeliveryPriceByPrice($price, $country_be->id_zone) * (1 + ($carrier_be_tax / 100)),
                2
            ) . "</shippingcost_be>\n";
    }
    echo "\t\t<eancode>" . $product['ean13'] . "</eancode>\n";
    echo "\t\t<description><![CDATA[" . $product['description_short'] . "]]></description>\n";

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
        echo "\t\t<brand>" . $product['manufacturer_name'] . "</brand>\n";
    }
    if (isset($product['size'])) {
        echo "\t\t<size>" . $product['size'] . "</size>\n";
    }
    if (isset($product['color'])) {
        echo "\t\t<color>" . $product['color'] . "</color>\n";
    }
    // echo "\t\t<gender> (man/vrouw/ jongen/meisje/baby/unisex) </gender>\n";
    // echo "\t\t<material>?</material>\n";
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
