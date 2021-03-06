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

    /** @var bool */
    public $published = false;

    /** @var int */
    public $status = self::STATUS_NEW;

    /** @var string */
    public $delivery_code_nl;

    /** @var string */
    public $delivery_code_be;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'beslist_product',
        'primary' => 'id_beslist_product',
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
            'published' => array(
                'type' => self::TYPE_BOOL,
                'shop' => true,
                'validate' => 'isBool'
            ),
            'status' => array(
                'type' => self::TYPE_INT,
                'shop' => true,
                'validate' => 'isInt'
            ),
            'delivery_code_nl' => array(
                'type' => self::TYPE_STRING,
                'shop' => true,
                'validate' => 'isString'
            ),
            'delivery_code_be' => array(
                'type' => self::TYPE_STRING,
                'shop' => true,
                'validate' => 'isString'
            )
        )
    );

    /**
     * Return the full list of categories, indexed by id
     *
     * @param int $id_lang
     * @return array
     */
    public static function getShopCategoriesComplete($id_lang = null)
    {
        $sql = 'SELECT category.id_category, category.id_parent, lang.name FROM '._DB_PREFIX_.'category category 
                INNER JOIN '._DB_PREFIX_.'category_lang lang ON category.id_category = lang.id_category
                WHERE lang.`id_lang` = ' . (int)$id_lang . '
                ORDER BY category.level_depth ASC';
        $rows = Db::getInstance()->executeS($sql);
        $result = array();
        foreach ($rows as $row) {
            if (array_key_exists($row['id_parent'], $result)) {
                $result[$row['id_category']] = $result[$row['id_parent']] . ' > ' . $row['name'];
            } else {
                $result[$row['id_category']] = $row['name'];
            }
        }
        return $result;
    }

    /**
     * Returns all Beslist products
     * @param int $id_lang
     * @param null $limit
     * @param int $start
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public static function getLoadedBeslistProducts($id_lang = null, $limit = null, $start = 0)
    {
        $filter_stock = (bool)Configuration::get('BESLIST_CART_FILTER_NO_STOCK');

        $sql = "SELECT b.*,
            p.*, prattr.`id_product_attribute`, prattr.`reference` AS attribute_reference, 
            prattr.`weight` AS attribute_weight,
            product_shop.*, pl.* , m.`name` AS manufacturer_name, s.`name` AS supplier_name,
            SUM(st.`quantity`) as stock, st.`out_of_stock` AS out_of_stock_behaviour,
            prattr.ean13 as attrean, size.`name` AS size, color.`name` AS color, 
            color.`id_attribute` AS variant, attrimg.id_image AS attribute_image
    				FROM `" . _DB_PREFIX_ . "beslist_product` b
            LEFT JOIN `" . _DB_PREFIX_ . "product` p ON (b.`id_product` = p.`id_product`)" .
                Shop::addSqlAssociation('product', 'p') . "
            LEFT JOIN `" . _DB_PREFIX_ . "product_lang` pl ON (p.`id_product` = pl.`id_product`" .
                Shop::addSqlRestrictionOnLang('pl') . ")
            LEFT JOIN `" . _DB_PREFIX_ . "manufacturer` m ON (m.`id_manufacturer` = p.`id_manufacturer`)
            LEFT JOIN `" . _DB_PREFIX_ . "supplier` s ON (s.`id_supplier` = p.`id_supplier`)
            LEFT JOIN `" . _DB_PREFIX_ . "stock_available` st ON (
              b.`id_product` = st.`id_product` AND
              b.`id_product_attribute` = st.`id_product_attribute`
            )
            LEFT JOIN `" . _DB_PREFIX_ . "product_attribute` prattr ON (
              b.`id_product_attribute` = prattr.`id_product_attribute`
            )
            LEFT JOIN (
                SELECT pac.`id_product_attribute`, pa.`id_attribute`, pal.`name`
                FROM `" . _DB_PREFIX_ . "product_attribute_combination` pac 
                INNER JOIN `" . _DB_PREFIX_ . "attribute` pa 
                    ON pac.`id_attribute` = pa.`id_attribute`
                INNER JOIN `" . _DB_PREFIX_ . "attribute_lang` pal
                    ON pal.`id_attribute` = pa.`id_attribute`
                WHERE pa.`id_attribute_group` = " . (int)Configuration::get('BESLIST_CART_ATTRIBUTE_SIZE') . "
            ) as size
              ON ( b.`id_product_attribute` = size.`id_product_attribute` ) 
            LEFT JOIN (
                SELECT pac.`id_product_attribute`, pa.`id_attribute`, pal.`name`
                FROM `" . _DB_PREFIX_ . "product_attribute_combination` pac 
                INNER JOIN `" . _DB_PREFIX_ . "attribute` pa 
                    ON pac.`id_attribute` = pa.`id_attribute`
                INNER JOIN `" . _DB_PREFIX_ . "attribute_lang` pal
                    ON pal.`id_attribute` = pa.`id_attribute`
                WHERE pa.`id_attribute_group` = " . (int)Configuration::get('BESLIST_CART_ATTRIBUTE_COLOR') . "
            ) as color
              ON ( b.`id_product_attribute` = color.`id_product_attribute` )
            LEFT JOIN `" . _DB_PREFIX_ . "product_attribute_image` attrimg ON (
              b.`id_product_attribute` = attrimg.`id_product_attribute`
            )
            WHERE pl.`id_lang` = " . (int)$id_lang . "
              AND product_shop.`active` = 1
            GROUP BY b.`id_beslist_product`, b.`id_product`, b.`id_product_attribute`
                " . ($filter_stock? "HAVING SUM(st.`quantity`) > 0" : "") . "
            ORDER BY p.id_product";
        if (isset($limit)) {
            $sql .= " LIMIT " . (int)$start . ", " . (int)$limit;
        }
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /**
     * Retrieve the product reference for the Beslist product
     * @param int $matcher
     * @return int|string
     */
    public function getReference($matcher = BeslistCart::BESLIST_MATCH_DEFAULT)
    {
        $isAttribute = isset($this->id_product_attribute)
            && !empty($this->id_product_attribute)
            && $this->id_product_attribute != 0;

        switch ((int)$matcher) {
            case BeslistCart::BESLIST_MATCH_EAN13:
                if ($isAttribute) {
                    $query = new DbQuery();
                    $query->select('pa.ean13');
                    $query->from('product_attribute', 'pa');
                    $query->where('pa.id_product_attribute = \'' . (int)$this->id_product_attribute . '\'');
                    $query->where('pa.id_product = ' . (int)$this->id_product);
                    return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
                } else {
                    $query = new DbQuery();
                    $query->select('p.ean13');
                    $query->from('product', 'p');
                    $query->where('p.id_product = \'' . (int)$this->id_product . '\'');
                    return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
                }
                break;
            case BeslistCart::BESLIST_MATCH_REFERENCE:
                if ($isAttribute) {
                    $query = new DbQuery();
                    $query->select('pa.reference');
                    $query->from('product_attribute', 'pa');
                    $query->where('pa.id_product_attribute = \'' . (int)$this->id_product_attribute . '\'');
                    $query->where('pa.id_product = ' . (int)$this->id_product);
                    return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
                } else {
                    $query = new DbQuery();
                    $query->select('p.reference');
                    $query->from('product', 'p');
                    $query->where('p.id_product = \'' . (int)$this->id_product . '\'');
                    return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($query);
                }
                break;
            case BeslistCart::BESLIST_MATCH_STORECOMMANDER:
                if ($isAttribute) {
                    return $this->id_product . '_' . $this->id_product_attribute;
                } else {
                    return $this->id_product;
                }
                break;
            case BeslistCart::BESLIST_MATCH_DEFAULT:
            default:
                if ($isAttribute) {
                    return $this->id_product_attribute . '-' . $this->id_product;
                } else {
                    return $this->id_product;
                }
                break;
        }
    }

    /**
     * Get the shop ids for a Beslist product
     *
     * @param BeslistProduct $beslistProduct
     * @return array|false|mysqli_result|null|PDOStatement|resource
     */
    public static function getShops($beslistProduct)
    {
        $sql = new DbQuery();
        $sql->select('ps.id_shop');
        $sql->from('product_shop', 'ps');
        $sql->where('ps.id_product = ' . (int) $beslistProduct->id_product);
        return Db::getInstance()->executeS($sql);
    }

    /**
     * Returns the BeslistProduct data for a product ID
     * @param string $id_product
     * @return array the BeslistProduct data
     */
    public static function getByProductId($id_product)
    {
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
      			SELECT *
      			FROM `' . _DB_PREFIX_ . 'beslist_product`
      			WHERE `id_product` = ' . (int)$id_product);
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
      			FROM `' . _DB_PREFIX_ . 'beslist_product`
      			WHERE `id_product` = ' . (int)$id_product . '
            AND `id_product_attribute` = ' . (int)$id_product_attribute);
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
                FROM `' . _DB_PREFIX_ . 'beslist_product`
                WHERE `status` > 0 
                LIMIT 1000')
        );
    }

    /**
     * Helper function to get the price
     *
     * @param $id_product
     * @param $id_product_attribute
     * @param Context|null $context
     * @param bool $usereduc
     * @param null $specific_price_output
     * @return float
     */
    public static function getPriceStatic(
        $id_product,
        $id_product_attribute,
        Context $context = null,
        $usereduc = true,
        &$specific_price_output = null
    ) {
        if (!$context) {
            $context = Context::getContext();
        }

        $id_currency = Validate::isLoadedObject($context->currency)
            ? (int)$context->currency->id
            : (int)Configuration::get('PS_CURRENCY_DEFAULT');
        $id_group = Configuration::get('BESLIST_CART_CUSTOMER_GROUP');

        return Product::priceCalculation(
            $context->shop->id,
            $id_product,
            $id_product_attribute,
            $context->country->id,
            0,
            0,
            $id_currency,
            $id_group,
            1,
            true,
            2,
            false,
            $usereduc,
            true,
            $specific_price_output,
            true,
            null,
            false,
            null,
            0
        );
    }

    public function getProductWeights($id_lang = null)
    {
        $sql = "SELECT p.weight, pl.available_now, ";
        if ($this->id_product_attribute > 0) {
            $sql .= "pa.weight as attribute_weight";
        } else {
            $sql .= "0 as attribute_weight";
        }
        $sql .= " FROM `" . _DB_PREFIX_ . "product` p ";
        $sql .= Shop::addSqlAssociation('product', 'p');
        $sql .= " LEFT JOIN `" . _DB_PREFIX_ . "product_lang` pl ON (p.`id_product` = pl.`id_product` ";
        $sql .= Shop::addSqlRestrictionOnLang('pl') . ")";
        if ($this->id_product_attribute > 0) {
            $sql .= " LEFT JOIN `" . _DB_PREFIX_ . "product_attribute` pa ON (
              p.`id_product` = pa.`id_product`
            ) ";
        }
        $sql .= " WHERE p.`id_product` = " . (int) $this->id_product . " AND pl.`id_lang` = " . (int) $id_lang;
        if ($this->id_product_attribute > 0) {
            $sql .= " AND pa.`id_product_attribute` = " . (int) $this->id_product_attribute;
        }
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }
}
