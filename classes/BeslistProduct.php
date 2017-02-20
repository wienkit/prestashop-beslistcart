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
 * @copyright 2013-2017 Wienk IT
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

    /** @var int */
    public $id_beslist_category;

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
            'id_beslist_category' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedId'
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
     * Return the categories in an indexed array
     * @return array
     */
    public static function getBeslistCategories()
    {
        $sql = 'SELECT id_beslist_category, name FROM `' . _DB_PREFIX_ . 'beslist_categories` ORDER BY name ASC';
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /**
     * Returns the category to beslist category mappings
     * @return array
     */
    public static function getMappedCategoryTree()
    {
        $sql = 'SELECT category.id_category, mappedparent.parentbeslistcategory FROM '._DB_PREFIX_.'category category
                INNER JOIN (
                   SELECT 
                        MIN(parent.nright - parent.nleft) as parentdist, 
                        parent.id_category as parentid, 
                        parent.nleft as parentnleft, 
                        parent.nright as parentnright, 
                        bc.id_beslist_category as parentbeslistcategory
                   FROM '._DB_PREFIX_.'category parent 
                   INNER JOIN '._DB_PREFIX_.'beslist_category bc ON bc.id_category = parent.id_category
                   GROUP BY parent.id_category, bc.id_beslist_category
                ) as mappedparent 
                ON mappedparent.parentnleft <= category.nleft 
                AND mappedparent.parentnright >= category.nright
                AND mappedparent.parentdist = (
                   SELECT MIN(parent.nright - parent.nleft) 
                   FROM '._DB_PREFIX_.'category parent 
                   INNER JOIN '._DB_PREFIX_.'beslist_category bc ON bc.id_category = parent.id_category
                   WHERE parent.nleft <= category.nleft AND parent.nright >= category.nright
                )';
        $result = array();
        $rows = Db::getInstance()->executeS($sql);
        foreach ($rows as $row) {
            $result[$row['id_category']] = $row['parentbeslistcategory'];
        }
        return $result;
    }

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
     * @return array
     */
    public static function getLoadedBeslistProducts($id_lang = null)
    {
        $sql = 'SELECT b.*,
            p.*, prattr.`id_product_attribute`, prattr.`reference` AS attribute_reference, 
            product_shop.*, pl.* , m.`name` AS manufacturer_name, s.`name` AS supplier_name,
            st.`quantity` as stock, st.`out_of_stock` AS out_of_stock_behaviour,
            prattr.ean13 as attrean, size.`name` AS size, color.`name` AS color, 
            color.`id_attribute` AS variant, attrimg.id_image AS attribute_image
    				FROM `' . _DB_PREFIX_ . 'beslist_product` b
            LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON (b.`id_product` = p.`id_product`)
    				' . Shop::addSqlAssociation('product', 'p') . '
    				LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (p.`id_product` = pl.`id_product`
            ' . Shop::addSqlRestrictionOnLang('pl') . ')
    				LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON (m.`id_manufacturer` = p.`id_manufacturer`)
    				LEFT JOIN `' . _DB_PREFIX_ . 'supplier` s ON (s.`id_supplier` = p.`id_supplier`)
            LEFT JOIN `' . _DB_PREFIX_ . 'stock_available` st ON (
              b.`id_product` = st.`id_product` AND
              b.`id_product_attribute` = st.`id_product_attribute`
            )
            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute` prattr ON (
              b.`id_product_attribute` = prattr.`id_product_attribute`
            )
            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` com ON (
              b.`id_product_attribute` = com.`id_product_attribute`
            )
            LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_image` attrimg ON (
              b.`id_product_attribute` = attrimg.`id_product_attribute`
            )
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute` attrsize ON (
              com.`id_attribute` = attrsize.`id_attribute` AND
              attrsize.`id_attribute_group` = ' . (int)Configuration::get('BESLIST_CART_ATTRIBUTE_SIZE') . '
            )
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` size ON (
              attrsize.`id_attribute` = size.`id_attribute`
            )
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute` attrcolor ON (
              com.`id_attribute` = attrcolor.`id_attribute` AND
              attrcolor.`id_attribute_group` = ' . (int)Configuration::get('BESLIST_CART_ATTRIBUTE_COLOR') . '
            )
            LEFT JOIN `' . _DB_PREFIX_ . 'attribute_lang` color ON (
              attrcolor.`id_attribute` = color.`id_attribute`
            )
            WHERE pl.`id_lang` = ' . (int)$id_lang . '
              AND product_shop.`active` = 1
              ' . ((bool)Configuration::get('BESLIST_CART_FILTER_NO_STOCK') ? 'AND st.`quantity` > 0' : '') . '
            GROUP BY b.`id_beslist_product`, b.`id_product`, b.`id_product_attribute`
    				ORDER BY p.id_product';
        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /**
     * Retrieve the product reference for the Beslist product
     * @return false|null|string the reference
     */
    public function getReference()
    {
        $isAttribute = isset($this->id_product_attribute)
            && !empty($this->id_product_attribute)
            && $this->id_product_attribute != 0;

        switch((int)Configuration::get('BESLIST_CART_MATCHER')) {
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
            case BeslistCart::BESLIST_MATCH_DEFAULT:
                if ($isAttribute) {
                    return $this->id_product_attribute . '-' . $this->id_product;
                } else {
                    return $this->id_product;
                }
                break;
            case BeslistCart::BESLIST_MATCH_STORECOMMANDER:
                if ($isAttribute) {
                    return $this->id_product . '_' . $this->id_product_attribute;
                } else {
                    return $this->id_product;
                }
                break;
            default:
                die(Tools::displayError("No Beslist matcher selected."));
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
}
