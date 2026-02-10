<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class It3860544 extends \TAccountChecker
{
    public $mailFiles = "avis/it-13.eml, avis/it-16.eml, avis/it-17.eml, avis/it-1807666.eml, avis/it-2122543.eml, avis/it-2392556.eml, avis/it-2607271.eml, avis/it-3675382.eml, avis/it-3778758.eml, avis/it-3795461.eml, avis/it-5.eml, avis/it-6.eml"; // +1 bcdtravel(html)[da]

    public $reBody = 'Avis';
    public $reBody2 = [
        "en" => "YOUR RENTAL DETAILS",
        "nl" => "UW VERHUURGEGEVENS",
        "it" => "DETTAGLI DEL TUO NOLEGGIO",
        "fr" => "Détails de votre réservation",
        "de" => "Ihre Details zur Anmietung",
        "es" => ["ESTOS SON LOS DETALLES DE SU RESERVA", "ESTOS SON LOS DETALLES DE TU RESERVA"],
        "da" => "OPLYSNINGER OM DIT LEJEMÅL",
    ];

    public static $dictionary = [
        "en" => [
            "Booking Confirmation"           => ["Booking Confirmation", "Cancellation of Booking Number"],
            "Cancellation of Booking Number" => "Cancellation of Booking Number",
            "Location:"                      => "Location:",
            "Price"                          => ["Total Paid:", "Total Price:"],
            "e.g."                           => "e\.g\.",
            "AfterModel"                     => "\.\s+This model",
        ],
        "nl" => [
            "Booking Confirmation"           => "Booking Confirmation",
            "Cancellation of Booking Number" => "NOTTRANSLATED",
            "Date:"                          => "Datum:",
            "Click here to view map"         => "NOTTRANSLATED",
            "Location:"                      => "Verhuurstation:",
            "Opening Hours:"                 => "Openingstijden:",
            "Car Type:"                      => "Type auto:",
            "e.g."                           => "bijv\.",
            "AfterModel"                     => "\. Dit model",
            "Price"                          => "Huurprijs:",
            // "Avis Account no:" => "NOTTRANSLATED",
        ],
        "it" => [
            "Booking Confirmation"           => "Conferma di Prenotazione",
            "Cancellation of Booking Number" => "NOTTRANSLATED",
            "Date:"                          => "Data:",
            "Click here to view map"         => "Clicca qui per visualizzare la mappa",
            "Location:"                      => "Località:",
            "Opening Hours:"                 => "Orari di Apertura:",
            "Car Type:"                      => ["TIPO DI AUTO:", "Categoria auto:"],
            "e.g."                           => "(?:Es\.:|Per esempio)",
            "AfterModel"                     => "\. (?:\(Il modello|Questo modello)",
            "Price"                          => "Prezzo del noleggio:",
            // "Avis Account no:" => "NOTTRANSLATED",
        ],
        "fr" => [
            "Booking Confirmation"           => "Confirmation de réservation",
            "Cancellation of Booking Number" => "NOTTRANSLATED",
            "Date:"                          => "Dates de location :",
            "Click here to view map"         => "Cliquez ici pour visualiser sur une carte",
            "Location:"                      => "Agence de location :",
            "Opening Hours:"                 => "Horaires d'ouverture :",
            "Car Type:"                      => "Type de véhicule :",
            "e.g."                           => "ex :",
            "AfterModel"                     => "\. Seule la catégorie",
            "Price"                          => "Montant total :",
            // "Avis Account no:" => "NOTTRANSLATED",
        ],
        "de" => [
            "Booking Confirmation"           => "Buchungsbestätigung",
            "Cancellation of Booking Number" => "NOTTRANSLATED",
            "Date:"                          => "Datum:",
            "Click here to view map"         => "Klicken Sie hier für eine Kartenansicht",
            "Location:"                      => "Anmietstation:",
            "Opening Hours:"                 => "Öffnungszeiten:",
            "Car Type:"                      => "Fahrzeugtyp:",
            "e.g."                           => "z\.B\.:?",
            "AfterModel"                     => "\.\s+(?:Dieses Modell|Das Fahrzeugmodell)",
            "Price"                          => ["Gesamtsumme:", "Gesamtsumme bezahlt:"],
            // "Avis Account no:" => "NOTTRANSLATED",
        ],
        "es" => [
            "Booking Confirmation"           => "Confirmación de Reserva",
            "Cancellation of Booking Number" => "NOTTRANSLATED",
            "Date:"                          => "Fecha:",
            "Click here to view map"         => "Haga click aquí para ver el mapa",
            "Location:"                      => "Localidad:",
            "Opening Hours:"                 => "Horario de Apertura:",
            "Car Type:"                      => "Tipo de Coche:",
            "e.g."                           => "(?:Ej\.|Intermedio por ejemplo)",
            "AfterModel"                     => "\.\s+(?:Le garantizamos|Este modelo no está garantizado)",
            "Price"                          => ["Total Pagado:", "Precio Total:", "Precio del alquiler:"],
            // "Avis Account no:" => "NOTTRANSLATED",
        ],
        "da" => [
            "Booking Confirmation" => "Din Avis-reservation",
            //			"Cancellation of Booking Number" => "",
            "Date:" => "Dato:",
            //			"Click here to view map" => "",
            "Location:"      => "Station:",
            "Opening Hours:" => "Åbningstider:",
            "Car Type:"      => "Biltype:",
            "e.g."           => " : ",
            "AfterModel"     => " Escape :",
            "Price"          => "Samlet beløb:",
            //			"Avis Account no:" => "",
        ],
    ];

    public $lang = "";
    public $langAdd = "";

    private $detectSubject = [
        "en" => "Your Avis Booking Confirmation",
        //        "nl" => "",
        "it" => "Conferma di Prenotazione",
        "fr" => "Confirmation de votre réservation Avis",
        "de" => "Avis Buchungsbestätigung",
        "es" => "Confirmación de Reserva",
        //        "da" => "",
    ];
    private $lastre;
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        foreach ($this->detectSubject as $lang => $dSubject) {
            if (stripos($parser->getSubject(), $dSubject) !== false) {
                $this->langAdd = $lang;

                break;
            }
        }

        $this->http->FilterHTML = false;

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        if (!$this->assignLang()) {
            $this->logger->debug("Can\'t determine a language");

            return null;
        }
        $email->setType('It3860544' . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $reBody2) {
            $reBody = (array) $reBody2;

            foreach ($reBody as $re) {
                if (strpos($this->http->Response["body"], $re) !== false) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t("Location:"))}]/preceding::td[{$this->contains($this->t("Booking Confirmation"))} and not(.//td)][1]",
                null, true, "#" . $this->opt($this->t("Booking Confirmation")) . "\s+([\w-]+)#"))
            ->traveller($this->http->FindSingleNode("//td[not(.//td) and starts-with(normalize-space(),\"" . $this->t("Date:") . "\")]/ancestor::tr[1]/preceding-sibling::tr[2]/descendant::text()[normalize-space()][1]"));

        $r->pickup()
            ->date(strtotime($this->normalizeDate($this->getField($this->t("Date:")))))
            ->location($this->re("#(.*?)\s*(?:[\d\+\- /]+)?\s*(?:" . $this->t("Click here to view map") . "|$)#",
                $this->getField($this->t("Location:"))))
            ->phone(trim($this->re("#^.*?\s*([\d\+\- /]+)\s*(?:" . $this->t("Click here to view map") . "|$)#",
                $this->getField($this->t("Location:")))))
            ->openingHours(preg_replace('/<.+?>/', '', $this->getField($this->t("Opening Hours:"))), true);

        $r->dropoff()
            ->date(strtotime($this->normalizeDate($this->getField($this->t("Date:"), 2))))
            ->location($this->re("#^(.*?)\s*(?:[\d\+\- /]+)?\s*(?:" . $this->t("Click here to view map") . "|$)#",
                $this->getField($this->t("Location:"), 2)))
            ->phone(trim($this->re("#^.*?\s*([\d\+\- /]+)\s*(?:" . $this->t("Click here to view map") . "|$)#",
                $this->getField($this->t("Location:"), 2))))
            ->openingHours($this->getField($this->t("Opening Hours:"), 2), true);

        $node = $this->getField($this->t("Car Type:"));
        $type = trim($this->re("#(.*?)\s*" . $this->opt($this->t("e.g."), false) . "#", $node), ' :');

        if (empty($type) && !empty($this->langAdd)) {
            $type = trim($this->re("#(.*?)\s*" . $this->opt($this->t("e.g.", $this->langAdd), false) . "#", $node), ' :');
        }
        $model = $this->re("#" . $this->opt($this->t("e.g."), false) . "\s*(.*?)" . $this->opt($this->t("AfterModel"), false) . "#", $node);

        if (empty($model) && !empty($this->langAdd)) {
            $model = $this->re("#" . $this->opt($this->t("e.g.", $this->langAdd), false) . "\s*(.*?)" . $this->opt($this->t("AfterModel", $this->langAdd), false) . "#", $node);
        }
        $r->car()
            ->type($type)
            ->model($model, false, true);

        $node = $this->http->FindSingleNode("(//td[not(.//td) and ({$this->starts($this->t("Price"))})])[last()]/following-sibling::td[1]");
        $total = $this->getTotalCurrency($this->re("#(.*?)(?:\(|$)#", $node));

        if (!empty($total['Total'])) {
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }

        $acc = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Avis Account no:"))}]", null, true,
            "#" . $this->opt($this->t("Avis Account no:")) . "\s+([\w-]{3,})#");

        if (!empty($acc)) {
            $r->program()
                ->account($acc, false);
        }

        if ($this->http->FindSingleNode("//h1[contains(., '" . $this->t("Cancellation of Booking Number") . "')]")) {
            $r->general()->cancelled();
        }
    }

    private function getField($name, $col = 1)
    {
        $xpath = "//td[not(.//td) and ({$this->starts($name)})]/following-sibling::td[{$col}]";

        return trim($this->http->FindSingleNode($xpath));
    }

    private function t($word, $lang = null)
    {
        if (empty($lang)) {
            $lang = $this->lang;
        }

        if (!isset(self::$dictionary[$lang]) || !isset(self::$dictionary[$lang][$word])) {
            return $word;
        }

        return self::$dictionary[$lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^\w+\s+(\d+)\s+(\w+)\s+At\s+(\d+:\d+)$#",
            "#^(\d+)\s+(\w+)\s+at\s*:\s*(\d+:\d+)$#",
            "#^\w+\s+(\d+)\s+(\w+)\s+bij\s+(\d+:\d+)$#",
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+ore\s+(\d+:\d+)$#",
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+à\s+(\d+:\d+)$#",
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(?:um|Um)\s+(\d+:\d+)$#",
            // Sonntag 26 Juni Um 19:30
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d+:\d+)$#",
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(?:på|På|a las)\s+(\d+:\d+)$#u",
            // fredag 21 september på 20:00  |  jueves 2 mayo a las 11:00
        ];
        $out = [
            "$1 $2 {$year}, $3",
            "$1 $2 {$year}, $3",
            "$1 $2 {$year}, $3",
            "$1 $2 {$year}, $3",
            "$1 $2 {$year}, $3",
            "$1 $2 {$year}, $3",
            "$1 $2 {$year}, $3",
            "$1 $2 {$year}, $3",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }

            if (!empty($this->langAdd) && $translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->langAdd)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function re($re, $str = null, $c = 1)
    {
        if (is_int($re) && $str === null) {
            if (isset($this->lastre[$re])) {
                return $this->lastre[$re];
            } else {
                return null;
            }
        }

        preg_match($re, $str, $m);
        $this->lastre = $m;

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Booking Confirmation"], $words["Location:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Booking Confirmation'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Location:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field, $quoted = true)
    {
        $field = (array) $field;

        if ($quoted === true) {
            $field = array_map(function ($s) {return preg_quote($s, '#'); }, $field);
        }

        return '(?:' . implode("|", $field) . ')';
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
