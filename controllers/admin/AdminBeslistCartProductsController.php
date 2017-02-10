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
 * @copyright 2013-2017 Wienk IT
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
        if ($id_product = Tools::getValue('id_product')) {
            Tools::redirectAdmin(
                Context::getContext()
                    ->link
                    ->getAdminLink('AdminProducts') . '&updateproduct&id_product=' . (int)$id_product
            );
        }

        $this->bootstrap = true;
        $this->table = 'beslist_product';
        $this->className = 'BeslistProduct';

        $this->addRowAction('view');

        $this->identifier = 'id_product';

        $cookie = Context::getContext()->cookie->getAll();
        $shopId = (int)substr($cookie['shopContext'], 2);

        $this->_join .= ' INNER JOIN `'._DB_PREFIX_.'product_lang` pl
                            ON (pl.`id_product` = a.`id_product` 
                            AND pl.`id_shop` = ' . $shopId . ') 
                          INNER JOIN `'._DB_PREFIX_.'lang` lang
                            ON (pl.`id_lang` = lang.`id_lang` AND lang.`iso_code` = \'nl\') ';

        $this->_select .= ' pl.`name` as `product_name`,
                            IF(status = 0, 1, 0) as badge_success,
                            IF(status > 0, 1, 0) as badge_danger ';

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

        parent::__construct();
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
     * Overrides parent::displayViewLink
     */
    public function displayViewLink($token = null, $id = 0, $name = null)
    {
        if ($this->tabAccess['view'] == 1) {
            $tpl = $this->createTemplate('helpers/list/list_action_view.tpl');
            if (!array_key_exists('View', self::$cache_lang)) {
                self::$cache_lang['View'] = $this->l('View', 'Helper');
            }

            $tpl->assign(array(
                'href' => $this->context->link->getAdminLink('AdminProducts') . '&updateproduct&id_product=' . (int)$id,
                'action' => self::$cache_lang['View'],
                'id' => $id
            ));

            return $tpl->fetch();
        } else {
            return;
        }
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
        DB::getInstance()->update('beslist_product', array(
            'status' => (int)$status
        ), 'id_beslist_product = ' . (int)$beslistProduct->id);
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
        $shopsIds = Product::getShopsByProduct($beslistProduct->id_product);
        $errors = array();

        foreach ($shopsIds as $shopId) {
            if (!BeslistCart::isEnabledForShop($shopId)) {
                continue;
            }
            $shopId = $shopId['id_shop'];
            $client = BeslistCart::getShopitemClient(null, $shopId);

            $beslistShopId = Configuration::get('BESLIST_CART_SHOPID', null, $shopId);
            $productRef = $beslistProduct->getReference();

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
        /** @var Adapter_ProductPriceCalculator $price_calculator */
        $price_calculator = Adapter_ServiceLocator::get('Adapter_ProductPriceCalculator');
        $price = $price_calculator->getProductPrice(
            (int)$beslistProduct->id_product,
            true,
            (int)$beslistProduct->id_product_attribute
        );

        $quantity = StockAvailable::getQuantityAvailableByProduct(
            $beslistProduct->id_product,
            $beslistProduct->id_product_attribute
        );

        $shopsIds = Product::getShopsByProduct($beslistProduct->id_product);
        $errors = array();

        foreach ($shopsIds as $shopId) {
            if (!BeslistCart::isEnabledForShop($shopId)) {
                continue;
            }
            $shopId = $shopId['id_shop'];
            $client = BeslistCart::getShopitemClient(null, $shopId);

            $beslistShopId = Configuration::get('BESLIST_CART_SHOPID', null, $shopId);

            $productRef = $beslistProduct->getReference();
            $options = array(
                'price' => $price,
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
                      id_beslist_category, 
                      published, 
                      delivery_code_nl, 
                      delivery_code_be
                    )
                    SELECT p.`id_product`, 
                        IFNULL(pa.`id_product_attribute`, 0) as id_product_attribute, 
                        0 as id_beslist_category,
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

    /**
     * Sets the category of all products to the closest Beslist category
     */
    public static function setDefaultCategories($overwrite = false)
    {
        $sql = 'SELECT 
                  bp.id_beslist_product as id_beslist_product,
                  bc.id_beslist_category,
                  parent.nright - parent.nleft as distance
                FROM `'._DB_PREFIX_.'beslist_product` bp
                INNER JOIN `'._DB_PREFIX_.'product` p 
                    ON p.id_product = bp.id_product
                INNER JOIN `'._DB_PREFIX_.'category_product`cp 
                    ON bp.id_product = cp.id_product
                INNER JOIN `'._DB_PREFIX_.'category` category 
                    ON category.id_category = cp.id_category
                INNER JOIN `'._DB_PREFIX_.'category` parent 
                    ON parent.nleft <= category.nleft 
                    AND parent.nright >= category.nright
                INNER JOIN `'._DB_PREFIX_.'beslist_category` bc 
                    ON parent.id_category = bc.id_category
                LEFT JOIN `'._DB_PREFIX_.'beslist_category` default_category
                    ON p.id_category_default = default_category.id_category
                WHERE cp.id_category != p.id_category_default
                AND default_category.id_category IS NULL';
        if (!$overwrite) {
            $sql .= ' AND (bp.id_beslist_category IS NULL
                     OR bp.id_beslist_category = 0)';
        }
        $sql .= ' ORDER BY id_beslist_product, distance';
        $rows = Db::getInstance()->executeS($sql);
        $current = 0;
        $total = 0;
        foreach ($rows as $row) {
            if ($row['id_beslist_product'] != $current) {
                $current = $row['id_beslist_product'];
                $updateSql = 'UPDATE `'._DB_PREFIX_.'beslist_product` 
                    SET `id_beslist_category` = ' . (int) $row['id_beslist_category'] . ' 
                    WHERE `id_beslist_product` = ' . (int) $row['id_beslist_product'];
                $result = Db::getInstance()->execute($updateSql);
                if ($result) {
                    $total++;
                }
            }
        }
        $context = Context::getContext();
        $context->controller->confirmations[] = $context->controller->l(
            'Updated categories, total: '
        ) . $total;
    }
}
