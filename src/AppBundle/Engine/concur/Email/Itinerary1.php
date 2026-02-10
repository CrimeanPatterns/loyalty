<?php

// bcdtravel

namespace AwardWallet\Engine\concur\Email;

// parsers with similar formats: ItineraryBlackIcons

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "";

    public $lang = '';

    public $reBody = [
        'pt' => ['Visão geral da viagem'],
        //		'en' => ['Trip Overview', 'If you have a ticket on file, would'],
    ];

    public static $dict = [
        'pt' => [
            'Reservations'  => 'Reservas',
            'Passengers:'   => 'Passageiros:',
            'Confirmation:' => 'Confirmação:',
            //			'Reservation for' => 'Reservas',
            'Departure:'             => 'Partida:',
            'Departure'              => 'Partida:',
            'Arrival:'               => 'Chegada:',
            'Duration:'              => 'Duração:',
            'Cabin:'                 => 'Cabina:',
            'Total Estimated Cost:'  => 'Custo total estimado:',
            'Taxes and fees:'        => 'Impostos e taxas:',
            'Airfare quoted amount:' => 'Valor indicado tarifa aérea:',
            // CAR
            'Pick Up:'       => 'Retirada:',
            'Pick-up at:'    => 'Retirada em:',
            'Number of'      => 'Número de',
            'Return:'        => 'Devolução:',
            'Returning to:'  => 'Devolução a:',
            'Total Rate:'    => 'Tarifa total:',
            'Rental Details' => 'Detalhes do Aluguel',
            // HOTEL
            'Checking In:'  => 'Check-in:',
            'Checking Out:' => 'Checkout:',
            'Room'          => 'Quarto',
            'Guests'        => 'Clientes',
            'Daily rate:'   => 'Tarifa diária:',
            'Total rate:'   => 'Tarifa total:',
        ],
        //		'en' => [],
    ];

    public function ParseEmailReservations()
    {
        $result = [];
        $air = [];
        $idx = 0;

        $text = $this->t('Passengers:');
        $root = $this->getFirstNode("//text()[contains(., '" . $text . "')]/parent::*");

        if (empty($root)) {
            $text = $this->t('Reservation for:');
            $root = $this->getFirstNode("//text()[contains(., '" . $text . "')]/parent::*");
        }

        if (!empty($root)) {
            $limit = 5;

            while (CleanXMLValue($root->nodeValue) == $text && $limit > 0) {
                $root = $this->firstNode("parent::*", $root);
                $limit--;
            }
            $name = trim(preg_replace("/" . $text . "/", "", CleanXMLValue($root->nodeValue)));
        }

        $root = $this->getFirstNode("//text()[contains(., '" . $this->t('Reservations') . "')]");

        while ($this->http->FindSingleNode("parent::*", $root) == $this->t('Reservations') && $root != null) {
            $root = $this->getFirstNode("parent::*", $root);
        }

        if (empty($root)) {
            return [];
        }

        $nodes = $this->http->XPath->query("following-sibling::*", $root);

        $date = null;

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $text = CleanXMLValue($node->nodeValue);

            if ($d = strtotime($this->normalizeDate($text, $this->lang), false)) {
                $date = $d;

                continue;
            }

            if (empty($date)) {
                continue;
            }

            if (stripos($text, $this->t('Departure:')) !== false) {
                $it = $this->ParseEmailAir($node, $date);
                //				$total = $this->http->FindSingleNode("//*[contains(text(), '".$this->t('Total Estimated Cost:')."')]/ancestor::td[1]/following-sibling::td[2]");
//
                //				$it['TotalCharge'] = preg_replace("#[^\d.]#", '', $total);
                //				$it['Currency'] = preg_replace("#^.*?([A-Z]{3})#", '$1', $total);
//
                //				$it['Tax'] = preg_replace("#[^\d.]#", '', $this->http->FindSingleNode("//*[contains(text(), '".$this->t('Taxes and fees:')."')]/ancestor::td[1]/following-sibling::td[last()-1]"));
                //				$it['BaseFare'] = preg_replace("#[^\d.]#", '', $this->http->FindSingleNode("//*[contains(text(), '".$this->t('Airfare quoted amount:')."')]/ancestor::td[1]/following-sibling::td[last()-1]"));

                if (!empty($it["RecordLocator"]) && !empty($it["TripSegments"])) {
                    if (isset($air[$it["RecordLocator"]])) {
                        $result[$air[$it["RecordLocator"]]]["TripSegments"] = array_merge($result[$air[$it["RecordLocator"]]]["TripSegments"], $it["TripSegments"]);
                    } else {
                        if (!empty($name)) {
                            $it["Passengers"][] = $name;
                        }
                        $result[$idx] = $it;
                        $air[$it["RecordLocator"]] = $idx;
                        $idx++;
                    }
                }
            }

            if (stripos($text, $this->t('Pick Up:')) !== false) {
                $it = $this->ParseEmailCar($node, $date);

                if (!empty($it["Number"])) {
                    if (!empty($name)) {
                        $it["RenterName"] = $name;
                    }
                    $result[$idx] = $it;
                    $idx++;
                }
            }

            if (stripos($text, $this->t('Checking In:')) !== false) {
                $it = $this->ParseEmailHotel($node, $date);

                if (!empty($it["ConfirmationNumber"]) || true) {
                    if (!empty($name)) {
                        $it["GuestNames"] = $name;
                    }
                    $result[$idx] = $it;
                    $idx++;
                }
            }
        }

        if (count($air) === 1) {
            $result[$air[key($air)]]['TotalCharge'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Air Total Price:']/ancestor::th[1]/following-sibling::td[normalize-space(.)][1]", null, true, "#\D([\d\,\.]+)\s+[A-Z]{3}#");
            $result[$air[key($air)]]['Currency'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Air Total Price:']/ancestor::th[1]/following-sibling::td[normalize-space(.)][1]", null, true, "#\D[\d\,\.]+\s+([A-Z]{3})#");
        }

        return $result;
    }

    // bcdtravel
    public function ParseEmailAir($root, $date)
    {
        $text = CleanXMLValue($root->nodeValue);
        $result = ["Kind" => "T", "TripSegments" => []];

        $segment = [];

        // Vôo Curitiba, Parana (CWB) para Sao Paulo (GRU) Aeromexico 8005 Operado por:
        // Flight Washington, DC (DCA) to Los Angeles, CA (LAX)American Airlines 53Departure:
        if (preg_match("/(?:Flight|Vôo)([^\(]+)\s*\(([A-Z]{3})[^\)]*\) (?:to|para) ([^\(]+)\s*\(([A-Z]{3})[^\)]*\)\s*([A-Za-z\s]+\d+).*?(?:Departure|Operated|Partida|Operado por|Este voo)/", $text, $m)) {
            $segment["DepName"] = trim($m[1]);
            $segment["DepCode"] = $m[2];
            $segment["ArrName"] = trim($m[3]);
            $segment["ArrCode"] = $m[4];
            $flight = trim($m[5]);

            if (preg_match("/(.+)\s(\d+)$/", $flight, $m2)) {
                $segment["FlightNumber"] = $m2[2];
                $segment["AirlineName"] = $m2[1];
            } else {
                $segment["FlightNumber"] = $flight;
            }
        }

        if (preg_match("/" . $this->t('Departure:') . "\s*(\d+:\d+\s*(?:[AP]M)?)/", $text, $m)) {
            $segment["DepDate"] = strtotime($m[1], $date);
        }

        if (preg_match("/" . $this->t('Arrival:') . "\s*(\d+:\d+\s*(?:[AP]M)?)/", $text, $m)) {
            $segment["ArrDate"] = strtotime($m[1], $date);
        }

        if (!empty($segment["ArrDate"]) && !empty($segment["DepDate"]) && $segment["ArrDate"] < $segment["DepDate"]) {
            $segment["ArrDate"] += SECONDS_PER_DAY;
        }

        if (preg_match("/" . $this->t('Duration:') . " (\d+) hours?, (\d+) minutes?/", $text, $m)) {
            $segment["Duration"] = $m[1] . ":" . $m[2];
        }

        if (preg_match("/" . $this->t('Cabin:') . " ([^\(]{1,25})\s\(([A-Z]+)\)/", $text, $m)) {
            $segment["Cabin"] = $m[1];
            $segment["BookingClass"] = $m[2];
        }

        if (preg_match("#" . $this->t('Seat:') . "\s*(\d+\w)#", $text, $m)) {
            $segment["Seats"] = [$m[1]];
        }

        if (preg_match("/" . $this->t('Meal:') . "\s*(.+)/", $text, $m)) {
            if (strpos($m[1], ':') === false) {
                $segment["Meal"] = preg_replace('/Check passport.*/', '', $m[1]);
            }
        }

        if (preg_match("#" . $this->t('Distance:') . "\s*(\d+\s*miles)#", $text, $m)) {
            $segment["TraveledMiles"] = $m[1];
        }

        if (preg_match("#" . $this->t('Aircraft:') . "\s*(.*?)\s+Distance#", $text, $m)) {
            if (strpos($m[1], ':') === false) {
                $segment["Aircraft"] = $m[1];
            }
        }

        if (preg_match("#(Non[- ]*stop)#i", $text, $m)) {
            $segment["Stops"] = 0;
        }

        if (preg_match("/" . $this->t('Confirmation:') . "\s*([A-Z\d]{5,6})/i", $text, $m) || preg_match("#Booking Builder /\s*([A-Z\d]+)#i", $text, $m)) {
            $result["RecordLocator"] = $m[1];
        }

        if (!empty($segment)) {
            $result["TripSegments"][] = $segment;
        }

        return $result;
    }

    // bcdtravel
    public function ParseFlightText($text)
    {
        $its = [];

        $created = preg_match("#\n\s*Created:\s*(\w+\s+\d+,\s*\d+)#", $text, $m) ? strtotime($m[1]) : null;
        $start = preg_match("#\n\s*Start Date:\s*(\w{3}\s+\d+,\s*\d+)#", $text, $m) ? $m[1] : null;

        if (!$start) {
            $start = preg_match("#\n\s*Start Date:\s*(\d+\s*\w+),\s*(\d+)#", $text, $m) ? "$m[1] $m[2]" : null;
        }
        $year = date('Y', strtotime($start));

        $trips = preg_split("#\n\s*(Flight\s+.*?\s*\(\w{3}\)\s+to\s+.*?\s*\(\w{3}\))#ms", $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        for ($i = 1; $i < count($trips) - 1; $i += 2) {
            $info = $trips[$i];
            $body = $trips[$i + 1];

            if (preg_match("#\w+,\s*(\w+\s+\d+,\s+\d+)\s*[-~]{15,}#", $trips[$i - 1], $m)) {
                $start = $m[1];
                $year = date('Y', strtotime($start));
            }

            $it = ['Kind' => 'T', 'ReservationDate' => $created];
            $it['TripSegments'] = [];
            $numbers = [];
            $seg = [];

            if (preg_match("#Flight\s+(.*?)\s*\((\w{3})\)\s+to\s+(.*?)\s*\((\w{3})\)#ms", $info, $m)) {
                $seg['DepName'] = trim($m[1], "* \n");
                $seg['DepCode'] = trim($m[2], "* \n");
                $seg['ArrName'] = trim($m[3], "* \n");
                $seg['ArrCode'] = trim($m[4], "* \n");
            }

            $seg['FlightNumber'] = preg_match("#^[\s*]*([A-Za-z\s]+)(\d+)#ms", $body, $m) ? $m[2] : null;
            $seg['AirlineName'] = preg_match("#^[\s*]*([A-Za-z\s]+)(\d+)#ms", $body, $m) ? trim($m[1]) : null;

            $seg['DepDate'] = preg_match("#Departure:\s*(\d+:\d+\s*\w{2})#ms", $body, $m) ? strtotime($start . ', ' . $m[1]) : null;
            $seg['ArrDate'] = preg_match("#Arrival:\s*(\d+:\d+\s*\w{2})#ms", $body, $m) ? strtotime($start . ', ' . $m[1]) : null;

            $seg['Duration'] = preg_match("#Duration:\s*([^\n\r]+)#ms", $body, $m) ? $m[1] : null;
            $seg['Stops'] = preg_match("#\s*(Non[- ]*stop)#i", $body, $m) ? 0 : null;

            $seg['Seats'] = preg_match("#\s*Seat:\s*([\d\w]+)#ms", $body, $m) ? [$m[1]] : null;
            $seg['Aircraft'] = preg_match("#\s*Aircraft:\s*([^\n\r]+)#ms", $body, $m) ? $m[1] : null;
            $seg['TraveledMiles'] = preg_match("#\s*Distance:\s*([^\r\n]+)#ms", $body, $m) ? $m[1] : null;
            $seg['Cabin'] = preg_match("#\s*Cabin:\s*([^\n\r]+)#ms", $body, $m) ? $m[1] : null;
            $seg['Meal'] = preg_match("/\s*Meal:\s*(.+)/", $body, $m) ? preg_replace('/Check passport.*/', '', $m[1]) : null;

            $it['TripSegments'][] = $seg;

            $it['RecordLocator'] = preg_match("#\s*Confirmation:\s*([\d\w\-]+)#ms", $body, $m) ? $m[1] : null;

            $it['AccountNumbers'][] = preg_match("#\s*Air Frequent Flyer Number:\s*([\d\w\-]+)#ms", $body, $m) ? $m[1] : null;

            $its[] = $it;

            // if hotel reservation inside
            if (preg_match("#Meal:\s*[^\n]+\n(?:\s*\[[^\]]+\])*(.*?Checking In:.*)#ms", $body, $m)) {
                $body = $m[1];
                $it = ['Kind' => 'R'];

                if (preg_match("#^\s*([^\r\n]+)\s+(.*?)\n([\d\-+]+)[\r\n]+#ms", $body, $m)) {
                    $it['HotelName'] = $m[1];
                    $it['Phone'] = $m[3];
                    $it['Address'] = trim(preg_replace("#[\r\n]#", ' ', $m[2]), ', ');
                }

                $it['CheckInDate'] = preg_match("#\s*Checking In:\s*([^\n]+)#", $body, $m) ? strtotime("$year, " . trim($m[1], ' *')) : null;
                $it['CheckOutDate'] = preg_match("#\s*Checking Out:\s*([^\n]+)#", $body, $m) ? strtotime("$year, " . trim($m[1], ' *')) : null;

                $it['Guests'] = preg_match("#\s+Guests\s*(\d+)#", $body, $m) ? $m[1] : null;
                $it['Rooms'] = preg_match("#\s+Room\s*(\d+)#", $body, $m) ? $m[1] : null;

                $it['ConfirmationNumber'] = preg_match("#\s*Confirmation:\s*([\w\d\-]+)#", $body, $m) ? $m[1] : null;

                $it['Rate'] = preg_match("#\s+Daily rate:\s*([^\r\n]+)#", $body, $m) ? $m[1] . ' daily' : null;

                if (preg_match("#\s+Total rate:\s*([^\s]+)\s+(\w{3})#", $body, $m)) {
                    $it['Total'] = $m[1];
                    $it['Currency'] = $m[2];
                }

                $it['CancellationPolicy'] = preg_match("#\s*Cancellation Policy[^\w]+([^\r\n]+)#ms", $body, $m) ? $m[1] : null;

                $its[] = $it;
            }
        }

        $newIts = [];

        foreach ($its as $it) {
            $key = $this->searchKeyValue($newIts, 'RecordLocator', $it['RecordLocator']);

            if ($key !== -1) {
                $newIts[$key]['AccountNumbers'] = array_unique(array_merge($newIts[$key]['AccountNumbers'], $it['AccountNumbers']));
                $newIts[$key]['TripSegments'] = array_merge($newIts[$key]['TripSegments'], $it['TripSegments']);
            } else {
                $newIts[] = $it;
            }
        }

        return $newIts;
    }

    public function searchKeyValue($array, $key, $value)
    {
        foreach ($array as $subarray) {
            if (isset($subarray[$key]) && $subarray[$key] == $value) {
                return key($array);
            }
        }

        return -1;
    }

    // bcdtravel
    public function ParseEmailCar($root, $date)
    {
        $text = CleanXMLValue($root->nodeValue);
        $result = ["Kind" => "L"];

        if (preg_match("/" . $this->t('Pick Up:') . "\s*(\d+:\d+\s*(?:[AP]M)?)\s*\w{3} ([\w\s]+)\s*" . $this->t('Pick-up at:') . "(.+?)" . $this->t('Number of') . "/ui", $text, $m)) {
            $result["PickupLocation"] = $m[3];
            $result["PickupDatetime"] = strtotime($m[2] . ", " . $m[1], $date);
        }

        if (preg_match("/" . $this->t('Return:') . "\s*(\d+:\d+\s*(?:[AP]M)?)\s*\w{3} ([\w\s]+)\s*" . $this->t('Returning to:') . "(.+?)\s*" . $this->t('Confirmation:') . "\s*(\w+)/ui", $text, $m)) {
            $result["DropoffLocation"] = $m[3];
            $result["DropoffDatetime"] = strtotime($m[2] . ", " . $m[1], $date);
            $result["Number"] = $m[4];
        }

        if (preg_match("/^([^:]+) (?:at|em):/", $text, $m)) {
            $result["RentalCompany"] = $m[1];
        }

        if (preg_match("/" . $this->t('Total Rate:') . "\D*([\d\.]+)\s*([A-Z]{3})/i", $text, $m)) {
            $result["TotalCharge"] = $m[1];
            $result["Currency"] = $m[2];
        }

        if (preg_match("/" . $this->t('Rental Details') . " (.+)$/", $text, $m)) {
            $result["CarType"] = $m[1];
        }

        return $result;
    }

    // bcdtravel
    public function ParseEmailHotel($root, $date)
    {
        $result = ["Kind" => "R"];
        $text = CleanXMLValue($root->nodeValue);
        $text2 = implode("\n", $this->http->FindNodes(".//text()[normalize-space(.)]", $root));
        $name = $this->http->FindSingleNode("descendant::*[h3 or h4]", $root);

        if (empty($name)) {
            return null;
        }

        $node = $this->firstNode("//*[tr[contains(., '" . $name . "') and not(contains(., '" . $this->t('Checking In:') . "'))]][tr[contains(., '" . $this->t('Checking In:') . "')]]");

        if (empty($node)) {
            return null;
        }

        $lines = array_filter($this->http->FindNodes("tr[3]//text()", $node), 'strlen');
        $result["HotelName"] = $name;
        $result["Address"] = "";

        foreach ($lines as $line) {
            if (preg_match("/^[\d\-]+$/", $line)) {
                $result["Phone"] = $line;
            } else {
                $result["Address"] .= ", " . $line;
            }
        }

        $result["Address"] = trim($result["Address"], ', ');

        if (preg_match("/" . $this->t('Checking In:') . " \w{3} (\w{3} \d{1,2}|\d+ [a-z]{3})\s*(\d{2}:\d{2})?/ui", $text2, $m)) {
            $d = $this->normalizeDate($m[1], $this->lang);

            if (isset($m[2])) {
                $d .= ', ' . trim($m[2]);
            }
            $result["CheckInDate"] = strtotime($d, $date);
        }

        if (preg_match("/" . $this->t('Checking Out:') . " \w{3} (\w{3} \d{1,2}|\d+ [a-z]{3})(\s*\d{2}:\d{2})?/ui", $text2, $m)) {
            $d = $this->normalizeDate($m[1], $this->lang);

            if (isset($m[2])) {
                $d .= ', ' . trim($m[2]);
            }

            $result["CheckOutDate"] = strtotime($d, $date);
        }

        if (preg_match("/" . $this->t('Room') . " (\d+)/", $text, $m)) {
            $result["Rooms"] = (int) $m[1];
        }

        if (preg_match("/" . $this->t('Guests') . " (\d+)/", $text, $m)) {
            $result["Guests"] = (int) $m[1];
        }

        if (preg_match("/" . $this->t('Confirmation:') . "\s*(\w+)/", $text, $m)) {
            $result["ConfirmationNumber"] = $m[1];
        } else {
            $result["ConfirmationNumber"] = TRIP_CODE_UNKNOWN;
        }

        if (preg_match("/" . $this->t('Daily rate:') . "\s*(.+?)\s*" . $this->t('Total rate:') . "/", $text, $m)) {
            $result["Rate"] = $m[1] . "/day";
        }

        if (preg_match("/must cancel by \d+ [ap]m/i", $text, $m)) {
            $result["CancellationPolicy"] = $m[0];
        }

        if (preg_match("/" . $this->t('Total rate:') . "\D*([\d\.\,]+)\s*([A-Z]{3})/i", $text, $m)) {
            $result["Total"] = preg_replace("/\,/", "", $m[1]);
            $result["Currency"] = $m[2];
        }

        return $result;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->detectEmailByBody($parser)) {
            $this->http->Log('File not recognized, check detectEmailByHeaders or detectEmailByBody method');

            return false;
        }

        $this->http->FilterHTML = false;
        $this->http->SetEmailBody($parser->getHTMLBody(), true);

        $its = $this->ParseEmailReservations();
        $type = "EmailReservations";

        if (!$its) {
            $text = $parser->getPlainBody();
            $its = $this->ParseFlightText($text);
            $type = "ParseFlightText";
        }

        if (!isset($its[0])) {
            $its = [$its];
        }

        $totalText = $this->http->FindSingleNode("//*[contains(text(), '" . $this->t('Total Estimated Cost:') . "')]/ancestor::tr[1]");

        if (empty($totalText)) {
            $totalText = $this->http->FindSingleNode("(//text()[contains(., '" . $this->t('Total Estimated Cost:') . "')])[last()]/ancestor::tr[1]");
        }

        if (isset($text) && empty($totalText)) {
            $totalText = $text;
        }

        $totalCharge = [];

        if (preg_match('/' . $this->t('Total Estimated Cost:') . '\D*([\d,.]+)\s*([A-Z]{3})/', $totalText, $m)) {
            $totalCharge['Amount'] = (float) str_replace(',', '', $m[1]);
            $totalCharge['Currency'] = $m[2];
        }
        $result = [
            'parsedData' => [
                'Itineraries' => $its,
                'TotalCharge' => $totalCharge,
            ],
            'emailType' => $type . '_' . $this->lang,
        ];

        $agent = $this->http->FindSingleNode('//td[contains(., "Agency Name:") and not(.//td)]');

        if (isset($agent) && strpos($agent, 'Agency Name: BCD Travel') !== false) {
            $result['providerCode'] = 'bcd';
        }

        return $result;
    }

    public static function getEmailProviders()
    {
        return ['concur', 'bcd'];
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict) * 3; // Air, Car, Hotel
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match("/Concur Itinerary|Concur Travel|Concur Itinerary/i", $headers['subject']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Concur') !== false
            && $this->detectLang($parser->getHTMLBody()) != false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]concursolutions\.com/i', $from)
            || stripos($from, '@concur.com') !== false;
    }

    public function getFirstNode($xpath, $root = null)
    {
        $nodes = $this->http->XPath->query($xpath, $root);

        return $nodes->length > 0 ? $nodes->item(0) : null;
    }

    public function firstNode($xpath, $root = null)
    {
        $nodes = $this->http->XPath->query($xpath, $root);

        if ($nodes->length > 0) {
            return $nodes->item(0);
        } else {
            return null;
        }
    }

    public function IsEmailAggregator()
    {
        return true;
    }

    protected function normalizeDate($subject, $lang)
    {
        $pattern = [
            '/^(\w+) (\d+).*?$/s',
            '/^\w+, (\d+ \w+), (\d+).*?$/s', // Domingo, 05 Fevereiro, 2017
            '/^\w+, (\w+) (\d+), (\d+).*?$/s', // Thursday, May 1, 2014
            '/^(\d+), (\w+) (\d+), \w+.*?$/s', // 2014, April 3, Thursday
        ];
        $replacement = [
            '$2 $1',
            '$1 $2',
            '$2 $1 $3',
            '$3 $2 $1',
        ];
        $replace = preg_replace($pattern, $replacement, trim($subject));

        return $this->translateDate('/^\d+ ([[:alpha:]]+).*/', $replace, $lang);
    }

    protected function translateDate($pattern, $string, $lang)
    {
        if (preg_match($pattern, $string, $matches)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($matches[1], $lang)) {
                return str_replace($matches[1], $en, $matches[0]);
            } else {
                return $matches[0];
            }
        }
    }

    // TODO: If possible, replace
    protected function translateMonth($string)
    {
        return $date = str_ireplace(
            ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Novembro'],
            ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'], $string);
    }

    protected function translateMonthMin($string)
    {
        return $date = str_ireplace(
            ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Nov'],
            ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'], $string);
    }

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    private function detectLang($body)
    {
        foreach ($this->reBody as $lang => $reBody) {
            foreach ($reBody as $needle) {
                if (stripos($body, $needle) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }
    }
}
