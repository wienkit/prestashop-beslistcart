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

$sql = array();

$sql[_DB_PREFIX_.'beslist_product'] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'beslist_product` (
              `id_beslist_product` int(11) NOT NULL AUTO_INCREMENT,
              `id_product` int(10) unsigned NOT NULL,
              `id_product_attribute` int(10) unsigned NOT NULL,
              `id_shop` INT(11) UNSIGNED NOT NULL DEFAULT \'1\',
              `id_beslist_category` int(11) NOT NULL,
              `published` tinyint(1) NOT NULL DEFAULT \'0\',
              `price` DECIMAL(20, 6) NOT NULL DEFAULT \'0.000000\',
              `status` tinyint(1) NOT NULL DEFAULT \'1\',
              PRIMARY KEY (`id_beslist_product`),
              UNIQUE KEY(`id_product`, `id_product_attribute`, `id_shop`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

$sql[_DB_PREFIX_.'beslist_categories'] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'beslist_categories` (
              `id_beslist_category` int(11) NOT NULL,
              `name` varchar(255),
              PRIMARY KEY (`id_beslist_category`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';
