<?php

namespace AwardWallet\Engine\finnair\Email;

class It5897173 extends \TAccountChecker
{
    public $mailFiles = "finnair/it-33057477.eml, finnair/it-39781890.eml, finnair/it-39978930.eml, finnair/it-5897173.eml, finnair/it-5940114.eml, finnair/it-5948744.eml, finnair/it-6199977.eml";

    public $reSubject = [
        'fi' => ['Ennen lentoasi', 'Lentosi kohteeseen'],
        'en' => ['Your journey to'],
        'sv' => ['Ditt flyg till'],
    ];

    protected $langDetectors = [
        'fi' => ['Lentotietosi', 'Matkatietosi'],
        'en' => ['Your flight details', 'YOUR FLIGHT DETAILS'],
        'sv' => ['Flyginformation'],
    ];

    protected static $dictionary = [
        'fi' => [
            "RL"            => "#Varauksesi\s+(\w+)#",
            "FL"            => "#ensimmäinen lento\s+(\w{2}\d+)#",
            "HEI "          => ["HEI ", "Hei "],
            "regExpSubject" => [
                "#Finnair - Ennen lentoasi ([\w, ]+)$#",
                "#^.*?\s*([^:\d]+?)[ ]*: Lentosi kohteeseen#",
            ],
        ],
        'en' => [
            "RL"                => "#on your reservation\s+(\w+)#",
            "FL"                => "#The first Finnair flight\s+(\w{2}[ ]*\d+)#",
            "ensimmäinen lento" => "on your reservation",
            "LÄHTEE"            => "DEPARTS",
            "SAAPUU"            => "ARRIVES",
            "Matkustusluokka:"  => "Travel class:",
            "Paikka:"           => "Seat:",
            "Lennon kesto:"     => "Total duration:",
            "Liikennöi"         => "Operated by",
            "Jäsennumero"       => "Membership number",
            "HEI "              => ["HELLO ", "Hello "],
            "regExpSubject"     => [
                "#^.*?\s*([^:\d]+?) \- Your journey to#",
            ],
        ],
        'sv' => [
            "RL"                => "#på din bokning\s+(\w+)#",
            "FL"                => "#Den första flygresan med Finnair\s+(\w{2}\d+)#",
            "ensimmäinen lento" => "på din bokning",
            "LÄHTEE"            => ["AVGÅR", "AVGÅNG"],
            "SAAPUU"            => ["ANKOMMER", "ANKOMST"],
            "Matkustusluokka:"  => "Reseklass:",
            "Paikka:"           => "NOTTRANSLATE",
            "Lennon kesto:"     => "NOTTRANSLATED",
            //            "Liikennöi" => "",
            "Jäsennumero"   => "Medlemsnummer",
            "HEI "          => ["HEJ ", "Hej "],
            "regExpSubject" => [
                "#^.*?\s*([^:\d]+?)[ ]*: Ditt flyg till#",
            ],
        ],
    ];

    protected $lang = '';
    private $subject;

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t("ensimmäinen lento") . "']/ancestor::p[1]", null, true, $this->t("RL"));

        // TripNumber
        // Passengers
        $pax = $this->http->FindSingleNode("//text()[normalize-space()=\"" . $this->t('Jäsennumero') . "\"]/preceding::text()[normalize-space()!=''][1]");

        if (empty($pax)) {
            // try get from subject
            $regExpSubject = $this->t('regExpSubject');

            if (is_array($regExpSubject)) {
                foreach ($regExpSubject as $regExp) {
                    $p = trim($this->re($regExp, $this->subject));

                    if (!empty($p)) {
                        $pax = $p;

                        break;
                    }
                }
            }
        }

        if (empty($pax)) {
            // get from "Hello ...,"
            $pax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('HEI '))}]", null, false,
                "#{$this->opt($this->t('HEI '))}\s*([\w ]+)[,]?\s*$#");
        }

        if (!empty($pax)) {
            $it['Passengers'][] = $pax;
        }
        // AccountNumbers
        $account = $this->http->FindSingleNode("//text()[normalize-space()=\"" . $this->t('Jäsennumero') . "\"]/following::text()[normalize-space()!=''][1]", null, false, '/^(\d+)$/');

        if (!empty($account)) {
            $it['AccountNumbers'][] = $account;
        }

        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $itsegment = [];

        // FlightNumber
        $itsegment['FlightNumber'] = $this->re("#^\w{2}[ ]*(\d+)$#", $this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t("ensimmäinen lento") . "']/ancestor::p[1]", null, true, $this->t("FL")));

        // DepCode
        $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})\)#", $this->nextText($this->t("LÄHTEE"), null, 4));

        // DepName
        $itsegment['DepName'] = trim($this->nextText($this->t("LÄHTEE"), null, 3), ', ');

        // DepDate
        $itsegment['DepDate'] = strtotime($this->normalizeDate($this->nextText($this->t("LÄHTEE"), null, 2) . ',' . $this->nextText($this->t("LÄHTEE"))));

        // ArrCode
        $itsegment['ArrCode'] = $this->re("#(?:\(|\/)([A-Z]{3})(?:\))?#", $this->nextText($this->t("SAAPUU"), null, 4));

        // ArrName
        $itsegment['ArrName'] = trim($this->nextText($this->t("SAAPUU"), null, 3), ', ');

        // ArrDate
        $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->nextText($this->t("SAAPUU"), null, 2) . ',' . $this->nextText($this->t("SAAPUU"))));

        // AirlineName
        $itsegment['AirlineName'] = $this->re("#^(\w{2})[ ]*\d+$#", $this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t("ensimmäinen lento") . "']/ancestor::p[1]", null, true, $this->t("FL")));

        // Operator
        $itsegment['Operator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'" . $this->t("Liikennöi") . "')]", null, true, "#" . $this->t("Liikennöi") . "\s+(.+?)\s*\.\s*" . $this->t("Lennon kesto:") . "#");

        // Aircraft
        // TraveledMiles
        // AwardMiles
        // Cabin
        $itsegment['Cabin'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'" . $this->t("Matkustusluokka:") . "')]", null, true, "#" . $this->t("Matkustusluokka:") . "\s+(.+)#");

        // BookingClass
        // PendingUpgradeTo
        // Seats
        $itsegment['Seats'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'" . $this->t("Paikka:") . "')]", null, true, "#" . $this->t("Paikka:") . "\s+(.+)#");

        // Duration
        $itsegment['Duration'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'" . $this->t("Lennon kesto:") . "')]", null, true, "#" . $this->t("Lennon kesto:") . "\s+(.+)#");

        // Meal
        // Smoking
        // Stops
        $it['TripSegments'][] = $itsegment;

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@finnair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], '@finnair.com') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($parser->getHTMLBody())) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Finnair flight") or contains(normalize-space(.),"Finnair Plus") or contains(normalize-space(.),"Finnair service") or contains(.,"finnair.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"www.finnair.com") or contains(@href,"services.finnair.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->subject = $parser->getSubject();

        $this->http->FilterHTML = false;

        if (empty($parser->getHTMLBody())) {
            $NBSP = chr(194) . chr(160);
            $html = str_replace($NBSP, ' ', html_entity_decode($parser->getPlainBody()));
            $this->http->SetEmailBody($html);
        }
        $this->assignLang();

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'It5897173' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[{$this->eq($field)}])/following::text()[normalize-space(.)!=''][{$n}]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\.(\d+)\.(\d{2}),(\d+:\d+)$#",
        ];
        $out = [
            "$1.$2.20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }
}
