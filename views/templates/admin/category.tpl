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
<div class="form-group">
	<label class="control-label col-lg-3" for="beslist_category">
		{l s='Beslist category' mod='beslistcart'}
	</label>
	<div class="col-lg-9">
		<div class="form-group">
			<select class="selectpicker form-control" data-live-search="true" name="beslistcart_category" id="beslistcart_category">
				{foreach $beslist_categories as $category}
					<option value="{$category.id_beslist_category|escape:'htmlall':'UTF-8'}">{$category.name|escape:'html':'UTF-8'}</option>
				{/foreach}
			</select>
			<script>
				$('.selectpicker').selectpicker('render');
			</script>
		</div>
	</div>

	<script>
		$('.selectpicker').selectpicker('render');
	</script>
</div>