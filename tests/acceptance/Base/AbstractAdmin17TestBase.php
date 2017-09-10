<?php
namespace Wienkit\Prestashop\Beslistcart\Base;

abstract class AbstractAdmin17TestBase extends ATestBase
{
    public function goToMenu(array $items)
    {
        $nav = $this->driver->findElement(\WebDriverBy::id('nav-sidebar'));
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