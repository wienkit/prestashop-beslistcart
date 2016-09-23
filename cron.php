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

include(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/../../init.php');
require_once(dirname(__FILE__).'/beslistcart.php');
require_once(dirname(__FILE__).'/controllers/admin/AdminBeslistCartOrdersController.php');


if (Tools::getIsset($_GET['secure_key'])) {
    $secureKey = md5(_COOKIE_KEY_.Configuration::get('PS_SHOP_NAME').'BESLISTCART');
    if (!empty($secureKey) && $secureKey === $_GET['secure_key']) {
        $shop_ids = Shop::getCompleteListOfShopsID();
        foreach ($shop_ids as $shop_id) {
            Shop::setContext(Shop::CONTEXT_SHOP, (int)$shop_id);
            BeslistCart::synchronize();
        }
    }
}
