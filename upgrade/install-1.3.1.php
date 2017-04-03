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

function upgrade_module_1_3_1($object)
{
    return
        Configuration::deleteByName('BESLIST_CART_PRICE_MULTIPLICATION') &&
        Configuration::deleteByName('BESLIST_CART_PRICE_ADDITION') &&
        Configuration::deleteByName('BESLIST_CART_PRICE_ROUNDUP') &&
        Configuration::updateValue('BESLIST_CART_FILTER_NOSTOCK', false) &&
        Db::getInstance()->execute('ALTER TABLE `'._DB_PREFIX_.'beslist_product` DROP `price`') &&
        Db::getInstance()->execute('ALTER TABLE `'._DB_PREFIX_.'beslist_product` DROP `id_shop`');
}
