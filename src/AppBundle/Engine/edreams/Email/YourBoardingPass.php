<?php

namespace AwardWallet\Engine\edreams\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourBoardingPass extends \TAccountChecker
{
    public $mailFiles = "edreams/it-10599974.eml, edreams/it-10616543.eml, edreams/it-10722160.eml, edreams/it-10775787.eml, edreams/it-10785479.eml, edreams/it-11131495.eml, edreams/it-11147010.eml, edreams/it-11168693.eml, edreams/it-11180878.eml, edreams/it-11238381.eml, edreams/it-11253264.eml, edreams/it-11253270.eml, edreams/it-11279739.eml, edreams/it-11292449.eml, edreams/it-11340983.eml, edreams/it-11360289.eml, edreams/it-15676549.eml, edreams/it-16163977.eml, edreams/it-18437277.eml, edreams/it-18695564.eml, edreams/it-29994480.eml";

    public static $dictionary = [
        "en" => [
            //			"Dear" => '',
            //			"Flight" => '',
            //			"Departure" => "",
            //			"Booking reference:" => '',
        ],
        "pt" => [
            "Dear"               => 'Caro',
            "Flight"             => 'Voo',
            "Departure"          => "Partida",
            "Booking reference:" => 'Referência da reserva:',
        ],
        "es" => [
            "Dear"               => 'Estimado/a',
            "Flight"             => 'Vuelo',
            "Departure"          => "Salida",
            "Booking reference:" => 'Referencia de reserva:',
        ],
        "it" => [
            "Dear"               => 'Gentile',
            "Flight"             => 'Volo',
            "Departure"          => ["Salida", "Andata"],
            "Booking reference:" => 'Numero di prenotazione:',
        ],
        "fr" => [
            "Dear"               => 'Cher/Chère',
            "Flight"             => 'Vol',
            "Departure"          => "Départ",
            "Booking reference:" => 'Référence de réservation:',
        ],
        "nl" => [
            "Dear"               => 'Beste',
            "Flight"             => 'Vlucht',
            "Departure"          => "Vertrek",
            "Booking reference:" => 'boekingsreferentie:',
        ],
        "de" => [
            "Dear"               => 'Sehr geehrte(r)',
            "Flight"             => 'Flug',
            "Departure"          => "Abflug",
            "Booking reference:" => 'Buchungsnummer:',
        ],
        "da" => [
            "Dear"               => 'Kære',
            "Flight"             => 'Fly',
            "Departure"          => ["Afgang", "Afrejse"],
            "Booking reference:" => 'Buchungsnummer:',
        ],
        "sv" => [
            "Dear"               => 'Kära',
            "Flight"             => 'Flyg',
            "Departure"          => "Avresa",
            "Booking reference:" => 'Bokningsnummer:',
        ],
        "ru" => [
            "Dear"               => 'Уважаемый(ая)',
            "Flight"             => ' Рейс',
            "Departure"          => "Вылет",
            "Booking reference:" => 'Посадачнный номер:',
        ],
        "pl" => [
            "Dear"               => 'Drogi/Droga',
            "Flight"             => 'Lot',
            "Departure"          => "Wylot",
            "Booking reference:" => 'Numer rezerwacji:',
        ],
    ];

    public static $detectProvider = [
        "gotogate"  => [
            'companyName' => 'gotogate',
            'from'        => 'gotogate.',
        ],
        "fnt"  => [
            'companyName' => 'Flightnetwork.com',
            // 'from' => 'gotogate.',
        ],
        "trip"  => [
            'companyName' => 'MYTRIP',
            // 'from' => 'gotogate.',
        ],
        "opodo"     => [
            'companyName' => 'Opodo',
            'from'        => 'opodo.',
        ],
        "tllink"    => [
            'companyName' => 'Travellink',
            'from'        => 'travellink.',
        ],
        "govoyages" => [
            'companyName' => 'Govoyages',
            // 'from' => '',
        ],
        // last
        "edreams"   => [
            'companyName' => 'eDreams',
            'from'        => 'edreams.',
        ],
    ];

    private $detectSubject = [
        "en"  => "Your boarding pass from",
        "en2" => "boarding pass(es)",
        "pl"  => "Status odprawy",
        "pt"  => "O seu cartão de embarque de",
        "pt2" => "Seus cartões de embarque",
        "es"  => "Tu tarjeta de embarque de",
        "es2" => "Tus tarjetas de embarque",
        "it"  => "La tua carta di imbarco da",
        "it2" => "Le tue carta di imbarco",
        "fr"  => "Votre carte d'embarquement",
        "fr2" => "Vos cartes d'embarquement",
        "nl"  => "instapkaart(en)",
        "de"  => "Bordkarte(n)",
        "da"  => "-boardingpas",
        "sv"  => "Din incheckningsstatus",
        "ru"  => "Ваш(и) посадочные талон(ы)",
    ];

    private $detectBody = [
        "pl" => "Lot ",
        "pt" => "Voo ",
        "es" => "Vuelo",
        "it" => "Volo ",
        "fr" => "Vol ",
        "nl" => "Vlucht ",
        "de" => "Flug",
        "da" => "Fly ",
        "sv" => "Flyg ",
        "ru" => "Рейс ",
        "en" => "Flight", // must be last
    ];

    private $lang = "en";
    private $provider;
    private $date;

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime("-10 day", strtotime($parser->getHeader('date')));
        //		if ($this->date == null) {
        //			$this->logger->info("not detect date");
        //			return $email;
        //		}

        $body = html_entity_decode($this->http->Response['body']);

        foreach ($this->detectBody as $lang => $detectBody) {
            if (strpos($body, $detectBody) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!empty($this->provider)) {
            $codeProvider = $this->provider;
        } else {
            $codeProvider = $this->getProvider();
        }

        if (!empty($codeProvider)) {
            $email->setProviderCode($codeProvider);
            $email->ota()->code($codeProvider);
        }

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectProvider as $value) {
            if (!empty($value['from']) && stripos($from, $value['from']) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $head = false;

        foreach (self::$detectProvider as $prov => $value) {
            if (!empty($value['from']) && strpos($headers["from"], $value['from']) !== false) {
                $head = true;
                $this->provider = $prov;

                break;
            }
        }

        if ($head === false) {
            return false;
        }

        foreach ($this->detectSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                foreach (self::$detectProvider as $prov => $value) {
                    if (!empty($value['companyName']) && stripos($headers["subject"], $value['companyName']) !== false) {
                        $this->provider = $prov;

                        return true;
                    }
                }

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = html_entity_decode($this->http->Response['body']);

        $finded = false;

        foreach (self::$detectProvider as $prov => $value) {
            if (!empty($value['from']) && $this->http->XPath->query('//a[contains(@href, "' . $value['from'] . '")]')->length > 0
                || !empty($value['companyName']) && stripos($body, $value['companyName']) !== false) {
                $finded = true;

                break;
            }
        }

        if ($finded === false) {
            return false;
        }

        foreach ($this->detectBody as $reBody) {
            if (stripos($body, $reBody) !== false) {
                return true;
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

    private function flight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking reference:")) . "]/following::text()[normalize-space(.)][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking reference:")) . "]", null, true, "#\s+([A-Z\d]{5,8})\s*$#");
        }
        $f->general()->confirmation($conf);

        // Passengers
        $travellers = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t("Flight")) . "]/ancestor::tr[1]/following-sibling::tr[count(./td) = 2 and contains((./td[2]//text()[normalize-space()]/ancestor::*[1])/@style,'color:')]/td[1]")));

        if (count($travellers) == 0) {
            $travellers = array_unique(array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Check-in scheduled (')]/ancestor::tr[1]/descendant::td[normalize-space()][1][not(contains(normalize-space(), 'hours'))]")));
        }

        if (count($travellers) == 0) {
            $travellers = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Boarding pass attached'))}]/ancestor::tr[1]/descendant::td[1][not({$this->contains($this->t('Boarding pass attached'))})]")));
        }

        if (!empty($travellers)) {
            $f->general()->travellers($travellers, true);
        }

        if (empty($travellers)) {
            $travellers = array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("Dear")) . "]", null, "#" . $this->opt($this->t("Dear")) . "\s+(.+),$#"));

            if (empty($travellers) && !empty($this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Dear")) . "])[1]", null, "#" . $this->opt($this->t("Dear")) . "\s*$#"))) {
                $travellers = $this->http->FindNodes("//text()[" . $this->starts($this->t("Dear")) . "]/following::text()[normalize-space()][1]", null, "#^([^,.]+),$#");
            }

            if (!empty($travellers)) {
                $f->general()->travellers($travellers, false);
            }
        }

        $xpath = "//text()[" . $this->eq($this->t("Departure")) . "]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./preceding::tr[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#\s+(\w{2})\d+(?:$|\s*" . $this->opt($this->t('Departure')) . ".*)#"))
                ->number($this->http->FindSingleNode("./preceding::tr[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#\s+\w{2}(\d+)(?:$|\s*" . $this->opt($this->t('Departure')) . ".*)#"));

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("./td[2]", $root, true, "#\(([A-Z]{3})\)#"))/*
                ->name($this->http->FindSingleNode("./td[2]/*[2]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#"))*/;

            $date = $this->normalizeDate($this->http->FindSingleNode("./td[2]/*[normalize-space()][last()]", $root));
            $s->departure()->date($date);

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("./td[4]", $root, true, "#\(([A-Z]{3})\)#"))/*
                ->name($this->http->FindSingleNode("./td[4]/*[2]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#"))*/;

            $date = $this->normalizeDate($this->http->FindSingleNode("./td[4]/*[normalize-space()][last()]", $root));

            $s->arrival()->date($date);
        }

        return $email;
    }

    private function getProvider()
    {
        foreach (self::$detectProvider as $prov => $value) {
            if (!empty($value['companyName']) && $this->http->XPath->query("//node()[{$this->contains($value['companyName'])}]")->length > 0
                || (!empty($value['from']) && $this->http->XPath->query('//a[contains(@href, "' . $value['from'] . '")]')->length > 0)
            ) {
                return $prov;
            }
        }

        return null;
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //		 $this->logger->info($str);
        $in = [
            "#^(\d+) ([^\s\d\,\.]+)[.,]* (\d+:\d+)$#", //05 Jan 08:30
        ];
        $out = [
            "$1 $2 %Y%, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], 'fr')) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return EmailDateHelper::parseDateRelative($str, $this->date, true, $str);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
