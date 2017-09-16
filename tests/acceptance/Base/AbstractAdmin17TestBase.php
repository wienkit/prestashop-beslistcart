<?php
namespace Wienkit\Prestashop\Beslistcart\Base;

abstract class AbstractAdmin17TestBase extends ATestBase
{
    public function goToMenu(array $items)
    {
        try {
            $nav = $this->driver->findElement(\WebDriverBy::id('nav-sidebar'));
        } catch (\NoSuchElementException $e) {
            $nav = $this->driver->findElement(\WebDriverBy::className('nav-bar'));
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
}