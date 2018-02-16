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
*  @copyright 2013-2018 Wienk IT
*  @license   LICENSE.txt
*
*}
<input type="hidden" name="beslistcart_loaded" value="1">
{if isset($product->id)}
    <div class="panel attributes-tab" id="product-ModuleBeslistcartAttributes">
        <h3 class="tab">{l s='Beslist.nl products' mod='beslistcart'}</h3>
        <div class="row">
            <div class="col-lg-12">
                <table class="table">
                    <thead>
                    <tr>
                        <th class="width: 10%; min-width: 50px;" align="center"><span class="title_box">{l s='Published' mod='beslistcart'}</span></th>
                        <th style="width: 40%"><span class="title_box">{l s='Product' mod='beslistcart'}</span></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td class="fixed-width-xs" align="center"><input type="checkbox" id="toggle_beslistcart_check" /></td>
                        <td class="bc-padded">-- {l s='All products' mod='beslistcart'} -- </td>
                        <td></td>
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
                            <td class="clickable collapsed bc-padded" data-toggle="collapse" data-target=".{$index|escape:'htmlall':'UTF-8'}collapsed">
                                {$product_designation[$attribute['id_product_attribute']]|escape:'htmlall':'UTF-8'}
                                <i class="icon-caret-up pull-right"></i>
                            </td>
                            <td></td>
                        </tr>
                        <tr class="collapse out {$index|escape:'htmlall':'UTF-8'}collapsed{if $index is odd} alt_row{/if}">
                            <td></td>
                            <td>{l s='Custom Delivery time NL (optional)' mod='beslistcart'}</td>
                            <td>
                                <input name="beslistcart_delivery_code_nl_{$attribute['id_product']|escape:'htmlall':'UTF-8'}_{$attribute['id_product_attribute']|escape:'htmlall':'UTF-8'}" id="beslistcart_delivery_code_{$attribute['id_product']|escape:'htmlall':'UTF-8'}_{$attribute['id_product_attribute']|escape:'htmlall':'UTF-8'}" type="text" value="{$delivery_code_nl|escape:'html':'UTF-8'}">
                            </td>
                        </tr>
                        <tr class="collapse out {$index|escape:'htmlall':'UTF-8'}collapsed{if $index is odd} alt_row{/if}">
                            <td></td>
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
