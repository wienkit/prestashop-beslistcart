<?php
namespace Wienkit\Prestashop\Beslistcart;

/**
 * Class SetupModuleTest
 *
 * @package Wienkit\Prestashop\Beslistcart
 */
class SetupModuleTest extends BaseTest
{

    public function testAdminLogin()
    {
        $this->doAdminLogin();
        $title = $this->driver->findElement(\WebDriverBy::tagName('h2'))->getText();
        $this->assertEquals('Dashboard', $title);
    }

    /**
     * @group 16
     */
    public function testEnableModule()
    {
        $this->doAdminLogin();
        $this->goToPath('index.php?controller=AdminModules');
        $this->goToPath('index.php?controller=AdminModules&install=beslistcart&tab_module=market_place&module_name=beslistcart');
        $this->assertContains("Installatie module(s) geslaagd", $this->getStatusMessageText());

        $this->goToPath('index.php?controller=AdminCountries&id_country=3&statuscountry');
        $this->assertContains('De status is bijgewerkt', $this->getStatusMessageText());
    }

    /**
     * @group 16
     * @depends testEnableModule
     */
    public function testConfigureModule()
    {
        $this->doAdminLogin();
        $this->goToPath('index.php?controller=AdminModules&configure=beslistcart&tab_module=market_place&module_name=beslistcart');
        $this->selectOption('beslist_cart_attribute_size', '1');
        $this->selectOption('beslist_cart_attribute_color', '3');

        $this->driver->findElement(\WebDriverBy::cssSelector("label[for='beslist_cart_enabled_nl_on']"))->click();
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_deliveryperiod_nl'))->sendKeys("Op werkdagen voor 15:00 uur besteld, volgende dag in huis!");
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_deliveryperiod_nostock_nl'))->sendKeys("Niet op voorraad");
        $this->selectOption('beslist_cart_carrier_nl', '2');

        $this->driver->findElement(\WebDriverBy::cssSelector("label[for='beslist_cart_enabled_be_on']"))->click();
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_deliveryperiod_be'))->sendKeys("Op werkdagen voor 15:00 uur besteld, volgende dag in huis!");
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_deliveryperiod_nostock_be'))->sendKeys("Niet op voorraad");
        $this->selectOption('beslist_cart_carrier_be', '2');

        $this->driver->findElement(\WebDriverBy::cssSelector("label[for='beslist_cart_enabled_on']"))->click();
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_shopid'))->sendKeys(getenv('SHOP_ID'));
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_clientid'))->sendKeys(getenv('CLIENT_ID'));
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_personalkey'))->sendKeys(getenv('ORDER_XML_KEY'));
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_shopitem_apikey'))->sendKeys(getenv('SHOPITEM_API_KEY'));
        $this->driver->findElement(\WebDriverBy::cssSelector("label[for='beslist_cart_testmode_on']"))->click();
        $this->driver->findElement(\WebDriverBy::id('beslist_cart_test_reference'))->sendKeys('2-1');
        $this->driver->findElement(\WebDriverBy::id('configuration_form'))->submit();

        $this->assertContains("Instellingen bijgewerkt", $this->getStatusMessageText());
    }

    /**
     * @group 16
     * @depends testConfigureModule
     */
    public function testSyncOrders()
    {
        $this->doAdminLogin();
        $this->goToPath('index.php?controller=AdminBeslistCartOrders');
        $this->driver->findElement(\WebDriverBy::id('page-header-desc-order-sync_orders'))->click();
        $this->assertContains('Beslist.nl Shopping cart sync completed', $this->getStatusMessageText());
        $tableText = $this->driver->findElement(\WebDriverBy::id('form-order'))->getText();
        $this->assertContains('T. van TestAchternaam', $tableText);
        $this->assertContains('Beslist order imported', $tableText);
    }

    /**
     * @group 16
     * @depends testSyncOrders
     */
    public function testOrderContents()
    {
        $this->doAdminLogin();
        $this->goToPath('index.php?controller=AdminOrders');
        $this->driver->findElement(\WebDriverBy::cssSelector(".table.order td.pointer"))->click();
        $mail = $this->driver->findElement(\WebDriverBy::partialLinkText("tester0@testmail.com"));
        $this->assertContains("tester0@testmail.com", $mail->getText());
        $product = $this->driver->findElement(\WebDriverBy::className("product-line-row"));
        $this->assertContains("Gebleekte T-shirts", $product->getText());
        $qty = $this->driver->findElement(\WebDriverBy::className('product_quantity_show'));
        $this->assertEquals("2", $qty->getText());
        $total = $this->driver->findElement(\WebDriverBy::className('total_product'));
        $this->assertEquals("39,95 €", trim($total->getText()));
        $shipping = $this->driver->findElement(\WebDriverBy::cssSelector(".total_shipping td.amount"));
        $this->assertEquals("8,47 €", trim($shipping->getText()));

        $shipping = $this->driver->findElement(\WebDriverBy::id('addressShipping'));
        $this->assertContains('TestStraat0 12 C', $shipping->getText());

        $this->driver->findElement(\WebDriverBy::cssSelector("#tabAddresses .icon-file-text"))->click();
        $invoice = $this->driver->findElement(\WebDriverBy::id('addressInvoice'));
        $this->assertContains('TestStraat0 58 E', $invoice->getText());
    }
}