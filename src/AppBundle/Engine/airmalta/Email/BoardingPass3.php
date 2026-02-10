<?php

namespace AwardWallet\Engine\airmalta\Email;

class BoardingPass3 extends \TAccountChecker
{
    public $mailFiles = "airmalta/it-5070018.eml, airmalta/it-7351102.eml";

    public $reBody = [
        'en' => ['Passenger Name', 'Departure Time'],
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }
        $NBSP = chr(194) . chr(160);
        $body = str_replace($NBSP, ' ', html_entity_decode($body));
        $this->http->SetBody($body);
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "BoardingPass3",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(.,'The airline of the Maltese Islands')]")->length > 0
        || $this->http->XPath->query("//img[contains(@src,'mobile/barcode')]")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'Your Air Malta Boarding Pass') !== false
        || isset($headers['from']) && stripos($headers['from'], 'Air Malta') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "airmalta.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $root = $this->http->XPath->query("//text()[normalize-space(.)='BOARDING PASS']/ancestor::*[contains(., 'From')][1]")->item(0);
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(.,'Reservation Number')]/following::b[1]");
        $it['Passengers'][] = $this->http->FindSingleNode("//text()[contains(.,'Passenger Name')]/following::b[1]");
        $seg = [];

        $node = $this->http->FindSingleNode(".//text()[normalize-space(.)='From' or normalize-space(.)='From:']/following::b[1]", $root);

        if (preg_match("#(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
            $seg['DepName'] = $m[1];
            $seg['DepCode'] = $m[2];
        }
        $node = $this->http->FindSingleNode(".//text()[normalize-space(.)='To' or normalize-space(.)='To:']/following::b[1]", $root);

        if (preg_match("#(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
            $seg['ArrName'] = $m[1];
            $seg['ArrCode'] = $m[2];
        }
        $date = $this->http->FindSingleNode(".//text()[contains(.,'Date')]/following::b[1]", $root);
        $date = str_replace(",", "", $date);
        $time = $this->http->FindSingleNode("//text()[contains(.,'Departure Time')]/following::b[1]", null, true, "#\d+:\d+#");
        $seg['DepDate'] = strtotime($date . ' ' . $time);
        $time = $this->http->FindSingleNode("//text()[contains(.,'Arrival Time')]/following::b[1]", null, true, "#\d+:\d+#");
        $seg['ArrDate'] = strtotime($date . ' ' . $time);
        $node = $this->http->FindSingleNode("//text()[contains(.,'Airline and Flight ')]/following::b[1]");

        if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];
        }
        $seg['Seats'] = $this->http->FindSingleNode("//text()[contains(.,'Seat Assignment')]/following::b[1]");

        $seg = array_filter($seg);
        $it['TripSegments'][] = $seg;

        $it = array_filter($it);

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
