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
 *  @copyright 2013-2018 Wienk IT
 *  @license   LICENSE.txt
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * @param BeslistCart $module
 * @return bool
 */
function upgrade_module_1_4_0($module)
{
    $shops = Shop::getShops();
    if (count($shops) > 1) {
        foreach ($shops as $shop) {
            Configuration::updateValue(
                'BESLIST_CART_FEED_LOCATION',
                $module->getDefaultFeedLocation($module->getDefaultFeedFilename($shop['id_shop'])),
                false,
                $shop['id_shop_group'],
                $shop['id_shop']
            );
        }
    } else {
        Configuration::updateValue(
            'BESLIST_CART_FEED_LOCATION',
            $module->getDefaultFeedLocation($module->getDefaultFeedFilename())
        );
    }
    return true;

}
