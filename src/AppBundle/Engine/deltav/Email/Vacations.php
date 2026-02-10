<?php

namespace AwardWallet\Engine\deltav\Email;

class Vacations extends \TAccountChecker
{
    public $mailFiles = "deltav/it-7735751.eml, deltav/it-7736275.eml, deltav/it-7805591.eml, deltav/it-8633416.eml, deltav/it-22409574.eml, deltav/it-34794333.eml";

    private $subjects = [
        'Itinerary', 'Reservation',
    ];

    private $bookingNumber = null;

    private $passengers = [];

    private $accountNumbers = [];
    private $its = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->bookingNumber = $this->http->FindSingleNode("//tr[ not(.//tr) and descendant::text()[normalize-space()][1][{$this->eq('Travelers')}] ]/preceding::text()[{$this->starts(['Booking Number:', 'CONFIRMATION NUMBER'])}]", null, true, "/{$this->opt(['Booking Number:', 'CONFIRMATION NUMBER'])}\s*([A-Z\d-]{5,})\s*$/");

        if (!$this->bookingNumber) {
            $this->bookingNumber = $this->http->FindSingleNode("//tr[ not(.//tr) and descendant::text()[normalize-space()][1][{$this->eq('Travelers')}] ]/preceding::text()[{$this->eq(['Booking Number:', 'CONFIRMATION NUMBER'])}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d-]{5,})\s*$/");
        }

        $passengers = $this->http->FindNodes("//tr[contains(., 'Travelers') and not(.//tr)]/following-sibling::tr[starts-with(normalize-space(.), '#')]/td[1]");
        $passengers = $this->http->FindNodes("//tr[contains(., 'Travelers') and not(.//tr)]/following-sibling::tr//text()[starts-with(normalize-space(.), '#')]");
        array_walk($passengers, function ($val) {
            if (preg_match('/\#(\d+)\s+(.+)/i', $val, $m)) {
                $this->passengers[$m[1]] = $m[2];
            }
        });

        $this->accountNumbers = array_values(array_filter($this->http->FindNodes("//tr[contains(., 'Travelers') and not(.//tr)]/following-sibling::tr[starts-with(normalize-space(.), '#')]/td[last()]", null, "#^\s*[^\\#\s]+#")));

        $this->parseAir();

        $carSections = $this->http->XPath->query("//tr[ not(.//tr) and descendant::text()[normalize-space()='Car Description'] ]");

        foreach ($carSections as $key => $carSection) {
            $carConfirmation = $this->http->FindSingleNode("preceding-sibling::*[normalize-space()][1]/descendant::td[not(.//td) and {$this->contains('Car Confirmation')}]", $carSection, true, '/Car Confirmation[#:\s]+([A-Z\d-]+)\s*$/');
            $followRows = $this->http->XPath->query("following-sibling::tr[ *[normalize-space()][4] ]", $carSection);

            foreach ($followRows as $row) {
                if (!empty($carSections[$key + 1]) && $carSections[$key + 1] === $row) {
                    break;
                }
                $this->parseCar($row, $carConfirmation);
            }
        }

        $hotelSections = $this->http->XPath->query("//tr[ not(.//tr) and descendant::text()[normalize-space()='Check Out'] ]");

        foreach ($hotelSections as $key => $hotelSection) {
            $hotelConfirmation = $this->http->FindSingleNode("preceding-sibling::*[normalize-space()][1]/descendant::td[not(.//td) and {$this->contains('Hotel Confirmation')}]", $hotelSection, true, '/Hotel Confirmation\s*\#\s*:\s*(\d+)$/');
            $followRows = $this->http->XPath->query("following-sibling::tr[ *[normalize-space()][4] ]", $hotelSection);

            foreach ($followRows as $row) {
                if (!empty($hotelSections[$key + 1]) && $hotelSections[$key + 1] === $row) {
                    break;
                }
                $this->parseHotel($row, $hotelConfirmation);
            }
        }

        $result = [
            'emailType'  => 'VacationsEn',
            'parsedData' => [
                "Itineraries" => $this->its,
            ],
        ];

        $payment = $this->http->FindSingleNode("//td[contains(., 'Payment Total')]/following-sibling::td[1]");

        if (preg_match('/(\D)\s*(\d[,.\'\d]*)/', $payment, $m)) {
            // $4741.00
            $result['parsedData']['TotalCharge']['Currency'] = str_replace('$', 'USD', $m[1]);
            $result['parsedData']['TotalCharge']['Amount'] = $this->correctSum($m[2]);
        }

        return $result;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Delta Vacations') === false) {
            return false;
        }

        foreach ($this->subjects as $phrase) {
            if (strpos($headers['subject'], $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Thank you for choosing Delta Vacations') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]delta(?:vacations)?\.com/i', $from) > 0;
    }

    private function parseAir()
    {
        $rls = $this->http->XPath->query("//*[contains(text(), 'Flight Confirmation #:')]");

        foreach ($rls as $rl) {
            $travelerNumbers = [];
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T'];

            if ($this->bookingNumber) {
                $it['TripNumber'] = $this->bookingNumber;
            }

            if (preg_match('/Flight Confirmation\s+#:\s+([A-Z\d]{6,7})/', $rl->nodeValue, $m)) {
                $it['RecordLocator'] = $m[1];
            }

            if (!empty($this->http->FindSingleNode("(//text()[contains(., 'Your reservation has been cancelled.')])[1]"))) {
                $it['Status'] = 'cancelled';
                $it['Cancelled'] = true;
            }
            $roots = $this->http->XPath->query("ancestor::table[1]/descendant::tr[contains(., 'departs')]", $rl);

            foreach ($roots as $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                if (!preg_match("/\w{3} (\d{1,2}-\w{3}-\d{1,4})/", $this->getNode($root), $date)) {
                    continue;
                }

                if (preg_match('/([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/', $this->getNode($root, 2), $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }

                $text = $this->getNode($root, 3);

                if (preg_match('/(.+?)\s*\(([A-Z]{3})\)\s+to\s*(.+?)\s*\(([A-Z]{3})\)/', $text, $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepCode'] = $m[2];
                    $seg['ArrName'] = $m[3];
                    $seg['ArrCode'] = $m[4];
                } elseif (preg_match('/(.+?)\s*to\s*(.+?)\s*\(([A-Z]{3})\)/', $text, $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    $seg['ArrName'] = $m[2];
                    $seg['ArrCode'] = $m[3];
                } elseif (preg_match('/(.+?)\s*\(([A-Z]{3})\)\s*to\s*(.+)/', $text, $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepCode'] = $m[2];
                    $seg['ArrName'] = $m[3];
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }

                if (preg_match("/departs (\d+:\d+ (?:[AP]\.M\.|Noon))(?:\s+(\+ ?\d day))?\s*arrives (\d+:\d+ (?:[AP]\.M\.|Noon))(?:\s+(\+ ?\d day))?/i", $this->getNode($root, 4), $m)) {
                    $depDate = strtotime($date[1] . " " . str_replace('.', '', $m[1]));
                    $arrDate = strtotime($date[1] . " " . str_replace('.', '', $m[3]));

                    if (!empty($m[2])) {
                        $depDate = strtotime($m[2], $depDate);
                    }

                    if (!empty($m[4])) {
                        $arrDate = strtotime($m[4], $arrDate);
                    }
                    $seg['DepDate'] = $depDate;
                    $seg['ArrDate'] = $arrDate;
                }

                $travelers = $this->getNode($root, 'last()');

                if (stripos($travelers, ',') !== false) {
                    $nums = explode(',', $travelers);
                    array_walk($nums, function ($val) use (&$travelerNumbers) {
                        $travelerNumbers[] = $val;
                    });
                } elseif (stripos($travelers, 'all') !== false) {
                    $it['Passengers'] = $this->passengers;
                } else {
                    array_push($travelerNumbers, $travelers);
                }

                $operator = $this->http->FindSingleNode("following-sibling::*[normalize-space()][1]", $root, true, "/{$this->opt('OPERATED BY')}\s+(.+?)\s*(?:--|$)/");

                if ($operator) {
                    $seg['Operator'] = $operator;
                }

                $it['TripSegments'][] = $seg;
            }

            foreach (array_unique($travelerNumbers) as $i => $travelerNumber) {
                if (isset($this->passengers[$travelerNumber])) {
                    $it['Passengers'][] = $this->passengers[$travelerNumber];
                }
            }

            if (count($this->accountNumbers) > 0) {
                $it['AccountNumbers'] = $this->accountNumbers;
            }

            $this->its[] = $it;
        }
    }

    private function parseHotel(\DOMNode $root, $confNumber)
    {
        /** @var \AwardWallet\ItineraryArrays\Hotel $it */
        $it = ['Kind' => 'R'];

        if ($this->bookingNumber) {
            $it['TripNumber'] = $this->bookingNumber;
        }

        $it['ConfirmationNumber'] = $confNumber ?? CONFNO_UNKNOWN;

        $name = $this->getNode($root);

        if (preg_match('/(.+?) - (.+?),\s*([^,]+)/i', $name, $m)) {
            // Rome, Italy (FCO) - Romanico Palace, Classic
            $it['Address'] = $m[1];
            $it['HotelName'] = $m[2];
            $it['RoomType'] = $m[3];
        } elseif (preg_match('/(.+?),\s*([^,]+)/i', $name, $m)) {
            // MercureChampsElysees, STANDARD
            $it['HotelName'] = $it['Address'] = $m[1];
            $it['RoomType'] = $m[2];
        }

        $checkinDate = $this->getNode($root, 2);
        $it['CheckInDate'] = strtotime($checkinDate);

        $checkoutDate = $this->getNode($root, 3);
        $it['CheckOutDate'] = strtotime($checkoutDate);

        $pNum = $this->getNode($root, 'last()');
        $travelerNumbers = [];

        if (stripos($pNum, ',') !== false) {
            $nums = explode(',', $pNum);
            array_walk($nums, function ($val) use (&$travelerNumbers) {
                $travelerNumbers[] = $val;
            });
        } elseif (stripos($pNum, 'all') !== false) {
            $it['GuestNames'] = $this->passengers;
        } else {
            array_push($travelerNumbers, $pNum);
        }

        foreach (array_unique($travelerNumbers) as $i => $travelerNumber) {
            if (isset($this->passengers[$travelerNumber])) {
                $it['GuestNames'][] = $this->passengers[$travelerNumber];
            }
        }

        $this->its[] = $it;
    }

    private function parseCar(\DOMNode $root, $confNumber)
    {
        /** @var \AwardWallet\ItineraryArrays\CarRental $it */
        $it = ['Kind' => 'L'];

        if ($this->bookingNumber) {
            $it['TripNumber'] = $this->bookingNumber;
        }

        $it['Number'] = $confNumber ?? CONFNO_UNKNOWN;

        $description = $this->getNode($root);

        if (preg_match('/(Full-size\s+.+?|mid-size\s+.+?|Economy\s+.+?)\s+\((.+)\)/i', $description, $m)) {
            $it['CarType'] = $m[1];
            $it['CarModel'] = $m[2];
        }

        $pickupDate = $this->getNode($root, 2);
        $dropoffDate = $this->getNode($root, 3);
        $re = '/(\d+\-\D+\-\d+,\s+\d+:\d+\s*[apm\.]*)(?:noon)?\s*at\s+(.+)/i';
        $arr = [
            'Pickup'  => $pickupDate,
            'Dropoff' => $dropoffDate,
        ];
        array_walk($arr, function ($val, $key) use (&$it, $re) {
            if (preg_match($re, $val, $m)) {
                $it[$key . 'Datetime'] = strtotime($m[1]);
                $it[$key . 'Location'] = $m[2];
            }
        });

        $pNum = $this->getNode($root, 'last()');

        if (isset($this->passengers[$pNum])) {
            $it['RenterName'] = $this->passengers[$pNum];
        }

        $this->its[] = $it;
    }

    private function getNode(\DOMNode $root, $td = 1)
    {
        return $this->http->FindSingleNode('descendant::td[' . $td . ']', $root);
    }

    private function correctSum($str)
    {
        $str = preg_replace('/\s+/', '', $str);			// 11 507.00	->	11507.00
        $str = preg_replace('/[,.](\d{3})/', '$1', $str);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $str = preg_replace('/^(.+),$/', '$1', $str);	// 18800,		->	18800
        $str = preg_replace('/,(\d{2})$/', '.$1', $str);	// 18800,00		->	18800.00

        return $str;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
