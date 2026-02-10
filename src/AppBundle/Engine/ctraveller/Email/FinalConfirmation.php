<?php

namespace AwardWallet\Engine\ctraveller\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class FinalConfirmation extends \TAccountChecker
{
    public $mailFiles = "ctraveller/it-12493914.eml";

    public $lang = "en";
    private $reFrom = "@ca.cievents.com";
    private $reSubject = [
        "en" => "Final Confirmation",
        "Registration Confirmed",
    ];
    private $reBody = 'www.cvent.com';
    private $reBody2 = [
        "en" => "Air Reservation Details",
        "en2"=> "Hotel Reservation Details",
    ];

    private static $dictionary = [
        "en" => [],
    ];
    private $date = null;

    public function parseHtml()
    {
        $itineraries = [];

        $xpath = "//text()[" . $this->eq("Flight Number") . "]/ancestor::tr[2]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            if (!$rl = $this->http->FindSingleNode(".//text()[" . $this->eq("Confirmation Number") . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#^\w{2} ([A-Z\d]+)$#")) {
                $this->logger->info("RL not matched");

                return;
            }
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];
            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $this->nextText("Confirmation Number:");

            // Passengers
            $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq("Air Reservation Details") . "]/ancestor::tr[2]/following-sibling::tr[normalize-space(.) and not(.//tr)]");

            // TicketNumbers
            // AccountNumbers
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

            foreach ($roots as $root) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Flight Number") . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#^\w{2} (\d+)$#");

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Departure") . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][2]", $root);

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Departure") . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]", $root);

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq("Departure") . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][2]", $root));

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Arrival") . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][2]", $root);

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Arrival") . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]", $root);

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq("Arrival") . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][2]", $root));

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Flight Number") . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#^(\w{2}) \d+$#");

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                // PendingUpgradeTo
                // Seats
                // Duration
                // Meal
                // Smoking
                // Stops

                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }

        //################
        //##   HOTEL   ###
        //################
        $xpath = "//text()[{$this->eq("Check-In Date")}]/ancestor::table[ descendant::tr[{$this->starts("Room Type -")}] ][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];
            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Confirmation Number") . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][2]", $root);
            if (empty($it['ConfirmationNumber'])) {
                $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
            }

            // TripNumber
            $it['TripNumber'] = $this->nextText("Confirmation Number:");

            // HotelName
            $it['HotelName'] = $this->http->FindSingleNode("descendant::tr[not(.//tr) and normalize-space()][1]/*[normalize-space()][1]", $root);

            $roots2 = $this->http->XPath->query("following::text()[normalize-space()][1][{$this->eq("Check in/out time")}]/ancestor::span[not(ancestor::span)]", $root);

            // CheckInDate
            $it['CheckInDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq("Check-In Date") . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][2]", $root));

            if ($roots2->length > 0 && !empty($it['CheckInDate'])) {
                $it['CheckInDate'] = $this->normalizeDate($this->http->FindSingleNode(".//strong[{$this->contains("check in time is")}]", $roots2->item(0), true, "#check in time is (.+)#"), $it['CheckInDate']);
            }

            // CheckOutDate
            $it['CheckOutDate'] = $this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->eq("Check-Out Date") . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][2]", $root));

            if ($roots2->length > 0 && !empty($it['CheckOutDate'])) {
                $it['CheckOutDate'] = $this->normalizeDate($this->http->FindSingleNode(".//strong[{$this->contains("check out is at")}]", $roots2->item(0), true, "#check out is at (.+)#"), $it['CheckOutDate']);
            }

            // Address
            $it['Address'] = implode("\n", $this->http->FindNodes("descendant::tr[not(.//tr) and normalize-space()][2]/*[normalize-space()][1]/descendant::text()[normalize-space()]", $root));
            $it['Address'] = preg_replace("/\n.{0,7}\\/\\/www\.[\w\.\\/]+$/", '', $it['Address']);
            if (preg_match('/^([\s\S]+?)\s*\n\s*([\d\-\+\(\) \.]{5,})\s*$/', $it['Address'], $m)
                && strlen(preg_replace("/\D+/", '', $m[2])) > 5
            ) {
                $it['Address'] = $m[1];
                $phone = $m[2];
            }
            $it['Address'] = preg_replace("/\s*\n\s*/", ' ', $it['Address']);

            // Phone
            if (empty($phone)) {
                $phone = $this->http->FindSingleNode("descendant::tr[not(.//tr) and normalize-space()][3]/descendant::text()[normalize-space()][1]",
                    $root, true, '/^[+(\d][-. \d)(]{5,}[\d)]$/');
            }

            if ($phone) {
                $it['Phone'] = $phone;
            }

            // GuestNames
            $guestName = $this->http->FindSingleNode("preceding::tr[not(.//tr) and normalize-space()][1][ preceding::text()[normalize-space()][1][{$this->eq("Hotel Reservation Details")}] ]", $root, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

            if ($guestName) {
                $it['GuestNames'] = [$guestName];
            }

            // Rooms
            $it['Rooms'] = $this->http->FindSingleNode("descendant::text()[{$this->eq("Quantity")}]/ancestor::td[1]/descendant::text()[normalize-space()][2]", $root, true, '/^\d{1,3}$/');

            // RoomType
            $it['RoomType'] = $this->http->FindSingleNode("descendant::text()[{$this->starts("Room Type")}]", $root, true, "/Room Type\s*-\s*(.+)/");

            $totalPrice = $this->http->FindSingleNode("descendant::text()[{$this->starts("Room Type")}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root);

            if (preg_match('/^(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)) {
                // GBP 335.00
                $it['Currency'] = $m['currency'];
                $it['Total'] = $this->normalizeAmount($m['amount']);
            }

            $itineraries[] = $it;
        }

        return $itineraries;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
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
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $this->logger->info('Relative date: ' . date('r', $this->date));

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseHtml(),
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

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        $in = [
            "#^(\d+)-([^\s\d]+)-(\d{4}) (\d+:\d+ [AP]M)$#", //13-Apr-2018 8:24 PM
            "#^(\d+:\d+[ap]m)\.$#", //11:00am.
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d', strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
