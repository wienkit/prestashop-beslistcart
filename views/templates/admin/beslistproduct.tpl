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
*  @copyright 2013-2016 Wienk IT
*  @license   LICENSE.txt
*
*}
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
                <div class="col-lg-1">
                    <span class="pull-right"></span>
                </div>
                <label class="control-label col-lg-3" for="beslist_category">
                    {l s='Beslist category' mod='beslistcart'}
                </label>
                <div class="col-lg-6">
                    <div class="form-group">
                        <select class="selectpicker form-control" data-live-search="true" name="beslistcart_category" id="beslistcart_category">
                            {foreach $beslist_categories as $category}
                                <option value="{$category.id_beslist_category|escape:'htmlall':'UTF-8'}" {if $category.id_beslist_category == $beslist_category}selected="selected"{/if}>{$category.name|escape:'html':'UTF-8'}</option>
                            {/foreach}
                        </select>
                        <script>
                            $('.selectpicker').selectpicker('render');
                        </script>
                    </div>
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
                        <th style="width: 50%"><span class="title_box">{l s='Product' mod='beslistcart'}</span></th>
                        <th style="width: 40%"><span class="title_box">{l s='Custom price (optional)' mod='beslistcart'}</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td class="fixed-width-xs" align="center"><input type="checkbox" id="toggle_beslistcart_check" /></td>
                        <td>-- {l s='All products' mod='beslistcart'} -- </td>
                        <td>
                            <div class="input-group">
                                <span class="input-group-addon"> &euro;</span>
                                <input id="toggle_beslistcart_price" type="text" onchange="noComma('toggle_beslistcart_price');" maxlength="27">
                            </div>
                        </td>
                    </tr>
                    {foreach $attributes AS $index => $attribute}
                        {assign var=price value=''}
                        {assign var=selected value=''}
                        {if array_key_exists($attribute['id_product_attribute'], $beslist_products)}
                            {assign var=price value=$beslist_products[$attribute['id_product_attribute']]['price']}
                            {assign var=selected value=$beslist_products[$attribute['id_product_attribute']]['published']}
                        {/if}
                        <tr {if $index is odd}class="alt_row"{/if}>
                            <td class="fixed-width-xs" align="center"><input type="checkbox"
                                                                             name="beslistcart_published_{$attribute['id_product']|escape:'htmlall':'UTF-8'}_{$attribute['id_product_attribute']|escape:'htmlall':'UTF-8'}"
                                                                             {if $selected == true}checked="checked"{/if}
                                                                             value="1" />
                            </td>
                            <td>
                                {$product_designation[$attribute['id_product_attribute']]|escape:'htmlall':'UTF-8'}
                            </td>
                            <td>
                                <div class="input-group">
                                    <span class="input-group-addon"> &euro;</span>
                                    <input name="beslistcart_price_{$attribute['id_product']|escape:'htmlall':'UTF-8'}_{$attribute['id_product_attribute']|escape:'htmlall':'UTF-8'}" id="beslistcart_price_{$attribute['id_product']|escape:'htmlall':'UTF-8'}_{$attribute['id_product_attribute']|escape:'htmlall':'UTF-8'}" type="text" value="{$price|escape:'html':'UTF-8'}" onchange="noComma('beslistcart_price_{$attribute['id_product']|escape:'htmlall':'UTF-8'}_{$attribute['id_product_attribute']|escape:'htmlall':'UTF-8'}');" maxlength="27">
                                </div>
                            </td>
                        </tr>
                    {/foreach}
                    </tbody>
                </table>
            </div>
        </div>
        <div class="panel-footer">
            <a href="{$link->getAdminLink('AdminProducts')|escape:'htmlall':'UTF-8'}" class="btn btn-default"><i class="process-icon-cancel"></i> {l s='Cancel' mod='beslistcart'}</a>
            <button type="submit" name="submitAddproduct" class="btn btn-default pull-right"><i class="process-icon-save"></i> {l s='Save' mod='beslistcart'}</button>
            <button type="submit" name="submitAddproductAndStay" class="btn btn-default pull-right"><i class="process-icon-save"></i> {l s='Save and stay' mod='beslistcart'}</button>
        </div>
    </div>
    <script>
        $('#toggle_beslistcart_check').click(function() {
            var value = $('#toggle_beslistcart_check').prop('checked');
            var checkBoxes = $("input[name^=beslistcart_published_]");
            checkBoxes.prop("checked", value);
        });
        $('#toggle_beslistcart_price').change(function() {
            var value = $(this).val();
            var prices = $("input[name^=beslistcart_price_]");
            prices.val(value);
        })
    </script>
{/if}
