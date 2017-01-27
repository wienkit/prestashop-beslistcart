{*
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
*
*}
<style>
    span.twitter-typeahead {
        width: 100%;
    }
    span.tt-dropdown-menu {
        width: 100%;
    }
    div.tt-suggestion {
        font-size: 13px;
        padding: 7px;
    }
    .bootstrap span.tt-suggestions {
        padding: 0;
    }
    .bootstrap .tt-suggestion p {
        border-bottom: 0;
    }
    div.tt-suggestion.tt-is-under-cursor,
    div.tt-suggestion:hover {
        background: #DCF4F9;
    }
</style>
<input type="hidden" name="beslistcart_loaded" value="1">
{if isset($product->id)}
    <div class="panel product-tab" id="product-ModuleBeslistcart">
        <input type="hidden" name="submitted_tabs[]" value="Beslistcart" />
        <h3 class="tab">{l s='Beslist.nl settings' mod='beslistcart'}</h3>
        <div class="row">
            <div class="alert alert-info" style="display:block; position:'auto';">
                <p>{l s='This interface allows you to edit the Beslist.nl shopping cart data.' mod='beslistcart'}</p>
                <p>{l s='You can also specify product/product combinations.' mod='beslistcart'}</p>
            </div>
        </div>
        <div class="row">
            <div class="form-group">
                <label class="control-label col-lg-3" for="beslist_category">
                    {l s='Beslist category ID' mod='beslistcart'}
                </label>
                <div class="col-lg-6">
                    <div class="form-group">
                        {assign var=beslist_category_name value=''}
                        <script>
                            $(document).ready(function() {
                                $('.typeahead').typeahead({
                                    name: 'categories',
                                    local: [
                                        {foreach $beslist_categories as $category}
                                            {if $category.id_beslist_category == $beslist_category}
                                                {assign var=beslist_category_name value=$category.name}
                                            {/if}
                                            {ldelim}
                                                value: '{$category.id_beslist_category|escape:'htmlall':'UTF-8'}',
                                                name: '[{$category.id_beslist_category|escape:'htmlall':'UTF-8'}] {$category.name|escape:'html':'UTF-8'}',
                                                tokens: [{foreach " "|explode:$category.name as $token}'{$token|escape:'html':'UTF-8'}',{/foreach}
                                                    '{$category.id_beslist_category|escape:'htmlall':'UTF-8'}'
                                                ]
                                            {rdelim},
                                        {/foreach}
                                    ],
                                    engine: Hogan,
                                    template: "<p>{literal}{{{name}}}{/literal}</p>"
                                });
                                $(document).on({
                                    'blur': function(e) {
                                        if(parseInt(e.currentTarget.value) != {$beslist_category|escape:'html':'UTF-8'}) {
                                            $("#currently_selected").val('{l s='Category changed, save the product first.' mod='beslistcart'}');
                                        }
                                    }
                                }, '.typeahead');
                            });
                        </script>
                        <input type="text" value="{$beslist_category|escape:'html':'UTF-8'}" class="form-control typeahead" autocomplete="off" name="beslistcart_category" id="beslistcart_category">
                        <div class="help-block">{l s='For a complete overview, look at: ' mod='beslistcart'} <a href="https://www.beslist.nl/categories/">https://www.beslist.nl/categories/</a></div>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="control-label col-lg-3" for="beslist_category">
                    {l s='Beslist category' mod='beslistcart'}
                </label>
                <div class="col-lg-6">
                    <input type="text" disabled id="currently_selected" value="{$beslist_category_name|escape:'html':'UTF-8'}"/>
                </div>
            </div>
        </div>
    </div>
    <div class="panel attributes-tab" id="product-ModuleBeslistcartAttributes">
        <h3 class="tab">{l s='Beslist.nl products' mod='beslistcart'}</h3>
        <div class="row">
            <div class="col-lg-12">
                <table class="table">
                    <thead>
                    <tr>
                        <th class="width: 10%; min-width: 50px;" align="center"><span class="title_box">{l s='Published' mod='beslistcart'}</span></th>
                        <th style="width: 40%"><span class="title_box">{l s='Product' mod='beslistcart'}</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td class="fixed-width-xs" align="center"><input type="checkbox" id="toggle_beslistcart_check" /></td>
                        <td colspan="2">-- {l s='All products' mod='beslistcart'} -- </td>
                    </tr>
                    {foreach $attributes AS $index => $attribute}
                        {assign var=selected value=''}
                        {assign var=delivery_code_nl value=''}
                        {assign var=delivery_code_be value=''}
                        {if array_key_exists($attribute['id_product_attribute'], $beslist_products)}
                            {assign var=selected value=$beslist_products[$attribute['id_product_attribute']]['published']}
                            {assign var=delivery_code_nl value=$beslist_products[$attribute['id_product_attribute']]['delivery_code_nl']}
                            {assign var=delivery_code_be value=$beslist_products[$attribute['id_product_attribute']]['delivery_code_be']}
                        {/if}
                        <tr {if $index is odd}class="alt_row"{/if}>
                            <td class="fixed-width-xs" align="center"><input type="checkbox"
                                                                             name="beslistcart_published_{$attribute['id_product']|escape:'htmlall':'UTF-8'}_{$attribute['id_product_attribute']|escape:'htmlall':'UTF-8'}"
                                                                             {if $selected == true}checked="checked"{/if}
                                                                             value="1" />
                            </td>
                            <td class="clickable collapsed" data-toggle="collapse" data-target=".{$index|escape:'htmlall':'UTF-8'}collapsed">
                                {$product_designation[$attribute['id_product_attribute']]|escape:'htmlall':'UTF-8'}
                                <i class="icon-caret-up pull-right"></i>
                            </td>
                        </tr>
                        <tr class="collapse out {$index|escape:'htmlall':'UTF-8'}collapsed{if $index is odd} alt_row{/if}">
                            <td>{l s='Custom Delivery time NL (optional)' mod='beslistcart'}</td>
                            <td>
                                <input name="beslistcart_delivery_code_nl_{$attribute['id_product']|escape:'htmlall':'UTF-8'}_{$attribute['id_product_attribute']|escape:'htmlall':'UTF-8'}" id="beslistcart_delivery_code_{$attribute['id_product']|escape:'htmlall':'UTF-8'}_{$attribute['id_product_attribute']|escape:'htmlall':'UTF-8'}" type="text" value="{$delivery_code_nl|escape:'html':'UTF-8'}">
                            </td>
                        </tr>
                        <tr class="collapse out {$index|escape:'htmlall':'UTF-8'}collapsed{if $index is odd} alt_row{/if}">
                            <td>{l s='Custom Delivery time BE (optional)' mod='beslistcart'}</td>
                            <td>
                                <input name="beslistcart_delivery_code_be_{$attribute['id_product']|escape:'htmlall':'UTF-8'}_{$attribute['id_product_attribute']|escape:'htmlall':'UTF-8'}" id="beslistcart_delivery_code_{$attribute['id_product']|escape:'htmlall':'UTF-8'}_{$attribute['id_product_attribute']|escape:'htmlall':'UTF-8'}" type="text" value="{$delivery_code_be|escape:'html':'UTF-8'}">
                            </td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
        <div class="panel-footer">
            <a href="{$link->getAdminLink('AdminProducts')|escape:'html':'UTF-8'}{if isset($smarty.request.page) && $smarty.request.page > 1}&amp;submitFilterproduct={$smarty.request.page|intval}{/if}" class="btn btn-default"><i class="process-icon-cancel"></i> {l s='Cancel' mod='beslistcart'}</a>
            <button type="submit" name="submitAddproduct" class="btn btn-default pull-right" disabled="disabled"><i class="process-icon-loading"></i> {l s='Save' mod='beslistcart'}</button>
            <button type="submit" name="submitAddproductAndStay" class="btn btn-default pull-right" disabled="disabled"><i class="process-icon-loading"></i> {l s='Save and stay' mod='beslistcart'}</button>
        </div>
    </div>
    <script>
        $('#toggle_beslistcart_check').click(function() {
            var value = $('#toggle_beslistcart_check').prop('checked');
            var checkBoxes = $("input[name^=beslistcart_published_]");
            checkBoxes.prop("checked", value);
        });
    </script>
{/if}
