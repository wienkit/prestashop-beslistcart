Beslist Shopping cart integration
=================================

1. Introduction
===============
The Beslist Shopping cart integration module allows you to import your orders from the Beslist Shopping cart account and synchronize your products to it.
That means that you can handle all logic you would normally follow, from your own backoffice.


2. Installation
===============
  1. Install the module by uploading the zip file.
  2. Enable it in a Shop context and fill out the settings on the Configure page of the Module (in the module list).
  		- It is recommended to use the Test API first, if everything works correctly, you can change it to the production values.

3. Usage
========

3.1 Orders
----------
Go to your Orders -> Beslist.nl orders page. You can now click on 'Synchronize orders' to retrieve the orders from Beslist.
If you now handle your order (via the Order details page), you can see the data that was used.
The Beslist Order ID is imported as the transaction ID of the payment.

If you are in testing mode, a button to clean the test data will appear on the Beslist.nl orders page.

3.2 Products
------------
You can also synchronize your products to Beslist.
You can use the productfeed on http://www.uwdomeinnaam.nl/modules/beslistcart/feed.php

You can manage your product data on the product page, a new tab will be visible for the Beslist settings.
In there you can publish the product.

When you edit a product, a message will be sent to Beslist.
However, it is very well possible that he product isn't yet available on Beslist, as they only process the productfeed once a day.
You will then receive a 404 error, telling you that the product couln't be found.
After the productfeed is processed again (the next day at 8AM), you can use the 'Synchronize products' button to synchronize the new products.
After a succesful run, the product will stay available and no 404 error will occur anymore.

A new tab is also added to the Catalog menu, showing you an overview of the currently available Beslist products.
It also shows a status, in case of an erroneous transfer.
You can click the 'Synchronize' button in the page's header to synchronize all products that have an erroneous status.

4. Frequently asked questions
=============================
1. Can I cancel an order via the connector?
- No this is currently not possible via the Beslist Order API.

2. Can the order be imported automatically?
- Yes, however, you need to setup a cron task for this.

3. Can I also import orders with products that aren't in stock?
- Yes, the product out of stock behaviour is temporarily changed during the import.

4. I get an error when I import multiple orders
- The error is caused by a bug in Prestashop core, it's not a problem (just a warning), but a bug report has been created http://forge.prestashop.com/browse/PSCSX-7858.
You can manually fix this by adding the following code in an override class for Cart:

/**
 * Get the delivery option selected, or if no delivery option was selected,
 * the cheapest option for each address
 *
 * @param Country|null $default_country
 * @param bool         $dontAutoSelectOptions
 * @param bool         $use_cache
 *
 * @return array|bool|mixed Delivery option
 */
public function getDeliveryOption($default_country = null, $dontAutoSelectOptions = false, $use_cache = true)
{
    static $cache = array();
    $cache_id = (int)(is_object($default_country) ? $default_country->id : 0).'-'.(int)$dontAutoSelectOptions.'-'.$this->id;
    if (isset($cache[$cache_id]) && $use_cache) {
        return $cache[$cache_id];
    }

    $delivery_option_list = $this->getDeliveryOptionList($default_country);

    // The delivery option was selected
    if (isset($this->delivery_option) && $this->delivery_option != '') {
        $delivery_option = Tools::unSerialize($this->delivery_option);
        $validated = true;
        foreach ($delivery_option as $id_address => $key) {
            if (!isset($delivery_option_list[$id_address][$key])) {
                $validated = false;
                break;
            }
        }

        if ($validated) {
            $cache[$cache_id] = $delivery_option;
            return $delivery_option;
        }
    }

    if ($dontAutoSelectOptions) {
        return false;
    }

    // No delivery option selected or delivery option selected is not valid, get the better for all options
    $delivery_option = array();
    foreach ($delivery_option_list as $id_address => $options) {
        foreach ($options as $key => $option) {
            if (Configuration::get('PS_CARRIER_DEFAULT') == -1 && $option['is_best_price']) {
                $delivery_option[$id_address] = $key;
                break;
            } elseif (Configuration::get('PS_CARRIER_DEFAULT') == -2 && $option['is_best_grade']) {
                $delivery_option[$id_address] = $key;
                break;
            } elseif ($option['unique_carrier'] && in_array(Configuration::get('PS_CARRIER_DEFAULT'), array_keys($option['carrier_list']))) {
                $delivery_option[$id_address] = $key;
                break;
            }
        }

        reset($options);
        if (!isset($delivery_option[$id_address])) {
            $delivery_option[$id_address] = key($options);
        }
    }

    $cache[$cache_id] = $delivery_option;

    return $delivery_option;
}
