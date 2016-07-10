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

require_once _PS_MODULE_DIR_.'beslistcart/libraries/autoload.php';
require_once _PS_MODULE_DIR_.'beslistcart/classes/BeslistProduct.php';
require_once _PS_MODULE_DIR_.'beslistcart/controllers/admin/AdminBeslistCartProductsController.php';

class BeslistCart extends Module
{
    const BESLIST_MATCH_REFERENCE = 1;
    const BESLIST_MATCH_EAN13     = 2;

    public function __construct()
    {
        $this->name = 'beslistcart';
        $this->tab = 'market_place';
        $this->version = '1.0.0';
        $this->author = 'Wienk IT';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->bootstrap = true;

        $this->display = 'view';

        parent::__construct();

        $this->displayName = $this->l('Beslist.nl Shopping Cart integration');
        $this->description = $this->l('Connect to Beslist.nl to synchronize your Beslist.nl Shopping Cart
                                       orders and products with your Prestashop website.');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    /**
     * Overrides parent::install()
     */
    public function install()
    {
        if (parent::install()) {
            return $this->installDb()
                && $this->installOrderState()
                && $this->installOrdersTab()
                && $this->installProductsTab()
                && $this->importCategories()
                && $this->registerHook('actionAdminControllerSetMedia')
                && $this->registerHook('actionProductUpdate')
                && $this->registerHook('actionUpdateQuantity')
                && $this->registerHook('actionObjectBeslistProductAddAfter')
                && $this->registerHook('actionObjectBeslistProductDeleteAfter')
                && $this->registerHook('actionObjectBeslistProductUpdateAfter')
                && $this->registerHook('displayAdminProductsExtra');
        }
        return false;
    }

    /**
     * Overrides parent::uninstall()
     */
    public function uninstall()
    {
        return $this->uninstallDb()
            && $this->uninstallTabs()
            && $this->uninstallOrderState()
            && $this->unregisterHook('actionAdminControllerSetMedia')
            && $this->unregisterHook('actionProductUpdate')
            && $this->unregisterHook('actionUpdateQuantity')
            && $this->unregisterHook('actionObjectBeslistProductAddAfter')
            && $this->unregisterHook('actionObjectBeslistProductDeleteAfter')
            && $this->unregisterHook('actionObjectBeslistProductUpdateAfter')
            && $this->unregisterHook('displayAdminProductsExtra')
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
        include(dirname(__FILE__).'/sql_install.php');
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
        include(dirname(__FILE__).'/sql_install.php');
        foreach ($sql as $name => $v) {
            Db::getInstance()->execute('DROP TABLE IF EXISTS '.pSQL($name));
        }
        return true;
    }

    /**
     * Install orders menu item
     * @return bool success
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
                    Configuration::updateValue('BESLIST_CART_ORDERS_INITIALSTATE', $state['id_order_state']);
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
        Configuration::updateValue('BESLIST_CART_ORDERS_INITIALSTATE', $order_state->id);
        return true;
    }

    /**
     * Uninstall the order state for beslist orders
     * @return bool success
     */
    public function uninstallOrderState()
    {
        $order_state = new OrderState(Configuration::get('BESLIST_CART_ORDERS_INITIALSTATE'));
        $order_state->hidden = true;
        $order_state->save();
        return true;
    }

    /**
     * Import Beslist Categories
     */
    public function importCategories()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://www.beslist.nl/atools/category_overview.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Wienk IT Beslist.nl PHP Client');
        $result = curl_exec($ch);
        $result = simplexml_load_string($result);

        $items = array();

        foreach ($result->categories->maincat as $maincat) {
            $this->parseCategory('', $maincat, $items);
        }
        Db::getInstance()->delete('beslist_categories');
        Db::getInstance()->insert('beslist_categories', $items);
        return true;
    }

    /**
     * Parses the categories recursively
     * @param $parent
     * @param $category
     * @param $items
     */
    protected function parseCategory($parent, $category, &$items) {
        $items[] = array(
            'id_beslist_category' => "" . $category[0]['id'],
            'name' => pSQL($parent . $category[0]['name'])
        );
        foreach($category->children() as $name => $child) {
            $this->parseCategory($parent . $category[0]['name'] . ' > ', $child, $items);
        }
    }

    /**
     * Render the module configuration page
     * @return string the rendered page
     */
    public function getContent()
    {
        $output = null;

        if (Tools::isSubmit('submit'.$this->name)) {

            $enabled = (bool) Tools::getValue('beslist_cart_enabled');
            $testmode = (bool) Tools::getValue('beslist_cart_testmode');
            $personalkey = (string) Tools::getValue('beslist_cart_personalkey');
            $shopid = (int) Tools::getValue('beslist_cart_shopid');
            $clientid = (int) Tools::getValue('beslist_cart_clientid');
            $shopitemKey = (string) Tools::getValue('beslist_cart_shopitem_apikey');

            $attribute_size = (int) Tools::getValue('beslist_cart_attribute_size');
            $attribute_color = (int) Tools::getValue('beslist_cart_attribute_color');
            $matcher = (int) Tools::getvalue('beslist_cart_matcher');
            $test_reference = (string) Tools::getValue('beslist_cart_test_reference');
            $startDate = (string) Tools::getValue('beslist_cart_startdate');

            $enabled_nl = (bool) Tools::getValue('beslist_cart_enabled_nl');
            $carrier_nl = (int) Tools::getValue('beslist_cart_carrier_nl');
            $deliveryperiod_nl = (string) Tools::getValue('beslist_cart_deliveryperiod_nl');
            $deliveryperiod_nostock_nl = (string) Tools::getValue('beslist_cart_deliveryperiod_nostock_nl');

            $enabled_be = (bool) Tools::getValue('beslist_cart_enabled_be');
            $carrier_be = (int) Tools::getValue('beslist_cart_carrier_be');
            $deliveryperiod_be = (string) Tools::getValue('beslist_cart_deliveryperiod_be');
            $deliveryperiod_nostock_be = (string) Tools::getValue('beslist_cart_deliveryperiod_nostock_be');


            $update_categories = (bool) Tools::getValue('beslist_cart_update_categories');
            $category = (int) Tools::getValue('beslist_cart_category');

            if (!$personalkey
                || $shopid == 0
                || $clientid == 0
                || $matcher == 0
                || empty($personalkey)
                || empty($startDate)
                || empty($shopitemKey)
                || ($testmode && empty($test_reference))
                || ($enabled_nl && empty($carrier_nl))
                || ($enabled_nl && empty($deliveryperiod_nl))
                || ($enabled_nl && empty($deliveryperiod_nostock_nl))
                || ($enabled_be && empty($carrier_be))
                || ($enabled_be && empty($deliveryperiod_be))
                || ($enabled_be && empty($deliveryperiod_nostock_be))
                || empty($category)
                )
                {
                $output .= $this->displayError($this->l('Invalid Configuration value'));
            } else {
                Configuration::updateValue('BESLIST_CART_ENABLED', $enabled);
                Configuration::updateValue('BESLIST_CART_TESTMODE', $testmode);
                Configuration::updateValue('BESLIST_CART_PERSONALKEY', $personalkey);
                Configuration::updateValue('BESLIST_CART_SHOPID', $shopid);
                Configuration::updateValue('BESLIST_CART_CLIENTID', $clientid);
                Configuration::updateValue('BESLIST_CART_SHOPITEM_APIKEY', $shopitemKey);
                Configuration::updateValue('BESLIST_CART_ATTRIBUTE_SIZE', $attribute_size);
                Configuration::updateValue('BESLIST_CART_ATTRIBUTE_COLOR', $attribute_color);
                Configuration::updateValue('BESLIST_CART_TEST_REFERENCE', $test_reference);
                Configuration::updateValue('BESLIST_CART_STARTDATE', $startDate);

                Configuration::updateValue('BESLIST_CART_ENABLED_NL', $enabled_nl);
                Configuration::updateValue('BESLIST_CART_CARRIER_NL', $carrier_nl);
                Configuration::updateValue('BESLIST_CART_DELIVERYPERIOD_NL', $deliveryperiod_nl);
                Configuration::updateValue('BESLIST_CART_DELIVERYPERIOD_NOSTOCK_NL', $deliveryperiod_nostock_nl);

                Configuration::updateValue('BESLIST_CART_ENABLED_BE', $enabled_be);
                Configuration::updateValue('BESLIST_CART_CARRIER_BE', $carrier_be);
                Configuration::updateValue('BESLIST_CART_DELIVERYPERIOD_BE', $deliveryperiod_be);
                Configuration::updateValue('BESLIST_CART_DELIVERYPERIOD_NOSTOCK_BE', $deliveryperiod_nostock_be);

                Configuration::updateValue('BESLIST_CART_CATEGORY', $category);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }

            if($update_categories) {
                $this->importCategories();
            }
        }
        return $output.$this->displayForm();
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
        $zones = Zone::getZones(true);
        $categories = BeslistProduct::getBeslistCategories();
        $attributes = AttributeGroup::getAttributesGroups(Context::getContext()->language->id);
        array_unshift($attributes, array(
          'id_attribute_group' => 0,
          'name' => $this->l("--- None ---")
        ));

        // Init Fields form array
        $fields_form = array();
        $fields_form[0]['form'] = array(
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
                    'label' => $this->l('Shop ID'),
                    'desc' => $this->l('Beslist.nl Order API Shop ID'),
                    'name' => 'beslist_cart_shopid',
                    'size' => 20
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Client ID'),
                    'desc' => $this->l('Beslist.nl Order API Client ID'),
                    'name' => 'beslist_cart_clientid'
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Personal key'),
                    'desc' => $this->l('Beslist.nl Order API Personal key'),
                    'name' => 'beslist_cart_personalkey',
                    'size' => 20
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Shopitem API key'),
                    'desc' => $this->l('Beslist.nl ShopItem API key'),
                    'name' => 'beslist_cart_shopitem_apikey',
                    'size' => 20
                ),
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
                    'type' => 'select',
                    'label' => $this->l('Beslist product matcher field'),
                    'desc' => $this->l('Select the unique field you want to use to match your products.'),
                    'name' => 'beslist_cart_matcher',
                    'options' => array(
                        'query' => array(
                            array(
                                'id_matcher' => self::BESLIST_MATCH_REFERENCE,
                                'name' => $this->l('Product reference')
                            ),
                            array(
                                'id_matcher' => self::BESLIST_MATCH_EAN13,
                                'name' => $this->l('EAN-13')
                            )
                        ),
                        'id' => 'id_matcher',
                        'name' => 'name'
                    )
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
                    'size' => 20
                ),
            )
        );

        $fields_form[1]['form'] = array(
            'legend' => array(
                'title' => $this->l('Beslist.nl settings'),
            ),
            'input' => array(
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
                               . $this->l('x werkdag(en)')  . '<br />'
                               . $this->l('x tot x werkdagen')  . '<br />'
                               . $this->l('x tot x weken')  . '<br />'
                               . $this->l('Op werkdagen voor xx:xx uur besteld, volgende dag in huis!')  . '<br />'
                               . $this->l('Direct te downloaden')  . '<br />'
                               . $this->l('Niet op voorraad')  . '<br />'
                               . $this->l('Pre-order'),
                    'name' => 'beslist_cart_deliveryperiod_nl',
                    'size' => 20
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Delivery period when not it stock'),
                    'desc' => $this->l('Use one of the following:') . '<br />'
                               . $this->l('x werkdag(en)')  . '<br />'
                               . $this->l('x tot x werkdagen')  . '<br />'
                               . $this->l('x tot x weken')  . '<br />'
                               . $this->l('Op werkdagen voor xx:xx uur besteld, volgende dag in huis!')  . '<br />'
                               . $this->l('Direct te downloaden')  . '<br />'
                               . $this->l('Niet op voorraad')  . '<br />'
                               . $this->l('Pre-order'),
                    'name' => 'beslist_cart_deliveryperiod_nostock_nl',
                    'size' => 20
                )
            )
        );

        $fields_form[2]['form'] = array(
            'legend' => array(
                'title' => $this->l('Beslist.be settings'),
            ),
            'input' => array(
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
                               . $this->l('x werkdag(en)')  . '<br />'
                               . $this->l('x tot x werkdagen')  . '<br />'
                               . $this->l('x tot x weken')  . '<br />'
                               . $this->l('Op werkdagen voor xx:xx uur besteld, volgende dag in huis!')  . '<br />'
                               . $this->l('Direct te downloaden')  . '<br />'
                               . $this->l('Niet op voorraad')  . '<br />'
                               . $this->l('Pre-order'),
                    'name' => 'beslist_cart_deliveryperiod_be',
                    'size' => 20
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Delivery period when not it stock'),
                    'desc' => $this->l('Use one of the following:') . '<br />'
                               . $this->l('x werkdag(en)')  . '<br />'
                               . $this->l('x tot x werkdagen')  . '<br />'
                               . $this->l('x tot x weken')  . '<br />'
                               . $this->l('Op werkdagen voor xx:xx uur besteld, volgende dag in huis!')  . '<br />'
                               . $this->l('Direct te downloaden')  . '<br />'
                               . $this->l('Niet op voorraad')  . '<br />'
                               . $this->l('Pre-order'),
                    'name' => 'beslist_cart_deliveryperiod_nostock_be',
                    'size' => 20
                )
            )
        );

        $fields_form[3]['form'] = array(
            'legend' => array(
                'title' => $this->l('Categories'),
                ),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->l('Update Beslist categories'),
                    'name' => 'beslist_cart_update_categories',
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'update_categories_enabled_1',
                            'value' => 1,
                            'label' => $this->l('Yes'),
                        ),
                        array(
                            'id' => 'update_categories_enabled_0',
                            'value' => 0,
                            'label' => $this->l('No')
                        )
                    ),
                    'hint' => $this->l('Updates the Beslist.nl categories list.')
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Default category'),
                    'desc' => $this->l('Select a default category for your Beslist.nl products'),
                    'name' => 'beslist_cart_category',
                    'options' => array(
                        'query' => $categories,
                        'id' => 'id_beslist_category',
                        'name' => 'name'
                    )
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
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

        // Language
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        // Title and toolbar
        $helper->title = $this->displayName;
        $helper->show_toolbar = true;
        $helper->toolbar_scroll = true;
        $helper->submit_action = 'submit'.$this->name;
        $helper->toolbar_btn = array(
            'save' =>
            array(
                'desc' => $this->l('Save'),
                'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
                '&token='.Tools::getAdminTokenLite('AdminModules'),
                ),
            'back' => array(
                'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
                'desc' => $this->l('Back to list')
                )
            );

        // Load current value
        $helper->fields_value['beslist_cart_enabled'] = Configuration::get('BESLIST_CART_ENABLED');
        $helper->fields_value['beslist_cart_testmode'] = Configuration::get('BESLIST_CART_TESTMODE');
        $helper->fields_value['beslist_cart_shopid'] = Configuration::get('BESLIST_CART_SHOPID');
        $helper->fields_value['beslist_cart_clientid'] = Configuration::get('BESLIST_CART_CLIENTID');
        $helper->fields_value['beslist_cart_personalkey'] = Configuration::get('BESLIST_CART_PERSONALKEY');
        $helper->fields_value['beslist_cart_shopitem_apikey'] = Configuration::get('BESLIST_CART_SHOPITEM_APIKEY');
        $helper->fields_value['beslist_cart_attribute_size'] = Configuration::get('BESLIST_CART_ATTRIBUTE_SIZE');
        $helper->fields_value['beslist_cart_attribute_color'] = Configuration::get('BESLIST_CART_ATTRIBUTE_COLOR');
        $helper->fields_value['beslist_cart_test_reference'] = Configuration::get('BESLIST_CART_TEST_REFERENCE');
        $helper->fields_value['beslist_cart_matcher'] = Configuration::get('BESLIST_CART_MATCHER');
        $helper->fields_value['beslist_cart_startdate'] = Configuration::get('BESLIST_CART_STARTDATE');

        $helper->fields_value['beslist_cart_enabled_nl'] = Configuration::get('BESLIST_CART_ENABLED_NL');
        $helper->fields_value['beslist_cart_carrier_nl'] = Configuration::get('BESLIST_CART_CARRIER_NL');
        $helper->fields_value['beslist_cart_deliveryperiod_nl'] = Configuration::get('BESLIST_CART_DELIVERYPERIOD_NL');
        $helper->fields_value['beslist_cart_deliveryperiod_nostock_nl'] = Configuration::get('BESLIST_CART_DELIVERYPERIOD_NOSTOCK_NL');

        $helper->fields_value['beslist_cart_enabled_be'] = Configuration::get('BESLIST_CART_ENABLED_BE');
        $helper->fields_value['beslist_cart_carrier_be'] = Configuration::get('BESLIST_CART_CARRIER_BE');
        $helper->fields_value['beslist_cart_deliveryperiod_be'] = Configuration::get('BESLIST_CART_DELIVERYPERIOD_BE');
        $helper->fields_value['beslist_cart_deliveryperiod_nostock_be'] = Configuration::get('BESLIST_CART_DELIVERYPERIOD_NOSTOCK_BE');


        $helper->fields_value['beslist_cart_category'] = Configuration::get('BESLIST_CART_CATEGORY');

        if(empty($helper->fields_value['beslist_cart_startdate'])) {
            $helper->fields_value['beslist_cart_startdate'] = date('Y-m-d');
        }
        $helper->fields_value['beslist_cart_update_categories'] = 0;

        return $helper->generateForm($fields_form);
    }

    /**
     * Add a new tab to the product page
     * Executes hook: displayAdminProductsExtra
     * @param array $params
     * @return string the form
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        if (!Configuration::get('BESLIST_CART_ENABLED')) {
            return $this->display(__FILE__, 'views/templates/admin/disabled.tpl');
        }
        $product = null;
        if ($id_product = (int)Tools::getValue('id_product')) {
            $product = new Product($id_product, true, $this->context->language->id, $this->context->shop->id);
        }
        if ($product == null || !Validate::isLoadedObject($product)) {
            return;
        }

        $attributes = $product->getAttributesResume($this->context->language->id);

        if (empty($attributes)) {
            $attributes[] = array(
                'id_product' => $product->id,
                'id_product_attribute' => 0,
                'attribute_designation' => ''
            );
        }

        $product_designation = array();

        foreach ($attributes as $attribute) {
            $product_designation[$attribute['id_product_attribute']] = rtrim(
                $product->name .' - ' . $attribute['attribute_designation'],
                ' - '
            );
        }

        $beslistProducts = BeslistProduct::getByProductId($id_product);
        $currentCategory = Configuration::get('BESLIST_CART_CATEGORY');
        $indexedBeslistProducts = array();
        foreach ($beslistProducts as $beslistProduct) {
            $currentCategory = $beslistProduct['id_beslist_category'];
            $indexedBeslistProducts[$beslistProduct['id_product_attribute']] = $beslistProduct;
        }

        $beslistCategories = BeslistProduct::getBeslistCategories();

        $this->context->controller->addJS("blaat.js");

        $this->context->smarty->assign(array(
            'attributes' => $attributes,
            'product_designation' => $product_designation,
            'product' => $product,
            'beslist_category' => $currentCategory,
            'beslist_products' => $indexedBeslistProducts,
            'beslist_categories' => $beslistCategories
        ));

        return $this->display(__FILE__, 'views/templates/admin/beslistproduct.tpl');
    }

    /**
     * Process BeslistProduct entities added on the product page
     * Executes hook: actionProductUpdate
     * @param array $params
     */
    public function hookActionProductUpdate($params)
    {
        if ((int)Tools::getValue('beslistcart_loaded') === 1
             && Validate::isLoadedObject($product = new Product((int)$params['id_product']))) {
            $this->processBeslistProductEntities($product);
        }
    }

    /**
     * Process the Beslist.nl products for a product
     * @param Product $product
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

        $category_id = Tools::getValue('beslistcart_category');

        $beslistProducts = BeslistProduct::getByProductId($product->id);

        $indexedBeslistProducts = array();
        foreach ($beslistProducts as $beslistProduct) {
            $indexedBeslistProducts[$beslistProduct['id_product_attribute']] = $beslistProduct;
        }

        // get form information
        foreach ($attributes as $attribute) {
            $key = $product->id.'_'.$attribute['id_product_attribute'];

            // get elements to manage
            $published = Tools::getValue('beslistcart_published_'.$key);
            $price = Tools::getValue('beslistcart_price_'.$key, 0);

            if (array_key_exists($attribute['id_product_attribute'], $indexedBeslistProducts)) {
                $beslistProduct = new BeslistProduct(
                    $indexedBeslistProducts[$attribute['id_product_attribute']]['id_beslist_product']
                );
                if ($beslistProduct->price == $price
                    && $beslistProduct->published == $published
                    && $beslistProduct->id_beslist_category == $category_id)
                {
                    continue;
                }
                $beslistProduct->status = BeslistProduct::STATUS_INFO_UPDATE;
            } elseif (!$published && $price == 0) {
                continue;
            } else {
                $beslistProduct = new BeslistProduct();
            }

            $beslistProduct->id_product = $product->id;
            $beslistProduct->id_product_attribute = $attribute['id_product_attribute'];
            $beslistProduct->id_beslist_category = $category_id;
            $beslistProduct->price = $price;
            $beslistProduct->published = $published;

            if (!$beslistProduct->published && $price == 0) {
                $beslistProduct->delete();
            } else {
                $beslistProduct->save();
            }
        }
    }

    /**
     * Send stock updates to Beslist
     * Executes hook: actionUpdateQuantity
     * @param array $param
     */
    public function hookActionUpdateQuantity($param)
    {
        $beslistProductId = BeslistProduct::getIdByProductAndAttributeId(
            $param['id_product'],
            $param['id_product_attribute']
        );
        if (!empty($beslistProductId)) {
            $beslistProduct = new BeslistProduct($beslistProductId);
            AdminBeslistCartProductsController::setProductStatus($beslistProduct, (int)BeslistProduct::STATUS_STOCK_UPDATE);
            AdminBeslistCartProductsController::processBeslistQuantityUpdate($beslistProduct, $param['quantity'], $this->context);
        }
    }

    /**
     * Send a creation request to Beslist
     * Executes hook: actionObjectBeslistProductAddAfter
     * @param array $param
     */
    public function hookActionObjectBeslistProductAddAfter($param)
    {
        if (!empty($param['object'])) {
            AdminBeslistCartProductsController::processBeslistProductUpdate($param['object'], $this->context);
        }
    }

    /**
     * Send an update request to Beslist
     * Executes hook: actionObjectBeslistProductUpdateAfter
     * @param array $param
     */
    public function hookActionObjectBeslistProductUpdateAfter($param)
    {
        if (!empty($param['object'])) {
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
     * @param array $param
     */
    public function hookActionObjectBeslistProductDeleteAfter($param)
    {
        if (!empty($param['object'])) {
            AdminBeslistCartProductsController::processBeslistProductDelete($param['object'], $this->context);
        }
    }

    /**
     * Add javascript and css to view
     * @param $params
     */
    public function hookActionAdminControllerSetMedia($params)
    {
        if ($this->context->controller->controller_name == 'AdminProducts') {
            $this->context->controller->addCSS(
                'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.10.0/css/bootstrap-select.min.css'
            );
            $this->context->controller->addJS(
                'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.10.0/js/bootstrap-select.min.js'
            );
        }
    }


    /**
     * Retrieve the BeslistOrdersClient
     * @return Wienkit\BeslistOrdersClient\BeslistOrdersClient
     */
    public static function getClient()
    {
        $personalKey = Configuration::get('BESLIST_CART_PERSONALKEY');
        $shopId = Configuration::get('BESLIST_CART_SHOPID');
        $clientId = Configuration::get('BESLIST_CART_CLIENTID');

        $client = new Wienkit\BeslistOrdersClient\BeslistOrdersClient($personalKey, $shopId, $clientId);
        if ((bool)Configuration::get('BESLIST_CART_TESTMODE')) {
            $client->setTestMode(true);
        }
        return $client;
    }

    /**
     * Retrieve the BeslistShopItemClient
     * @return Wienkit\BeslistShopitemClient\BeslistShopitemClient
     */
    public static function getShopitemClient()
    {
        $apiKey = Configuration::get('BESLIST_CART_SHOPITEM_APIKEY');

        $client = new Wienkit\BeslistShopitemClient\BeslistShopitemClient($apiKey);
        if ((bool)Configuration::get('BESLIST_CART_TESTMODE')) {
            $client->setTestMode(true);
        }

        return $client;
    }
}
