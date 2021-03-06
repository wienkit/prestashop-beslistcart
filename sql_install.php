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

$sql = array();

$sql[_DB_PREFIX_.'beslist_product'] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'beslist_product` (
              `id_beslist_product` int(11) NOT NULL AUTO_INCREMENT,
              `id_product` int(10) unsigned NOT NULL,
              `id_product_attribute` int(10) unsigned NOT NULL,
              `published` tinyint(1) NOT NULL DEFAULT \'0\',
              `status` tinyint(1) NOT NULL DEFAULT \'1\',
              `delivery_code_nl` VARCHAR(255),
              `delivery_code_be` VARCHAR(255),
              PRIMARY KEY (`id_beslist_product`),
              UNIQUE KEY(`id_product`, `id_product_attribute`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';
