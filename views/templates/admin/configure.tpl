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
<div class="panel">
    <h3>{l s='How do I use this module?' mod='beslistcart'}</h3>
    <div class="row">
        <div class="col-md-2 text-center"><img src="{$module_dir|escape:'htmlall':'UTF-8'}/logo.png" id="beslistcart-logo" /></div>
        <div class="col-md-10">
            <p class="lead">
                {l s='This module uses the Beslist.nl Shopping cart functionality. You can apply for an account at Beslist.nl.' mod='beslistcart'}
            </p>
            <p>
                {l s='Find help online at ' mod='beslistcart'}<a href="http://werkaandewebshop-beslistcart.readthedocs.io/">{l s='the online documentation (dutch)' mod='beslistcart'}</a>.
            </p>
            <p><a data-toggle="collapse" href="#collapseAdvanced" aria-expanded="false" aria-controls="collapseAdvanced">
                    {l s='Show advanced options' mod='beslistcart'}
                </a></p>
            <div class="collapse" id="collapseAdvanced">
                <div class="well">
                    <div class="form-horizontal">
                        <div class="form-group">
                            <label for="cron_url" class="col-md-2 control-label"><strong>{l s='Cron URL' mod='beslistcart'}</strong></label>
                            <div class="col-md-10">
                                <input id="cron_url" readonly class="form-control" type="text" value="{$cron_url|escape:'htmlall':'UTF-8'}" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="cron_cmd" class="col-md-2 control-label"><strong>{l s='Cron command' mod='beslistcart'}</strong></label>
                            <div class="col-md-10">
                                <input id="cron_cmd" readonly class="form-control" type="text" value="*/10 * * * * curl --silent {$cron_url|escape:'htmlall':'UTF-8'} &>/dev/null" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="feed_url" class="col-md-2 control-label"><strong>{l s='Live feed' mod='beslistcart'}</strong></label>
                            <div class="col-md-10">
                                <input id="feed_url" readonly class="form-control" type="text" value="{$feed_url|escape:'htmlall':'UTF-8'}" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="feed_cmd" class="col-md-2 control-label"><strong>{l s='Feed generator' mod='beslistcart'}</strong></label>
                            <div class="col-md-10">
                                <input id="feed_cmd" readonly class="form-control" type="text" value="0 1 * * * curl {$feed_url|escape:'htmlall':'UTF-8'} > {$feed_loc}" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="feed_web" class="col-md-2 control-label"><strong>{l s='Generated feed' mod='beslistcart'}</strong></label>
                            <div class="col-md-10">
                                <input id="feed_web" readonly class="form-control" type="text" value="{$feed_web|escape:'htmlall':'UTF-8'}" />
                            </div>
                        </div>
                    </div>
                    <p><strong>{l s='Note:' mod='beslistcart'}</strong> {l s='If you use multistore, setup a cron task for each shop (look at the module settings page for each shop, because the secret key differs per shop)'  mod='beslistcart'}
                </div>
            </div>
        </div>
    </div>
</div>
