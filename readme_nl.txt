Beslist Winkelwagen integratie
==============================
Uitgebreide documentatie kun je ook vinden op: http://www.werkaandewebshop.com/beslistcart-docs

1. Introductie
==============
De Beslist Winkelwagen integratie laat je jouw Beslist.nl orders afhandelen in jouw backoffice. Dit betekent dat je je normale proces kunt volgen, alsof het een order via je gewone shop betreft.

2. Installatie
==============
  1. Installeer de module door het zip bestand te uploaden
  2. Activeer de module voor de shop(s) waarin je de Beslist.nl orders wilt hebben. Vul de gegevens in op de module configuratiepagina.
  		- Het wordt aangeraden om eerst de Test API te gebruiken, als alles werkt, kun je de productiegegevens invullen.

3. Gebruik
==========

3.1 Orders
----------
Ga naar Orders -> Beslist.nl orders. Je kunt daar klikken op 'Synchroniseer bestellingen', waarna er een testorder wordt geïmporteerd.
Wanneer je nu je order afhandelt (via de normale Orders pagina), kun je zien welke data er gebruikt is.
Het Beslist Order ID wordt geïmporteerd als het transactie ID van de betaling.

Als je in de test modus zit, kun je de 'Delete test data' knop gebruiken om de testorders te verwijderen.

3.2 Producten
-------------
Het is mogelijk om de producten vanuit jouw prestashop omgeving naar Beslist te publiceren.
Je kunt ook gebruik maken van de productfeed generator (de URL staat op de configuratiepagina van de module).

Je kunt de data zoals deze op Beslist wordt getoond beheren op de productpagina (via de Beslist Winkelwagen integratie tab).
Daar kun je per product of combinatie aangeven hoe deze op Beslist getoond moet worden.
Je kunt instellen of het artikel gepubliceerd moet worden.

Wanneer je je product aanpast, wordt er een bericht naar Beslist.nl gestuurd met de nieuwe informatie.
Er zit echter een vertraging tussen de verwerking van de productfeed en de productupdates.
Het kan dus goed zijn dat er 404 errors veroorzaakt worden bij het opslaan van producten.
Dit komt doordat Beslist de nieuwe producten nog niet kent.
De productfeed wordt de volgende dag pas weer verwerkt, daarna kun je via de Catalogus -> Beslist.nl producten pagina opnieuw synchroniseren.

Er is ook een nieuw menuitem toegevoegd aan het Catalogus menu. Daarin zie je een overzicht van alle producten die naar Beslist zijn gemeld.
Wanneer er een foutieve melding is gedaan, staat dat in de statusbalk aangegeven.
Je kunt dan handmatig de melding opnieuw doen door op de knop 'Synchroniseer producten' te drukken.

4. Veelgestelde vragen
=============================
1. Kan ik een order annuleren via de connector?
- Helaas is dit (momenteel) niet mogelijk via de Beslist API.

2. Hoe kan ik mijn producten synchroniseren naar Beslist?
- Dit kun je doen vanaf de productpagina in de backoffice

3. Kunnen de orders ook automatisch worden geïmporteerd?
- Ja, maar daar moet je een crontaak voor opzetten (zie de moduleconfiguratiepagina voor een voorbeeld).

4. Kan ik orders importeren voor producten die niet op voorraad zijn?
- Ja, de bestelbaar status wordt tijdelijk aangepast tijdens de import.

5. Ik krijg een waarschuwing wanneer ik meerdere orders tegelijk importeer
- De waarschuwing komt door een bug in Prestashop core, het probleem kan geen kwaad, maar er is een bug report gemaakt: http://forge.prestashop.com/browse/PSCSX-7858.
Je kunt de waarschuwing verwijderen door handmatig een override class te maken voor de Cart class:

/**
 * Get the delivery option selected, or if no delivery option was selected,
 * the cheapest option for each address
 *
 * @param Country|null $default_country
 * @param bool         $dontAutoSelectOptions
 * @param bool         $use_cache
 *
 * @return array|bool|mixed Delivery option
 */
public function getDeliveryOption($default_country = null, $dontAutoSelectOptions = false, $use_cache = true)
{
    static $cache = array();
    $cache_id = (int)(is_object($default_country) ? $default_country->id : 0).'-'.(int)$dontAutoSelectOptions.'-'.$this->id;
    if (isset($cache[$cache_id]) && $use_cache) {
        return $cache[$cache_id];
    }

    $delivery_option_list = $this->getDeliveryOptionList($default_country);

    // The delivery option was selected
    if (isset($this->delivery_option) && $this->delivery_option != '') {
        $delivery_option = Tools::unSerialize($this->delivery_option);
        $validated = true;
        foreach ($delivery_option as $id_address => $key) {
            if (!isset($delivery_option_list[$id_address][$key])) {
                $validated = false;
                break;
            }
        }

        if ($validated) {
            $cache[$cache_id] = $delivery_option;
            return $delivery_option;
        }
    }

    if ($dontAutoSelectOptions) {
        return false;
    }

    // No delivery option selected or delivery option selected is not valid, get the better for all options
    $delivery_option = array();
    foreach ($delivery_option_list as $id_address => $options) {
        foreach ($options as $key => $option) {
            if (Configuration::get('PS_CARRIER_DEFAULT') == -1 && $option['is_best_price']) {
                $delivery_option[$id_address] = $key;
                break;
            } elseif (Configuration::get('PS_CARRIER_DEFAULT') == -2 && $option['is_best_grade']) {
                $delivery_option[$id_address] = $key;
                break;
            } elseif ($option['unique_carrier'] && in_array(Configuration::get('PS_CARRIER_DEFAULT'), array_keys($option['carrier_list']))) {
                $delivery_option[$id_address] = $key;
                break;
            }
        }

        reset($options);
        if (!isset($delivery_option[$id_address])) {
            $delivery_option[$id_address] = key($options);
        }
    }

    $cache[$cache_id] = $delivery_option;

    return $delivery_option;
}
