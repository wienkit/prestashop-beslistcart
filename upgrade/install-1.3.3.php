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
 *  @copyright 2013-2017 Wienk IT
 *  @license   LICENSE.txt
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_3_3()
{
    return Db::getInstance()->execute(
        'UPDATE `'._DB_PREFIX_.'configuration` 
        SET `id_shop_group` = NULL, `id_shop` = NULL WHERE name = \'BESLIST_CART_ORDERS_INITIALSTATE\''
    ) && Configuration::updateValue('BESLIST_CART_USE_LONG_DESCRIPTION', false);
}
