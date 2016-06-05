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

class BeslistCart extends Module
{
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
            return $this->installOrdersTab();
        }
        return false;
    }

    /**
     * Overrides parent::uninstall()
     */
    public function uninstall()
    {
        return $this->uninstallTabs()
          && parent::uninstall();
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
     * Remove menu items
     * @return bool success
     */
    public function uninstallTabs()
    {
        $id_tab = (int)Tab::getIdFromClassName('AdminBeslistCartOrders');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            if(!$tab->delete()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Render the module configuration page
     * @return $output the rendered page
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
            $carrier = (int) Tools::getValue('beslist_cart_carrier');

            // $deliveryCode = (string) Tools::getValue('beslist_cart_delivery_code');
            // $freeShipping = (bool) Tools::getValue('beslist_cart_free_shipping');

            if (!$personalkey
                || $shopid == 0
                || $clientid == 0
                || empty($personalkey)
                || empty($carrier)
                // || empty($deliveryCode)
                )
                {
                $output .= $this->displayError($this->l('Invalid Configuration value'));
            } else {
                Configuration::updateValue('BESLIST_CART_ENABLED', $enabled);
                Configuration::updateValue('BESLIST_CART_TESTMODE', $testmode);
                Configuration::updateValue('BESLIST_CART_PERSONALKEY', $personalkey);
                Configuration::updateValue('BESLIST_CART_SHOPID', $shopid);
                Configuration::updateValue('BESLIST_CART_CARRIER', $carrier);
                Configuration::updateValue('BESLIST_CART_CLIENTID', $clientid);
                // Configuration::updateValue('BESLIST_CART_DELIVERY_CODE', $deliveryCode);
                // Configuration::updateValue('BESLIST_CART_FREE_SHIPPING', $freeShipping);
                $output .= $this->displayConfirmation($this->l('Settings updated'));
            }
        }
        return $output.$this->displayForm();
    }

    /**
     * Render a form on the module configuration page
     * @return the form
     */
    public function displayForm()
    {
        // Get default language
        $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

        $carriers = Carrier::getCarriers(Context::getContext()->language->id);
        // $delivery_codes = BolPlazaProduct::getDeliveryCodes();

        // Init Fields form array
        $fields_form = array();
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Settings'),
                ),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->l('Enable Beslist.nl Shopping Cart integration'),
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
                    'type' => 'select',
                    'label' => $this->l('Carrier'),
                    'desc' => $this->l('Choose a carrier for your Beslist.nl orders'),
                    'name' => 'beslist_cart_carrier',
                    'options' => array(
                        'query' => $carriers,
                        'id' => 'id_carrier',
                        'name' => 'name'
                    )
                ),
                // array(
                //     'type' => 'select',
                //     'label' => $this->l('Delivery code'),
                //     'desc' => $this->l('Choose a delivery code for your Bol.com products'),
                //     'name' => 'beslist_cart_delivery_code',
                //     'options' => array(
                //         'query' => $delivery_codes,
                //         'id' => 'deliverycode',
                //         'name' => 'description'
                //     )
                // ),
                // array(
                //     'type' => 'switch',
                //     'label' => $this->l('Use free shipping'),
                //     'name' => 'beslist_cart_free_shipping',
                //     'is_bool' => true,
                //     'values' => array(
                //         array(
                //             'id' => 'beslist_cart_free_shipping_1',
                //             'value' => 1,
                //             'label' => $this->l('Yes'),
                //         ),
                //         array(
                //             'id' => 'beslist_cart_free_shipping_0',
                //             'value' => 0,
                //             'label' => $this->l('No')
                //         )
                //     ),
                //     'hint' => $this->l('Don\'t calculate shipping costs.')
                // ),
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
        $helper->fields_value['beslist_cart_carrier'] = Configuration::get('BESLIST_CART_CARRIER');
        // $helper->fields_value['beslist_cart_delivery_code'] = Configuration::get('BESLIST_CART_DELIVERY_CODE');
        // $helper->fields_value['beslist_cart_free_shipping'] = Configuration::get('BESLIST_CART_FREE_SHIPPING');

        return $helper->generateForm($fields_form);
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
}
