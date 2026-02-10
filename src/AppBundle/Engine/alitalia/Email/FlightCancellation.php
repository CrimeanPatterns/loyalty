<?php

namespace AwardWallet\Engine\alitalia\Email;

use AwardWallet\Engine\MonthTranslate;

class FlightCancellation extends \TAccountChecker
{
    public $mailFiles = "alitalia/it-6936946.eml, alitalia/it-6972320.eml, alitalia/it-6996917.eml, alitalia/it-7021823.eml, alitalia/it-7029013.eml";

    public $reFrom = '@alitalia.';
    public $reSubject = [
        'en' => 'Flight Cancellation Notification',
    ];

    public $lang = '';

    public $reBody = 'alitalia';
    public $langDetectors = [
        'it' => ['stato cancellato'],
        'en' => ['has been cancelled'],
    ];

    public static $dictionary = [
        'it' => [
            'Booking Code:' => 'CODICE DI PRENOTAZIONE:',
            'Flight'        => 'Volo',
            'on'            => 'il giorno', //il giorno 16 giu 2017
            'cancelled'     => 'cancellato',
            'Departs'       => 'Partenza alle',
            'Operated by'   => 'Volo operato da',
            'to'            => 'a', // Percorso FCO a ZRH
        ],
        'en' => [
            'Booking Code:' => ['Booking Code:', 'Confirmation Code:'],
        ],
    ];

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = "T";

        //Cancelled
        $it['Cancelled'] = true;

        //Status
        $it['Status'] = "cancelled";

        //RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode('//text()[' . $this->contains($this->t('Booking Code:')) . ']/ancestor::td[1]/following-sibling::td[1]', null, true, '/[A-Z\d]{5,7}/');

        //Passengers
        $it['Passengers'] = array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Departs")) . "]/ancestor::tr[1]/following-sibling::tr/td[6]"));

        $year = '';
        $yearstr = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'" . $this->t('Flight') . "') and contains(normalize-space(.),'" . $this->t('cancelled') . "')][1]");
        // on 16 Jun 2017 12:15
        if (preg_match('#' . $this->t('on') . "\s*\d{2}\s[a-z]{3}\s(\d{4})#i", $yearstr, $m)) {
            $year = $m[1];
        }

        $seatPos = $this->http->XPath->query("//tr/*[normalize-space()!=''][7][ descendant::text()[{$this->eq($this->t("Seat"))}] ]/preceding-sibling::*")->length;

        //Air Trip Segment
        $xpath = "//text()[" . $this->eq($this->t("Departs")) . "]/ancestor::tr[1]/following-sibling::tr[count(./td[string-length(normalize-space(.))>1])>4]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $key => $root) {
            $segment = [];
            $date = $this->http->FindSingleNode("./td[1]", $root, true);

            // Fri, Jun 16
            if (preg_match("#([a-z]{3})\s(\d{2})#i", $date, $m)) {
                $date = $m[2] . ' ' . MonthTranslate::translate($m[1], $this->lang) . ' ' . $year;
            }

            //DepDate
            $time = $this->http->FindSingleNode("./td[2]", $root, true, "#(\d{1,2}:\d{2} (AM|PM))#");
            $segment["DepDate"] = strtotime($date . ' ' . $time);

            //ArrDate
            $time2 = $this->http->FindSingleNode("./td[3]", $root, true, "#(\d{1,2}:\d{2} (AM|PM))#");
            $segment["ArrDate"] = strtotime($date . ' ' . $time2);

            //FlightNumber
            //AirlineName
            //Operator
            $f = $this->http->FindSingleNode("./td[4]", $root);

            if (preg_match("#([\w]{2})\s(\d{2,5})\s*(" . $this->t('Operated by') . "\s*(.+))?#i", $f, $m)) {
                $segment["FlightNumber"] = $m[2];
                $segment["AirlineName"] = $m[1];

                if (!empty($m[4])) {
                    $segment["Operator"] = $m[4];
                }
            }

            //DepCode
            //ArrCode
            $route = $this->http->FindSingleNode("./td[5]", $root);

            if (preg_match("#([A-Z]{3})\s" . $this->t('to') . "\s*([A-Z]{3})#", $route, $m)) {
                $segment["DepCode"] = $m[1];
                $segment["ArrCode"] = $m[2];
            }

            //Seats
            $seats = [];

            if ($seatPos > 0
                && ($seat = $this->http->FindSingleNode("*[$seatPos+1]", $root, true, '/^\d{1,5}[A-z]$/'))
            ) {
                $seats[] = $seat;
            }
            $followRows = $this->http->XPath->query('following-sibling::tr', $root);

            foreach ($followRows as $row) {
                if (!empty($roots[$key + 1]) && $roots[$key + 1] === $row) {
                    break;
                }

                if ($seatPos > 0
                    && ($seat = $this->http->FindSingleNode("*[$seatPos+1]", $row, true, '/^\d{1,5}[A-z]$/'))
                ) {
                    $seats[] = $seat;
                }
            }

            if (count($seats)) {
                $segment["Seats"] = $seats;
            }

            //DepartureTerminal
            if ($t = $this->http->FindSingleNode('./td[8]', $root)) {
                $segment["DepartureTerminal"] = trim(str_ireplace('Terminal', '', $t));
            }

            //TripSegments
            $it['TripSegments'][] = $segment;
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (isset($headers["from"]) && stripos($headers["from"], $this->reFrom) !== false
                    && isset($headers["subject"]) && stripos($headers["subject"], $re) !== false) {
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

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
//        $NBSP = chr(194) . chr(160);
//        $this->http->Response['body'] = str_replace($NBSP, ' ', $this->http->Response['body']);

        if ($this->assignLang() === false) {
            return false;
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'FlightCancellation' . ucfirst($this->lang),
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

    private function assignLang(): bool
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
