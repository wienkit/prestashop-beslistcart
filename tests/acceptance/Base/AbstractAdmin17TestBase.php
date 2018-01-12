<?php
namespace Wienkit\Prestashop\Beslistcart\Base;

abstract class AbstractAdmin17TestBase extends ATestBase
{
    public function goToMenu(array $items)
    {
        $nav = $this->getNav();

        $body = $this->driver->findElement(\WebDriverBy::tagName('body'));
        $isFrontPage = strpos($body->getAttribute('class'), 'admindashboard') !== false;

        if ($isFrontPage) {
            foreach ($items as $item) {
                $link = $nav->findElement(\WebDriverBy::linkText($item));
                $link->click();
                $nav = $this->getNav();
                break;
            }
        }

        $trail = $nav;
        foreach ($items as $item) {
            $trail = $nav->findElement(\WebDriverBy::linkText($item));
            $this->driver->getMouse()->mouseMove($trail->getCoordinates());
            $this->driver->wait()->until(
                \WebDriverExpectedCondition::elementToBeClickable(\WebDriverBy::linkText($item))
            );
        }
        $trail->click();
    }

    public function getNav()
    {
        try {
            return $this->driver->findElement(\WebDriverBy::id('nav-sidebar'));
        } catch (\NoSuchElementException $e) {
            return $this->driver->findElement(\WebDriverBy::className('nav-bar'));
        }
    }
}