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

require_once(dirname(__FILE__).'/feedhandlers/BeslistFeaturesFeedHandler.php');
require_once(dirname(__FILE__).'/feedhandlers/BeslistShippingFeedHandler.php');

class BeslistFeed
{
    /** @var BeslistShippingFeedHandler */
    private $shipping_handler;

    /** @var BeslistFeaturesFeedHandler  */
    private $features_handler;

    /** @var bool|resource */
    private $file;

    /** @var int */
    private $start;

    /** @var int */
    private $limit;

    /** @var Context */
    private $context;

    /** @var array */
    private $shop_categories;

    /** @var array */
    private $prices = array();

    /** @var bool */
    private $use_long_description;

    /** @var bool */
    private $ps_stock_management;

    /** @var int */
    private $stock_behaviour;

    /**
     * Run the Beslist feed genator.
     */
    public static function run()
    {
        $generator = new static();
        $generator->generateFeed();
    }

    /**
     * Returns the script url (with secret)
     * @return string
     */
    public static function getScriptLocation()
    {
        $base_url = Tools::getShopDomainSsl(true, true);
        $module_url = $base_url . __PS_BASE_URI__ . basename(_PS_MODULE_DIR_);
        $generator = $module_url . '/beslistcart/cron-generate.php';
        $secret = md5(_COOKIE_KEY_ . Configuration::get('PS_SHOP_NAME') . 'BESLISTCART');
        return $generator . '?secure_key=' . $secret;
    }

    /**
     * Return the public feed URL
     * @return mixed
     */
    public static function getPublicFeedLocation()
    {
        $base_url = Tools::getShopDomainSsl(true, true);
        $feed_path = self::getFeedLocation();
        $docroot = $_SERVER['DOCUMENT_ROOT'];
        return $base_url . str_replace($docroot, '', $feed_path);
    }

    /**
     * BeslistFeed constructor.
     */
    private function __construct()
    {
        $tempFileName = self::getTemporaryFilename();
        $dirname = dirname($tempFileName);
        if (!is_dir($dirname)) {
            mkdir($dirname, 0755, true);
        }
        $this->file = fopen($tempFileName, 'a');
        $this->start = Tools::getValue('start', 0);
        $this->limit = Tools::getValue('limit', 500);
        $this->context = Context::getContext();
        $this->shop_categories = BeslistProduct::getShopCategoriesComplete((int)$this->context->language->id);
        $this->shipping_handler = new BeslistShippingFeedHandler();
        $this->features_handler = new BeslistFeaturesFeedHandler();

        $this->use_long_description = (bool) Configuration::get('BESLIST_CART_USE_LONG_DESCRIPTION');
        $this->ps_stock_management = (bool) Configuration::get('PS_STOCK_MANAGEMENT');
        $this->stock_behaviour = (int) Configuration::get('PS_ORDER_OUT_OF_STOCK');
    }

    /**
     * Get a temporary name (the file with .part)
     * @return string
     */
    private static function getTemporaryFilename()
    {
        return self::getFeedLocation() . '.part';
    }

    /**
     * Get the feed location.
     *
     * @return string
     */
    private static function getFeedLocation()
    {
        return (string) Configuration::get('BESLIST_CART_FEED_LOCATION');
    }

    /**
     * Continue generating the feed.
     *
     * @throws PrestaShopException
     */
    public function generateFeed()
    {
        if ($this->isBegin()) {
            $this->writeFileHeader();
        }

        $this->handleProducts();

        if ($this->isLast()) {
            $this->writeFileFooter();
            self::renameFile();
        } else {
            $cron_url = self::getScriptLocation();
            Tools::redirect($cron_url . '&start=' . ($this->start + $this->limit) . '&limit=' . $this->limit);
        }
    }

    /**
     * Returns true if this is the first iteration.
     *
     * @return bool
     */
    private function isBegin()
    {
        return $this->start === 0;
    }

    /**
     * Returns true if this is the last iteration.
     *
     * @return bool
     */
    private function isLast()
    {
        $sql = 'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'beslist_product b
            LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON (b.`id_product` = p.`id_product`)' .
            Shop::addSqlAssociation('product', 'p');
        $total = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
        return $this->start + $this->limit > $total;
    }

    /**
     * Writes the xml prefix for the file
     */
    private function writeFileHeader()
    {
        $this->write('<?xml version="1.0" encoding="UTF-8"?>');
        $date = date('Y-m-d H:i:s');
        $this->write("<productfeed type=\"beslist\" date=\"{$date}\">");
    }

    /**
     * End processing, end the xml file.
     */
    private function writeFileFooter()
    {
        $this->write("</productfeed>");
    }

    /**
     * Write a piece of text to the file.
     *
     * @param $text
     */
    private function write($text)
    {
        fwrite($this->file, $text . PHP_EOL);
    }

    /**
     * Rename the file to a production ready xml file.
     */
    private static function renameFile()
    {
        rename(self::getTemporaryFilename(), self::getFeedLocation());
    }

    /**
     * Process the products for this iteration
     *
     * @throws PrestaShopException
     */
    private function handleProducts()
    {
        $products = BeslistProduct::getLoadedBeslistProducts(
            (int)$this->context->language->id,
            $this->limit,
            $this->start
        );
        foreach ($products as $product) {
            $this->handleProduct($product);
        }
    }

    /**
     * Process the product
     * @param array $product
     * @throws PrestaShopException
     */
    private function handleProduct($product)
    {
        $this->write('<product>');

        $this->handleProductPrices($product);
        $this->handleProductIdentifiers($product);
        $this->handleProductLink($product);
        $this->handleProductImages($product);
        $this->handleProductCategory($product);
        $this->handleProductShipping($product);
        $this->handleProductDescription($product);
        $this->handleProductStock($product);
        $this->handleProductBrand($product);
        $this->handleProductFeatures($product);
        $this->handleProductCondition($product);

        $this->write('</product>');
    }

    /**
     * @param $product
     */
    private function handleProductPrices($product)
    {
        $price = $this->getProductPrice($product);
        $this->write("<price>" . number_format($price, 2, ',', '') . "</price>");
        $price_old = (float)BeslistProduct::getPriceStatic(
            $product['id_product'],
            $product['id_product_attribute'],
            $this->context,
            false
        );
        if ($price_old != $price) {
            $this->write("<price_old>" .
                number_format($price_old, 2, ',', '') .
                "</price_old>");
        }
    }

    /**
     * @param $product
     */
    private function handleProductIdentifiers($product)
    {
        if ($product['id_product_attribute']) {
            $this->write("<code>{$product['id_product_attribute']}-{$product['id_product']}</code>");
        } else {
            $this->write("<code>{$product['id_product']}</code>");
        }

        if (isset($product['attribute_reference'])) {
            $this->write("<sku>{$product['attribute_reference']}</sku>");
        } elseif (isset($product['reference'])) {
            $this->write("<sku>{$product['reference']}</sku>");
        }

        if (isset($product['size'])) {
            if (isset($product['variant'])) {
                $this->write("<variantcode>{$product['id_product']}-{$product['variant']}</variantcode>");
            } else {
                $this->write("<variantcode>{$product['id_product']}</variantcode>");
            }
            $this->write("<size>{$product['size']}</size>");
        }

        if (isset($product['color'])) {
            $this->write("<modelcode>{$product['id_product']}</modelcode>");
            $this->write("<color>{$product['color']}</color>");
        }

        if (isset($product['attrean'])) {
            $this->write("<eancode>{$product['attrean']}</eancode>");
        } else {
            $this->write("<eancode>{$product['ean13']}</eancode>");
        }
    }

    /**
     * @param $product
     * @throws PrestaShopException
     */
    private function handleProductLink($product)
    {
        $link = $this->context->link->getProductLink(
            $product['id_product'],
            $product['link_rewrite'],
            Category::getLinkRewrite(
                (int)($product['id_category_default']),
                $this->context->cookie->id_lang
            ),
            null,
            null,
            null,
            $product['id_product_attribute']
        );
        $link = htmlspecialchars($link);
        $link = str_replace('&amp;', '&', $link);
        $this->write("<productlink><![CDATA[{$link}]]></productlink>");
    }

    /**
     * @param $product
     */
    private function handleProductImages($product)
    {
        $hasAttributeImage = !empty($product['attribute_image']);
        $images = Image::getImages((int)$this->context->language->id, $product['id_product']);
        if (is_array($images) and sizeof($images)) {
            $extraImageCounter = 1;
            foreach ($images as $idx => $image) {
                $isPrimary = false;
                if ($hasAttributeImage && $image['id_image'] == $product['attribute_image']) {
                    $isPrimary = true;
                } elseif (!$hasAttributeImage && $idx == 0) {
                    $isPrimary = true;
                }
                $suffix = $isPrimary ? "" : "_" . $extraImageCounter++;
                $link = $this->context->link->getImageLink($product['link_rewrite'], $image['id_image']);
                $this->write("<imagelink{$suffix}><![CDATA[{$link}]]></imagelink{$suffix}>");
            }
        }
    }

    /**
     * @param $product
     */
    private function handleProductCategory($product)
    {
        if ($product['id_category_default'] && !empty($this->shop_categories[$product['id_category_default']])) {
            $category = htmlspecialchars(
                $this->shop_categories[$product['id_category_default']],
                ENT_XML1,
                'UTF-8'
            );
            $this->write("<shop_category>{$category}</shop_category>");
        }
    }

    /**
     * @param $product
     * @return float
     */
    private function getProductPrice($product)
    {
        $key = $product['id_beslist_product'];
        if (empty($this->prices[$key])) {
            $this->prices[$key] = (float)BeslistProduct::getPriceStatic(
                $product['id_product'],
                $product['id_product_attribute'],
                $this->context
            );
        }
        return $this->prices[$key];
    }

    /**
     * @param $product
     */
    private function handleProductShipping($product)
    {
        $price = $this->getProductPrice($product);
        $shipping = $this->shipping_handler->handle($product, $price);
        $this->write($shipping);
    }

    /**
     * @param $product
     */
    private function handleProductDescription($product)
    {
        $description = $this->use_long_description ? $product['description'] : $product['description_short'];
        $description = str_replace(array('<br>', '<br />', '<p>', '</p>'), '\\\\n', $description);
        $description = trim($description, "\\\\n");
        $this->write("<description><![CDATA[{$description}]]></description>");
    }

    /**
     * @param $product
     */
    private function handleProductStock($product)
    {
        if ($product['published'] == 0) {
            $display = 0;
        } elseif ($product['stock'] > 0 || !$this->ps_stock_management) {
            $display = 1;
        } elseif ($product['out_of_stock_behaviour'] == 1) {
            $display = 1;
        } elseif ($product['out_of_stock_behaviour'] == 2 && $this->stock_behaviour == 1) {
            $display = 1;
        } else {
            $display = 0;
        }
        $this->write("<display>{$display}</display>");
    }

    /**
     * @param $product
     */
    private function handleProductBrand($product)
    {
        if (isset($product['manufacturer_name'])) {
            $this->write("<brand><![CDATA[{$product['manufacturer_name']}]]></brand>");
        }
    }

    /**
     * @param $product
     */
    private function handleProductFeatures($product)
    {
        $features = $this->features_handler->handle($product);
        $this->write($features);
    }

    /**
     * @param $product
     */
    private function handleProductCondition($product)
    {
        $condition = 'Nieuw';
        if ($product['condition'] == 'refurbished') {
            $condition = 'Refurbished';
        } elseif ($product['condition'] == 'used') {
            $condition = 'Gebruikt';
        }
        $this->write("<condition>{$condition}</condition>");
    }
}
