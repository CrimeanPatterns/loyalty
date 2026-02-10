<?php

namespace AwardWallet\Engine\airbaltic\Email;

class BoardingPass extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $mailFiles = "airbaltic/it-4951314.eml, airbaltic/it-6107210.eml, airbaltic/it-8573429.eml";

    public $reFrom = 'airbaltic.com';
    public $reSubject = [
        'ru' => 'Ваш посадочный талон',
        'lv' => 'Jūsu iekāpšanas karte',
        'de' => 'Ihre Bordkarte(n)',
    ];

    /** @var \HttpBrowser */
    public $pdf;
    public $reBody = 'Air Baltic';
    public $reBody2 = [
        'ru' => 'Ваш посадочный талон',
        'lv' => 'Jūsu iekāpšanas karte',
        'en' => ['Thank you for choosing airBaltic', 'Thank you for booking your flight(s) directly at airBaltic.com'],
        'de' => ['Ihre Bordkarte(n)'],
    ];

    public static $dictionary = [
        'ru' => [
            'Booking reference' => 'НОМЕР БРОНИРОВАНИЯ',
            'FLIGHT NUMBER'     => 'НОМЕР РЕЙСА',
        ],
        'lv' => [
            'Booking reference' => 'REZERVĀCIJAS NUMURS',
            'FLIGHT NUMBER'     => 'LIDOJUMA NUMURS',
        ],
        'en' => [
            'Booking reference' => [
                'Booking reference', 'BOOKING REFERENCE',
            ],
        ],
        'de' => [
            'Booking reference' => 'BUCHUNGSNUMMER',
            'FLIGHT NUMBER'     => 'FLUGNUMMER',
        ],
    ];

    public $lang = '';

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = 'T';

        $it['RecordLocator'] = $this->http->FindSingleNode("(.//text()[" . $this->getXpath($this->t('Booking reference')) . "])[1]/following::text()[normalize-space(.)][1]");

        if (isset($this->pdf)) {
            $it['Passengers'] = array_unique($this->pdf->FindNodes("//text()[contains(.,'Name of passenger')]/following::text()[position()=4 and string-length(normalize-space(.))>3]"));
        }

        if (isset($this->pdf)) {
            $ticketNumbers = array_unique(array_diff($this->pdf->FindNodes("//text()[contains(.,'Ticket No')]/following::text()[(position()=3 or position()=4) and string-length(normalize-space(.))>4]", null, '/(\d{11,})/'), [null]));
            sort($ticketNumbers);
            $it['TicketNumbers'] = $ticketNumbers;
        }

        $xpath = "//text()[normalize-space(.)='" . $this->t("FLIGHT NUMBER") . "']/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->info("Segments root not found: $xpath");
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            $itsegment['FlightNumber'] = $this->re("#\w{2}(\d+)#", $this->nextText($this->t("FLIGHT NUMBER"), $root));
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#\(([A-Z]{3})\)#");
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][2]", $root)));
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root, true, "#\(([A-Z]{3})\)#");
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][2]", $root, null, "#\d+:\d+#")), $itsegment['DepDate']);
            $itsegment['AirlineName'] = $this->re("#(\w{2})\d+#", $this->nextText($this->t("FLIGHT NUMBER"), $root));
            $flight = $itsegment['AirlineName'] . $itsegment['FlightNumber'];

            if (isset($this->pdf)) {
                $itsegment['Seats'] = array_unique($this->pdf->FindNodes("//text()[contains(.,'{$flight}')]/following::text()[position()=12 and string-length(normalize-space(.))<=3]"));
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

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (is_string($re) && stripos($body, $re) !== false) {
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
        $this->http->SetBody(str_replace(" ", " ", $this->http->Response['body'])); // bad fr char " :"

        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $lang => $re) {
            if (is_string($re) && strpos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            } elseif (is_array($re)) {
                foreach ($re as $r) {
                    if (stripos($body, $r) !== false) {
                        $this->lang = $lang;

                        break;
                    }
                }
            }
        }

        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $html = '';

            foreach ($pdfs as $pdf) {
                if (($html .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                } else {
                    return null;
                }
            }
            $this->pdf->SetBody($html);
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'BoardingPass' . ucfirst($this->lang),
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

    private function getXpath($str, $node = '.')
    {
        $res = '';

        if (is_array($str)) {
            $contains = array_map(function ($str) use ($node) {
                return "normalize-space(" . $node . ") = '" . $str . "'";
            }, $str);
            $res = implode(' or ', $contains);
        } elseif (is_string($str)) {
            $res = "normalize-space(" . $node . ") = '" . $str . "'";
        }

        return $res;
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
