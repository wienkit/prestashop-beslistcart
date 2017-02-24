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
{extends file="helpers/form/form.tpl"}

{block name="input_row"}
    {assign var=hasWrapper value=false}
    {if $input.name == 'beslist_cart_overwrite_categories' ||
        $input.name == 'beslist_cart_testmode' ||
        $input.name == 'beslist_cart_shopid' ||
        $input.name == 'beslist_cart_clientid' ||
        $input.name == 'beslist_cart_personalkey' ||
        $input.name == 'beslist_cart_shopitem_apikey' ||
        $input.name == 'beslist_cart_test_reference' ||
        $input.name == 'beslist_cart_startdate' ||
        $input.name == 'beslist_cart_carrier_nl' ||
        $input.name == 'beslist_cart_deliveryperiod_nl' ||
        $input.name == 'beslist_cart_deliveryperiod_nostock_nl' ||
        $input.name == 'beslist_cart_carrier_be' ||
        $input.name == 'beslist_cart_deliveryperiod_be' ||
        $input.name == 'beslist_cart_deliveryperiod_nostock_be'}
        {assign var=hasWrapper value=true}
        <div id="{$input.name|escape:'htmlall':'UTF-8'}_wrapper" style="display:none">
    {/if}
    {$smarty.block.parent}
    {if $hasWrapper}
        </div>
    {/if}
{/block}

{block name="script"}
	$(document).ready(function() {
        var showCountry = function (country, value) {
            $('#beslist_cart_carrier_' + country + '_wrapper').css('display', (value == 1) ? 'block' : 'none');
            $('#beslist_cart_deliveryperiod_' + country + '_wrapper').css('display', (value == 1) ? 'block' : 'none');
            $('#beslist_cart_deliveryperiod_nostock_' + country + '_wrapper').css('display', (value == 1) ? 'block' : 'none');
        }
        var showCartSection = function(value) {
            $('#beslist_cart_testmode_wrapper').css('display', (value == 1) ? 'block' : 'none');
            $('#beslist_cart_shopid_wrapper').css('display', (value == 1) ? 'block' : 'none');
            $('#beslist_cart_clientid_wrapper').css('display', (value == 1) ? 'block' : 'none');
            $('#beslist_cart_personalkey_wrapper').css('display', (value == 1) ? 'block' : 'none');
            $('#beslist_cart_shopitem_apikey_wrapper').css('display', (value == 1) ? 'block' : 'none');
            $('#beslist_cart_test_reference_wrapper').css('display', (value == 1) ? 'block' : 'none');
            $('#beslist_cart_startdate_wrapper').css('display', (value == 1) ? 'block' : 'none');
        }
		$('input[name="beslist_cart_add_default_categories"]').change(function() {
			$('#beslist_cart_overwrite_categories_wrapper').css('display', ($(this).val() == 1) ? 'block' : 'none');
		});
        $('input[name="beslist_cart_enabled"]').change(function() {
            showCartSection($(this).val());
        });
        $('input[name="beslist_cart_enabled_be"]').change(function() {
            showCountry('be', $(this).val());
        });
        $('input[name="beslist_cart_enabled_nl"]').change(function() {
            showCountry('nl', $(this).val());
        });
        showCartSection($('input[name="beslist_cart_enabled"]:checked').val());
        showCountry('nl', $('input[name="beslist_cart_enabled_nl"]:checked').val());
        showCountry('be', $('input[name="beslist_cart_enabled_be"]:checked').val());
	});
{/block}
