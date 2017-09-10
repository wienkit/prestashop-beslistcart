<?php
namespace Wienkit\Prestashop\Beslistcart;

use PHPUnit\Framework\TestCase;

abstract class BaseTest extends TestCase
{
    /** @var \RemoteWebDriver */
    protected $driver;

    /** @var string */
    private $token;
    private $host;

    public function setUp()
    {
        $host = getenv('SELENIUM_HOST');
        $this->driver = \RemoteWebDriver::create($host, \DesiredCapabilities::chrome());
        $this->host = "http://" . getenv('SITE_HOST');
    }

    public function goToPath($path)
    {
        $this->driver->get($this->host . '/admin-dev/' . $path);
        try {
            $this->driver->findElement(\WebDriverBy::linkText("Ik begrijp het risico maar wil de pagina toch bekijken"))->click();
        } catch (\NoSuchElementException $e) {
        }
    }

    public function doAdminLogin()
    {
        $this->goToPath('index.php');
        $this->driver->findElement(\WebDriverBy::name('email'))->click();
        $this->driver->getKeyboard()->sendKeys('admin@example.com');
        $this->driver->findElement(\WebDriverBy::name('passwd'))->click();
        $this->driver->getKeyboard()->sendKeys('password');
        $this->driver->findElement(\WebDriverBy::name('submitLogin'))->click();
        $this->driver->wait()->until(
            \WebDriverExpectedCondition::titleContains('Dashboard')
        );
        $url = $this->driver->getCurrentURL();
        $parts = parse_url($url);
        parse_str($parts['query'], $query);
        $this->token = $query['token'];
    }

    public function getStatusMessageText()
    {
        try {
            $text = $this->driver->findElement(\WebDriverBy::cssSelector(".bootstrap .alert-success"))->getText();
            return $text;
        } catch (\NoSuchElementException $e) {
            return "";
        }
    }

    public function tearDown()
    {
        if ($this->hasFailed()) {
            $this->driver->takeScreenshot('results/' . time() . '_' . $this->getName() . '.png');
        }
        $this->driver->close();
    }

    /**
     * Select an option in a select element
     * @param $selectId
     * @param $optionValue
     */
    public function selectOption($selectId, $optionValue)
    {
        $select = $this->driver->findElement(\WebDriverBy::id($selectId));
        $options = $select->findElements(\WebDriverBy::tagName('option'));
        foreach ($options as $option) {
            if($option->getAttribute('value') == $optionValue) {
                $option->click();
            }
        }
    }
}