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

class BeslistFeaturesHelper
{

    /** @var array */
    private $featureValues = array();

    /** @var array */
    private $features = array();

    /** @var array */
    private $attributes = array();

    /** @var array */
    private $attributeGroups = array();

    /** @var bool */
    private $use_attributes_in_title;

    /** @var int */
    private $attribute_size;

    /** @var int */
    private $attribute_color;

    public function __construct()
    {
        $context = Context::getContext();

        $this->use_attributes_in_title = (bool) Configuration::get('BESLIST_CART_ATTRIBUTES_IN_TITLE');
        $this->attribute_size = (int) Configuration::get('BESLIST_CART_ATTRIBUTE_SIZE');
        $this->attribute_color = (int) Configuration::get('BESLIST_CART_ATTRIBUTE_COLOR');

        foreach (Feature::getFeatures($context->language->id) as $feature) {
            $id_feature = $feature['id_feature'];
            $this->featureValues[$id_feature] = array();
            foreach (FeatureValue::getFeatureValuesWithLang($context->language->id, $id_feature) as $featureValue) {
                $this->featureValues[$id_feature][$featureValue['id_feature_value']] = $featureValue;
            }
            $this->features[$id_feature] = $feature;
        }

        foreach (AttributeGroup::getAttributesGroups($context->language->id) as $attributeGroup) {
            $id_attribute_group = $attributeGroup['id_attribute_group'];
            $this->attributes[$id_attribute_group] = array();
            foreach (AttributeGroup::getAttributes($context->language->id, $id_attribute_group) as $attributeValue) {
                $this->attributes[$id_attribute_group][$attributeValue['id_attribute']] = $attributeValue;
            }
            $this->attributeGroups[$id_attribute_group] = $attributeGroup;
        }
    }

    public function handleFeedEntry($product)
    {
        $result = '';
        $featureField = '<features_combined><![CDATA[';
        $prFeatures = Product::getFeaturesStatic($product['id_product']);
        foreach ($prFeatures as $prFeature) {
            $rawName = $this->features[$prFeature['id_feature']]['name'];
            $name = Tools::strtolower($rawName);
            $name = preg_replace('/[^a-z0-9]/', '', $name);
            if ($name != '' &&
                array_key_exists($prFeature['id_feature'], $this->features) &&
                array_key_exists($prFeature['id_feature_value'], $this->featureValues[$prFeature['id_feature']]) &&
                $this->featureValues[$prFeature['id_feature']][$prFeature['id_feature_value']]['value'] != ''
            ) {
                $value = $this->featureValues[$prFeature['id_feature']][$prFeature['id_feature_value']]['value'];
                $result .= "<{$name}><![CDATA[{$value}]]></{$name}>";
                $featureField .= "{$rawName}: {$value}\\\\n";
            }
        }

        $nameSuffix = '';
        $attributes = Product::getAttributesParams($product['id_product'], $product['id_product_attribute']);
        foreach ($attributes as $attribute) {
            $rawName = $this->attributeGroups[$attribute['id_attribute_group']]['name'];
            $name = Tools::strtolower($rawName);
            $name = preg_replace('/[^a-z0-9]/', '', $name);
            if ($name != '' &&
                array_key_exists($attribute['id_attribute_group'], $this->attributes) &&
                array_key_exists($attribute['id_attribute'], $this->attributes[$attribute['id_attribute_group']]) &&
                $this->attributes[$attribute['id_attribute_group']][$attribute['id_attribute']]['name'] != ''
            ) {
                $value = $this->attributes[$attribute['id_attribute_group']][$attribute['id_attribute']]['name'];
                $result .= "<{$name}><![CDATA[{$value}]]></{$name}>";
                $featureField .= "{$rawName}: {$value}\\\\n";
                if ((isset($product['color']) && $attribute['id_attribute_group'] == $this->attribute_color) ||
                    (isset($product['size']) && $attribute['id_attribute_group'] == $this->attribute_size)
                ) {
                    continue;
                }
                $nameSuffix .= $this->use_attributes_in_title ? ", " . $value : '';
            }
        }
        $featureField .= "]]></features_combined>";
        $result .= $featureField;
        $result .= "<title><![CDATA[{$product['name']}{$nameSuffix}]]></title>";
        return $result;
    }
}
