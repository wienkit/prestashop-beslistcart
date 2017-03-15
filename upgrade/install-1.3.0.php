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

function upgrade_module_1_3_0($object)
{
    return $object->registerHook('displayBackOfficeCategory')
        && $object->registerHook('actionObjectCategoryDeleteAfter')
        && $object->registerHook('actionAdminCategoriesControllerSaveBefore')
        && Db::getInstance()->execute(
            'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'beslist_category` (
              `id_category` int(11) NOT NULL,
              `id_beslist_category` int(11) NOT NULL,
            PRIMARY KEY (`id_category`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;'
        )
        && Db::getInstance()->execute(
            'ALTER TABLE `'._DB_PREFIX_.'beslist_product`
            MODIFY COLUMN `id_beslist_category` int(11) NULL'
        );
}
