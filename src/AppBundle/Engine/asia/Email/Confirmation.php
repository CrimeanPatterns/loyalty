<?php

namespace AwardWallet\Engine\asia\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "asia/it-29605754.eml";

    public $reFrom = "cathaypacific.com";
    public $reSubject = [
        "en" => "Booking Confirmation",
    ];
    public $reBody = 'Cathay';
    public $reBody2 = [
        "en"  => "Flights +  Hotel Package",
        "en2" => "Flights + Hotel Package",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "";

    protected $result = [];

    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            $inputResult = mb_strstr($left, $searchFinish, true);
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*Booking\s*Confirmation.*pdf');

        if (isset($pdf[0])) {
            $body = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf[0]), \PDF::MODE_SIMPLE));
        } else {
            return null;
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($body, $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $priceText = $this->findСutSection($body, 'Package Price Details', ['Payment Details', 'Your registered contact details']);
        $Flights = $this->parseEmail($body);

        if (!empty($Flights)) {
            $itineraries[] = $Flights;

            if (preg_match_all("#\s*(?:Mr|Miss|Mrs|Mstr|Ms)\s*.*\s*Marco Polo Club\s*([\d]{5,})#i", $body, $m)) {
                $itineraries[0]['AccountNumbers'] = $m[1];
            }

            if (preg_match("#Asia\s+Miles\s+earned\s+per\s+member.*?:\n(.+?)\n#is", $priceText, $m)) {
                $itineraries[0]['EarnedAwards'] = $m[1];
            }
        }

        $this->parseEmailHotel($this->findСutSection($body, 'Hotel details', 'Package Price Details'), $itineraries);

        $result = [
            'emailType'  => 'Confirmation',
            'parsedData' => ['Itineraries' => $itineraries],
        ];

        if (count($itineraries) == 1) {
            $this->parseTotal($priceText, $itineraries);
        } elseif (count($itineraries) > 1) {
            if (preg_match("#Total:\s(.+?)\n#is", $priceText, $m)) {
                $tot = $this->getTotalCurrency($m[1]);
                $result['parsedData']['TotalCharge']['Amount'] = $tot['Total'];
                $result['parsedData']['TotalCharge']['Currency'] = $tot['Currency'];
            }
        }

        return $result;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*Booking\s*Confirmation.*pdf');

        if (isset($pdf[0])) {
            $body = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf[0]), \PDF::MODE_SIMPLE));
        } else {
            return null;
        }

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
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

    protected function parseEmail($plainText)
    {
        if (empty($plainText)) {
            return false;
        }
        $this->result = [];
        $this->result['Kind'] = 'T';
        $this->recordLocator($this->findСutSection($plainText, 'Airline booking reference number', 'Total duration'));
        $this->parsePassengers($this->findСutSection($plainText, 'Passenger Details', 'Frequent Flyer Programme'));

        $this->parseSegments($this->findСutSection($plainText, 'Flight details', 'Passenger Details'));

        return $this->result;
    }

    protected function parseEmailHotel($plainTextAll, &$itineraries)
    {
        if (empty($plainTextAll)) {
            return false;
        }
        $segments = $this->split("#(\:[ ]*Booking reference number )#", $plainTextAll);

        foreach ($segments as $plainText) {
            $this->result = [];
            $this->result['Kind'] = 'R';
            $this->recordLocator($this->findСutSection($plainText, 'Booking reference number', 'Room'), 2);
            $this->parsePassengers($this->findСutSection($plainText, 'Hotel guest details', 'Package Terms and Conditions'), 2);

            if (preg_match("#Booking\s+reference\s+number\s+[A-Z\d]+\n\s*(.+?)\n\s*([\s\S]+?)\n\s*Room#", $plainText, $m)) {
                $this->result['HotelName'] = $m[1];
                $this->result['Address'] = preg_replace("#\s+#", '', $m[2]);
            }

            if (preg_match("#\n\s*Room\s+\d+\s*:\s*(\d+)\s+Adults?(?:,\s+(\d+)\s+Chil(?:dren)?|).*?\s+(\S+\s+\d+\s+\S+\s+\d+)\s+-\s+(\S+\s+\d+\s+\S+\s+\d+)#i", $plainText, $m)) {
                $this->result['Guests'] = $m[1];
                $this->result['Kids'] = $m[2];
                $this->result['CheckInDate'] = strtotime($m[3]);
                $this->result['CheckOutDate'] = strtotime($m[4]);
            }

            if (preg_match("#\n\s*Room\s+type\s*:\s*(.+)#i", $plainText, $m)) {
                $this->result['RoomType'] = $m[1];
            }

            if (preg_match("#\n\s*Special\s+Requests\s*:\s*(.+)#i", $plainText, $m)) {
                $this->result['RoomTypeDescription'] = $m[1];
            }

            $this->result['CancellationPolicy'] = str_replace("\n", " ", trim($this->findСutSection($plainText, 'Cancellation terms:', 'Amendment terms')));
            $itineraries[] = $this->result;
        }

        return $itineraries;
    }

    protected function recordLocator($recordLocator, $n = 1)
    {
        if (preg_match('#^\s*:?\s*([A-Z\d]{5,})#', $recordLocator, $m)) {
            if ($n == 2) {
                $this->result['ConfirmationNumber'] = $m[1];
            } else {
                $this->result['RecordLocator'] = $m[1];
            }
        }
    }

    protected function recordLocatorHotel($recordLocator)
    {
        if (preg_match('#^\s*:?\s*([A-Z\d]{5,})#', $recordLocator, $m)) {
            $this->result['RecordLocator'] = $m[1];
        }
    }

    protected function parseTotal($total, &$itineraries)
    {
        if (preg_match("#Base\s+price:\s(.+?)\n#is", $total, $m)) {
            $tot = $this->getTotalCurrency($m[1]);

            if ($itineraries[0]['Kind'] == "T") {
                $itineraries[0]['BaseFare'] = $tot['Total'];
            } else {
                $itineraries[0]['Cost'] = $tot['Total'];
            }
            $itineraries[0]['Currency'] = $tot['Currency'];
        }

        if (preg_match("#Taxes,\s+fees\s+and\s+charges:\s(.+?)\n#is", $total, $m)) {
            $tot = $this->getTotalCurrency($m[1]);

            if ($itineraries[0]['Kind'] == "T") {
                $itineraries[0]['Tax'] = $tot['Total'];
            } else {
                $itineraries[0]['Taxes'] = $tot['Total'];
            }
            $itineraries[0]['Currency'] = $tot['Currency'];
        }

        if (preg_match("#Total:\s(.+?)\n#is", $total, $m)) {
            $tot = $this->getTotalCurrency($m[1]);

            if ($itineraries[0]['Kind'] == "T") {
                $itineraries[0]['TotalCharge'] = $tot['Total'];
            } else {
                $itineraries[0]['Total'] = $tot['Total'];
            }
            $itineraries[0]['Currency'] = $tot['Currency'];
        }
    }

    protected function parsePassengers($plainText, $n = 1)
    {
        if (preg_match_all("#\s*\n\s*(?:Mr|Miss|Mrs|Mstr|Ms)\s+(.+)#ui", $plainText, $m)) {
            if ($n == 2) {
                $this->result['GuestNames'] = array_map(function ($s) {
                    return trim(str_replace("*", "", $s));
                }, $m[1]);
            } else {
                $this->result['Passengers'] = $m[1];
            }
        }
    }

    protected function parseSegments($plainText, $segmentsSplitter = '(?:Outbound:|Return:)')
    {
        foreach (preg_split('/' . $segmentsSplitter . '/', $plainText, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $value = trim($value);

            if (empty($value) !== true && strlen($value) > 100) {
                $this->result['TripSegments'][] = $this->iterationSegments(html_entity_decode($value));
            }
        }
    }

    private function iterationSegments($value)
    {
        $segment = [];
        $date = null;

        if (preg_match('#\S+\s+\d+\s+\S+\s+\d+\s+Total duration:\s+(.+)#ui', $value, $m)) {
            $segment['Duration'] = preg_replace("#\s+#", ' ', $m[1]);
        }

        if (preg_match_all('#\(([A-Z]{3})\)(.*Terminal.*)?#ui', $value, $m, PREG_SET_ORDER)) {
            $segment['DepCode'] = $m[0][1];
            $segment['ArrCode'] = $m[1][1];

            if (isset($m[0][2]) && !empty($m[0][2])) {
                $segment['DepartureTerminal'] = trim(preg_replace("#Terminal#i", '', $m[0][2]));
            }

            if (isset($m[1][2]) && !empty($m[1][2])) {
                $segment['ArrivalTerminal'] = trim(preg_replace("#Terminal#i", '', $m[1][2]));
            }
        }

        $timeDep = $this->re("#Depart\s+(\d+:\d+)#", $value);
        $timeArr = $this->re("#Arrive\s+(\d+:\d+)#", $value);

        if (preg_match_all("#^\S+\s+(\d+\s+\S+\s+\d+)\n#uim", $value, $m, PREG_SET_ORDER)) {
            $segment['DepDate'] = strtotime($m[0][1] . ' ' . $timeDep);

            if (isset($m[2][1]) && !empty($m[2][1])) {
                $segment['ArrDate'] = strtotime($m[2][1] . ' ' . $timeArr);
            } else {
                $segment['ArrDate'] = strtotime($m[1][1] . ' ' . $timeDep);
            }
        }

        if (preg_match("#^([A-Z\d]{2})\s*(\d+)$#uim", $value, $m)) {
            $segment['AirlineName'] = $m[1];
            $segment['FlightNumber'] = $m[2];
        }

        return $segment;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            } elseif (preg_match("#^\s*\d+\,\d{3}\s*$#", $m['t'])) {
                $m['t'] = str_replace(',', '', $m['t']);
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
