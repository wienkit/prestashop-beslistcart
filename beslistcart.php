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
require_once _PS_MODULE_DIR_ . 'beslistcart/classes/BeslistProduct.php';
require_once _PS_MODULE_DIR_ . 'beslistcart/classes/BeslistFeed.php';
require_once _PS_MODULE_DIR_ . 'beslistcart/controllers/admin/AdminBeslistCartProductsController.php';

class BeslistCart extends Module
{
    const BESLIST_MATCH_REFERENCE = 1;
    const BESLIST_MATCH_EAN13 = 2;
    const BESLIST_MATCH_DEFAULT = 3;
    const BESLIST_MATCH_STORECOMMANDER = 4;

    public function __construct()
    {
        $this->name = 'beslistcart';
        $this->tab = 'market_place';
        $this->version = '1.4.0';
        $this->author = 'Wienk IT';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;
        $this->module_key = 'eff58cb5fb5aafdbb330323b300d3331';
        $this->display = 'view';

        parent::__construct();

        $this->displayName = $this->l('Beslist.nl Shopping Cart integration');
        $this->description = $this->l(
            'Connect to Beslist.nl to synchronize your Beslist.nl Shopping Cart 
            orders and products with your Prestashop website.'
        );

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    /**
     * Overrides parent::install()
     *
     * {@inheritdoc}
     *
     * @throws PrestaShopException
     */
    public function install()
    {
        if (parent::install()) {
            return $this->installDb()
                && $this->installOrderState()
                && $this->installOrdersTab()
                && $this->installProductsTab()
                && $this->registerHook('actionAdminControllerSetMedia')
                && $this->registerHook('actionProductUpdate')
                && $this->registerHook('actionUpdateQuantity')
                && $this->registerHook('actionObjectBeslistProductDeleteAfter')
                && $this->registerHook('actionObjectBeslistProductUpdateAfter')
                && $this->registerHook('displayAdminProductsExtra')
                && $this->registerHook('displayBackOfficeCategory')
                && $this->registerHook('actionObjectCategoryDeleteAfter')
                && $this->registerHook('actionAdminCategoriesControllerSaveBefore');
        }
        return false;
    }

    /**
     * Overrides parent::uninstall()
     *
     * {@inheritdoc}
     */
    public function uninstall()
    {
        return $this->uninstallDb()
            && $this->uninstallTabs()
            && $this->uninstallOrderState()
            && $this->unregisterHook('actionAdminControllerSetMedia')
            && $this->unregisterHook('actionProductUpdate')
            && $this->unregisterHook('actionUpdateQuantity')
            && $this->unregisterHook('actionObjectBeslistProductDeleteAfter')
            && $this->unregisterHook('actionObjectBeslistProductUpdateAfter')
            && $this->unregisterHook('displayAdminProductsExtra')
            && $this->unregisterHook('displayBackOfficeCategory')
            && $this->unregisterHook('actionObjectCategoryDeleteAfter')
            && $this->unregisterHook('actionAdminCategoriesControllerSaveBefore')
            && parent::uninstall();
    }

    /**
     * Install the database tables
     * @return bool success
     */
    public function installDb()
    {
        $sql = array();
        $return = true;
        include(dirname(__FILE__) . '/sql_install.php');
        foreach ($sql as $s) {
            $return &= Db::getInstance()->execute($s);
        }
        return $return;
    }

    /**
     * Remove the database tables
     * @return bool success
     */
    public function uninstallDb()
    {
        $sql = array();
        include(dirname(__FILE__) . '/sql_install.php');
        foreach (array_keys($sql) as $name) {
            Db::getInstance()->execute('DROP TABLE IF EXISTS ' . pSQL($name));
        }
        return true;
    }

    /**
     * Install orders menu item
     * @return bool success
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function installOrdersTab()
    {
        $ordersTab = new Tab();
        $ordersTab->active = 1;
        $ordersTab->name = array();
        $ordersTab->class_name = 'AdminBeslistCartOrders';

        foreach (Language::getLanguages(true) as $lang) {
            $ordersTab->name[$lang['id_lang']] = 'Beslist.nl orders';
        }

        $ordersTab->id_parent = (int)Tab::getIdFromClassName('AdminParentOrders');
        $ordersTab->module = $this->name;

        return $ordersTab->add();
    }

    /**
     * Install the products menu item
     *
     * @throws PrestaShopException
     */
    public function installProductsTab()
    {
        $productsTab = new Tab();
        $productsTab->active = 1;
        $productsTab->name = array();
        $productsTab->class_name = 'AdminBeslistCartProducts';

        foreach (Language::getLanguages(true) as $lang) {
            $productsTab->name[$lang['id_lang']] = 'Beslist.nl products';
        }

        $productsTab->id_parent = (int)Tab::getIdFromClassName('AdminCatalog');
        $productsTab->module = $this->name;

        return $productsTab->add();
    }

    /**
     * Remove menu items
     * @return bool success
     *
     * @throws PrestaShopException
     */
    public function uninstallTabs()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminBeslistCartOrders');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            if (!$tab->delete()) {
                return false;
            }
        }
        $id_tab = (int)Tab::getIdFromClassName('AdminBeslistCartProducts');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            if (!$tab->delete()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Install a new order state for beslist orders
     * @return bool success
     *
     * @throws PrestaShopException
     */
    public function installOrderState()
    {
        $orderStateName = 'Beslist order imported';
        foreach (Language::getLanguages(true) as $lang) {
            $order_states = OrderState::getOrderStates($lang['id_lang']);
            foreach ($order_states as $state) {
                if ($state['name'] == $orderStateName) {
                    $order_state = new OrderState($state['id_order_state']);
                    $order_state->hidden = false;
                    $order_state->save();
                    Configuration::updateValue(
                        'BESLIST_CART_ORDERS_INITIALSTATE',
                        $state['id_order_state'],
                        false,
                        null,
                        null
                    );
                    return true;
                }
            }
        }

        $order_state = new OrderState();
        $order_state->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $order_state->name[$lang['id_lang']] = $orderStateName;
        }

        $order_state->send_email = false;
        $order_state->module_name = $this->name;
        $order_state->invoice = false;
        $order_state->logable = true;
        $order_state->shipped = false;
        $order_state->unremovable = true;
        $order_state->delivery = false;
        $order_state->paid = true;
        $order_state->pdf_invoice = false;
        $order_state->pdf_delivery = false;
        $order_state->color = '#32CD32';
        $order_state->hidden = false;
        $order_state->deleted = false;
        $order_state->add();
        Configuration::updateValue(
            'BESLIST_CART_ORDERS_INITIALSTATE',
            $order_state->id,
            false,
            null,
            null
        );
        return true;
    }

    /**
     * Uninstall the order state for beslist orders
     * @return bool success
     *
     * @throws PrestaShopException
     */
    public function uninstallOrderState()
    {
        $order_state = new OrderState(Configuration::get('BESLIST_CART_ORDERS_INITIALSTATE'));
        $order_state->hidden = true;
        $order_state->save();
        return true;
    }

    /**
     * Render the module configuration page
     * @return string the rendered page
     */
    public function getContent()
    {
        $status = '';

        if (Tools::isSubmit('submit' . $this->name)) {
            $attribute_size = (int)Tools::getValue('beslist_cart_attribute_size');
            $attribute_color = (int)Tools::getValue('beslist_cart_attribute_color');
            $use_long_descriptions = (bool)Tools::getValue('beslist_cart_use_long_description');
            $attributes_in_title = (bool)Tools::getValue('beslist_cart_attributes_in_title');
            $matcher = (int)Tools::getvalue('beslist_cart_matcher');
            $filterNoStock = (bool)Tools::getValue('beslist_cart_filter_no_stock');
            $feedLocation = (string) Tools::getValue('beslist_cart_feed_location');

            $enabled_nl = (bool)Tools::getValue('beslist_cart_enabled_nl');
            $carrier_nl = (int)Tools::getValue('beslist_cart_carrier_nl');
            $deliveryperiod_nl = (string)Tools::getValue('beslist_cart_deliveryperiod_nl');
            $deliveryperiod_nostock_nl = (string)Tools::getValue('beslist_cart_deliveryperiod_nostock_nl');

            $enabled_be = (bool)Tools::getValue('beslist_cart_enabled_be');
            $carrier_be = (int)Tools::getValue('beslist_cart_carrier_be');
            $deliveryperiod_be = (string)Tools::getValue('beslist_cart_deliveryperiod_be');
            $deliveryperiod_nostock_be = (string)Tools::getValue('beslist_cart_deliveryperiod_nostock_be');

            $cartEnabled = (bool)Tools::getValue('beslist_cart_enabled');
            $shopid = (int)Tools::getValue('beslist_cart_shopid');
            $clientid = (int)Tools::getValue('beslist_cart_clientid');
            $personalkey = (string)Tools::getValue('beslist_cart_personalkey');
            $shopitemKey = (string)Tools::getValue('beslist_cart_shopitem_apikey');
            $customerGroup = (int) Tools::getValue('beslist_cart_customer_group');
            $testmode = (bool)Tools::getValue('beslist_cart_testmode');
            $test_reference = (string)Tools::getValue('beslist_cart_test_reference');
            $startDate = (string)Tools::getValue('beslist_cart_startdate');
            $useAddress2 = (bool) Tools::getValue('beslist_cart_use_address2');

            $add_products = (bool)Tools::getValue('beslist_cart_add_all_products');
            $update_bulk_status = (bool)Tools::getValue('beslist_cart_update_bulk_status');

            if ($cartEnabled && (!$personalkey
                || $shopid == 0
                || $clientid == 0
                || $matcher == 0
                || empty($personalkey)
                || empty($startDate)
                || empty($shopitemKey)
                || empty($customerGroup)
                || empty($feedLocation)
                || ($enabled_nl && empty($carrier_nl))
                || ($enabled_nl && empty($deliveryperiod_nl))
                || ($enabled_nl && empty($deliveryperiod_nostock_nl))
                || ($enabled_be && empty($carrier_be))
                || ($enabled_be && empty($deliveryperiod_be))
                || ($enabled_be && empty($deliveryperiod_nostock_be)))
            ) {
                $status .= $this->displayError($this->l('Invalid Configuration value'));
            } else {
                Configuration::updateValue('BESLIST_CART_ATTRIBUTE_SIZE', $attribute_size);
                Configuration::updateValue('BESLIST_CART_ATTRIBUTE_COLOR', $attribute_color);
                Configuration::updateValue('BESLIST_CART_USE_LONG_DESCRIPTION', $use_long_descriptions);
                Configuration::updateValue('BESLIST_CART_ATTRIBUTES_IN_TITLE', $attributes_in_title);
                Configuration::updateValue('BESLIST_CART_MATCHER', $matcher);
                Configuration::updateValue('BESLIST_CART_FILTER_NO_STOCK', $filterNoStock);
                Configuration::updateValue('BESLIST_CART_FEED_LOCATION', $feedLocation);

                Configuration::updateValue('BESLIST_CART_ENABLED_NL', $enabled_nl);
                Configuration::updateValue('BESLIST_CART_CARRIER_NL', $carrier_nl);
                Configuration::updateValue('BESLIST_CART_DELIVERYPERIOD_NL', $deliveryperiod_nl);
                Configuration::updateValue('BESLIST_CART_DELIVERYPERIOD_NOSTOCK_NL', $deliveryperiod_nostock_nl);

                Configuration::updateValue('BESLIST_CART_ENABLED_BE', $enabled_be);
                Configuration::updateValue('BESLIST_CART_CARRIER_BE', $carrier_be);
                Configuration::updateValue('BESLIST_CART_DELIVERYPERIOD_BE', $deliveryperiod_be);
                Configuration::updateValue('BESLIST_CART_DELIVERYPERIOD_NOSTOCK_BE', $deliveryperiod_nostock_be);

                Configuration::updateValue('BESLIST_CART_ENABLED', $cartEnabled);
                Configuration::updateValue('BESLIST_CART_SHOPID', $shopid);
                Configuration::updateValue('BESLIST_CART_CLIENTID', $clientid);
                Configuration::updateValue('BESLIST_CART_PERSONALKEY', $personalkey);
                Configuration::updateValue('BESLIST_CART_SHOPITEM_APIKEY', $shopitemKey);
                Configuration::updateValue('BESLIST_CART_CUSTOMER_GROUP', $customerGroup);
                Configuration::updateValue('BESLIST_CART_TESTMODE', $testmode);
                Configuration::updateValue('BESLIST_CART_TEST_REFERENCE', $test_reference);
                Configuration::updateValue('BESLIST_CART_STARTDATE', $startDate);
                Configuration::updateValue('BESLIST_CART_USE_ADDRESS2', $useAddress2);

                $status .= $this->displayConfirmation($this->l('Settings updated'));
            }

            if ($update_bulk_status) {
                AdminBeslistCartProductsController::setBulkProductsToUpdatedStatus();
            }
            if ($add_products) {
                AdminBeslistCartProductsController::addAllProducts();
            }
        }

        $intro = $this->getContentIntro();
        return $intro . $status . $this->displayForm();
    }

    /**
     * Render a form on the module configuration page
     * @return string the form
     */
    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $carriers = Carrier::getCarriers(Context::getContext()->language->id);
        $attributes = AttributeGroup::getAttributesGroups(Context::getContext()->language->id);
        $customer_groups = Group::getGroups(Context::getContext()->language->id);

        array_unshift($attributes, array(
            'id_attribute_group' => 0,
            'name' => $this->l('--- None ---')
        ));

        $feedfile = $this->getDefaultFeedFilename();
        $feed_loc = $this->getDefaultFeedLocation($feedfile);

        // Init Fields form array
        $fields_form = array();
        $fields_form[]['form'] = array(
            'legend' => array(
                'title' => $this->l('Beslist Productfeed Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'select',
                    'label' => $this->l('Default size attribute (optional)'),
                    'desc' => $this->l('Select your size attribute'),
                    'name' => 'beslist_cart_attribute_size',
                    'options' => array(
                        'query' => $attributes,
                        'id' => 'id_attribute_group',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Default color attribute (optional)'),
                    'desc' => $this->l('Select your color attribute'),
                    'name' => 'beslist_cart_attribute_color',
                    'options' => array(
                        'query' => $attributes,
                        'id' => 'id_attribute_group',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Use long descriptions in feed.'),
                    'name' => 'beslist_cart_use_long_description',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'beslist_cart_use_long_description_1',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ),
                        array(
                            'id' => 'beslist_cart_use_long_description_0',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                    'hint' => $this->l(
                        'Uses long descriptions in the feed. Note that you should not '.
                        'be using HTML markup in the content.'
                    )
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Use attributes in title.'),
                    'name' => 'beslist_cart_attributes_in_title',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'beslist_cart_attributes_in_title_1',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ),
                        array(
                            'id' => 'beslist_cart_attributes_in_title_0',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                    'hint' => $this->l('Shows attributes in title unless they are mapped as size or color.')
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Beslist product matcher field'),
                    'desc' => $this->l('Select the unique field you want to use to match your products.'),
                    'name' => 'beslist_cart_matcher',
                    'options' => array(
                        'query' => array(
                            array(
                                'id_matcher' => self::BESLIST_MATCH_DEFAULT,
                                'name' => $this->l('Default ([$combinationid-]$productid)')
                            ),
                            array(
                                'id_matcher' => self::BESLIST_MATCH_REFERENCE,
                                'name' => $this->l('Product reference')
                            ),
                            array(
                                'id_matcher' => self::BESLIST_MATCH_EAN13,
                                'name' => $this->l('EAN-13')
                            ),
                            array(
                                'id_matcher' => self::BESLIST_MATCH_STORECOMMANDER,
                                'name' => $this->l('Storecommander ($productid[_$combinationid])')
                            ),
                        ),
                        'id' => 'id_matcher',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Filter products without stock from feed'),
                    'name' => 'beslist_cart_filter_no_stock',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'beslist_cart_filter_no_stock_1',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ),
                        array(
                            'id' => 'beslist_cart_filter_no_stock_0',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                    'hint' => $this->l('Removes the items without stock from your productfeed.')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Productfeed location'),
                    'desc' => sprintf($this->l('Use a local server path (e.g. %s)'), $feed_loc),
                    'name' => 'beslist_cart_feed_location',
                    'size' => 20
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Enable Beslist.nl integration'),
                    'name' => 'beslist_cart_enabled_nl',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'beslist_cart_nl_enabled_1',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ),
                        array(
                            'id' => 'beslist_cart_nl_enabled_0',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                    'hint' => $this->l('Publish on Beslist.nl.')
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Carrier'),
                    'desc' => $this->l('Choose a carrier for your Beslist.nl orders'),
                    'name' => 'beslist_cart_carrier_nl',
                    'options' => array(
                        'query' => $carriers,
                        'id' => 'id_reference',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Delivery period'),
                    'desc' => $this->l('Use one of the following:') . '<br />'
                        . $this->l('x werkdag(en)') . '<br />'
                        . $this->l('x tot x werkdagen') . '<br />'
                        . $this->l('x tot x weken') . '<br />'
                        . $this->l('Op werkdagen voor xx:xx uur besteld, volgende dag in huis!') . '<br />'
                        . $this->l('Direct te downloaden') . '<br />'
                        . $this->l('Niet op voorraad') . '<br />'
                        . $this->l('Pre-order'),
                    'name' => 'beslist_cart_deliveryperiod_nl',
                    'size' => 20
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Delivery period when not it stock'),
                    'desc' => $this->l('Use one of the following:') . '<br />'
                        . $this->l('x werkdag(en)') . '<br />'
                        . $this->l('x tot x werkdagen') . '<br />'
                        . $this->l('x tot x weken') . '<br />'
                        . $this->l('Op werkdagen voor xx:xx uur besteld, volgende dag in huis!') . '<br />'
                        . $this->l('Direct te downloaden') . '<br />'
                        . $this->l('Niet op voorraad') . '<br />'
                        . $this->l('Pre-order'),
                    'name' => 'beslist_cart_deliveryperiod_nostock_nl',
                    'size' => 20
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Enable Beslist.be integration'),
                    'name' => 'beslist_cart_enabled_be',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'beslist_cart_be_enabled_1',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ),
                        array(
                            'id' => 'beslist_cart_be_enabled_0',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                    'hint' => $this->l('Publish on Beslist.be.')
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Carrier'),
                    'desc' => $this->l('Choose a carrier for your Beslist.be orders'),
                    'name' => 'beslist_cart_carrier_be',
                    'options' => array(
                        'query' => $carriers,
                        'id' => 'id_reference',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Delivery period'),
                    'desc' => $this->l('Use one of the following:') . '<br />'
                        . $this->l('x werkdag(en)') . '<br />'
                        . $this->l('x tot x werkdagen') . '<br />'
                        . $this->l('x tot x weken') . '<br />'
                        . $this->l('Op werkdagen voor xx:xx uur besteld, volgende dag in huis!') . '<br />'
                        . $this->l('Direct te downloaden') . '<br />'
                        . $this->l('Niet op voorraad') . '<br />'
                        . $this->l('Pre-order'),
                    'name' => 'beslist_cart_deliveryperiod_be',
                    'size' => 20
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Delivery period when not it stock'),
                    'desc' => $this->l('Use one of the following:') . '<br />'
                        . $this->l('x werkdag(en)') . '<br />'
                        . $this->l('x tot x werkdagen') . '<br />'
                        . $this->l('x tot x weken') . '<br />'
                        . $this->l('Op werkdagen voor xx:xx uur besteld, volgende dag in huis!') . '<br />'
                        . $this->l('Direct te downloaden') . '<br />'
                        . $this->l('Niet op voorraad') . '<br />'
                        . $this->l('Pre-order'),
                    'name' => 'beslist_cart_deliveryperiod_nostock_be',
                    'size' => 20
                )
            ),
        );
        $fields_form[]['form'] = array(
            'legend' => array(
                'title' => $this->l('Beslist Shopping Cart Settings'),
            ),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->l('Enable Beslist Shopping Cart integration'),
                    'name' => 'beslist_cart_enabled',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'beslist_cart_enabled_1',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ),
                        array(
                            'id' => 'beslist_cart_enabled_0',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                    'hint' => $this->l('You can enable the connector per shop.')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Shop ID'),
                    'desc' => $this->l('Beslist.nl Order API Shop ID'),
                    'name' => 'beslist_cart_shopid',
                    'required' => true,
                    'size' => 20
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Client ID'),
                    'desc' => $this->l('Beslist.nl Order API Client ID'),
                    'required' => true,
                    'name' => 'beslist_cart_clientid'
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Personal key'),
                    'desc' => $this->l('Beslist.nl Order API Personal key'),
                    'name' => 'beslist_cart_personalkey',
                    'required' => true,
                    'size' => 20
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Shopitem API key'),
                    'desc' => $this->l('Beslist.nl ShopItem API key'),
                    'name' => 'beslist_cart_shopitem_apikey',
                    'required' => true,
                    'size' => 20
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Customer group'),
                    'desc' => $this->l('Choose a customer group for your Beslist customers'),
                    'name' => 'beslist_cart_customer_group',
                    'options' => array(
                        'query' => $customer_groups,
                        'id' => 'id_group',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Use test connection'),
                    'name' => 'beslist_cart_testmode',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'beslist_cart_testmode_1',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ),
                        array(
                            'id' => 'beslist_cart_testmode_0',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                    'hint' => $this->l('Enables the testing connection.')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Test product reference #'),
                    'desc' => $this->l('This product reference will be used for the testing connection'),
                    'name' => 'beslist_cart_test_reference'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Order from date'),
                    'desc' => $this->l('Use this field to override the from date (format: 2016-12-31).'),
                    'name' => 'beslist_cart_startdate',
                    'required' => true,
                    'size' => 20
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Housenumber in address2'),
                    'name' => 'beslist_cart_use_address2',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'beslist_cart_use_address2_1',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ),
                        array(
                            'id' => 'beslist_cart_use_address2_0',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                    'desc' => $this->l('Won\'t append housenumber to street but uses separate field for housenumber')
                )
            )
        );

        $fields_form[]['form'] = array(
            'legend' => array(
                'title' => $this->l('Bulk operations')
            ),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->l('Add all products'),
                    'name' => 'beslist_cart_add_all_products',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'beslist_cart_add_all_products_1',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ),
                        array(
                            'id' => 'beslist_cart_add_all_products_0',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                    'hint' => $this->l('Add all product to Beslist.'),
                    'desc' => $this->l('Mark all products to be added to Beslist, using the default settings.')
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Set 1000 products as updated'),
                    'name' => 'beslist_cart_update_bulk_status',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'beslist_cart_update_bulk_status_1',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ),
                        array(
                            'id' => 'beslist_cart_update_bulk_status_0',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                    'hint' => $this->l('Send products on next run.'),
                    'desc' => $this->l('Set 1000 products as updated, so they are sent to Beslist on the next run.')
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
                'class' => 'btn btn-default pull-right'
            )
        );

        $helper = new HelperForm();

        // Module, token and currentIndex
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit' . $this->name;
        $helper->toolbar_btn = array(
            'save' =>
                array(
                    'desc' => $this->l('Save'),
                    'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&save' . $this->name .
                        '&token=' . Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex . '&token=' . Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
            )
        );

        // Load current value
        $helper->fields_value['beslist_cart_attribute_size'] = Configuration::get('BESLIST_CART_ATTRIBUTE_SIZE');
        $helper->fields_value['beslist_cart_attribute_color'] = Configuration::get('BESLIST_CART_ATTRIBUTE_COLOR');
        $helper->fields_value['beslist_cart_use_long_description'] =
            Configuration::get('BESLIST_CART_USE_LONG_DESCRIPTION');
        $helper->fields_value['beslist_cart_attributes_in_title'] =
            Configuration::get('BESLIST_CART_ATTRIBUTES_IN_TITLE');
        $helper->fields_value['beslist_cart_matcher'] = Configuration::get('BESLIST_CART_MATCHER');
        $helper->fields_value['beslist_cart_filter_no_stock'] = Configuration::get('BESLIST_CART_FILTER_NO_STOCK');
        $feed_location = Configuration::get('BESLIST_CART_FEED_LOCATION');
        if (empty($feed_location)) {
            $feed_location = $this->getDefaultFeedLocation($this->getDefaultFeedFilename());
        }
        $helper->fields_value['beslist_cart_feed_location'] = $feed_location;

        $helper->fields_value['beslist_cart_enabled_nl'] = Configuration::get('BESLIST_CART_ENABLED_NL');
        $helper->fields_value['beslist_cart_carrier_nl'] = Configuration::get('BESLIST_CART_CARRIER_NL');
        $helper->fields_value['beslist_cart_deliveryperiod_nl'] = Configuration::get('BESLIST_CART_DELIVERYPERIOD_NL');
        $helper->fields_value['beslist_cart_deliveryperiod_nostock_nl'] =
            Configuration::get('BESLIST_CART_DELIVERYPERIOD_NOSTOCK_NL');

        $helper->fields_value['beslist_cart_enabled_be'] = Configuration::get('BESLIST_CART_ENABLED_BE');
        $helper->fields_value['beslist_cart_carrier_be'] = Configuration::get('BESLIST_CART_CARRIER_BE');
        $helper->fields_value['beslist_cart_deliveryperiod_be'] = Configuration::get('BESLIST_CART_DELIVERYPERIOD_BE');
        $helper->fields_value['beslist_cart_deliveryperiod_nostock_be'] =
            Configuration::get('BESLIST_CART_DELIVERYPERIOD_NOSTOCK_BE');

        $helper->fields_value['beslist_cart_enabled'] = Configuration::get('BESLIST_CART_ENABLED');
        $helper->fields_value['beslist_cart_shopid'] = Configuration::get('BESLIST_CART_SHOPID');
        $helper->fields_value['beslist_cart_clientid'] = Configuration::get('BESLIST_CART_CLIENTID');
        $helper->fields_value['beslist_cart_personalkey'] = Configuration::get('BESLIST_CART_PERSONALKEY');
        $helper->fields_value['beslist_cart_shopitem_apikey'] = Configuration::get('BESLIST_CART_SHOPITEM_APIKEY');
        $customerGroup = Configuration::get('BESLIST_CART_CUSTOMER_GROUP');
        if (empty($customerGroup)) {
            $customerGroup = Configuration::get('PS_CUSTOMER_GROUP');
        }
        $helper->fields_value['beslist_cart_customer_group'] = $customerGroup;
        $helper->fields_value['beslist_cart_testmode'] = Configuration::get('BESLIST_CART_TESTMODE');
        $helper->fields_value['beslist_cart_test_reference'] = Configuration::get('BESLIST_CART_TEST_REFERENCE');
        $helper->fields_value['beslist_cart_startdate'] = Configuration::get('BESLIST_CART_STARTDATE');
        $helper->fields_value['beslist_cart_use_address2'] = Configuration::get('BESLIST_CART_USE_ADDRESS2');
        if (empty($helper->fields_value['beslist_cart_startdate'])) {
            $helper->fields_value['beslist_cart_startdate'] = date('Y-m-d');
        }

        $helper->fields_value['beslist_cart_add_all_products'] = 0;
        $helper->fields_value['beslist_cart_update_bulk_status'] = 0;

        return $helper->generateForm($fields_form);
    }

    /**
     * Add a new tab to the product page
     * Executes hook: displayAdminProductsExtra
     *
     * @param array $params
     *
     * @return string the form
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        if (!Configuration::get('BESLIST_CART_ENABLED_NL') && !Configuration::get('BESLIST_CART_ENABLED_BE')) {
            return $this->display(__FILE__, 'views/templates/admin/disabled.tpl');
        }
        $product = null;
        if ($id_product = (int)Tools::getValue('id_product')) {
            $product = new Product($id_product, true, $this->context->language->id, $this->context->shop->id);
        } elseif (isset($params['id_product'])) {
            $product = new Product($params['id_product'], true, $this->context->language->id, $this->context->shop->id);
        }
        if ($product == null || !Validate::isLoadedObject($product)) {
            return;
        }

        $attributes = $product->getAttributesResume($this->context->language->id);

        if (empty($attributes)) {
            $attributes[] = array(
                'id_product' => $product->id,
                'id_product_attribute' => 0,
                'attribute_designation' => $product->name
            );
        }

        $product_designation = array();

        foreach ($attributes as $attribute) {
            $product_designation[$attribute['id_product_attribute']] = $attribute['attribute_designation'];
        }

        $beslistProducts = BeslistProduct::getByProductId($id_product);
        $indexedBeslistProducts = array();
        foreach ($beslistProducts as $beslistProduct) {
            $indexedBeslistProducts[$beslistProduct['id_product_attribute']] = $beslistProduct;
        }

        $this->context->smarty->assign(array(
            'attributes' => $attributes,
            'product_designation' => $product_designation,
            'product' => $product,
            'beslist_products' => $indexedBeslistProducts
        ));

        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            return $this->display(__FILE__, 'views/templates/admin/beslistproduct-panel.tpl');
        }
        return $this->display(__FILE__, 'views/templates/admin/beslistproduct-tab.tpl');
    }

    /**
     * Process BeslistProduct entities added on the product page
     * Executes hook: actionProductUpdate
     *
     * @param array $params
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionProductUpdate($params)
    {
        if ((int)Tools::getValue('beslistcart_loaded') === 1
            && (Configuration::get('BESLIST_CART_ENABLED_NL') || Configuration::get('BESLIST_CART_ENABLED_BE'))
            && Validate::isLoadedObject($product = new Product((int)$params['id_product']))
        ) {
            $this->processBeslistProductEntities($product);
        }
    }

    /**
     * Process the Beslist.nl products for a product
     *
     * @param Product $product
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function processBeslistProductEntities($product)
    {
        // Get all id_product_attribute
        $attributes = $product->getAttributesResume($this->context->language->id);
        if (empty($attributes)) {
            $attributes[] = array(
                'id_product_attribute' => 0,
                'attribute_designation' => ''
            );
        }

        $beslistProducts = BeslistProduct::getByProductId($product->id);

        $indexedBeslistProducts = array();
        foreach ($beslistProducts as $beslistProduct) {
            $indexedBeslistProducts[$beslistProduct['id_product_attribute']] = $beslistProduct;
        }

        // get form information
        foreach ($attributes as $attribute) {
            $key = $product->id . '_' . $attribute['id_product_attribute'];

            // get elements to manage
            $published = Tools::getValue('beslistcart_published_' . $key);
            $delivery_code_nl = Tools::getValue('beslistcart_delivery_code_nl_' . $key, 0);
            $delivery_code_be = Tools::getValue('beslistcart_delivery_code_be_' . $key, 0);

            if (array_key_exists($attribute['id_product_attribute'], $indexedBeslistProducts)) {
                $beslistProduct = new BeslistProduct(
                    $indexedBeslistProducts[$attribute['id_product_attribute']]['id_beslist_product']
                );
                if ($beslistProduct->published == $published
                    && $beslistProduct->delivery_code_nl == $delivery_code_nl
                    && $beslistProduct->delivery_code_be == $delivery_code_be
                ) {
                    continue;
                }
                if ($product->active) {
                    $beslistProduct->status = BeslistProduct::STATUS_INFO_UPDATE;
                }
            } elseif (!$published && $delivery_code_nl == '' && $delivery_code_be == '') {
                continue;
            } else {
                $beslistProduct = new BeslistProduct();
            }

            $beslistProduct->id_product = $product->id;
            $beslistProduct->id_product_attribute = $attribute['id_product_attribute'];
            $beslistProduct->published = $published;
            $beslistProduct->delivery_code_nl = $delivery_code_nl;
            $beslistProduct->delivery_code_be = $delivery_code_be;

            if (!$beslistProduct->published && $delivery_code_nl == '' && $delivery_code_be == '') {
                $beslistProduct->delete();
            } else {
                $beslistProduct->save();
            }
        }
    }

    /**
     * Send stock updates to Beslist
     * Executes hook: actionUpdateQuantity
     *
     * @param array $param
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionUpdateQuantity($param)
    {
        if (!Configuration::get('BESLIST_CART_ENABLED')) {
            return;
        }
        $product = new Product($param['id_product']);
        if ($product->active) {
            $beslistProductId = BeslistProduct::getIdByProductAndAttributeId(
                $param['id_product'],
                $param['id_product_attribute']
            );
            if (!empty($beslistProductId)) {
                $beslistProduct = new BeslistProduct($beslistProductId);
                AdminBeslistCartProductsController::setProductStatus(
                    $beslistProduct,
                    (int)BeslistProduct::STATUS_STOCK_UPDATE
                );
                AdminBeslistCartProductsController::processBeslistQuantityUpdate(
                    $beslistProduct,
                    $param['quantity'],
                    $this->context
                );
            }
        }
    }

    /**
     * Send an update request to Beslist
     * Executes hook: actionObjectBeslistProductUpdateAfter
     *
     * @param array $param
     */
    public function hookActionObjectBeslistProductUpdateAfter($param)
    {
        if (!empty($param['object']) && Configuration::get('BESLIST_CART_ENABLED')) {
            AdminBeslistCartProductsController::setProductStatus(
                $param['object'],
                (int)BeslistProduct::STATUS_INFO_UPDATE
            );
            AdminBeslistCartProductsController::processBeslistProductUpdate($param['object'], $this->context);
        }
    }

    /**
     * Send a product deletion request to Beslist
     * Executes hook: actionObjectBeslistProductDeleteAfter
     *
     * @param array $param
     */
    public function hookActionObjectBeslistProductDeleteAfter($param)
    {
        if (!empty($param['object']) && Configuration::get('BESLIST_CART_ENABLED')) {
            AdminBeslistCartProductsController::processBeslistProductDelete($param['object'], $this->context);
        }
    }

    /**
     * Retrieve the BeslistOrdersClient
     *
     * @param null $id_lang
     * @param null $id_shop
     * @param null $id_shop_group
     *
     * @return \Wienkit\BeslistOrdersClient\BeslistOrdersClient
     */
    public static function getClient($id_shop = null, $id_shop_group = null, $id_lang = null)
    {
        $personalKey = Configuration::get('BESLIST_CART_PERSONALKEY', $id_lang, $id_shop_group, $id_shop);
        $shopId = Configuration::get('BESLIST_CART_SHOPID', $id_lang, $id_shop_group, $id_shop);
        $clientId = Configuration::get('BESLIST_CART_CLIENTID', $id_lang, $id_shop_group, $id_shop);

        $client = new Wienkit\BeslistOrdersClient\BeslistOrdersClient($personalKey, $shopId, $clientId);
        if ((bool)Configuration::get('BESLIST_CART_TESTMODE', $id_lang, $id_shop_group, $id_shop)) {
            $client->setTestMode(true);
        }
        return $client;
    }

    /**
     * Check if the Beslist cart is enabled
     *
     * @param null $id_shop
     * @param null $id_shop_group
     * @param null $id_lang
     *
     * @return bool
     */
    public static function isEnabledForShop($id_shop = null, $id_shop_group = null, $id_lang = null)
    {
        return (bool) Configuration::get('BESLIST_CART_ENABLED', $id_lang, $id_shop_group, $id_shop);
    }

    /**
     * Retrieve the BeslistShopItemClient
     *
     * @param null $id_shop
     * @param null $id_shop_group
     * @param null $id_lang
     *
     * @return \Wienkit\BeslistShopitemClient\BeslistShopitemClient
     */
    public static function getShopitemClient($id_shop = null, $id_shop_group = null, $id_lang = null)
    {
        $apiKey = Configuration::get('BESLIST_CART_SHOPITEM_APIKEY', $id_lang, $id_shop_group, $id_shop);

        $client = new Wienkit\BeslistShopitemClient\BeslistShopitemClient($apiKey);
        if ((bool)Configuration::get('BESLIST_CART_TESTMODE', $id_lang, $id_shop_group, $id_shop)) {
            $client->setTestMode(true);
        }

        return $client;
    }

    /**
     * Run the cron functionality
     */
    public static function synchronize()
    {
        AdminBeslistCartOrdersController::synchronize();
        AdminBeslistCartProductsController::synchronize(Context::getContext());
    }

    /**
     * Return the default feed filename
     *
     * @return string
     */
    public function getDefaultFeedFilename($shop_id = null)
    {
        if ($shop_id === null) {
            $shop_id =  Context::getContext()->shop->id;
        }
        $shop = new Shop($shop_id);
        $feedfile = 'beslist-' . Tools::strtolower(rawurlencode($shop->name)) . '.xml';
        return $feedfile;
    }

    /**
     * Get the default feed location.
     *
     * @param $filename
     *
     * @return string
     */
    public function getDefaultFeedLocation($filename)
    {
        return dirname(dirname(dirname(__FILE__))) . '/' . $filename;
    }

    /**
     * @return string
     */
    private function getCronUrl()
    {
        $base_url = Tools::getShopDomainSsl(true, true);
        $module_url = $base_url . __PS_BASE_URI__ . basename(_PS_MODULE_DIR_);
        $cron_url = $module_url . '/beslistcart/cron.php?secure_key=' .
            md5(_COOKIE_KEY_ . Configuration::get('PS_SHOP_NAME') . 'BESLISTCART');
        return $cron_url;
    }

    /**
     * @return string
     */
    private function getContentIntro()
    {
        $this->context->smarty->assign(array(
            'cron_url' => $this->getCronUrl(),
            'feed_url' => BeslistFeed::getScriptLocation(),
            'feed_web' => BeslistFeed::getPublicFeedLocation(),
            'module_dir' => $this->_path,
        ));
        $intro = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');
        return $intro;
    }
}
