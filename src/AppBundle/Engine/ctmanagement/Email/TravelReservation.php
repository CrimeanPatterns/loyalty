<?php

namespace AwardWallet\Engine\ctmanagement\Email;

class TravelReservation extends \TAccountChecker
{
    public $mailFiles = "ctmanagement/it-292879508.eml";

    public $reFrom = ["@travelctm.com"];
    public $reBody = [
        'en' => [
            "THANK YOU FOR BOOKING WITH CORPORATE TRAVEL MGMT",
        ],
    ];
    public $reSubject = [
        '#Travel Reservation .+? for [A-Z\s]+#i',
    ];
    public $lang = '';

    public static $dict = [
        'en' => [
            'tripNum'  => 'CTM - East Reservation Code:',
            '_flights' => 'DEPARTURE:',
            '_hotels'  => 'CHECK IN:',
            '_cars'    => 'PICK UP:',
        ],
    ];

    private $tripNum;
    private $pax;
    private $reservDate;
    private $date;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $this->AssignLang();
        $its = $this->parseEmail();

        $class = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($class) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'ctm-logo')] | //text()[contains(.,'travelctm.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $flag = false;

        if (isset($headers['from'])) {
            foreach ($this->reFrom as $from) {
                if (stripos($headers['from'], $from) !== false) {
                    $flag = true;
                }
            }
        }

        if ($flag && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $re) {
            if (stripos($from, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseFlights()
    {
        $its = [];
        $nodes = $this->http->FindNodes("//text()[normalize-space(.)='Airline Reservation Code:']/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space(.)]", null, "#^\s*([A-Z\d]+\s*\([A-Z\d]{2}\))\s*$#");
        $rls = [];

        foreach ($nodes as $node) {
            $airline = $this->re("#[A-Z\d]+\s*\(([A-Z\d]{2})\)#", $node);
            $rl = $this->re("#([A-Z\d]+)\s*\([A-Z\d]{2}\)#", $node);
            $rls[$airline] = $rl;
        }

        $rule = $this->getRule($this->t('_flights'));
        $xpath = "//text()[{$rule}]";

        $flights = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($flights as $root) {
            $airline = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::*[self::a | self::text()[contains(.,'Flight Duration')]]/ancestor::td[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#^\s*([A-Z\d]{2})\s*\d+\s*$#");

            if (isset($rls[$airline])) {
                $airs[$rls[$airline]][] = $root;
            } else {
                $airs[$this->tripNum][] = $root;
            }
        }

        foreach ($airs as $rl => $roots) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['Passengers'] = $this->pax;
            $it['TripNumber'] = $this->tripNum;
            $it['ReservationDate'] = $this->reservDate;
            $tickets = [];
            $ffs = [];
            $pass = [];

            foreach ($roots as $root) {
                $seg = [];
                $dateDep = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::text()[normalize-space(.)][1]", $root)));
                $dateArr = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::text()[contains(.,'ARRIVAL')][1]/following::text()[normalize-space(.)][1]", $root)));

                $node = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::*[self::a | self::text()[contains(.,'Flight Duration')]]/ancestor::td[1]/descendant::text()[normalize-space(.)][2]", $root);

                if (preg_match("#^\s*([A-Z\d]{2})\s*(\d+)\s*$#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $seg['Duration'] = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Flight Duration')]/following::text()[normalize-space(.)][1]", $root);

                $node = implode("\n", $this->http->FindNodes("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Departing ')]/ancestor::table[1]/descendant::tr[1]/td[normalize-space(.)][1]//text()[normalize-space(.)]", $root));

                if (preg_match("#([A-Z]{3})\s+(.+)#", $node, $m)) {
                    $seg['DepName'] = $m[2];
                    $seg['DepCode'] = $m[1];
                }
                $node = implode("\n", $this->http->FindNodes("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Departing ')]/ancestor::table[1]/descendant::tr[1]/td[normalize-space(.)][2]//text()[normalize-space(.)]", $root));

                if (preg_match("#([A-Z]{3})\s+(.+)#", $node, $m)) {
                    $seg['ArrName'] = $m[2];
                    $seg['ArrCode'] = $m[1];
                }

                $node = implode("\n", $this->http->FindNodes("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Departing ')]/ancestor::td[1]//text()[normalize-space(.)]", $root));
                $date = strtotime($this->normalizeDate($this->re("#\n\s*\((.+)\)#", $node)));

                if ($date) {
                    $dateDep = $date;
                }
                $seg['DepDate'] = strtotime($this->normalizeDate($this->re("#\n(\d+:\d+\s*(?:[ap]m)?)\n#i", $node)), $dateDep);
                $seg['DepartureTerminal'] = $this->re("#Terminal:\s+(.+)#i", $node);

                $node = implode("\n", $this->http->FindNodes("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Departing ')]/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.))>2][1]//text()[normalize-space(.)]", $root));
                $date = strtotime($this->normalizeDate($this->re("#\n\s*\((.+)\)#", $node)));

                if ($date) {
                    $dateArr = $date;
                }
                $seg['ArrDate'] = strtotime($this->normalizeDate($this->re("#\n(\d+:\d+\s*(?:[ap]m)?)\n#i", $node)), $dateArr);
                $seg['ArrivalTerminal'] = $this->re("#Terminal:\s+(.+)#i", $node);

                $seg['Aircraft'] = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Aircraft')]/following::text()[normalize-space(.)][1]", $root);
                $seg['TraveledMiles'] = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Distance (in miles)')]", $root, true, '#:\s*(\d+)#');
                $node = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Stops')]", $root, true, "#:\s*(.+)#");

                if (preg_match('#(\d+)#', $node, $m)) {
                    $seg['Stops'] = $m[1];
                }
                $seg['Seats'] = implode(",", $this->http->FindNodes("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Passenger Name')]/ancestor::tr[1]/following-sibling::tr/td[2]", $root));
                $seg['Cabin'] = implode("|", array_unique($this->http->FindNodes("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Passenger Name')]/ancestor::tr[1]/following-sibling::tr/td[3]", $root)));
                $seg['Meal'] = implode("|", array_unique($this->http->FindNodes("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Passenger Name')]/ancestor::tr[1]/following-sibling::tr/td[7]", $root)));

                $pas = $this->http->FindNodes("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Passenger Name')]/ancestor::tr[1]/following-sibling::tr/td[1]", $root);
                $ticket = $this->http->FindNodes("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Passenger Name')]/ancestor::tr[1]/following-sibling::tr/td[6]", $root);
                $ff = $this->http->FindNodes("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Passenger Name')]/ancestor::tr[1]/following-sibling::tr/td[5]", $root);

                foreach ($pas as $item) {
                    $pass[] = $item;
                }

                foreach ($ticket as $item) {
                    $tickets[] = $item;
                }

                foreach ($ff as $item) {
                    $ffs[] = $item;
                }
                $it['TripSegments'][] = $seg;
            }
            $it['TicketNumbers'] = array_values(array_filter(array_unique($tickets)));
            $it['AccountNumbers'] = array_values(array_filter(array_unique($ffs)));

            if (count($pass) > 0) {
                $it['Passengers'] = array_values(array_filter(array_unique($pass)));
            }
            $its[] = $it;
        }

        return $its;
    }

    private function parseHotels()
    {
        $its = [];
        $rule = $this->getRule($this->t('_hotels'));
        $xpath = "//text()[$rule]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'R'];
            $it['TripNumber'] = $this->tripNum;
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::text()[normalize-space(.)][1]", $root)));
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::text()[contains(.,'CHECK OUT')][1]/following::text()[normalize-space(.)][1]", $root)));
            $it['GuestNames'] = $this->pax;

            $it['ConfirmationNumber'] = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Confirmation Number')]/following::text()[1]", $root);
            $it['Status'] = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Status')]/following::text()[1]", $root);

            $node = implode("\n", array_filter($this->http->FindNodes("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Confirmation Number')]/ancestor::td[1]//text()", $root)));
            $it['HotelName'] = str_replace("\n", " ", $this->re("#(.+?)\s+Phone#s", $node));
            $it['Address'] = str_replace("\n", " ", $this->re("#Fax.+?\n(.+?)\nConfirmation#s", $node));

            if (empty($it['Address'])) {
                $it['Address'] = str_replace("\n", " ", $this->re("#Phone.+?\n(.+?)\nConfirmation#s", $node));
            }
            $it['Phone'] = $this->re("#Phone[:\s]+(.+)?#", $node);
            $it['Fax'] = $this->re("#Fax[:\s]+(.+)?#", $node);

            $node = implode("\n", array_filter($this->http->FindNodes("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Room Details')]/ancestor::td[1]", $root)));
            $it['RoomTypeDescription'] = $this->re("#Room Details[:\s]+(.+)?#", $node);
            $it['Rooms'] = $this->re("#Rooms[:\s]+(\d+)#", $node);
            $it['Guests'] = $this->re("#Guests[:\s]+(\d+)#", $node);
            $it['Rate'] = $this->re("#Rate[:\s]+(.+)#", $node);
            $it['CancellationPolicy'] = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Cancellation Information')]/following::text()[1]", $root);

            $its[] = $it;
        }

        return $its;
    }

    private function parseCars()
    {
        $its = [];
        $rule = $this->getRule($this->t('_cars'));
        $xpath = "//text()[$rule]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'L'];
            $it['TripNumber'] = $this->tripNum;
            $it['PickupDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::text()[normalize-space(.)][1]", $root)));
            $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::text()[contains(.,'DROP OFF')][1]/following::text()[normalize-space(.)][1]", $root)));

            if (isset($this->pax[0])) {
                $it['RenterName'] = $this->pax[0];
            }

            $it['Number'] = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Confirmation Number')]/following::text()[1]", $root);
            $it['Status'] = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Status')]/following::text()[1]", $root);

            $it['RentalCompany'] = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Confirmation Number')]/ancestor::td[1]/descendant::div[1]", $root);
            $it['CarType'] = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Car Type')]/following::text()[normalize-space(.)][1]", $root);
            $it['PickupDatetime'] = strtotime($this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Pick Up Time')]/following::text()[normalize-space(.)][1]", $root), $it['PickupDatetime']);
            $it['DropoffDatetime'] = strtotime($this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Drop Off Time')]/following::text()[normalize-space(.)][1]", $root), $it['DropoffDatetime']);
            $it['PickupLocation'] = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Pick Up Time')]/ancestor::table[1]/descendant::tr[1]/td[1]", $root);
            $it['DropoffLocation'] = $this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Pick Up Time')]/ancestor::table[1]/descendant::tr[1]/td[1]/following-sibling::td[string-length(normalize-space(.))>2][1]", $root);
            $it['RenterName'] = $this->http->FindSingleNode(".//descendant::td[contains(.,'Driver')]/following-sibling::td[1]", $root);

            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./ancestor::tr[2]/following-sibling::tr[1]/descendant::text()[contains(.,'Approx. Total Price')]/following::text()[normalize-space(.)][1]", $root));

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }

            $its[] = $it;
        }

        return $its;
    }

    private function parseEmail()
    {
        $this->tripNum = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$this->t('tripNum')}')]/ancestor::td[1]/following-sibling::td[1]");
        $this->pax = $this->http->FindNodes("//text()[normalize-space(.)='Prepared For:']/ancestor::*[1]/following-sibling::*[contains(.,'/')]");
        $this->reservDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'THE TOTAL TICKET COST OF THIS ITINERARY')]/preceding::text()[normalize-space(.)][1]")));

        if ($this->reservDate) {
            $this->date = $this->reservDate;
        }

        $its = [];
        //###FLIGHT###########
        $rule = $this->getRule($this->t('_flights'));

        if ($this->http->XPath->query("//text()[{$rule}]")->length > 0) {
            $flights = $this->parseFlights();

            foreach ($flights as $it) {
                $its[] = $it;
            }
        }

        //###HOTEL###########
        $rule = $this->getRule($this->t('_hotels'));

        if ($this->http->XPath->query("//text()[{$rule}]")->length > 0) {
            $hotels = $this->parseHotels();

            foreach ($hotels as $it) {
                $its[] = $it;
            }
        }

        //###CAR###########
        $rule = $this->getRule($this->t('_cars'));

        if ($this->http->XPath->query("//text()[{$rule}]")->length > 0) {
            $cars = $this->parseCars();

            foreach ($cars as $it) {
                $its[] = $it;
            }
        }

        return $its;
    }

    private function getRule($fields, $type = 0)
    {
        $w = (array) $fields;
        //for XPath return: "normalize-space(.)='value1' or .. or normalize-space(.)='valueN'"
        $str = implode(" or ", array_map(function ($s) {
            return "normalize-space(.)='{$s}'";
        }, $w));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $re) {
                    if ($this->http->XPath->query('//*[contains(normalize-space(.),"' . $re . '")]')->length > 0) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            // TUESDAY 06 JUN 17 *
            '#^\s*\w+\s+(\d+)\s+(\w{3,})\s+(\d{2})\s*\*{0,3}\s*$#',
            //MONDAY 26 JUN
            '#^\s*\w+\s+(\d+)\s+(\w{3,})\s*$#',
            //Mon, Jun 26
            '#^\s*\w{3},+\s+(\w{3,})\s+(\d+)\s*$#',
        ];
        $out = [
            '$1 $2 20' . '$3',
            '$1 $2 ' . $year,
            '$2 $1 ' . $year,
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

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
