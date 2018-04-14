<?php
namespace Wienkit\Prestashop\Beslistcart;

use PHPUnit\Runner\Exception;
use Wienkit\Prestashop\Beslistcart\Base\AbstractAdmin17TestBase;

/**
 * Class SetupModuleTest17
 *
 * @group 17
 * @package Wienkit\Prestashop\Beslistcart
 */
class SetupModule17Test extends AbstractAdmin17TestBase
{

    public function testAdminLogin()
    {
        $this->doAdminLogin();
        $title = $this->driver->findElement(\WebDriverBy::tagName('h2'))->getText();
        $this->assertEquals('Dashboard', $title);
    }

    public function testSetProductPrice()
    {
        $this->doAdminLogin();
        $this->goToMenu(['Catalogus', 'Producten']);
        $this->driver->wait()->until(
            \WebDriverExpectedCondition::elementToBeClickable(\WebDriverBy::cssSelector('[title="Close Toolbar"]'))
        );
        $this->driver->findElement(\WebDriverBy::cssSelector('[title="Close Toolbar"]'))->click();
        $this->driver->wait()->until(
            \WebDriverExpectedCondition::elementToBeClickable(\WebDriverBy::linkText('Hummingbird printed t-shirt'))
        );
        $this->driver->findElement(\WebDriverBy::linkText('Hummingbird printed t-shirt'))->click();
        $this->driver->findElement(\WebDriverBy::linkText('Tarieven'))->click();
        $this->driver->findElement(\WebDriverBy::id('form_step2_price'))->clear()->sendKeys('20');
        sleep(5);
        $this->driver->findElement(\WebDriverBy::name('form'))->submit();
        $this->driver->wait()->until(
            \WebDriverExpectedCondition::visibilityOfElementLocated(\WebDriverBy::className('growl-message'))
        );
        $status = $this->driver->findElement(\WebDriverBy::className('growl-message'))->getText();
        $this->assertContains('Instellingen bijgewerkt', $status);
    }

    public function testEnableModule()
    {
        $this->doAdminLogin();
        $this->goToMenu(['Modules', 'Modules en services']);
        $this->driver->findElement(\WebDriverBy::className('pstaggerAddTagInput'))->sendKeys('beslist');
        $this->driver->findElement(\WebDriverBy::className('search-button'))->click();
        $this->driver->wait()->until(
            \WebDriverExpectedCondition::elementToBeClickable(\WebDriverBy::cssSelector('button[data-confirm_modal="module-modal-confirm-beslistcart-install"]'))
        );
        $this->driver->findElement(\WebDriverBy::cssSelector('button[data-confirm_modal="module-modal-confirm-beslistcart-install"]'))->click();
        $this->driver->wait()->until(
            \WebDriverExpectedCondition::elementToBeClickable(\WebDriverBy::cssSelector('button[data-confirm_modal="module-modal-confirm-beslistcart-configure"]'))
        );
        $text = $this->driver->findElement(\WebDriverBy::cssSelector('button[data-confirm_modal="module-modal-confirm-beslistcart-configure"]'))->getText();
        $this->assertContains("Configureer", $text);

        $this->goToMenu(['Internationaal', 'Locaties']);
        $this->driver->findElement(\WebDriverBy::linkText('Landen'))->click();
        $this->driver->findElement(\WebDriverBy::name('countryFilter_b!name'))->sendKeys("Belgi");
        $this->driver->findElement(\WebDriverBy::id('submitFilterButtoncountry'))->click();
        $this->driver->findElement(\WebDriverBy::cssSelector('#form-country a.list-action-enable'))->click();
        $this->assertContains("De status is bijgewerkt", $this->getStatusMessageText());
    }

    /**
     * @depends testEnableModule
     */
    public function testConfigureModule()
    {
        $this->doAdminLogin();
        $this->goToMenu(['Modules', 'Modules en services']);
        $this->driver->findElement(\WebDriverBy::linkText("Geïnstalleerde modules"))->click();
        $this->driver->findElement(\WebDriverBy::cssSelector('button[data-confirm_modal="module-modal-confirm-beslistcart-configure"]'))->click();

        $this->selectOption('beslist_cart_attribute_size', '1');
        $this->selectOption('beslist_cart_attribute_color', '3');

        $this->driver->findElement(\WebDriverBy::cssSelector("label[for='beslist_cart_enabled_nl_on']"))->click();
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_deliveryperiod_nl'))->clear()->sendKeys("Op werkdagen voor 15:00 uur besteld, volgende dag in huis!");
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_deliveryperiod_nostock_nl'))->clear()->sendKeys("Niet op voorraad");
        $this->selectOption('beslist_cart_carrier_nl', '2');

        $this->driver->findElement(\WebDriverBy::cssSelector("label[for='beslist_cart_enabled_be_on']"))->click();
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_deliveryperiod_be'))->clear()->sendKeys("Op werkdagen voor 15:00 uur besteld, volgende dag in huis!");
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_deliveryperiod_nostock_be'))->clear()->sendKeys("Niet op voorraad");
        $this->selectOption('beslist_cart_carrier_be', '2');

        $this->driver->findElement(\WebDriverBy::cssSelector("label[for='beslist_cart_enabled_on']"))->click();
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_shopid'))->clear()->sendKeys(getenv('SHOP_ID'));
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_clientid'))->clear()->sendKeys(getenv('CLIENT_ID'));
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_personalkey'))->clear()->sendKeys(getenv('ORDER_XML_KEY'));
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_shopitem_apikey'))->clear()->sendKeys(getenv('SHOPITEM_API_KEY'));
        $this->driver->findElement(\WebDriverBy::cssSelector("label[for='beslist_cart_testmode_on']"))->click();
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_test_reference'))->clear()->sendKeys('2-1');
        $this->driver->findElement(\WebDriverBy::id('configuration_form'))->submit();

        $this->assertContains("Instellingen bijgewerkt", $this->getStatusMessageText());
    }

    /**
     * @depends testConfigureModule
     */
    public function testSyncOrders()
    {
        $this->doAdminLogin();
        $this->goToMenu(['Bestellingen', 'Beslist.nl orders']);

        $this->driver->findElement(\WebDriverBy::id('page-header-desc-order-sync_orders'))->click();
        $this->assertContains('Beslist.nl Shopping cart sync completed', $this->getStatusMessageText());
        $tableText = $this->driver->findElement(\WebDriverBy::id('form-order'))->getText();
        $this->assertContains('T. van TestAchternaam', $tableText);
        $this->assertContains('Beslist order imported', $tableText);
    }

    /**
     * @depends testSyncOrders
     */
    public function testOrderContents()
    {
        $this->doAdminLogin();
        $this->goToMenu(['Bestellingen', 'Beslist.nl orders']);
        $this->driver->findElement(\WebDriverBy::cssSelector(".table.order td.pointer"))->click();
        $mail = $this->driver->findElement(\WebDriverBy::partialLinkText("tester0@testmail.com"));
        $this->assertContains("tester0@testmail.com", $mail->getText());
        $product = $this->driver->findElement(\WebDriverBy::className("product-line-row"));
        $this->assertContains("Hummingbird printed t-shirt", $product->getText());
        $qty = $this->driver->findElement(\WebDriverBy::className('product_quantity_show'));
        $this->assertEquals("2", $qty->getText());
        $total = $this->driver->findElement(\WebDriverBy::className('total_product'));
        $this->assertEquals("€ 38,72", trim($total->getText()));
        $shipping = $this->driver->findElement(\WebDriverBy::cssSelector("#total_shipping td.amount"));
        $this->assertEquals("€ 8,47", trim($shipping->getText()));

        $shipping = $this->driver->findElement(\WebDriverBy::cssSelector('#addressShipping .row'));
        $this->assertContains('TestStraat0', $shipping->getText());

        $this->driver->findElement(\WebDriverBy::cssSelector("#tabAddresses .icon-file-text"))->click();
        $invoice = $this->driver->findElement(\WebDriverBy::cssSelector('#addressInvoice .row'));
        $this->assertContains('TestStraat0', $invoice->getText());
    }
}