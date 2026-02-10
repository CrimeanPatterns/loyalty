<?php

namespace AwardWallet\Engine\amextravel\Email;

use AwardWallet\Engine\MonthTranslate;

class Invoice extends \TAccountChecker
{
    public $mailFiles = "amextravel/it-1617405.eml, amextravel/it-1618435.eml, amextravel/it-6209106.eml, amextravel/it-6304693.eml, amextravel/it-6331582.eml, amextravel/it-6374054.eml, amextravel/it-6639808.eml";

    public $reFrom = [
        'americanexpress.com',
        'American Express Global Business Travel',
    ];
    public $reSubject = [
        'Travel invoice for',
        'PLEASE REVIEW invoice',
    ];
    public $reBody = 'American Express Global Business Travel';
    public $reBody2 = [
        'en' => ['SEE ATTACHMENTS FOR YOUR INVOICE', 'SEE ATTACHMENT FOR YOUR INVOICE'],
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $check1 = false;

        foreach ($this->reFrom as $reFrom) {
            if (stripos($headers['from'], $reFrom) !== false) {
                $check1 = true;
            }
        }

        if ($check1 === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($body, $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->setBody($parser->getPlainBody());

        $result = [];

        foreach ($this->reBody2 as $reBody2) {
            foreach ($reBody2 as $lang => $re) {
                if (strpos($this->http->Response['body'], $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parsePlain($result);

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

    private function parsePlain(&$result)
    {
        $text = str_replace(["\n> ", "\r"], ["\n", ""], $this->http->Response['body']);

        $totalPNR = $this->re('/[Aa]mex [Rr]ecord [Ll]ocator:\s+([A-Z\d]{5,})/', $text);

        if (empty($totalPNR)) {
            $totalPNR = $this->re('/[Rr]ecord [Ll]ocator:\s+([A-Z\d]{5,})/', $text);
        }

        if (empty($totalPNR) && !preg_match('/Invoice\s+detail\s*:/i', $text)) {
            $totalPNR = CONFNO_UNKNOWN;
        }

        $traveler = $this->re('/Traveler:\s+(.+)/', $text);

        $pnrList = [];
        $pnrListStartTexts = ["Airline Confirmation #'s:", 'Airline Confirmation Numbers:'];

        foreach ($pnrListStartTexts as $pnrListStartText) {
            $pnrListStart = stripos($text, $pnrListStartText);

            if ($pnrListStart !== false) {
                $pnrListText = substr($text, strlen($pnrListStartText) + $pnrListStart);

                if (preg_match_all('/^\s*(.+)\s*$/m', $pnrListText, $pnrRowMatches, PREG_SET_ORDER)) {
                    foreach ($pnrRowMatches as $pnrRow) {
                        if (preg_match('/^(.+)\s+([A-Z\d]{5,})$/', $pnrRow[1], $matches)) {
                            $pnrList[trim($matches[1])] = $matches[2];
                        } else {
                            break;
                        }
                    }
                }

                break;
            }
        }

        $itineraries = [];
        $allsegments = $this->split('/(' . $this->t('Flight Information:') . '|' . $this->t('Hotel Information:') . '|' . $this->t('Car Information:') . ')/', $text);

        //##################
        //##   FLIGHTS   ###
        //##################

        $airs = [];

        foreach ($allsegments as $stext) {
            if (strpos($stext, $this->t('Flight Information:')) === false) {
                continue;
            }

            if (!$airline = trim($this->re('/^[>\s]*Reserved:\s+([^\n]{2,})\s+\d+$/m', $stext))) {
                return;
            }

            if (isset($pnrList[$airline])) {
                $airs[$pnrList[$airline]][] = $stext;
            } else {
                $airs[$totalPNR][] = $stext;
            }
        }

        foreach ($airs as $rl => $segments) {
            $it = [];
            $it['Kind'] = 'T';

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // Passengers
            if ($traveler) {
                $it['Passengers'] = [$traveler];
            }

            foreach ($segments as $stext) {
                $itsegment = [];

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#" . $this->t("Reserved:") . "\s+(.*?)\s+\d+\n#", $stext);

                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#" . $this->t("Reserved:") . "\s+.*?\s+(\d+)\n#", $stext);

                // Cabin
                $itsegment['Cabin'] = $this->re("#" . $this->t("Class:") . "\s+(.+)#", $stext);

                // Seats
                $itsegment['Seats'] = $this->re("#" . $this->t("Seat") . "s?:\s+(.+)#", $stext);

                // Duration
                $itsegment['Duration'] = $this->re("#" . $this->t("Estimated time:") . "\s+(.+)#", $stext);

                // DepName
                $itsegment['DepName'] = $this->re("#" . $this->t("Departs:") . "\s+(.*?)\s+-\s+[A-Z]{3}\n#", $stext);

                // DepCode
                $itsegment['DepCode'] = $this->re("#" . $this->t("Departs:") . "\s+.*?\s+-\s+([A-Z]{3})\n#", $stext);

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->re("#" . $this->t("Departs:") . ".*?\n[>\s]*Date:\s+(.+)#", $stext)));

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->re("#" . $this->t("Dept.Terminal:") . "\s+(.+)#", $stext);

                // ArrName
                $itsegment['ArrName'] = $this->re("#" . $this->t("Arrives:") . "\s+(.*?)\s+-\s+[A-Z]{3}\n#", $stext);

                // ArrCode
                $itsegment['ArrCode'] = $this->re("#" . $this->t("Arrives:") . "\s+.*?\s+-\s+([A-Z]{3})\n#", $stext);

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->re("#" . $this->t("Arrives:") . ".*?\n[>\s]*Date:\s+(.+)#", $stext)));

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->re("#" . $this->t("Arr.Terminal:") . "\s+(.+)#", $stext);

                // Aircraft
                $itsegment['Aircraft'] = $this->re("#" . $this->t("Equipment:") . "\s+(.+)#", $stext);

                // Meal
                $itsegment['Meal'] = $this->re("#" . $this->t("Meal:") . "\s+(.+)#", $stext);

                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }

        //#################
        //##   HOTELS   ###
        //#################

        foreach ($allsegments as $stext) {
            if (strpos($stext, $this->t('Hotel Information:')) === false) {
                continue;
            }

            $it = [];
            $it['Kind'] = 'R';

            // Hotel Name
            $it['HotelName'] = $this->re("#Reserved:\s+(.+)#", $stext);

            // Address
            $it['Address'] = trim(implode(', ', array_filter(array_map('trim', explode("\n", $this->re("#Address:\s+(.*?)Phone:#ms", $stext))))), ', ');

            // Phone
            $it['Phone'] = $this->re("#Phone:\s+(.+)#", $stext);

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#Check-In:\s+(.+)#", $stext)));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#Check-Out:\s+(.+)#", $stext)));

            // RoomType
            $it['RoomType'] = $this->re("#Room Type:\s+(.+)#", $stext);

            // Rate
            $it['Rate'] = $this->re('/Price:.*?  (.+)/', $stext);

            if (empty($it['Rate'])) {
                $it['Rate'] = $this->re('/Hotel\s+Rate:\s+(.*)/', $stext);
            }

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->re("#Confirmation:\s+(.+)#", $stext);

            // GuestNames
            $it['GuestNames'] = [$traveler];

            if ($this->http->FindSingleNode("//text()[" . $this->contains($this->t("changed")) . "]")) {
                $it['Status'] = 'changed';
            } elseif ($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation Number:")) . "]")) {
                $it['Status'] = 'confirmed';
            } elseif ($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Cancellation Number:")) . "]")) {
                $it['Status'] = 'cancelled';
            }

            // Cancelled
            if ($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Cancellation Number:")) . "]")) {
                $it['Cancelled'] = true;
            }

            $itineraries[] = $it;
        }

        //##############
        //##   CAR   ###
        //##############

        foreach ($allsegments as $stext) {
            if (strpos($stext, $this->t('Car Information:')) === false) {
                continue;
            }

            $it = [];
            $it['Kind'] = 'L';

            $reserved = $this->re('/Reserved:\s+(.+)/', $stext);

            if (preg_match('/([A-Z\s]+)\s+(.*)/', $reserved, $matches)) {
                $it['RentalCompany'] = $matches[1];
                $it['PickupLocation'] = $matches[2];
                $it['DropoffLocation'] = $matches[2];
            }

            $it['PickupDatetime'] = strtotime($this->re('/Reserved\s+Dates:\s+([\d\/]+)/', $stext));
            $it['DropoffDatetime'] = strtotime($this->re('/Reserved\s+Dates:\s+.*?Through\s+([\d\/]+)/', $stext));

            if (preg_match('/Car\s+Size:\s+(.*?)\s+Car\s+Category:\s+(.*)/i', $stext, $matches)) {
                $it['CarType'] = $matches[1] . ' ' . $matches[2];
            }

            $it['Number'] = $this->re('/Confirmation:\s+([-\w]+)/', $stext);

            $it['RenterName'] = $traveler;

            $itineraries[] = $it;
        }

        $a = explode('\\', __CLASS__);
        $result = [
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        $payment = $this->re("#Ticket Amount:\s+(.+)#", $text);

        if (preg_match('/^\s*([,.\d\s]+)\s*([A-Z]{3})?/', $payment, $matches)) {
            $result['parsedData']['TotalCharge']['Amount'] = $this->normalizePrice($matches[1]);

            if (!empty($matches[2])) {
                $result['parsedData']['TotalCharge']['Currency'] = $matches[2];
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizePrice($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function normalizeDate($str)
    {
        $in = [
            '/^([^\d\s]+)\s+(\d{1,2}),\s*(\d{4})\s+Time:\s+(\d{1,2}:\d{2}\s*[AP]M)$/', // May 20,2017       Time:  9:48 AM
            "#^([^\d\s]+)\s+(\d+),\s*(\d{4})\s*,\s+[^\d\s]+\s+Time:\s+(\d+:\d+\s*[AP]M)$#", // Apr 23, 2017 , Sunday  Time:  9:50 AM
            "#^([^\d\s]+)\s+(\d+),\s*(\d{4})$#", // Apr 23,2017
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3, $4",
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
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
        } elseif (count($r) === 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
