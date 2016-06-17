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

class BeslistProduct extends ObjectModel
{
    const STATUS_OK = 0;
    const STATUS_NEW = 1;
    const STATUS_STOCK_UPDATE = 2;
    const STATUS_INFO_UPDATE = 3;

    /** @var int */
    public $id_beslist_product;

    /** @var int */
    public $id_product;

    /** @var int */
    public $id_product_attribute;

    /** @var int */
    public $id_beslist_category;

    /** @var bool */
    public $published = false;

    /** @var float */
    public $price;

    /** @var int */
    public $status = self::STATUS_NEW;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'beslist_product',
        'primary' => 'id_beslist_product',
        'multishop' => true,
        'fields' => array(
            'id_product' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true
            ),
            'id_product_attribute' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true
            ),
            'id_beslist_category' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId',
                'required' => true
            ),
            'published' => array(
                'type' => self::TYPE_BOOL,
                'shop' => true,
                'validate' => 'isBool'
            ),
            'price' => array(
                'type' => self::TYPE_FLOAT,
                'shop' => true,
                'validate' => 'isPrice'
            ),
            'status' => array(
                'type' => self::TYPE_INT,
                'shop' => true,
                'validate' => 'isInt'
            )
        )
    );

    /**
     * Return the categories in an indexed array
     * @return array
     */
    public static function getBeslistCategories()
    {
        $sql = 'SELECT id_beslist_category, name FROM `'._DB_PREFIX_.'beslist_categories` ORDER BY name ASC';
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /**
     * Returns all Beslist products
     * @return array
     */
    public static function getLoadedBeslistProducts($id_lang = null)
    {
        // $id_lang = $context->language->id;

        $sql = 'SELECT b.*, c.`name` AS category_name,
        p.*, product_shop.*, pl.* ,
        m.`name` AS manufacturer_name, s.`name` AS supplier_name
				FROM `'._DB_PREFIX_.'beslist_product` b
        LEFT JOIN `'._DB_PREFIX_.'product` p ON (b.`id_product` = p.`id_product`)
				'.Shop::addSqlAssociation('product', 'p').'
				LEFT JOIN `'._DB_PREFIX_.'product_lang` pl ON (p.`id_product` = pl.`id_product`
        '.Shop::addSqlRestrictionOnLang('pl').')
				LEFT JOIN `'._DB_PREFIX_.'manufacturer` m ON (m.`id_manufacturer` = p.`id_manufacturer`)
				LEFT JOIN `'._DB_PREFIX_.'supplier` s ON (s.`id_supplier` = p.`id_supplier`)
        LEFT JOIN `'._DB_PREFIX_.'beslist_categories` c ON (b.`id_beslist_category` = c.`id_beslist_category`)
				WHERE pl.`id_lang` = '.(int)$id_lang.'
          AND product_shop.`active` = 1
				ORDER BY p.id_product ASC LIMIT 5';
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /**
     * Returns the BeslistProduct data for a product ID
     * @param string $id_product
     * @return array the BolPlazaProduct data
     */
    public static function getByProductId($id_product)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
			SELECT *
			FROM `'._DB_PREFIX_.'beslist_product`
			WHERE `id_product` = '.(int)$id_product);
    }

    /**
     * Returns the BeslistProduct data for a product ID and attribute ID
     * @param string $id_product
     * @param string $id_product_attribute
     * @return array the BeslistProduct data
     */
    public static function getIdByProductAndAttributeId($id_product, $id_product_attribute)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
			SELECT `id_beslist_product`
			FROM `'._DB_PREFIX_.'beslist_product`
			WHERE `id_product` = '.(int)$id_product.'
      AND `id_product_attribute` = '.(int)$id_product_attribute);
    }

    /**
     * Returns a list of BeslistProduct objects that need an update
     * @return array
     */
    public static function getUpdatedProducts()
    {
        return ObjectModel::hydrateCollection(
            'BeslistProduct',
            Db::getInstance()->executeS('
                SELECT *
                FROM `'._DB_PREFIX_.'beslist_product`
                WHERE `status` > 0')
        );
    }

    /**
     * Returns a list of delivery codes
     * @return array
     */
    public static function getDeliveryCodes()
    {
          return array(
            array(
                'deliverycode' => '24uurs-23',
                'description' => 'Ordered before 23:00 on working days, delivered the next working day.',
                'shipsuntil' => 23,
                'addtime' => 1
            ),
            array(
                'deliverycode' => '24uurs-22',
                'description' => 'Ordered before 22:00 on working days, delivered the next working day.',
                'shipsuntil' => 22,
                'addtime' => 1
            ),
            array(
                'deliverycode' => '24uurs-21',
                'description' => 'Ordered before 21:00 on working days, delivered the next working day.',
                'shipsuntil' => 21,
                'addtime' => 1
            ),
            array(
                'deliverycode' => '24uurs-20',
                'description' => 'Ordered before 20:00 on working days, delivered the next working day.',
                'shipsuntil' => 20,
                'addtime' => 1
            ),
            array(
                'deliverycode' => '24uurs-19',
                'description' => 'Ordered before 19:00 on working days, delivered the next working day.',
                'shipsuntil' => 19,
                'addtime' => 1
            ),
            array(
                'deliverycode' => '24uurs-18',
                'description' => 'Ordered before 18:00 on working days, delivered the next working day.',
                'shipsuntil' => 18,
                'addtime' => 1
            ),
            array(
                'deliverycode' => '24uurs-17',
                'description' => 'Ordered before 17:00 on working days, delivered the next working day.',
                'shipsuntil' => 17,
                'addtime' => 1
            ),
            array(
                'deliverycode' => '24uurs-16',
                'description' => 'Ordered before 16:00 on working days, delivered the next working day.',
                'shipsuntil' => 16,
                'addtime' => 1
            ),
            array(
                'deliverycode' => '24uurs-15',
                'description' => 'Ordered before 15:00 on working days, delivered the next working day.',
                'shipsuntil' => 15,
                'addtime' => 1
            ),
            array(
                'deliverycode' => '24uurs-14',
                'description' => 'Ordered before 14:00 on working days, delivered the next working day.',
                'shipsuntil' => 14,
                'addtime' => 1
            ),
            array(
                'deliverycode' => '24uurs-13',
                'description' => 'Ordered before 13:00 on working days, delivered the next working day.',
                'shipsuntil' => 13,
                'addtime' => 1
            ),
            array(
                'deliverycode' => '24uurs-12',
                'description' => 'Ordered before 12:00 on working days, delivered the next working day.',
                'shipsuntil' => 12,
                'addtime' => 1
            ),
            array(
                'deliverycode' => '1-2d',
                'description' => '1-2 working days.',
                'shipsuntil' => 12,
                'addtime' => 2
            ),
            array(
                'deliverycode' => '2-3d',
                'description' => '2-3 working days.',
                'shipsuntil' => 12,
                'addtime' => 3
            ),
            array(
                'deliverycode' => '3-5d',
                'description' => '3-5 working days.',
                'shipsuntil' => 12,
                'addtime' => 5
            ),
            array(
                'deliverycode' => '4-8d',
                'description' => '4-8 working days.',
                'shipsuntil' => 12,
                'addtime' => 8
            ),
            array(
                'deliverycode' => '1-8d',
                'description' => '1-8 working days.',
                'shipsuntil' => 12,
                'addtime' => 8
            )
        );
    }
}
