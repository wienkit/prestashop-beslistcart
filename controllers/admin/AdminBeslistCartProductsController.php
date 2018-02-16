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

require_once _PS_MODULE_DIR_ . 'beslistcart/libraries/autoload.php';
require_once _PS_MODULE_DIR_ . 'beslistcart/beslistcart.php';
require_once _PS_MODULE_DIR_ . 'beslistcart/classes/BeslistProduct.php';

class AdminBeslistCartProductsController extends AdminController
{

    protected $statuses_array;

    public function __construct()
    {
        if (Tools::getIsset('viewbeslist_product') && $id_beslist_product = Tools::getValue('id_beslist_product')) {
            $beslistProduct =  new BeslistProduct($id_beslist_product);
            $id_product = $beslistProduct->id_product;
            if (version_compare(_PS_VERSION_, '1.7', '>=')) {
                $link = Context::getContext()
                    ->link
                    ->getAdminLink(
                        'AdminProducts',
                        true,
                        array('id_product' => $id_product),
                        array('updateproduct' => '1')
                    );
            } else {
                $link = Context::getContext()
                        ->link
                        ->getAdminLink('AdminProducts') . '&updateproduct&id_product=' . (int)$id_product;
            }
            Tools::redirectAdmin($link);
        }

        $this->bootstrap = true;
        $this->table = 'beslist_product';
        $this->className = 'BeslistProduct';

        $this->addRowAction('view');
        $this->addRowAction('delete');

        $cookie = Context::getContext()->cookie->getAll();
        $shopId = (int)Tools::substr($cookie['shopContext'], 2);

        $this->_join .= ' INNER JOIN `'._DB_PREFIX_.'product_lang` pl
                            ON (pl.`id_product` = a.`id_product` AND pl.`id_shop` = ' . (int)$shopId . ')
                          INNER JOIN `'._DB_PREFIX_.'product_shop` ps
                            ON (ps.`id_product` = a.`id_product` AND ps.`id_shop` = ' . (int)$shopId . ')
                          INNER JOIN `'._DB_PREFIX_.'lang` lang
                            ON (pl.`id_lang` = lang.`id_lang` AND lang.`iso_code` = \'nl\') ';

        $this->_select .= ' pl.`name` as `product_name`,
                            IF(status = 0, 1, 0) as badge_success,
                            IF(status > 0, 1, 0) as badge_danger ';

        parent::__construct();

        $this->statuses_array = array(
            BeslistProduct::STATUS_OK => $this->l('OK'),
            BeslistProduct::STATUS_STOCK_UPDATE => $this->l('Stock updated'),
            BeslistProduct::STATUS_INFO_UPDATE => $this->l('Info updated'),
            BeslistProduct::STATUS_NEW => $this->l('New')
        );

        $this->fields_list = array(
            'id_beslist_product' => array(
                'title' => $this->l('Beslist Product ID'),
                'align' => 'text-left',
                'class' => 'fixed-width-xs'
            ),
            'id_product' => array(
                'title' => $this->l('Product ID'),
                'align' => 'text-left',
                'class' => 'fixed-width-xs',
                'filter_key' => 'a!id_product'
            ),
            'product_name' => array(
                'title' => $this->l('Product'),
                'align' => 'text-left',
                'filter_key' => 'pl!name'
            ),
            'id_product_attribute' => array(
                'title' => $this->l('Product combination'),
                'align' => 'text-left',
            ),
            'published' => array(
                'title' => $this->l('Published'),
                'type' => 'bool',
                'active' => 'published',
                'align' => 'text-center',
                'class' => 'fixed-width-sm'
            ),
            'status' => array(
                'title' => $this->l('Synchronized'),
                'type' => 'select',
                'callback' => 'getSynchronizedState',
                'badge_danger' => true,
                'badge_success' => true,
                'align' => 'text-center',
                'class' => 'fixed-width-sm',
                'list' => $this->statuses_array,
                'filter_key' => 'status',
                'filter_type' => 'int'
            )
        );
    }

    /**
     * Callback for the static column in the list
     * @param int $status the status
     * @return string the status
     */
    public function getSynchronizedState($status)
    {
        return $this->statuses_array[$status];
    }

    /**
     * Overrides parent::initPageHeaderToolbar
     */
    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();
        if (!Configuration::get('BESLIST_CART_ENABLED')) {
            return;
        }
        $this->page_header_toolbar_btn['sync_products'] = array(
            'href' => self::$currentIndex . '&token=' . $this->token . '&sync_products=1',
            'desc' => $this->l('Sync products'),
            'icon' => 'process-icon-update'
        );
    }

    public function initToolbar()
    {
        $this->allow_export = true;
        parent::initToolbar();
        unset($this->toolbar_btn['new']);
    }

    public function initProcess()
    {
        if (Tools::getIsset('published'.$this->table)) {
            $this->action = 'published';
        }
        if (!$this->action) {
            parent::initProcess();
        }
    }

    /**
     * Processes the request
     */
    public function postProcess()
    {
        if ((bool)Tools::getValue('sync_products')) {
            if (!Configuration::get('BESLIST_CART_ENABLED')) {
                $this->errors[] = Tools::displayError(
                    $this->l('The Beslist Cart functionality isn\'t enabled for the current store.')
                );
                return;
            }
            self::synchronize($this->context);
            $this->confirmations[] = $this->l('Beslist products fully synchronized.');
        }
        return parent::postProcess();
    }

    public function processPublished()
    {
        /** @var BeslistProduct $beslistProduct */
        if (Validate::isLoadedObject($beslistProduct = $this->loadObject())) {
            $beslistProduct->published = $beslistProduct->published ? 0 : 1;
            $beslistProduct->save();
        }
    }

    /**
     * Synchronize changed products
     */
    public static function synchronize($context)
    {
        $beslistProducts = BeslistProduct::getUpdatedProducts();
        foreach ($beslistProducts as $beslistProduct) {
            switch ($beslistProduct->status) {
                case BeslistProduct::STATUS_STOCK_UPDATE:
                    self::processBeslistStockUpdate($beslistProduct, $context);
                    break;
                default:
                    self::processBeslistProductUpdate($beslistProduct, $context);
                    break;
            }
        }
    }

    /**
     * Set the synchronization status of a product
     * @param BeslistProduct $beslistProduct
     * @param int $status
     */
    public static function setProductStatus($beslistProduct, $status)
    {
        Db::getInstance()->update('beslist_product', array(
            'status' => (int)$status
        ), 'id_beslist_product = ' . (int)$beslistProduct->id);
    }

    /**
     * Update a thousand products to status updated
     */
    public static function setBulkProductsToUpdatedStatus()
    {
        $context = Context::getContext();
        $id_shop = $context->shop->id;
        if (!$id_shop) {
            $context->controller->errors[] = Tools::displayError(
                'You have to be in a Shop context to add all products'
            );
            return;
        }

        $update = 'UPDATE `' . _DB_PREFIX_ . 'beslist_product` 
                   SET `status` = '. (int)BeslistProduct::STATUS_INFO_UPDATE .'
                   WHERE `id_product` IN (
                       SELECT ps.`id_product`
                       FROM `'. _DB_PREFIX_ . 'product_shop` ps
                       WHERE ps.`id_shop` = '. (int) $id_shop .'
                   ) LIMIT 1000';

        Db::getInstance()->execute($update);
    }

    /**
     * Delete a product from Beslist
     * @param BeslistProduct $beslistProduct
     * @param Context $context
     */
    public static function processBeslistProductDelete($beslistProduct, $context)
    {
        self::processBeslistQuantityUpdate($beslistProduct, 0, $context);
    }

    /**
     * Update the stock on Beslist
     * @param BeslistProduct $beslistProduct
     * @param Context $context
     */
    public static function processBeslistStockUpdate($beslistProduct, $context)
    {
        $quantity = StockAvailable::getQuantityAvailableByProduct(
            $beslistProduct->id_product,
            $beslistProduct->id_product_attribute
        );
        self::processBeslistQuantityUpdate($beslistProduct, $quantity, $context);
    }

    /**
     * Update the stock on Beslist
     * @param BeslistProduct $beslistProduct
     * @param int $quantity
     * @param Context $context
     */
    public static function processBeslistQuantityUpdate($beslistProduct, $quantity, $context)
    {
        $shopIds = array();
        $shopIds = BeslistProduct::getShops($beslistProduct);

        $errors = array();

        foreach ($shopIds as $shopIdRow) {
            $shopId = $shopIdRow['id_shop'];
            if (!BeslistCart::isEnabledForShop($shopId)) {
                continue;
            }

            $client = BeslistCart::getShopitemClient($shopId);
            $beslistShopId = (int)Configuration::get('BESLIST_CART_SHOPID', null, null, $shopId);
            $matcher = (int)Configuration::get('BESLIST_CART_MATCHER', null, null, $shopId);

            $productRef = $beslistProduct->getReference($matcher);

            $options = array(
                'stock' => $quantity
            );

            if (Configuration::get('BESLIST_CART_ENABLED_NL')) {
                $delivery_time_nl = $beslistProduct->delivery_code_nl;
                if ($delivery_time_nl == '' || $quantity == 0) {
                    $delivery_time_nl = Configuration::get(
                        'BESLIST_CART_DELIVERYPERIOD' . ($quantity == 0 ? '_NOSTOCK' : '') . '_NL'
                    );
                }
                $options['delivery_time_nl'] = $delivery_time_nl;
            }

            if (Configuration::get('BESLIST_CART_ENABLED_BE')) {
                $delivery_time_be = $beslistProduct->delivery_code_be;
                if ($delivery_time_be == '' || $quantity == 0) {
                    $delivery_time_be = Configuration::get(
                        'BESLIST_CART_DELIVERYPERIOD' . ($quantity == 0 ? '_NOSTOCK' : '') . '_BE'
                    );
                }
                $options['delivery_time_be'] = $delivery_time_be;
            }

            try {
                $client->getShopItem($beslistShopId, $productRef);
            } catch (Exception $e) {
                continue;
            }

            try {
                $client->updateShopItem($beslistShopId, $productRef, $options);
            } catch (Exception $e) {
                $message = $e->getMessage();
                if (strpos($message, '404') !== false) {
                    $errors[] = Tools::displayError(
                        '[beslistcart] Couldn\'t send update to Beslist, your feed probably isn\'t processed yet.'
                    );
                } else {
                    $errors[] = Tools::displayError(
                        '[beslistcart] Couldn\'t send update to Beslist, error: ' . $message
                    );
                }
            }
        }
        if (count($errors) == 0) {
            self::setProductStatus($beslistProduct, (int)BeslistProduct::STATUS_OK);
        } else {
            $context->controller->errors = array_merge($context->controller->errors, $errors);
        }
    }

    /**
     * Update a product on Beslist
     * @param BeslistProduct $beslistProduct
     * @param Context $context
     */
    public static function processBeslistProductUpdate($beslistProduct, $context)
    {
        $price = BeslistProduct::getPriceStatic(
            $beslistProduct->id_product,
            $beslistProduct->id_product_attribute
        );
        $quantity = StockAvailable::getQuantityAvailableByProduct(
            $beslistProduct->id_product,
            $beslistProduct->id_product_attribute
        );

        $shopIds = array();
        if ($quantity > 0 || !(bool)Configuration::get('BESLIST_CART_FILTER_NO_STOCK')) {
            $shopIds = BeslistProduct::getShops($beslistProduct);
        }

        $errors = array();

        foreach ($shopIds as $shopIdRow) {
            $shopId = $shopIdRow['id_shop'];
            if (!BeslistCart::isEnabledForShop($shopId)) {
                continue;
            }

            $client = BeslistCart::getShopitemClient($shopId);

            $beslistShopId = (int) Configuration::get('BESLIST_CART_SHOPID', null, null, $shopId);
            $matcher = (int) Configuration::get('BESLIST_CART_MATCHER', null, null, $shopId);

            $productRef = $beslistProduct->getReference($matcher);
            $options = array(
                'price' => $price,
                'stock' => $beslistProduct->published ? $quantity : 0
            );

            if (Configuration::get('BESLIST_CART_ENABLED_NL')) {
                $delivery_time_nl = $beslistProduct->delivery_code_nl;
                if ($delivery_time_nl == '' || $quantity == 0) {
                    $delivery_time_nl = Configuration::get(
                        'BESLIST_CART_DELIVERYPERIOD' . ($quantity == 0 ? '_NOSTOCK' : '') . '_NL'
                    );
                }
                $options['delivery_time_nl'] = $delivery_time_nl;
            }

            if (Configuration::get('BESLIST_CART_ENABLED_NL')) {
                $delivery_time_be = $beslistProduct->delivery_code_be;
                if ($delivery_time_be == '' || $quantity == 0) {
                    $delivery_time_be = Configuration::get(
                        'BESLIST_CART_DELIVERYPERIOD' . ($quantity == 0 ? '_NOSTOCK' : '') . '_BE'
                    );
                }
                $options['delivery_time_be'] = $delivery_time_be;
            }

            try {
                $client->getShopItem($beslistShopId, $productRef);
            } catch (Exception $e) {
                continue;
            }

            try {
                $client->updateShopItem($beslistShopId, $productRef, $options);
            } catch (Exception $e) {
                $message = $e->getMessage();
                if (strpos($message, '404') !== false) {
                    $errors[] = Tools::displayError(
                        '[beslistcart] Couldn\'t send update to Beslist, your feed probably isn\'t processed yet.'
                    );
                } else {
                    $errors[] = Tools::displayError(
                        '[beslistcart] Couldn\'t send update to Beslist, error: ' . $message
                    );
                }
            }
        }
        if (count($errors) == 0) {
            self::setProductStatus($beslistProduct, (int)BeslistProduct::STATUS_OK);
        } else {
            $context->controller->errors = array_merge($context->controller->errors, $errors);
        }
    }

    /**
     * Adds all products to Beslist, uses the default settings
     */
    public static function addAllProducts()
    {
        $context = Context::getContext();
        $id_shop = $context->shop->id;
        if (!$id_shop) {
            $context->controller->errors[] = Tools::displayError(
                'You have to be in a Shop context to add all products'
            );
            return;
        }

        $insert = 'INSERT INTO `' . _DB_PREFIX_ . 'beslist_product` (
                      id_product, 
                      id_product_attribute,
                      published, 
                      delivery_code_nl, 
                      delivery_code_be
                    )
                    SELECT p.`id_product`, 
                        IFNULL(pa.`id_product_attribute`, 0) as id_product_attribute, 
                        1 as published,
                        \'\' as delivery_code_nl,
                        \'\' as delivery_code_be
                    FROM `' . _DB_PREFIX_ . 'product` p
                    INNER JOIN `' . _DB_PREFIX_ . 'product_shop` ps ON p.`id_product` = ps.`id_product`
                    AND ps.`id_shop` = ' . (int)$id_shop . '
                    LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` pa ON pa.`id_product` = p.`id_product`
                   ON DUPLICATE KEY UPDATE id_product = p.id_product';
        $result = Db::getInstance()->execute($insert);
        if ($result) {
            $context->controller->confirmations[] = $context->controller->l(
                'All products and combinations were marked to be added to Beslist'
            );
        }
    }
}
