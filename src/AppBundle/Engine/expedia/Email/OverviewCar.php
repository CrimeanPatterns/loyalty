<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OverviewCar extends \TAccountCheckerExtended
{
    public $mailFiles = "expedia/it-237982087.eml, expedia/it-3277451.eml, expedia/it-3285783.eml, expedia/it-3303843.eml, expedia/it-3306749.eml, expedia/it-3406853.eml, expedia/it-3424470.eml, expedia/it-3490309.eml, expedia/it-5751591.eml, expedia/it-6021655.eml, expedia/it-6706352.eml, expedia/it-8844324.eml, expedia/it-9076816.eml";

    public $reFrom = "Confirmation@ExpediaConfirm.com";
    public $reSubject = [
        "ExpediaEn"=> "Expedia travel confirmation",
        "ExpediaDe"=> "Expedia-Reisebestätigung",
        "ExpediaEs"=> "Confirmación de viaje de Expedia", 'Confirmación de viaje con Expedia',
        'Confirmación de Expedia',
        "ExpediaPt"=> "Aluguel de carro em",

        "OrbitzEn"     => "Orbitz travel confirmatio",
        "TravelocityEn"=> "Travelocity travel confirmation",
        "ebookersFr"   => "Votre confirmation de voyage ebookers",
    ];
    public $reBody = [//it's important expedia - last in list
        'orbitz'       => 'Orbitz',
        'travelocity'  => 'Travelocity',
        'ebookers'     => 'ebookers',
        'cheaptickets' => 'CheapTickets',
        'rbcbank'      => ['RBCRewards.com'],
        'expedia'      => ['Expedia', '.expediamail.com/'],
    ];

    public static $dictionary = [
        "en" => [
            "Total:"             => ["Total:", "Due at pick-up:", 'Paid:'],
            "Rental car overview"=> ["Rental car overview", "Car hire overview"],
            // "cancelledPhrases" => [""],
        ],
        "de" => [
            "Confirmation"        => "Bestätigung",
            "Itinerary #"         => "Reiseplannummer",
            "Pick-up"             => "Abholung",
            "Drop-off"            => "Rückgabe",
            "Pick-up instructions"=> "Infos zur Abholung",
            //			"Reservation dates"=>"",
            "Drop-off instructions"=> "Infos zur Rückgabe",
            "Open"                 => "Öffnungszeiten",

            "reservation is confirmed"             => "NOTTRANSLATED",
            "#Your (.*?) reservation is confirmed#"=> "#NOTTRANSLATED#",

            "Map and directions" => "Karte und Wegbeschreibung",
            "Car type"           => "Fahrzeugklasse",
            "Rental car overview"=> "Mietwagenübersicht",
            // "cancelledPhrases" => [""],
            "Reserved for"       => "Reserviert für",
            "Total:"             => ["Gesamt:", "Bezahlt:"],
            "Taxes"              => "NOTTRANSLATED",
        ],
        "es" => [
            "Confirmation"         => "Confirmación",
            "Itinerary #"          => ["N.º de itinerario", "No. de itinerario"],
            "Pick-up"              => ["Recogida"],
            "Drop-off"             => ["Devolución", "Entrega"],
            "Pick-up instructions" => ["Instrucciones de recogida", "Instrucciones para la entrega"],
            "Reservation dates"    => "Fechas de la reservación",
            "Drop-off instructions"=> ["Infos zur Rückgabe", "Instrucciones para la devolución"],
            "Open"                 => ["Horario", "Abierto"],

            "reservation is confirmed"             => "NOTTRANSLATED",
            "#Your (.*?) reservation is confirmed#"=> "#NOTTRANSLATED#",

            "Map and directions" => ["Mapa e indicaciones", "Mapas e indicaciones"],
            "Car type"           => ["Tipo de coche", "Tipo de auto"],
            "Rental car overview"=> ["Alquiler de coche", "Resumen de la renta del auto"],
            "cancelledPhrases"   => ["Esta reservación se canceló por completo."],
            "Reserved for"       => ["Reserva para", "Reservado para"],
            "Total:"             => ["Pagado:", "Pagada:", "A pagar al momento de la entrega:"],
            "Taxes"              => "Impuestos y cargos",
        ],
        "pt" => [
            "Confirmation"         => "Confirmação",
            "Itinerary #"          => "Nº do itinerário",
            "Pick-up"              => "Retirada",
            "Drop-off"             => ["Entrega", "Devolução"],
            "Pick-up instructions" => "Instruções de retirada",
            "Reservation dates"    => "Datas da reserva",
            "Drop-off instructions"=> "Instruções de entrega",
            "Open"                 => "Abertura",

            "reservation is confirmed"             => "NOTTRANSLATED",
            "#Your (.*?) reservation is confirmed#"=> "#NOTTRANSLATED#",

            "Map and directions" => ["Mapa e indicações", "Ver mapa"],
            "Car type"           => "Tipo de carro",
            "Rental car overview"=> "Resumo do aluguel de carro",
            // "cancelledPhrases" => [""],
            "Reserved for"       => "Reservado para",
            "Total:"             => "NOTTRANSLATED",
            "Taxes"              => "NOTTRANSLATED",
        ],
        "pl" => [
            "Confirmation"         => "Bevestiging",
            "Itinerary #"          => "Reisplannummer",
            "Pick-up"              => "Ophalen",
            "Drop-off"             => "Inleveren",
            "Pick-up instructions" => "Ophaalinstructies",
            'Reservation dates'    => 'Boekingsdatums',
            //            "Drop-off instructions" => "",
            "Open" => "Geopend",
            //            "reservation is confirmed" => "",
            //            "#Your (.*?) reservation is confirmed#" => "#NOTTRANSLATED#",
            "Map and directions"  => "Kaart en routebeschrijving",
            "Car type"            => "Autotype",
            "Rental car overview" => "Overzicht huurauto",
            // "cancelledPhrases" => [""],
            "Reserved for"        => "Geboekt voor",
            "Total:"              => "Totaal:",
            //            "Taxes" => "",
        ],
        "fr" => [
            "Confirmation"         => "Confirmation",
            "Itinerary #"          => "N° de voyage",
            "Pick-up"              => "Prise en charge",
            "Drop-off"             => ["Restitution", "Remise"],
            "Pick-up instructions" => "Consignes de prise en charge",
            'Reservation dates'    => 'Dates de réservation',
            //            "Drop-off instructions" => "",
            "Open" => "Heures d'ouverture",
            //            "reservation is confirmed" => "",
            //            "#Your (.*?) reservation is confirmed#" => "#NOTTRANSLATED#",
            "Map and directions"  => ["Carte et itinéraire", "Carte et directions"],
            "Car type"            => ["Type de voiture de location", "Type de voiture"],
            "Rental car overview" => ["Récapitulatif de votre location", "Résumé de la location de voiture"],
            // "cancelledPhrases" => [""],
            "Reserved for"        => ["Réservation pour", 'Réservé pour'],
            "Total:"              => ["Total :", "À payer à la prise en charge ::"],
            //            "Taxes" => "",
        ],
    ];

    public $lang = "en";
    private $langDetectors = [
        'de' => ['Karte und Wegbeschreibung'],
        'es' => ['Mapa e indicaciones', 'Mapas e indicaciones'],
        'pt' => ['Mapa e indicações', 'Retirada e devolução'],
        'pl' => ['Zie live updates voor je reisplan, waar en wanneer'],
        'fr' => ['Carte et itinéraire', 'Consulter l’itinéraire'],
        'en' => ['Map and directions', 'Drop-off instructions'],
    ];

    private $date;

    public static function getEmailProviders()
    {
        return ['orbitz', 'travelocity', 'ebookers', 'cheaptickets', 'expedia', 'rbcbank'];
    }

    public function parseHtml(Email $email): void
    {
        $r = $email->add()->rental();
        $patterns = [
            'confNumber' => '[-A-z\d]{5,}', // 11986371476    |    M5GPQK
        ];

        // TripNumber
        // Number
        $tripNumber = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Itinerary #")) . "]/ancestor::tr[1]/following-sibling::tr[1]", null, true, "/^({$patterns['confNumber']})$/");
        $confNumber = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation")) . "]/ancestor::tr[1]/following-sibling::tr[1]", null, true, "/^(?:.+\)\-)?({$patterns['confNumber']})/");

        if ($tripNumber && $confNumber) {
            $email->ota()
                ->confirmation($tripNumber);

            $r->general()
                ->confirmation($confNumber);
        } elseif ($tripNumber && !$confNumber) {
            $email->ota()
                ->confirmation($tripNumber);
        } elseif (!$tripNumber && $confNumber) {
            $r->general()
                ->confirmation($confNumber);
        }

        $r->general()
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reserved for")) . "]/ancestor::div[1]/following-sibling::div[1]"));

        $resDates = $this->http->FindSingleNode("//tr[not(.//tr) and " . $this->contains($this->t("Reservation dates")) . "]/following-sibling::tr[1]/td[1]");
        $pickUpYear = null;
        $dropOffYear = null;

        if (preg_match('/\b(\d{2,4})\s*-\s*(?:\w+\s+\d{1,2},|\d{1,2}(?:\s+de)?\s+\w+\.?(?:\s+de)?)\s*(\d{2,4})/iu', $resDates, $m)) {
            $pickUpYear = $m[1];
            $dropOffYear = $m[2];
        }

        // PickupDatetime
        $pickupDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t("Pick-up"))}]/ancestor::div[1]/following-sibling::div[1]"), $pickUpYear));
        $pickupLoc = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Pick-up"))}]/ancestor::div[1]/following-sibling::div[last()]");

        // DropoffDatetime
        $dropoffDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t("Drop-off"))}]/ancestor::div[1]/following-sibling::div[1]"), $dropOffYear));
        $dropoffLoc = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Drop-off"))}]/ancestor-or-self::div[1]/following-sibling::div[last()]");

        if ($this->lang == 'es' && empty($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Pick-up")) . "]"))
                && count($this->http->FindNodes("//text()[" . $this->eq($this->t("Drop-off")) . "]")) == 2) {
//            for es lang where pickup => Entrega, dropoff => Devolución

            // PickupDatetime
            $pickupDate = strtotime($this->normalizeDate($this->http->FindSingleNode("(//text()[{$this->eq($this->t("Drop-off"))}])[1]/ancestor::div[1]/following-sibling::div[1]"), $pickUpYear));
            $pickupLoc = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("Drop-off"))}])[1]/ancestor::div[1]/following-sibling::div[last()]");

            // DropoffDatetime
            $dropoffDate = strtotime($this->normalizeDate($this->http->FindSingleNode("(//text()[{$this->eq($this->t("Drop-off"))}])[2]/ancestor::div[1]/following-sibling::div[1]"), $dropOffYear));
            $dropoffLoc = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("Drop-off"))}])[2]/ancestor-or-self::div[1]/following-sibling::div[last()]");
        }

        $r->pickup()->date($pickupDate)->location($pickupLoc);
        $r->dropoff()->date($dropoffDate)->location($dropoffLoc);

        // PickupHours
        $pickUpHours = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Pick-up instructions")) . "]/ancestor::div[1]/following-sibling::div[(1 or 2) and " . $this->contains($this->t("Open")) . "][1]");

        if (!empty($pickUpHours)) {
            $r->pickup()
                ->openingHours($pickUpHours);
        }

        // DropoffHours
        $dropOffHours = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Drop-off instructions")) . "]/ancestor::div[1]/following-sibling::div[(1 or 2) and " . $this->contains($this->t("Open")) . "][1]");

        if (!empty($dropOffHours)) {
            $r->dropoff()
                ->openingHours($dropOffHours);
        }

        if (empty($dropOffHours) && !empty($pickUpHours) && $r->getPickUpLocation() == $r->getDropOffLocation()) {
            $dropOffHours = $pickUpHours;
            $r->dropoff()
                ->openingHours($dropOffHours);
        }

        // RentalCompany
        $rentalCompany = $this->http->FindSingleNode("(//a[" . $this->eq($this->t("Map and directions")) . "]/ancestor::tr[2]/../tr[1]/descendant::text()[normalize-space(.)])[1][./ancestor::b]");

        if (empty($rentalCompany)) {
            $rentalCompany = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("reservation is confirmed")) . "]", null, true, $this->t("#Your (.*?) reservation is confirmed#"));
        }

        if (empty($rentalCompany)) {
            $rentalCompany = $this->http->FindSingleNode("//a[" . $this->eq($this->t("Map and directions")) . "]/ancestor::tr[2]/../descendant::img[contains(@src,'cars/logos/')]/@alt");
        }

        if (empty($r->getCompany()) && $this->http->FindSingleNode("//img[contains(@src,'cars/logos/ET.png')]") !== null) {
            $r->setCompany('Enterprise');
        }

        if (!empty($rentalCompany)) {
            $r->setCompany($rentalCompany);
        }

        // CarType
        $r->car()
            ->type($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Car type")) . "]/ancestor::div[1]/following-sibling::div[1]"));

        $model = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Car type")) . "]/ancestor::div[1]/following-sibling::div[2]");

        if (!empty($model)) {
            $r->car()
                ->model($model);
        }

        $image = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Rental car overview")) . "]/ancestor::tr[1]/following-sibling::tr[1]//img)[1]/@src[" . $this->contains("://") . " and not(" . $this->contains("cars/logos") . ")]");

        if (!empty($image)) {
            $r->car()
                ->image($image);
        }

        if ($this->http->XPath->query("//*[{$this->eq($this->t("Rental car overview"))}]/following::text()[normalize-space()][1][{$this->contains($this->t("cancelledPhrases"))}]")->length) {
            $r->general()->cancelled();
        }

        //BaseFare
        $basePrice = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Base Price:")) . "]");

        if ($basePrice && preg_match("#\d#", $basePrice)) {
            $it["BaseFare"] = $this->amount($basePrice);
        }

        // TotalCharge
        // Currency
        $total = $this->http->FindSingleNode("(//td[" . $this->contains($this->t("Total:")) . " and not(.//td)]/following-sibling::td[normalize-space(.)][1])[1]");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Total:")) . "])[1]");
        }

        if (preg_match("/Total Paid[ \:]+([\d,]+) pts and (.+)/", $total, $m)) {
            $r->price()
                ->spentAwards($m[1])
                ->total($this->amount($m[2]))
                ->currency($this->currency($m[2]));
        } elseif ($total) {
            $r->price()
                ->total($this->amount($total))
                ->currency($this->currency($total));
        }

        // TotalTaxAmount
        $taxes = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Taxes")) . "]");

        if ($taxes && preg_match("#\d#", $taxes)) {
            $r->price()
                ->tax($this->amount($taxes));
        }
    }

    public function parseText(Email $email, $text): void
    {
        $email->ota()
            ->confirmation($this->re("/Itinerary\s*[#]\s*\n(\d+)/u", $text));

        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->re("/Confirmation\s*\n\s*([A-Z\d]+)/", $text))
            ->traveller($this->re("/{$this->opt($this->t('Reserved for'))}\s*([A-Z\s]+)\n/u", $text));

        if (preg_match("/{$this->opt($this->t('Car type'))}\s*\n(?<type>.+)\s*\n(?<model>.+)\s*\n/", $text, $m)) {
            $r->car()
                ->type($m['type'])
                ->model($m['model']);
        }

        if (preg_match("/^\s*{$this->opt($this->t('Pick-up'))}\s*\n\s*(?<pickUpDate>.*\d.*?)\s*\n\s*(?<location>.{3,}?)\s*\n/mu", $text, $m)) {
            $r->pickup()
                ->location($m['location'])
                ->date(strtotime($this->normalizeDate($m['pickUpDate'])));
        }

        if (preg_match("/^\s*{$this->opt($this->t('Drop-off'))}\s*\n\s*(?<dropOffDate>.*\d.*?)\s*\n\s*(?<location>.{3,}?)\s*\n/mu", $text, $m)) {
            $r->dropoff()
                ->location($m['location'])
                ->date(strtotime($this->normalizeDate($m['dropOffDate'])));
        }

        if (preg_match("/{$this->opt($this->t('Due at pick-up:'))}\s*(?<currency>\D{1,4})\s*(?<total>[\d\.\,]+)\s*\n/", $text, $m)) {
            $currency = $this->currency($m['currency']);
            $r->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);

            $cost = $this->re("/Base Price:\s*\D{1,4}\s*(?<total>[\d\.\,]+)\s*\n/", $text);

            if (!empty($cost)) {
                $r->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $tax = $this->re("/Taxes and Fees:\s*\D{1,4}\s*(?<total>[\d\.\,]+)\s*\n/", $text);

            if (!empty($tax)) {
                $r->price()
                    ->tax(PriceHelper::parse($tax, $currency));
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getBodyStr();
        }

        if ($this->getProvider($body) === false) {
            return false;
        }

        return $this->assignLang($body);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getBodyStr();
        }

        if (($provider = $this->getProvider($body)) === false) {
            $this->logger->debug('provider not detected!');

            return null;
        } else {
            $email->setProviderCode($provider);
        }

        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        $this->assignLang($this->http->Response['body']);

        if (!empty($parser->getHTMLBody())) {
            $this->parseHtml($email);
        } elseif ($body = $parser->getBodyStr()) {
            $this->parseText($email, $body);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false
                    || $this->http->XPath->query('//node()[contains(normalize-space(),"' . $phrase . '")]')->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str, $year = null)
    {
//        $this->logger->debug('Date: ' . $str. '; Year : '. $year);
        if (empty($year)) {
            $year = date("Y", $this->date);
        }
        $in = [
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4})\s+(\d+:\d+:\d+\s+[AP]M)$#", // Dec 25, 2015 11:30:00 AM
            "#^([^\d\s]+)\s+(\d+),\s+(\d+:\d+(?:\s*[AP]M)?)$#ui", // Jan 30, 4:00PM
            "#^(\d+)\.?\s+([^\d\s]+)\.,\s+(\d+[:.]\d+)\s+(?:Uhr|uur)$#", // 14.? Feb., 19[:.]30 Uhr
            "#^(\d+)\.\s+([^\d\s]+),\s+(\d+:\d+)\s+Uhr$#", //25. März, 17:30 Uhr
            "#^(\d+)(?:\s+de)?\s+([^\d\s\.\,]+)[,.]*\s+(\d+)\s*h\s*(\d+)$#", //7 jun, 9h00; 11 de jan, 18h00

            "#^(\d+)(?:\s+de)?\s+([^\d\s\.\,]+)[,.]*\s+(\d+:\d+(?:\s*[ap]m)?)$#i", //29 Mar., 10:30 am    |    21 de sep, 12:00
            "#^\s*(\d{1,2})\s+([^\d\s\.\,]+)[,.]*[,\s]+(\d+:\d+\s*[ap])(?:\.\s*m\.?)?\s*$#i", //15 Apr., 5:00p
            "#^\s*([^\d\s\.\,]+)[,.]*\s+(\d{1,2})[,\s]+(\d+:\d+\s*[ap])(?:\.\s*m\.?)?\s*$#i", //15 Apr., 5:00p
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $year, $3",
            "$1 $2 $year, $3",
            "$1 $2 $year, $3",
            "$1 $2 $year, $3:$4",

            "$1 $2 $year, $3",
            "$1 $2 $year, $3m",
            "$2 $1 $year, $3m",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return str_replace('.', ':', $str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            'MXN$'   => 'MXN',
            'SG$'    => 'SGD',
            'HK$'    => 'HKD',
            'AU$'    => 'AUD',
            '$ CA'   => 'CAD',
            'R$'     => 'BRL',
            'C$'     => 'CAD',
            'kr'     => 'NOK',
            'RM'     => 'MYR',
            '€'      => 'EUR',
            '£'      => 'GBP',
            '฿'      => 'THB',
            '$'      => 'USD',
            'US$'    => 'USD',
            'NZ$'    => 'NZD',
        ];

        foreach ($sym as $f=>$r) {
            $s = preg_replace("/(?:^|\s|\d)" . preg_quote($f, '/') . "(?:\d|\s|$)/", " " . $r . " ", $s);
        }

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        return null;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function getProvider($body)
    {
        foreach ($this->reBody as $prov => $phrases) {
            if (is_array($phrases)) {
                foreach ($phrases as $phrase) {
                    if (stripos($body, $phrase) !== false) {
                        return $prov;
                    }
                }
            } elseif (is_string($phrases)) {
                if (stripos($body, $phrases) !== false) {
                    return $prov;
                }
            }
        }

        return false;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
