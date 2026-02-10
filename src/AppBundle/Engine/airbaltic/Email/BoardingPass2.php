<?php

namespace AwardWallet\Engine\airbaltic\Email;

class BoardingPass2 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $mailFiles = "airbaltic/it-5030549.eml, airbaltic/it-6018924.eml, airbaltic/it-6122352.eml, airbaltic/it-6149730.eml, airbaltic/it-6704068.eml, airbaltic/it-8424324.eml";

    public $reFrom = 'airbaltic.com';
    public $reSubject = [
        'lt' => 'Jūsų įlaipinimo kortelė',
        'lv' => 'Jūsu iekāpšanas karte',
        'et' => 'Teie pardakaart/-kaardid',
        'ru' => 'Ваш посадочный талон',
        'de' => 'Ihre Bordkarte(n)',
    ];

    /** @var \HttpBrowser */
    public $pdf;
    public $reBody = 'Air Baltic';
    public $reBody2 = [
        'lt' => 'Jūsų įlaipinimo bilietas',
        'lv' => 'Jūsu iekāpšanas karte',
        'et' => 'Teie pardakaart/-kaardid',
        'ru' => 'Ваш посадочный талон',
        'en' => ['Thank you for choosing airBaltic', 'Thank you for booking your flight(s) directly at airBaltic.com'],
        'de' => 'Ihre Bordkarte(n)',
    ];

    public static $dictionary = [
        'lt' => [
            'Booking reference' => 'UŽSAKYMO NUMERIS',
            'FLIGHT NUMBER'     => 'SKRYDŽIO NUMERIS',
        ],
        'lv' => [
            'Booking reference' => 'REZERVĀCIJAS NUMURS',
            'FLIGHT NUMBER'     => 'LIDOJUMA NUMURS',
        ],
        'et' => [
            'Booking reference' => 'BRONEERINGU NUMBER',
            'FLIGHT NUMBER'     => 'LENNU NUMBER',
        ],
        'ru' => [
            'Booking reference' => 'НОМЕР БРОНИРОВАНИЯ',
            'FLIGHT NUMBER'     => 'НОМЕР РЕЙСА',
        ],
        'en' => [
            'Booking reference' => 'BOOKING REFERENCE',
        ],
        'de' => [
            'Booking reference' => 'BUCHUNGSNUMMER',
            'FLIGHT NUMBER'     => 'FLUGNUMMER',
        ],
    ];

    public $lang = 'lt';

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = $this->nextText($this->t("Booking reference"));

        if (isset($this->pdf)) {
            $it['Passengers'] = array_unique($this->pdf->FindNodes("//text()[contains(.,'Name of passenger')]/following::text()[position()=4 and string-length(normalize-space(.))>3]"));
        }

        if (isset($this->pdf)) {
            $it['TicketNumbers'] = array_unique($this->pdf->FindNodes('//text()[normalize-space(.)="From"]/preceding::text()[string-length(normalize-space(.))>10][1]', null, '/^\s*(\S[-\d\s]+\S)\s*$/'));
        }
        $xpath = "//text()[normalize-space(.)='" . $this->t("FLIGHT NUMBER") . "']/ancestor::tr[2]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            $itsegment['FlightNumber'] = $this->re("#\w{2}(\d+)#", $this->nextText($this->t("FLIGHT NUMBER"), $root));
            $itsegment['DepCode'] = $this->http->FindSingleNode(".//tr[2]/descendant::text()[normalize-space(.)][2]", $root, true, "#\(([A-Z]{3})\)#");
            $itsegment['DepName'] = $this->http->FindSingleNode(".//tr[2]/descendant::text()[normalize-space(.)][2]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//tr[2]/descendant::text()[normalize-space(.)][2]", $root, true, "#\([A-Z]{3}\),\s+(.+)#")));
            $itsegment['ArrCode'] = $this->http->FindSingleNode(".//tr[3]/descendant::text()[normalize-space(.)][2]", $root, true, "#\(([A-Z]{3})\)#");
            $itsegment['ArrName'] = $this->http->FindSingleNode(".//tr[3]/descendant::text()[normalize-space(.)][2]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//tr[3]/descendant::text()[normalize-space(.)][2]", $root, true, "#\([A-Z]{3}\),\s+(.+)#")));
            $itsegment['AirlineName'] = $this->re("#(\w{2})\d+#", $this->nextText($this->t("FLIGHT NUMBER"), $root));
            $flight = $itsegment['AirlineName'] . $itsegment['FlightNumber'];

            if (isset($this->pdf)) {
                $itsegment['Seats'] = implode(',', array_unique($this->pdf->FindNodes("//text()[contains(.,'{$flight}')]/following::text()[position()=12 and string-length(normalize-space(.))<=3]")));
            }

            if (isset($this->pdf)) {
                $itsegment['BookingClass'] = implode(',', array_unique($this->pdf->FindNodes("//text()[contains(.,'{$flight}')]/following::text()[position()=6 and string-length(normalize-space(.))<=3]")));
            }
            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (is_string($re) && strpos($body, $re) !== false) {
                return true;
            } elseif (is_array($re)) {
                foreach ($re as $r) {
                    if (stripos($body, $r) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->subject = $parser->getSubject();
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();
        $this->http->SetBody(str_replace(" ", " ", $body)); // bad fr char " :"

        foreach ($this->reBody2 as $lang => $re) {
            if (is_string($re) && strpos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            } elseif (is_array($re)) {
                foreach ($re as $r) {
                    if (stripos($body, $r) !== false) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            }
        }

        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = \PDF::convertToHtml($parser->getAttachmentBody(array_shift($pdfs)), \PDF::MODE_SIMPLE);
            $this->pdf->SetBody($html);
        }

        $this->parseHtml($itineraries);

        return [
            'emailType'  => 'BoardingPass2' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
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
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)/(\d+)/(\d{4}),\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
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
}
