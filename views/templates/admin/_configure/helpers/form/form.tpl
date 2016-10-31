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
{extends file="helpers/form/form.tpl"}

{block name="input_row"}
    {if $input.name == 'beslist_cart_overwrite_categories'}<div id="{$input.name|escape:'htmlall':'UTF-8'}_wrapper" style="display:none">{/if}
    {$smarty.block.parent}
    {if $input.name == 'beslist_cart_overwrite_categories'}</div>{/if}
{/block}

{block name="script"}
	$(document).ready(function() {
        console.log("INIT");
		$('input[name="beslist_cart_add_default_categories"]').change(function() {
            console.log("DO");
			$('#beslist_cart_overwrite_categories_wrapper').css('display', ($(this).val() == 1) ? 'block' : 'none');
		});
	});
{/block}