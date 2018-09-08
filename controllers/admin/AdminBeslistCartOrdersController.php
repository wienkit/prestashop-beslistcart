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
require_once _PS_MODULE_DIR_ . 'beslistcart/classes/BeslistPayment.php';
require_once _PS_MODULE_DIR_ . 'beslistcart/classes/BeslistTestPayment.php';

class AdminBeslistCartOrdersController extends AdminController
{

    public function __construct()
    {
        if ($id_order = Tools::getValue('id_order')) {
            Tools::redirectAdmin(
                Context::getContext()->link->getAdminLink('AdminOrders') . '&vieworder&id_order=' . (int)$id_order
            );
        }
        $this->bootstrap = true;
        $this->table = 'order';
        $this->className = 'Order';
        $this->lang = false;
        $this->addRowAction('view');
        $this->explicitSelect = true;
        $this->allow_export = true;
        $this->deleted = false;
        $this->context = Context::getContext();

        $this->_select = '
		a.id_currency,
		a.id_order AS id_pdf,
		CONCAT(LEFT(c.`firstname`, 1), \'. \', c.`lastname`) AS `customer`,
		osl.`name` AS `osname`,
		os.`color`,
		IF((SELECT so.id_order FROM `' . _DB_PREFIX_ . 'orders` so WHERE so.id_customer = a.id_customer 
		AND so.id_order < a.id_order LIMIT 1) > 0, 0, 1) as new,
		country_lang.name as cname,
		IF(a.valid, 1, 0) badge_success';

        $this->_join = '
		LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (c.`id_customer` = a.`id_customer`)
		LEFT JOIN `' . _DB_PREFIX_ . 'address` address ON address.id_address = a.id_address_delivery
		LEFT JOIN `' . _DB_PREFIX_ . 'country` country ON address.id_country = country.id_country
		LEFT JOIN `' . _DB_PREFIX_ . 'country_lang` country_lang ON (country.`id_country` = country_lang.`id_country`
		    AND country_lang.`id_lang` = ' . (int)$this->context->language->id . ')
		LEFT JOIN `' . _DB_PREFIX_ . 'order_payment` op ON (op.`order_reference` = a.`reference`)
		LEFT JOIN `' . _DB_PREFIX_ . 'order_state` os ON (os.`id_order_state` = a.`current_state`)
		LEFT JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl ON (os.`id_order_state` = osl.`id_order_state` 
		    AND osl.`id_lang` = ' . (int)$this->context->language->id . ')';
        $this->_orderBy = 'id_order';
        $this->_orderWay = 'DESC';
        $this->_where = 'AND a.module IN (\'beslistcart_payment\', \'beslistcarttest\')';
        $this->_use_found_rows = true;

        $statuses = OrderState::getOrderStates((int)$this->context->language->id);
        foreach ($statuses as $status) {
            $this->statuses_array[$status['id_order_state']] = $status['name'];
        }

        parent::__construct();

        $this->fields_list = array(
            'id_order' => array(
                'title' => $this->l('ID'),
                'align' => 'text-center',
                'class' => 'fixed-width-xs'
            ),
            'reference' => array(
                'title' => $this->l('Reference')
            ),
            'transaction_id' => array(
                'title' => $this->l('Beslist ID')
            ),
            'new' => array(
                'title' => $this->l('New client'),
                'align' => 'text-center',
                'type' => 'bool',
                'tmpTableFilter' => true,
                'orderby' => false,
                'callback' => 'printNewCustomer'
            ),
            'customer' => array(
                'title' => $this->l('Customer'),
                'havingFilter' => true,
            ),
        );

        $this->fields_list = array_merge($this->fields_list, array(
            'total_paid_tax_incl' => array(
                'title' => $this->l('Total'),
                'align' => 'text-right',
                'type' => 'price',
                'currency' => true,
                'callback' => 'setOrderCurrency',
                'badge_success' => true
            ),
            'osname' => array(
                'title' => $this->l('Status'),
                'type' => 'select',
                'color' => 'color',
                'list' => $this->statuses_array,
                'filter_key' => 'os!id_order_state',
                'filter_type' => 'int',
                'order_key' => 'osname'
            ),
            'date_add' => array(
                'title' => $this->l('Date'),
                'align' => 'text-right',
                'type' => 'datetime',
                'filter_key' => 'a!date_add'
            )
        ));

        if (ObjectModel::isCurrentlyUsed('country', true)) {
            $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS('
			SELECT DISTINCT c.id_country, cl.`name`
			FROM `' . _DB_PREFIX_ . 'orders` o
			' . Shop::addSqlAssociation('orders', 'o') . '
			INNER JOIN `' . _DB_PREFIX_ . 'address` a 
			    ON a.id_address = o.id_address_delivery
			INNER JOIN `' . _DB_PREFIX_ . 'country` c 
			    ON a.id_country = c.id_country
			INNER JOIN `' . _DB_PREFIX_ . 'country_lang` cl 
			    ON (c.`id_country` = cl.`id_country` 
			    AND cl.`id_lang` = ' . (int)$this->context->language->id . ')
			ORDER BY cl.name ASC');

            $country_array = array();
            foreach ($result as $row) {
                $country_array[$row['id_country']] = $row['name'];
            }

            $part1 = array_slice($this->fields_list, 0, 3);
            $part2 = array_slice($this->fields_list, 3);
            $part1['cname'] = array(
                'title' => $this->l('Delivery'),
                'type' => 'select',
                'list' => $country_array,
                'filter_key' => 'country!id_country',
                'filter_type' => 'int',
                'order_key' => 'cname'
            );
            $this->fields_list = array_merge($part1, $part2);
        }

        $this->shopLinkType = 'shop';
        $this->shopShareDatas = Shop::SHARE_ORDER;
    }

    /**
     * Set the minimal quantity, return the old value
     *
     * @param $id
     * @param null $id_product_attribute
     * @param int $minimalQuantity
     * @return int
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private static function updateMinimalQuantity($id, $id_product_attribute = null, $minimalQuantity = 1)
    {
        if (isset($id_product_attribute) && $id_product_attribute > 0) {
            $attribute = new Combination($id_product_attribute);
            $oldMinimalQuantity = $attribute->minimal_quantity;
            if ($oldMinimalQuantity != $minimalQuantity) {
                $attribute->minimal_quantity = $minimalQuantity;
                $attribute->save();
            }
        } else {
            $product = new Product($id);
            $oldMinimalQuantity = $product->minimal_quantity;
            if ($oldMinimalQuantity != $minimalQuantity) {
                $product->minimal_quantity = $minimalQuantity;
                $product->save();
            }
        }
        return $oldMinimalQuantity;
    }

    /**
     * Set the minimal quantity, return the old value
     *
     * @param int $id_product
     *   The product id.
     * @param int $id_product_attribute
     *   The product attribute id.
     * @param int $stockBehaviour
     *   The new stock behaviour.
     *
     * @return int
     *   The former value.
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private static function updateOutOfStockBehaviour($id_product, $id_product_attribute = null, $stockBehaviour = 1)
    {
        $stock_available_id = StockAvailable::getStockAvailableIdByProductId($id_product, $id_product_attribute);
        $stock_available = new StockAvailable($stock_available_id);
        $oldValue = $stock_available->out_of_stock;
        $stock_available->out_of_stock = $stockBehaviour;
        $stock_available->save();
        return $oldValue;
    }

    /**
     * Saves the address if it is new, returns the existing address if it is known already
     *
     * @param Address $address
     * @param Address|null $otherAddress
     * @return Address
     *
     * @throws PrestaShopException
     */
    private static function saveAndRetrieve(Address $address, Address $otherAddress = null)
    {
        if ($otherAddress != null) {
            if ($otherAddress->firstname == $address->firstname &&
                $otherAddress->lastname == $address->lastname &&
                $otherAddress->address1 == $address->address1 &&
                $otherAddress->address2 == $address->address2 &&
                $otherAddress->postcode == $address->postcode &&
                $otherAddress->city == $address->city &&
                $otherAddress->id_country == $address->id_country
            ) {
                return $address;
            } else {
                return self::saveAndRetrieve($otherAddress);
            }
        }
        $id_address = Db::getInstance()->getValue('
            SELECT id_address
            FROM `' . _DB_PREFIX_ . 'address`
            WHERE `id_customer` = '. (int) $address->id_customer . '
            AND `active` = 1
            AND `firstname` = \''. (string) pSQL($address->firstname) . '\'
            AND `lastname` = \''. (string) pSQL($address->lastname) . '\'
            AND `address1` = \''. (string) pSQL($address->address1) .'\'
            AND `address2` = \''. (string) pSQL($address->address2) .'\'
            AND `postcode` = \''. (string) pSQL($address->postcode) .'\'
            AND `city` = \''. (string) pSQL($address->city) .'\' 
            AND `id_country` = '. (int) $address->id_country);

        if ($id_address !== false && $id_address > 0) {
            return new Address($id_address);
        } else {
            $address->save();
            return $address;
        }
    }

    /**
     * Overrides parent::initPageHeaderToolbar
     */
    public function initPageHeaderToolbar()
    {
        parent::initPageHeaderToolbar();

        $this->page_header_toolbar_btn['sync_orders'] = array(
            'href' => self::$currentIndex . '&token=' . $this->token . '&sync_orders=1',
            'desc' => $this->l('Sync orders'),
            'icon' => 'process-icon-download'
        );

        if (Configuration::get('BESLIST_CART_TESTMODE')) {
            $this->page_header_toolbar_btn['delete_testdata'] = array(
                'href' => self::$currentIndex . '&token=' . $this->token . '&delete_testdata=1',
                'desc' => $this->l('Delete test data'),
                'icon' => 'process-icon-eraser'
            );
        }
    }

    public function initToolbar()
    {
        $this->allow_export = true;
        parent::initToolbar();
        unset($this->toolbar_btn['new']);
    }

    /**
     * Prints Yes if a new customer was found
     *
     * @param $id_order
     * @param $tr
     * @return string
     */
    public function printNewCustomer($id_order, $tr)
    {
        return ($tr['new'] ? $this->l('Yes') : $this->l('No'));
    }

    /**
     * Processes the request
     */
    public function postProcess()
    {
        /* PrestaShop demo mode */
        if (_PS_MODE_DEMO_) {
            $this->errors[] = Tools::displayError('This functionality has been disabled.');
            return;
        }

        if ((bool)Tools::getValue('sync_orders')) {
            self::synchronize();
        } elseif ((bool)Tools::getValue('delete_testdata')) {
            $orders = new PrestaShopCollection('Order');
            $orders->where('module', '=', 'beslistcarttest');
            /**
             * @var Order $order
             */
            foreach ($orders->getResults() as $order) {
                $customer = $order->getCustomer();
                $addresses = $customer->getAddresses($customer->id_lang);
                foreach ($addresses as $addressArr) {
                    $address = new Address($addressArr['id_address']);
                    $address->delete();
                }
                $details = $order->getOrderDetailList();
                foreach ($details as $detail) {
                    (new OrderDetail($detail['id_order_detail']))->delete();
                }
                (new Cart($order->id_cart))->delete();
                $payments = OrderPayment::getByOrderReference($order->reference);
                foreach ($payments as $payment) {
                    $payment->delete();
                }

                Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'order_history`
                                              WHERE `id_order` = ' . (int)pSQL($order->id));
                $order->delete();
                $customer->delete();
            }
        }
        return parent::postProcess();
    }

    public static function synchronize()
    {
        $context = Context::getContext();
        $module = Module::getInstanceByName('beslistcart');
        if (!Configuration::get('BESLIST_CART_ENABLED') || !$module->isEnabledForShopContext()) {
            $context->controller->errors[] = Tools::displayError(
                'Beslist Shopping cart isn\'t enabled for the current store.'
            );
            return;
        }
        $Beslist = BeslistCart::getClient();
        $payment_module = new BeslistPayment();
        if ((bool)Configuration::get('BESLIST_CART_TESTMODE')) {
            $payment_module = new BeslistTestPayment();
        }

        $startDate = (string)Configuration::get('BESLIST_CART_STARTDATE');
        $endDate = date('Y-m-d');

        $data = array();
        if (Configuration::get('BESLIST_CART_TESTMODE')) {
            $testReference = (string) Configuration::get('BESLIST_CART_TEST_REFERENCE');
            $productId = self::getProductIdByBvbCode($testReference);
            $product = new Product($productId['id_product']);
            $carrier = Carrier::getCarrierByReference(Configuration::get('BESLIST_CART_CARRIER_NL'));

            // Shipping cost
            $price = (float)BeslistProduct::getPriceStatic(
                $productId['id_product'],
                $productId['id_product_attribute'],
                $context
            );

            $country_nl = new Country(Country::getByIso('NL'));
            $address = new Address();
            $address->id_country = $country_nl->id;
            $address->id_state = 0;
            $address->postcode = 0;
            $tax_rate = $carrier->getTaxesRate($address);

            $priceExtra = 0;
            $quantity = 2;

            if ($price < Configuration::get('PS_SHIPPING_FREE_PRICE') ||
                Configuration::get('PS_SHIPPING_FREE_PRICE') == 0
            ) {
                $priceExtra = Configuration::get('PS_SHIPPING_HANDLING');
            }

            if ($product->additional_shipping_cost > 0) {
                $priceExtra += $quantity * $product->additional_shipping_cost;
            }

            $shippingTaxExcl = $priceExtra;
            if ($carrier->getShippingMethod() == Carrier::SHIPPING_METHOD_WEIGHT) {
                $weight = $product->weight;
                if ($productId['id_product_attribute']) {
                    $combination = new Combination($productId['id_product_attribute']);
                    $weight += $combination->weight;
                }
                $shippingTaxExcl += $carrier->getDeliveryPriceByWeight(
                    $weight * $quantity,
                    $country_nl->id_zone
                );
            } else {
                $shippingTaxExcl += $carrier->getDeliveryPriceByPrice($price, $country_nl->id_zone);
            }

            $shippingTaxIncl = $shippingTaxExcl * (1 + ($tax_rate / 100));

            $data = array(array(
                'number_ordered' => $quantity,
                'bvb_code' => $testReference,
                'item_shipping' => $shippingTaxIncl,
                'item_price' => $price
            ));
        }

        $beslistShoppingCart = $Beslist->getShoppingCartData($startDate, $endDate, $data);

        $success = true;
        foreach ($beslistShoppingCart->shopOrders as $shopOrder) {
            if (Configuration::get('BESLIST_CART_TESTMODE')) {
                $shopOrder->orderNumber = $shopOrder->orderNumber . '-' . Context::getContext()->shop->id;
            }
            if (!self::getTransactionExists($shopOrder->orderNumber)) {
                $cart = self::parse($shopOrder);

                if (!$cart) {
                    $context->controller->errors[] = Translate::getAdminTranslation(
                        'Couldn\'t create a cart for order ',
                        'AdminBeslistCartOrders'
                    ) . $shopOrder->orderNumber;
                    $success = false;
                    continue;
                }

                Context::getContext()->cart = $cart;
                Context::getContext()->currency = new Currency((int)$cart->id_currency);
                Context::getContext()->customer = new Customer((int)$cart->id_customer);

                $id_order_state = Configuration::get('BESLIST_CART_ORDERS_INITIALSTATE');
                $amount_paid = self::getBeslistPaymentTotal($shopOrder);
                $verified = $payment_module->validateOrder(
                    (int)$cart->id,
                    (int)$id_order_state,
                    $amount_paid,
                    $payment_module->displayName,
                    null,
                    array(
                        'transaction_id' => $shopOrder->orderNumber
                    ),
                    null,
                    false,
                    $cart->secure_key
                );
                if (!$verified) {
                    $success = false;
                    $context->controller->errors[] = Tools::displayError('Beslist.nl Shopping cart sync failed.');
                }
            }
        }
        if ($success) {
            Configuration::updateValue('BESLIST_CART_STARTDATE', $endDate);
            $context->controller->confirmations[] = Translate::getAdminTranslation(
                'Beslist.nl Shopping cart sync completed.',
                'AdminBeslistCartOrders'
            );
        }
    }

    /**
     * Display the order total in the correct currency
     * @param $echo
     * @param $tr
     * @return string
     */
    public static function setOrderCurrency($echo, $tr)
    {
        $order = new Order($tr['id_order']);
        return Tools::displayPrice($echo, (int)$order->id_currency);
    }

    /**
     * Get OrderID for a Transaction ID
     * @param string $transaction_id
     * @return bool
     */
    public static function getTransactionExists($transaction_id)
    {
        $sql = new DbQuery();
        $sql->select('order_reference');
        $sql->from('order_payment', 'op');
        $sql->where('op.transaction_id = \'' . pSQL($transaction_id) . '\'');
        return (bool)Db::getInstance()->executeS($sql);
    }

    /**
     * Parse a Beslist order to a fully prepared Cart object
     * @param Wienkit\BeslistOrdersClient\Entities\BeslistOrder $shopOrder
     * @return Cart
     */
    public static function parse(Wienkit\BeslistOrdersClient\Entities\BeslistOrder $shopOrder)
    {
        $customer = self::parseCustomer($shopOrder);
        Context::getContext()->customer = $customer;
        $shipping = self::parseAddress($shopOrder->addresses->shipping, $customer, 'Shipping');
        $shipping = self::saveAndRetrieve($shipping);
        $billing = self::parseAddress($shopOrder->addresses->invoice, $customer, 'Billing');
        $billing = self::saveAndRetrieve($shipping, $billing);
        $cart = self::parseCart($shopOrder, $customer, $billing, $shipping);
        return $cart;
    }

    /**
     * Parse a name (truncate & replace).
     *
     * @param $name
     *   The name to parse.
     *
     * @return bool|string
     */
    private static function parseName($name)
    {
        $name = preg_replace(
            "/[^A-Za-z ]/",
            '',
            $name
        );
        return Tools::substr($name, 0, 32);
    }

    /**
     * Parse a customer for the order
     * @param Wienkit\BeslistOrdersClient\Entities\BeslistOrder $shopOrder
     * @return Customer
     */
    public static function parseCustomer(Wienkit\BeslistOrdersClient\Entities\BeslistOrder $shopOrder)
    {
        $customers = Customer::getCustomersByEmail($shopOrder->customer->email);
        if (count($customers) > 0) {
            $customer = $customers[0];
            return new Customer($customer['id_customer']);
        }
        $customer = new Customer();
        $customer->firstname = self::parseName($shopOrder->addresses->invoice->firstName);
        $lastname = trim(
            $shopOrder->addresses->invoice->lastNameInsertion .
            ' ' .
            $shopOrder->addresses->invoice->lastName
        );
        $customer->lastname = self::parseName($lastname);
        $customer->email = $shopOrder->customer->email;
        $customer->passwd = Tools::passwdGen(8, 'RANDOM');
        $customer->id_default_group = Configuration::get('BESLIST_CART_CUSTOMER_GROUP');
        $customer->newsletter = false;
        $customer->add();
        return $customer;
    }

    /**
     * Parse an address for the order
     * @param Wienkit\BeslistOrdersClient\Entities\BeslistAddressShipping $details
     * @param Customer $customer
     * @param string $alias a name for the address
     * @return Address
     */
    public static function parseAddress(
        Wienkit\BeslistOrdersClient\Entities\BeslistAddressShipping $details,
        Customer $customer,
        $alias
    ) {
        $address = new Address();
        $address->id_customer = $customer->id;
        $address->firstname = self::parseName($details->firstName);
        $lastname = trim($details->lastNameInsertion . ' ' . $details->lastName);
        $address->lastname = self::parseName($lastname);
        $address1 = $details->address;
        $address2 = '';
        $houseNumber = $details->addressNumber;
        if ($details->addressNumberAdditional != '') {
            $houseNumber .= ' ' . $details->addressNumberAdditional;
        }
        if (Configuration::get('BESLIST_CART_USE_ADDRESS2')) {
            $address2 = trim($houseNumber);
        } else {
            $address1 .= ' ' . trim($houseNumber);
        }

        $address->address1 = preg_replace(
            '/[!<>?=+@{}_$%]*/',
            '',
            $address1
        );

        $address->address2 = preg_replace(
            '/[!<>?=+@{}_$%]*/',
            '',
            $address2
        );

        $address->postcode = $details->zip;
        $address->city = $details->city;
        $address->id_country = Country::getByIso($details->country);
        $address->alias = $alias;
        return $address;
    }

    /**
     * Parse the cart for the order
     *
     * @param \Wienkit\BeslistOrdersClient\Entities\BeslistOrder $order
     * @param Customer $customer
     * @param Address $billing
     * @param Address $shipping
     *
     * @return bool|Cart
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public static function parseCart(
        Wienkit\BeslistOrdersClient\Entities\BeslistOrder $order,
        Customer $customer,
        Address $billing,
        Address $shipping
    ) {
        $context = Context::getContext();
        $cart = new Cart();
        $cart->id_customer = $customer->id;
        $cart->id_address_delivery = $shipping->id;
        $cart->id_address_invoice = $billing->id;
        $cart->id_shop = (int)$context->shop->id;
        $cart->id_shop_group = (int)$context->shop->id_shop_group;
        $cart->id_lang = (int)$context->language->id;
        $cart->id_currency = (int)Currency::getIdByIsoCode('EUR');
        $country = new Country($shipping->id_country);
        $cart->recyclable = 0;
        $cart->gift = 0;
        $cart->secure_key = md5(uniqid(rand(), true));
        $cart->add();
        $items = $order->products;
        $hasProducts = false;
        if (!empty($items)) {
            foreach ($items as $item) {
                $productIds = self::getProductIdByBvbCode($item->bvbCode);
                if (empty($productIds) || !array_key_exists('id_product', $productIds)) {
                    $context->controller->errors[] = Translate::getAdminTranslation(
                        'Couldn\'t find product for Bvb code: ',
                        'AdminBeslistCartOrders'
                    ) . $item->bvbCode;
                    continue;
                }
                $product = new Product($productIds['id_product']);
                if (!Validate::isLoadedObject($product)) {
                    $context->controller->errors[] = Translate::getAdminTranslation(
                        'Couldn\'t load product for Bvb code: ',
                        'AdminBeslistCartOrders'
                    ) . $item->bvbCode;
                    continue;
                }
                $hasProducts = true;
                $oldMinimalQuantity = self::updateMinimalQuantity($product->id, $productIds['id_product_attribute']);
                $oldOutOfStockBehaviour = self::updateOutOfStockBehaviour(
                    $product->id,
                    $productIds['id_product_attribute']
                );
                $cartResult = $cart->updateQty($item->numberOrdered, $product->id, $productIds['id_product_attribute']);
                if ($oldMinimalQuantity > 1) {
                    self::updateMinimalQuantity($product->id, $productIds['id_product_attribute'], $oldMinimalQuantity);
                }
                self::updateOutOfStockBehaviour(
                    $product->id,
                    $productIds['id_product_attribute'],
                    $oldOutOfStockBehaviour
                );
                if (!$cartResult) {
                    $context->controller->errors[] = Tools::displayError(
                        'Couldn\'t add product to cart. The product cannot
                         be sold because it\'s unavailable or out of stock.'
                    ) . ' Code: ' . $item->bvbCode
                        . '. Product: ' . $product->id
                        . ' (attribute: ' . $productIds['id_product_attribute'] . ')';
                    return false;
                }
            }
        }

        if ($country->iso_code == 'NL') {
            $carrier_nl = Carrier::getCarrierByReference(Configuration::get('BESLIST_CART_CARRIER_NL'));
            $id_carrier = $carrier_nl->id;
        } else {
            $carrier_be = Carrier::getCarrierByReference(Configuration::get('BESLIST_CART_CARRIER_BE'));
            $id_carrier = $carrier_be->id;
        }
        $cart->setDeliveryOption(array($shipping->id => $id_carrier . ","));
        $cart->update();
        if (!$hasProducts) {
            return false;
        }
        return $cart;
    }

    /**
     * Return the product ID for a bvbCode
     * @param string $bvbCode
     * @return array the product (and attribute)
     */
    public static function getProductIdByBvbCode($bvbCode)
    {
        switch ((int)Configuration::get('BESLIST_CART_MATCHER')) {
            case BeslistCart::BESLIST_MATCH_EAN13:
                $attributes = self::getAttributeByEan($bvbCode);
                break;
            case BeslistCart::BESLIST_MATCH_REFERENCE:
                $attributes = self::getAttributeByReference($bvbCode);
                break;
            case BeslistCart::BESLIST_MATCH_STORECOMMANDER:
                $attributes = self::getAttributeByStorecommanderCode($bvbCode);
                break;
            case BeslistCart::BESLIST_MATCH_DEFAULT:
            default:
                $attributes = self::getAttributeByDefaultCode($bvbCode);
                break;
        }
        if (is_array($attributes) && count($attributes) == 1) {
            return $attributes[0];
        }

        switch ((int)Configuration::get('BESLIST_CART_MATCHER')) {
            case BeslistCart::BESLIST_MATCH_EAN13:
                $id = Product::getIdByEan13($bvbCode);
                break;
            case BeslistCart::BESLIST_MATCH_REFERENCE:
                $id = self::getProductByReference($bvbCode);
                break;
            case BeslistCart::BESLIST_MATCH_STORECOMMANDER:
                $id = self::getProductByStorecommanderCode($bvbCode);
                break;
            case BeslistCart::BESLIST_MATCH_DEFAULT:
            default:
                $id = self::getProductByDefaultCode($bvbCode);
                break;
        }

        if ($id) {
            return array('id_product' => $id, 'id_product_attribute' => 0);
        }
        return $attributes;
    }

    /**
     * Return the product for a reference
     * @param string $reference
     * @return array|false|int|mysqli_result|null|PDOStatement|resource the product
     */
    private static function getProductByReference($reference)
    {
        if (empty($reference)) {
            return 0;
        }

        if (!Validate::isReference($reference)) {
            return 0;
        }

        $query = new DbQuery();
        $query->select('p.id_product');
        $query->from('product', 'p');
        $query->where('p.reference = \'' . pSQL($reference) . '\'');
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
    }

    /**
     * Return the product id for a default code
     * @param $code
     * @return int|bool
     */
    private static function getProductByDefaultCode($code)
    {
        if (strpos($code, '-')) {
            $splitted = explode('-', $code);
            return $splitted[1];
        } else {
            return $code;
        }
    }

    /**
     * Return the product id for a storecommander code
     * @param $code
     * @return int|bool
     */
    private static function getProductByStorecommanderCode($code)
    {
        if (strpos($code, '_')) {
            $splitted = explode('_', $code);
            return $splitted[0];
        } else {
            return $code;
        }
    }

    /**
     * Return the attribute for an ean
     * @param string $ean
     * @return array|false|int|mysqli_result|null|PDOStatement|resource the product/attribute combination
     */
    private static function getAttributeByEan($ean)
    {
        if (empty($ean)) {
            return 0;
        }

        if (!Validate::isEan13($ean)) {
            return 0;
        }

        $query = new DbQuery();
        $query->select('pa.id_product, pa.id_product_attribute');
        $query->from('product_attribute', 'pa');
        $query->where('pa.ean13 = \'' . pSQL($ean) . '\'');
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($query);
    }

    /**
     * Return the attribute for a reference
     * @param string $reference
     * @return array|false|int|mysqli_result|null|PDOStatement|resource the product/attribute combination
     */
    private static function getAttributeByReference($reference)
    {
        if (empty($reference)) {
            return 0;
        }

        if (!Validate::isReference($reference)) {
            return 0;
        }

        $query = new DbQuery();
        $query->select('pa.id_product, pa.id_product_attribute');
        $query->from('product_attribute', 'pa');
        $query->where('pa.reference = \'' . pSQL($reference) . '\'');
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($query);
    }

    /**
     * Return the attribute for a default code
     * @param $code
     * @return array|int the attribute ids
     */
    private static function getAttributeByDefaultCode($code)
    {
        $splitted = explode('-', $code);
        if (count($splitted) == 2) {
            $query = new DbQuery();
            $query->select('pa.id_product, pa.id_product_attribute');
            $query->from('product_attribute', 'pa');
            $query->where(
                'pa.id_product = \'' . (int)$splitted[1] . '\' AND ' .
                'pa.id_product_attribute = \'' . (int)$splitted[0] . '\''
            );
            if ((bool)Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query)) {
                return array(
                    array(
                        'id_product' => $splitted[1],
                        'id_product_attribute' => $splitted[0]
                    )
                );
            }
        }
        return 0;
    }

    /**
     * Return the attribute for a storecommander code
     * @param $code
     * @return array|int the attribute ids
     */
    private static function getAttributeByStorecommanderCode($code)
    {
        $splitted = explode('_', $code);
        if (count($splitted) == 2) {
            $query = new DbQuery();
            $query->select('pa.id_product, pa.id_product_attribute');
            $query->from('product_attribute', 'pa');
            $query->where(
                'pa.id_product = \'' . (int)$splitted[0] . '\' AND ' .
                'pa.id_product_attribute = \'' . (int)$splitted[1] . '\''
            );
            if ((bool)Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query)) {
                return array(
                    array(
                        'id_product' => $splitted[0],
                        'id_product_attribute' => $splitted[1]
                    )
                );
            }
        }
        return 0;
    }

    /**
     * Get the Payment total of the Bol.com order
     * @param Wienkit\BeslistOrdersClient\Entities\BeslistOrder $shopOrder
     * @return float the total
     */
    private static function getBeslistPaymentTotal(Wienkit\BeslistOrdersClient\Entities\BeslistOrder $shopOrder)
    {
        return $shopOrder->price + $shopOrder->shipping;
    }
}
