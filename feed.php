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
if (!Module::getInstanceByName('beslistcart')->active)
	exit;

$affiliate = '?ac=beslist';
$products = BeslistProduct::getLoadedBeslistProducts((int)$context->language->id);
// $products = Product::getProducts((int)$context->language->id, 0, 10, 'id_product', 'ASC', false, true);

// Send feed
header("Content-Type:text/xml; charset=utf-8");
echo '<?xml version="1.0" encoding="UTF-8"?>'."\n";
?>
<productfeed type="beslist" date="<?php echo date('Y-m-d H:i:s'); ?>">
<?php
  foreach ($products AS $product) {
      echo "\t<product>\n";
      echo "\t\t<title><![CDATA[".$product['name']."]]></title>\n";
      echo "\t\t<price>".number_format((float)Product::getPriceStatic($product['id_product']), 2, ',', '')."</price>\n";
      echo "\t\t<code><![CDATA[".$product['reference']."]]></code>\n";
      echo "\t\t<productlink><![CDATA[".str_replace('&amp;', '&', htmlspecialchars($link->getproductLink($product['id_product'], $product['link_rewrite'], Category::getLinkRewrite((int)($product['id_category_default']), $cookie->id_lang)))).$affiliate."]]></productlink>\n";

      $images = Image::getImages((int)$context->language->id, $product['id_product']);
      if (is_array($images) AND sizeof($images))
      {
          foreach ($images as $idx => $image) {
              $imageObj = new Image($image['id_image']);
              $suffix = $idx > 0 ? "_" . $idx : "";
              echo "\t\t<imagelink".$suffix."><![CDATA[".$link->getImageLink($product['link_rewrite'], $image['id_image'], 'thickbox_default')."]]></imagelink".$suffix.">\n";
          }
      }

      echo "\t\t<category>" . $product['category_name'] . "</category>\n";
      echo "\t\t<deliveryperiod>Niet op voorraad</deliveryperiod>\n";
      echo "\t\t<shippingcost>3.95</shippingcost>\n";
      echo "\t\t<eancode>" . $product['ean13'] . "</eancode>\n";
      echo "\t\t<description><![CDATA[" . $product['description_short'] . "]]></description>\n";
      echo "\t\t<display>1</display>\n";
      echo "\t\t<sku>" . $product['reference'] . "</sku>\n";
      echo "\t\t<brand>" . $product['reference'] . "</brand>\n";
      echo "\t\t<size>xl</size>\n";
      echo "\t\t<color>rood</color>\n";
      echo "\t\t<gender>Man</gender>\n";
      echo "\t\t<material>textiel</material>\n";
      echo "\t\t<condition>Nieuw</condition>\n";
      echo "\t\t<variantcode>" . $product['reference'] . "</variantcode>\n"; // Grouping id
      echo "\t\t<modelcode>" . $product['reference'] . "</modelcode>\n"; // Grouping id
      echo "\t\t<oldprice>100,00</oldprice>\n";
      echo "\t</product>\n";
  }
?>
</productfeed>
