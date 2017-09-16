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
<input type="hidden" name="beslistcart_loaded" value="1">
{if isset($product->id)}
    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-info" role="alert">
                <i class="material-icons">help</i>
                <p>{l s='This interface allows you to edit the Beslist.nl shopping cart data.' mod='beslistcart'}</p>
                <p>{l s='You can also specify product/product combinations.' mod='beslistcart'}</p>
            </div>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><strong>{l s='Beslist.nl products' mod='beslistcart'}</strong></div>
        <div class="panel-body" id="beslistcart_combinations">
            <div>
                <table class="table table-striped table-no-bordered">
                    <thead>
                    <tr>
                        <th class="width: 10%; min-width: 50px;" align="center"><span class="title_box">{l s='Published' mod='beslistcart'}</span></th>
                        <th style="width: 40%"><span class="title_box">{l s='Product' mod='beslistcart'}</span></th>
                        <th>{l s='Custom Delivery time NL (optional)' mod='beslistcart'}</th>
                        <th>{l s='Custom Delivery time BE (optional)' mod='beslistcart'}</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td class="fixed-width-xs" align="center"><input type="checkbox" id="toggle_beslistcart_check" /></td>
                        <td colspan="3" class="bc-padded">-- {l s='All products' mod='beslistcart'} -- </td>
                    </tr>
                    {foreach $attributes AS $index => $attribute}
                        {assign var=selected value=''}
                        {assign var=delivery_code_nl value=''}
                        {assign var=delivery_code_be value=''}
                        {assign var=key value=$attribute['id_product']|cat:'_'|cat:$attribute['id_product_attribute']}
                        {if array_key_exists($attribute['id_product_attribute'], $beslist_products)}
                            {assign var=selected value=$beslist_products[$attribute['id_product_attribute']]['published']}
                            {assign var=delivery_code_nl value=$beslist_products[$attribute['id_product_attribute']]['delivery_code_nl']}
                            {assign var=delivery_code_be value=$beslist_products[$attribute['id_product_attribute']]['delivery_code_be']}
                        {/if}
                        <tr>
                            <td class="fixed-width-xs" align="center">
                                <input type="checkbox" name="beslistcart_published_{$key}" {if $selected == true}checked="checked"{/if} value="1" />
                            </td>
                            <td class="clickable collapsed bc-padded">
                                {$product_designation[$attribute['id_product_attribute']]}
                            </td>
                            <td>
                                <input name="beslistcart_delivery_code_nl_{$key}" id="beslistcart_delivery_code_{$key}" type="text" value="{$delivery_code_nl}" class="form-control">
                            </td>
                            <td>
                                <input name="beslistcart_delivery_code_be_{$key}" id="beslistcart_delivery_code_{$key}" type="text" value="{$delivery_code_be}" class="form-control">
                            </td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
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
