<?php

namespace AwardWallet\Engine\flightcentre\Email;

use AwardWallet\Engine\MonthTranslate;

class InvoicePDF extends \TAccountChecker
{
    public $mailFiles = "flightcentre/it-10998662.eml, flightcentre/it-10998665.eml, flightcentre/it-10998671.eml, flightcentre/it-11085714.eml, flightcentre/it-26224627.eml, flightcentre/it-28022098.eml, flightcentre/it-28202977.eml, flightcentre/it-28253892.eml, flightcentre/it-6686241.eml, flightcentre/it-6771301.eml, flightcentre/it-6771336.eml, flightcentre/it-8778997.eml";

    public $reFrom = "flightcentre.com.au";
    public $reBody = [
        'en' => ['Invoice', 'Name(s) as per valid passport(s)', 'Travel Plan'],
    ];
    public $reSubject = [
        'PLEASE AMEND ITINERARY ON PROFILE',
    ];
    public $lang = '';
    public $date;
    /** @var \HttpBrowser */
    public $pdf;
    /** @var \HttpBrowser */
    public $pdfComplex;
    public $pdfNamePattern = ".*pdf";
    // for detect only
    public $pdfNamePatternInvoice = "Invoice.*pdf";
    public static $dict = [
        'en' => [
            "Depart" => ["Depart:", "Date:"],
            "Arrive" => ["Arrive:", "Return Date:"],
        ],
    ];
    private $prov = 'Flight Centre';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!empty($parser->searchAttachmentByName('.*Itinerary\.pdf'))) {//go to parse by src/engine/tport/Email/MyTripPdf.php
            return false;
        }

        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $cnt = count($pdfs);

        for ($i = 0; $i < $cnt; $i++) {
            $this->date = strtotime($parser->getDate());
            $this->tablePdf($parser, $i);

            $air = $this->parseEmailAir();
            $airs = $this->parseEmailAirs();

            if (!empty($airs["check"]) && !empty($air["check"])) {
                foreach ($air["check"] as $item) {
                    if (in_array($item, $airs["check"])) {
                        $this->logger->debug('duplicate segment in parseEmailAirs();');

                        return [];
                    }
                }
            }

            if (!empty($air['its'])) {
                foreach ($air['its'] as $it) {
                    if (count($it) > 0) {
                        $its[] = $it;
                    }
                }
            }

            if (!empty($airs['its'])) {
                foreach ($airs['its'] as $it) {
                    if (count($it) > 0) {
                        $its[] = $it;
                    }
                }
            }

            $cr = $this->parseEmailCruise();

            foreach ($cr as $it) {
                $its[] = $it;
            }

            $trans = $this->parseEmailTransfer();

            foreach ($trans as $it) {
                $its[] = $it;
            }

            $cr = $this->parseEmailTour();

            foreach ($cr as $it) {
                $its[] = $it;
            }

            $tr = $this->parseEmailTrain();

            foreach ($tr as $it) {
                $its[] = $it;
            }

            $car = $this->parseEmailCar();

            foreach ($car as $it) {
                $its[] = $it;
            }

            $hotel = $this->parseEmailHotel();

            foreach ($hotel as $it) {
                $its[] = $it;
            }

            $total = $this->pdf->FindSingleNode("//tr[starts-with(normalize-space(.),'Total price including surcharges') or starts-with(normalize-space(.),'Total Price including surcharges')]"
                . "/following-sibling::tr[normalize-space()][position()<3][starts-with(normalize-space(),'Cash')]",
                null, true, "#.+:\s*(\S+.+)#");

            if (empty($total) && $this->pdf->FindSingleNode("//tr[starts-with(normalize-space(.),'Total price including surcharges') or starts-with(normalize-space(.),'Total Price including surcharges')]"
                    . "/following-sibling::tr[normalize-space()][position()<3][starts-with(normalize-space(),'Cash')]",
                    null, true, "#:\s*$#")) {
                $total = $this->pdf->FindSingleNode("//tr[starts-with(normalize-space(.),'Total price including surcharges') or starts-with(normalize-space(.),'Total Price including surcharges')]"
                    . "/following-sibling::tr[normalize-space()][position()<3][starts-with(normalize-space(),'Cash')]/following::text()[normalize-space()][1]",
                    null, true, "#^\s*\\$[\d\.\, ]+\s*$#");

                if (empty($total)) {
                    $total = $this->pdf->FindSingleNode("//tr[starts-with(normalize-space(.),'Total price including surcharges') or starts-with(normalize-space(.),'Total Price including surcharges')]"
                        . "/following-sibling::tr[normalize-space()][position()<3][starts-with(normalize-space(),'Cash')]/preceding::text()[normalize-space()][1]",
                        null, true, "#^\s*\\$[\d\.\, ]+\s*$#");
                }
            }

            if (preg_match('#^\s*(?<total>\$[\d\,\.]+)\s*$#', $total, $m)) {
                $totalCharge['Currency'] = 'AUD';
                $tot = $this->getTotalCurrency($m['total']);
                $totalCharge['Amount'] = isset($totalCharge['Amount']) ? $totalCharge['Amount'] + $tot['Total'] : $tot['Total'];
            }
        }
        $result = [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'InvoicePDF' . ucfirst($this->lang),
        ];

        if (isset($totalCharge['Amount'])) {
            $result['TotalCharge']['Currency'] = $totalCharge['Currency'];
            $result['TotalCharge']['Amount'] = $totalCharge['Amount'];
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if ($this->AssignLang($text)) {
                return true;
            }
        }
        $pdf = $parser->searchAttachmentByName($this->pdfNamePatternInvoice);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            return $this->AssignLang($text);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 8; // 2 flights type | cruise | tour |  transfer | train | car | hotel
        $cnt = count(self::$dict) * $types;

        return $cnt;
    }

    private function parseEmailAirs()
    {
        $its = [];
        $checkAirs = [];
        $xpath = "descendant::td[normalize-space(.)='Airline']/ancestor::tr[1][contains(.,'Flight') or preceding-sibling::tr[1][contains(.,'Flight')]]";
        $nodes = $this->pdf->XPath->query($xpath);

        if ($nodes->length == 0) {
            return [];
        }
        $totalCharge = 0.0;
        $baseFare = 0.0;
        $tax = 0.0;
        $airs = [];

        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = CONFNO_UNKNOWN; // for mobext
        $it['TripNumber'] = $this->pdf->FindSingleNode("descendant::td[starts-with(normalize-space(.),'Ref Booking') or starts-with(normalize-space(.),'Quote ID')]",
            null, true, "#:\s+([A-Z\d]+)#");
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("descendant::td[starts-with(normalize-space(.),'Issue date')]",
            null, true, "#:\s+(.+?)\s*(?:\(|$)#")));

        $c = $this->countTrBetween(['Traveller(s):', 'Title'], 'Details');
        $pax = $this->pdf->XPath->query("descendant::td[normalize-space(.)='Traveller(s):']/ancestor::tr[1][contains(.,'Title') or preceding-sibling::tr[1][contains(.,'Title')]]/following-sibling::tr[position()<{$c}][normalize-space(.)!='']");

        if ($pax->length > 0) {
            foreach ($pax as $p) {
                $it['Passengers'][] = implode(" ", $this->pdf->FindNodes("./td", $p));
            }
        }

        foreach ($nodes as $i => $node) {
            $c = $this->countTrBetween(['Airline', 'Flight'], 'Travellers:', $i + 1);
            $roots = $this->pdf->XPath->query("./following-sibling::tr[position()<={$c} and count(td)>5]", $node);

            $n = $this->pdf->FindSingleNode("./following-sibling::tr[contains(.,'Total flight price:')][1]", $node,
                true, "#.+:\s*(\S+.+)#");

            if (empty($n) && $this->pdf->FindSingleNode("./following-sibling::tr[contains(.,'Total flight price:')][1]",
                    $node, true, "#:\s*$#")) {
                $n = $this->pdf->FindSingleNode("./following-sibling::tr[contains(.,'Total flight price:')][1]/following::text()[normalize-space()][1]",
                    $node, true, "#^\s*\\$[\d\.\, ]+\s*$#");

                if (empty($n)) {
                    $n = $this->pdf->FindSingleNode("./following-sibling::tr[contains(.,'Total flight price:')][1]/preceding::text()[normalize-space()][1]",
                        $node, true, "#^\s*\\$[\d\.\, ]+\s*$#");
                }
            }

            if (preg_match('#^\s*(?<total>\$[\d\,\.]+)\s*$#', $n, $m)) {
                $it['Currency'] = 'AUD'; //$ 1234.56 pp -> AUD
                $tot = $this->getTotalCurrency($m['total']);
                $totalCharge += $tot['Total'];
            }

            $passengersCount = $this->pdf->FindSingleNode("./following-sibling::tr[starts-with(normalize-space(.),'Travellers:')][1]",
                $node, true, "#.+:\s*(\d+) \w+#");

            $n = $this->pdf->FindSingleNode("./following-sibling::tr[contains(.,'Airfare')][1]", $node);

            if (!empty($passengersCount) && preg_match('#Airfare:\s*(?P<base>\$[\d\,\.]+)pp. plus taxes & surcharges of (?P<tax>\$[\d\,\.]+)pp. Total (?P<total>\$[\d\,\.]+)pp.#',
                    $n, $m)) {
                $it['Currency'] = 'AUD'; //$ 1234.56 pp -> AUD
                $tot = $this->getTotalCurrency($m['base']);
                $baseFare += $passengersCount * $tot['Total'];
                $tot = $this->getTotalCurrency($m['tax']);
                $tax += $passengersCount * $tot['Total'];
            }

            foreach ($roots as $root) {
                $airs[] = $root;
            }
        }

        if (isset($it['Currency'])) {
            $it['TotalCharge'] = $totalCharge;
            $it['BaseFare'] = $baseFare;
            $it['Tax'] = $tax;
        }

        foreach ($airs as $root) {
            $seg = [];
            $node = $this->pdf->FindSingleNode("./td[2]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)(\s+[\s\S]+)?#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];

                if (!empty($m[3])) {
                    $seg['Aircraft'] = trim($m[3]);
                }
            }
            $node = $this->pdf->FindSingleNode("./td[3]", $root);

            if (preg_match("#(.+)\s+Cabin\s+Class:\s*(.+)#", $node, $m)) {
                $seg['DepDate'] = strtotime($this->normalizeDate($m[1]));
                $seg['Cabin'] = $m[2];
            } else {
                $seg['DepDate'] = strtotime($this->normalizeDate($node));
            }
            $seg['ArrDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("./td[4]", $root)));

            $seg['DepName'] = $this->pdf->FindSingleNode("./td[5]", $root);
            $seg['ArrName'] = $this->pdf->FindSingleNode("./td[6]", $root);
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            if (empty($seg['AirlineName']) && empty($seg['FlightNumber']) && empty($seg['DepDate'])) {
                return [$it];
            }
            $finded = false;

            foreach ($it['TripSegments'] as $key => $value) {
                if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                    && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                    && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                    $finded = true;
                }
            }

            if ($finded == false) {
                $it['TripSegments'][] = $seg;
            }

            $checkAirs[] = $seg['AirlineName'] . "-" . preg_replace("/^[0]+/", "",
                    $seg['FlightNumber']) . "-" . $seg['DepDate'];
        }
        $its[] = $it;

        return ["its" => $its, "check" => $checkAirs];
    }

    private function parseEmailAir()
    {
        $nodes = $this->pdf->XPath->query("descendant::td[starts-with(normalize-space(.),'Air:') or normalize-space()='Flights']");
        $its = [];
        $check = [];

        if ($nodes->length === 0) {
            return [];
        }

        $tripNumber = $this->pdf->FindSingleNode("descendant::td[starts-with(normalize-space(.),'Ref Booking') or starts-with(normalize-space(.),'Quote ID')]",
            null, true, "#:\s+([A-Z\d]+)#");
        $reservationDate = strtotime($this->normalizeDate($this->pdf->FindSingleNode("descendant::td[starts-with(normalize-space(.),'Issue date')]",
            null, true, "#:\s+(.+?)\s*(?:\(|$)#")));
        $c = $this->countTrBetween(['Traveller(s):', 'Title'], 'Details');
        $pax = $this->pdf->XPath->query("descendant::td[normalize-space(.)='Traveller(s):']/ancestor::tr[1][contains(.,'Title') or preceding-sibling::tr[1][contains(.,'Title')]]/following-sibling::tr[position()<{$c}][normalize-space(.)!='']");

        if ($pax->length == 0) {
            $c = $this->countTrBetween(['Traveller(s):', 'Title'], 'Date of travel:');
            $pax = $this->pdf->XPath->query("descendant::td[normalize-space(.)='Traveller(s):']/ancestor::tr[1][contains(.,'Title') or preceding-sibling::tr[1][contains(.,'Title')]]/following-sibling::tr[position()<{$c}][normalize-space(.)!='']");
        }

        if ($pax->length > 0) {
            foreach ($pax as $p) {
                $passengers[] = implode(" ", $this->pdf->FindNodes("./td", $p));
            }
        }

        foreach ($nodes as $i => $node) {
            unset($rl);
            $num = $i + 1;
            $c = 20;

            if ($pos1 = $this->pdf->XPath->query("(descendant::td[starts-with(normalize-space(.),'Air:') or normalize-space()='Flights']/ancestor::tr[1])[{$num}]/preceding-sibling::tr")->length) {
                if ($pos2 = $this->pdf->XPath->query("(descendant::td[starts-with(normalize-space(.),'Air:') or normalize-space()='Flights']/ancestor::tr[1])[{$num}]/following-sibling::tr[td[starts-with(normalize-space(.),'Total flight price:')]][1]/preceding-sibling::tr")->length) {
                    $c = $pos2 - $pos1 + 5;
                }
            }

            $segment = implode("\n",
                $this->pdf->FindNodes("(descendant::td[starts-with(.,'Air:') or normalize-space()='Flights']/ancestor::tr[1]/following-sibling::tr[1][contains(.,'Airline') or contains(., 'Origin:')])[{$num}]/preceding::tr[2]/following-sibling::tr[position()<{$c}]"));
            $seg1 = [];
            $seg2 = [];

            if (preg_match("#\nTrip Type:\s*Return#", $segment)) {
                $seg1['AirlineName'] = $seg2['AirlineName'] = $this->re("#\nAirline:.+?\(([A-Z\d]{2})\)#", $segment);
                $seg1['FlightNumber'] = $seg2['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
                $seg1['Cabin'] = $seg2['Cabin'] = $this->re("#\nClass:\s*(.+)#", $segment);

                if (preg_match("#\nOrigin:\s*(.+?) *\(([A-Z]{3})\)#", $segment, $m)) {
                    $seg1['DepCode'] = $seg2['ArrCode'] = $m[2];
                    $seg1['DepName'] = $seg2['ArrName'] = $m[1];
                }

                if (preg_match("#\nDestination:\s*(.+?) *\(([A-Z]{3})\)#", $segment, $m)) {
                    $seg2['DepCode'] = $seg1['ArrCode'] = $m[2];
                    $seg2['DepName'] = $seg1['ArrName'] = $m[1];
                }

                if (preg_match("#.+?\s*[\-‐] *.+? (\d+:\d+(?:\s*[ap]m)?) *[\-‐] *(\d+:\d+(?:\s*[ap]m)?)\s+.+? *[\-‐] *.+?(\d+:\d+(?:\s*[ap]m)?) *[\-‐] *(\d+:\d+(?:\s*[ap]m)?)#umi",
                    $segment, $m)) {
                    $date = strtotime($this->normalizeDate($this->re("#\nDate:\s*(.+)#", $segment)));
                    $seg1['DepDate'] = strtotime($m[1], $date);
                    $seg1['ArrDate'] = strtotime($m[2], $date);
                    $date = strtotime($this->normalizeDate($this->re("#\nReturn Date:\s*(.+)#", $segment)));
                    $seg2['DepDate'] = strtotime($m[3], $date);
                    $seg2['ArrDate'] = strtotime($m[4], $date);
                }
            } else {
                //TODO: need more examples to describe;
                $seg1['AirlineName'] = $this->re("#\n\s*Flight Number:[ ]*([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*\d{1,5}\s*\n#",
                    $segment);
                $seg1['FlightNumber'] = $this->re("#\n\s*Flight Number:[ ]*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(\d{1,5})\s*\n#",
                    $segment);

                if (empty($seg1['AirlineName'])) {
                    $seg1['AirlineName'] = $this->re("#Airline:(.+)\s\([A-Z]{2,3}\)#", $segment);
                }

                if (empty($seg1['FlightNumber'])) {
                    $seg1['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
                }

                if (preg_match("#\n\s*Origin:\s*(.+?) *\(([A-Z]{3})\)#", $segment, $m)) {
                    $seg1['DepCode'] = $m[2];
                    $seg1['DepName'] = $m[1];
                }

                if (preg_match("#\n\s*Destination:\s*(.+?) *\(([A-Z]{3})\)#", $segment, $m)) {
                    $seg1['ArrCode'] = $m[2];
                    $seg1['ArrName'] = $m[1];
                }

                $depDate = $this->re("#\n\s*" . $this->opt($this->t("Depart")) . "[ ]*(.+)\n#", $segment);

                if (!empty($depDate)) {
                    $depTime = $this->re("#(?:[" . $this->opt($this->t(" Comments:")) . "]+?|[" . $this->opt($this->t(" Comments:")) . "].+?)(\d{1,2}:\d{1,2}[\s]?(?:[ap]m|))\s‐\s\d{1,2}:\d{1,2}[\s]?(?:[ap]m|)#",
                        $segment);
                    $seg1['DepDate'] = strtotime($this->normalizeDate($this->re("#(" . str_replace("/", "\/",
                                $depDate) . ")#", $segment) . " " . $depTime));
                } else {
                    $seg1['DepDate'] = MISSING_DATE;
                }

                $arrDate = $this->re("#\n\s*" . $this->opt($this->t("Arrive")) . "[ ]*(.+)\n#", $segment);

                if (!empty($arrDate)) {
                    $arrTime = $this->re("#(?:[" . $this->opt($this->t(" Comments:")) . "]+?|[" . $this->opt($this->t(" Comments:")) . "].+?)\d{1,2}:\d{1,2}[\s]?(?:[ap]m|)\s‐\s(\d{1,2}:\d{1,2}[\s]?(?:[ap]m|))#",
                        $segment);
                    $seg1['ArrDate'] = strtotime($this->normalizeDate($this->re("#(" . str_replace("/", "\/",
                                $arrDate) . ")#", $segment) . " " . $arrTime));
                } else {
                    $seg1['ArrDate'] = MISSING_DATE;
                }

                $seg1['Duration'] = $this->re("#\n\s*Flying Time:[ ]*(.+)\n#", $segment);

                $rl = $this->re("#\n\s*Reference:[ ]*([A-Z\d]{5,7})\s*\n#", $segment);
            }

            $tot = $this->re('/Total flight price[\s:]+(\\$[\d\.\, ]+)(?:$|\s+)/', $segment);

            if (empty($tot)) {
                $tot = $this->re('/\n\s*(\\$[\d\.\, ]+)\s+Total flight price[\s:]+/', $segment);
            }
            $tot = $this->getTotalCurrency($tot);

            if (empty($rl)) {
                $rl = CONFNO_UNKNOWN;
            }

            $foundRl = false;

            foreach ($its as $key => $it) {
                if ($it['RecordLocator'] == $rl) {
                    $its[$key]['TripSegments'][] = $seg1;

                    if (!empty($seg2)) {
                        $its[$key]['TripSegments'][] = $seg2;
                    }

                    if (!empty($tot['Total'])) {
                        if (isset($it['TotalCharge'])) {
                            $it['TotalCharge'] += $tot['Total'];
                            $it['Currency'] = $tot['Currency'];
                        } else {
                            $it['TotalCharge'] = $tot['Total'];
                            $it['Currency'] = $tot['Currency'];
                        }
                    }

                    $foundRl = true;

                    break;
                }
            }

            if ($foundRl == false) {
                /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
                $it = ['Kind' => 'T', 'TripSegments' => []];
                $it['RecordLocator'] = $rl;

                if (!empty($tripNumber)) {
                    $it['TripNumber'] = $tripNumber;
                }

                if (!empty($reservationDate)) {
                    $it['ReservationDate'] = $reservationDate;
                }

                if (!empty($passengers)) {
                    $it['Passengers'] = $passengers;
                }

                $it['TripSegments'][] = $seg1;

                if (!empty($seg2)) {
                    $it['TripSegments'][] = $seg2;
                }

                if (!empty($tot['Total'])) {
                    $it['TotalCharge'] = $tot['Total'];
                    $it['Currency'] = $tot['Currency'];
                }
                $its[] = $it;
            }

            $check[] = $seg1['AirlineName'] . "-" . preg_replace("/^[0]+/", "", $seg1['FlightNumber']) . "-" . $seg1['DepDate'];
        }

        return ["its" => $its, "check" => $check];
    }

    private function parseEmailCruise()
    {
        $its = [];

        if ($this->pdf->XPath->query("descendant::td[starts-with(.,'Cruise line:')]")->length !== 0) {
            $xpath = "descendant::td[starts-with(.,'Cruise:')]/ancestor::tr[1]";
            $nodes = $this->pdf->XPath->query($xpath);

            foreach ($nodes as $i => $root) {
                $num = $i + 1;
                $c = $this->countTrBetween('Cruise:', 'Nominated Calculation Currency:', $num, true);

                if (empty($c)) {
                    $c = 20;
                }
                $it = ['Kind' => 'T', 'TripSegments' => []];
                $it['TripCategory'] = TRIP_CATEGORY_CRUISE;
                $it['RecordLocator'] = CONFNO_UNKNOWN;
                $it['TripNumber'] = $this->pdf->FindSingleNode("descendant::td[starts-with(normalize-space(.),'Ref Booking') or starts-with(normalize-space(.),'Quote ID')]",
                    null, true, "#:\s+([A-Z\d]+)#");
                $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("descendant::td[starts-with(normalize-space(.),'Issue date')]",
                    null, true, "#:\s+(.+?)\s*(?:\(|$)#")));
                $d = $this->countTrBetween(['Traveller(s):', 'Title'], 'Details');
                $nodes = $this->pdf->XPath->query("descendant::td[normalize-space(.)='Traveller(s):']/ancestor::tr[1 and contains(.,'Title')]/following-sibling::tr[position()<{$d}][normalize-space(.)!='']");

                if ($nodes->length > 0) {
                    foreach ($nodes as $node) {
                        $it['Passengers'][] = implode(" ", $this->pdf->FindNodes("./td", $node));
                    }
                }

                if ($it['ReservationDate']) {
                    $this->date = $it['ReservationDate'];
                }

                $it['ShipName'] = $this->pdfComplex->FindSingleNode("(//p[contains(normalize-space(.),'Cruise:')])[{$num}]/following-sibling::p[normalize-space(.)!=''][position()<10][contains(.,'Ship name:')]/following::p[string-length(normalize-space(.))>2][1]");

                $it['CruiseName'] = $this->pdfComplex->FindSingleNode("(//p[contains(normalize-space(.),'Cruise:')])[{$num}]/following-sibling::p[normalize-space(.)!=''][position()<6][contains(.,'Cruise name:')]/following::p[string-length(normalize-space(.))>2][1]");

                if (empty($it['CruiseName'])) {
                    $it['CruiseName'] = $this->pdf->FindSingleNode("descendant::td[starts-with(.,'Cruise:')]/ancestor::tr[1]/following-sibling::tr[1]/td[1][contains(.,'Cruise line:')]/following-sibling::td[1]");
                }

                if (empty($it['CruiseName'])) {
                    $it['CruiseName'] = $this->pdfComplex->FindSingleNode("(descendant::p[contains(normalize-space(.),'Cruise:')])[{$num}]/following-sibling::p[normalize-space(.)!=''][1][contains(.,'Cruise line:')]/following::p[string-length(normalize-space(.))>2][1]");
                }

                $seg = [];

                $node = $this->pdfComplex->FindSingleNode("(//p[contains(normalize-space(.),'Cruise:')])[{$num}]/following-sibling::p[normalize-space(.)!=''][position()<20][contains(.,'Sailing From:')]/following::p[string-length(normalize-space(.))>2][1]");

                if (preg_match("#(.+)\s+on\s+(\d+\/\d+\/\d+.*)#", $node, $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepDate'] = strtotime($this->normalizeDate($m[2]));
                }
                $node = $this->pdfComplex->FindSingleNode("(//p[contains(normalize-space(.),'Cruise:')])[{$num}]/following-sibling::p[normalize-space(.)!=''][position()<20][contains(.,'Disembarking at:')]/following::p[string-length(normalize-space(.))>2][1]");

                if (preg_match("#(.+?)\s*(?:on\s+(\d+\/\d+\/\d+.*)|$)#", $node, $m)) {
                    $seg['ArrName'] = $m[1];

                    if (isset($m[2]) && !empty($m[2])) {
                        $seg['ArrDate'] = strtotime($this->normalizeDate($m[2]));
                    }
                }
                $node = $this->pdf->FindSingleNode("./following-sibling::tr[position()<{$c}][contains(.,'Departs') and contains(.,'Arrives')]",
                    $root);

                if (preg_match("#Departs\s+(.+)\s+(\d+\s+\w+)\s+(\d+)(:\d+)?\s*([ap]m)\s*Arrives\s+(.+)\s+(\d+\s+\w+)\s+(\d+)(:\d+)?\s*([ap]m)#i",
                    $node, $m)) {
                    $seg['DepName'] = $m[1] . (isset($seg['DepName']) ? '-' . $seg['DepName'] : '');

                    if (isset($seg['DepDate'])) {
                        $year = date('Y', $seg['DepDate']);
                    } else {
                        $year = date('Y', $this->date);
                    }
                    $seg['DepDate'] = strtotime(strtolower($m[2] . ' ' . $year . ' ' . $m[3] . (isset($m[4]) && !empty($m[4]) ? $m[4] : ':00') . $m[5]));

                    if ($seg['DepDate'] < $this->date) {
                        $seg['DepDate'] = strtotime("+1 year", $seg['DepDate']);
                        $year++;
                    }
                    $seg['ArrName'] = $m[6] . (isset($seg['ArrName']) ? '-' . $seg['ArrName'] : '');
                    $seg['ArrDate'] = strtotime(strtolower($m[7] . ' ' . $year . ' ' . $m[8] . (isset($m[9]) && !empty($m[9]) ? $m[9] : ':00') . $m[10]));
                }

                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $year = date("Y", $this->date);
        $in = [
            //26/06/2017 11:15 AM, Sunday 10/09/2017 9:45 AM
            '#^\s*[a-z]*\s*(\d+)\/(\d+)\/(\d+)\s+(\d+:\d+(?:\s*[ap]m))\s*$#i',
            //26/06/2017
            '#^\s*(\d+)\/(\d+)\/(\d+)\s*$#i',
            //09 Jul 10:20
            '#^\s*(\d+\s+\w+)\s+(\d+:\d+)\s*$#',
            //17/03/2015 at 2:00pm
            '#^\s*(\d+)\/(\d+)\/(\d+)\s+at\s+(\d+:\d+(?::\d+)?(?:\s*[ap]m)?)\s*$#i',
            //17/03/2015 at 2.00pm
            '#^\s*(\d+)\/(\d+)\/(\d+)\s+at\s+(\d+)\.(\d+(?:\s*[ap]m)?)\s*$#i',
            //17/03/2015 at 2pm
            '#^\s*(\d+)\/(\d+)\/(\d+)\s+at\s+(\d+)(\s*[ap]m)?\s*$#i',

            //17/03/2015 at 2pm
            '#^\s*(\d+)\/(\d+)\/(\d+)\s+at\s+(\d+)(\s*[ap]m)\s*$#i',
            //01:55 PM Saturday 25 May 2019
            "#^\s*(\d+:\d+(?:\s*[ap]m))\s+\w+\s+(\d{1,2})\s+([^\d\s]+)\s+(\d{4})\s*$#iu",
            "#^(\d+)\/(\d+)\/(\d+)\s(\d+:\d+)$#",
        ];
        $out = [
            '$3-$2-$1 $4',
            '$3-$2-$1',
            '$1 ' . $year . ' $2',
            '$3-$2-$1 $4',
            '$3-$2-$1 $4:$5',
            '$3-$2-$1 $4:00 $5',

            '$3-$2-$1 $4:00 $5',
            '$2 $3 $4, $1',
            '$1.$2.$3, $4',
        ];
        $str = preg_replace($in, $out, $date);

        if (preg_match("#(?<date>.+\s)(?<t1>\d+)(?<t2>:\d+)(?<m>\s*[ap]m)\s*$#i", $str, $m)) {
            if ($m['t1'] > 13) {
                $str = $m['date'] . $m['t1'] . $m['t2'];
            }
        }
        $str = $this->dateStringToEnglish($str);

        return $str;
    }

    private function parseEmailTour()
    {
        $nodes = $this->pdf->XPath->query("descendant::td[starts-with(normalize-space(.),'Touring:')]");
        $its = [];
        $flights = [];

        foreach ($nodes as $i => $node) {
            $num = $i + 1;
            $it = ['Kind' => 'E'];
            $it['EventType'] = EVENT_EVENT;
            $it['ConfNo'] = CONFNO_UNKNOWN;
            $it['TripNumber'] = $this->pdf->FindSingleNode("descendant::td[starts-with(normalize-space(.),'Ref Booking') or starts-with(normalize-space(.),'Quote ID')]",
                null, true, "#:\s+([A-Z\d]+)#");
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdfComplex->FindSingleNode("(//text()[starts-with(normalize-space(.),'Issue date:')])[1]",
                null, true, "#:\s+(.+?)\s*(?:\(|$)#")));
            $it['Name'] = $this->pdfComplex->FindSingleNode("(//text()[starts-with(normalize-space(.),'Name of tour')]/ancestor::p[1]/following::p[normalize-space(.)!=''][1])[{$num}]");

            $c = 20;

            if ($pos1 = $this->pdf->XPath->query("(descendant::td[starts-with(normalize-space(.),'Touring:')]/ancestor::tr[1])[{$num}]/preceding-sibling::tr")->length) {
                if ($pos2 = $this->pdf->XPath->query("(descendant::td[starts-with(normalize-space(.),'Touring:')]/ancestor::tr[1])[{$num}]/following-sibling::tr[td[starts-with(normalize-space(.),'Total touring price:')]][1]/preceding-sibling::tr")->length) {
                    $c = $pos2 - $pos1 + 5;
                }
            }

            $segment = implode("\n",
                $this->pdf->FindNodes("(descendant::td[starts-with(.,'Touring:')]/ancestor::tr[1]/following-sibling::tr[1][contains(.,'Name of tour:')])[{$num}]/preceding::tr[2]/following-sibling::tr[position()<{$c}]"));

            $it['Address'] = $this->re("#Starts:[ ]*(.+)\s+on#", $segment);

            if (empty($it['Address'])) {
                $it['Address'] = $this->re("#\n(.+)[ ]+on .+\s+Starts:[ ]*\n#", $segment);
            }

            if (empty($it['Address'])) {
                $it['Address'] = $this->re("#\n(.+)[ ]+on .+\s+Starts:[ ]*\n#", $segment);
            }
            $it['Guests'] = $this->re("#Traveller\(s\):[ ]*(\d+)\s+adult#", $segment);

            if (empty($it['Guests'])) {
                $it['Guests'] = $this->re("#\n\s*(\d+)\s+adult.*\s+Traveller\(s\):[ ]*\n#", $segment);
            }

            $startDate = $this->re("#Starts:[ ]*.+on[ ]+(\d+\/\d+\/\d+)#i", $segment);

            if (empty($startDate)) {
                $startDate = $this->re("#\n\s*.+on[ ]+(\d+\/\d+\/\d+.*)\s+Starts[ :]+#", $segment);
            }

            if (!empty($startDate)) {
                $it['StartDate'] = strtotime($this->normalizeDate($startDate));
            }
            $this->date = $it['StartDate'];

            $endDate = $this->re("#Ends:[ ]*.+on[ ]+(\d+\/\d+\/\d+)#i", $segment);

            if (empty($endDate)) {
                $endDate = $this->re("#\n\s*.+on[ ]+(\d+\/\d+\/\d+.*)\s+Ends[ :]+#", $segment);
            }

            if (!empty($endDate)) {
                $it['EndDate'] = strtotime($this->normalizeDate($endDate));
            }

            // Total
            // Currency
            $tot = $this->re('/Total touring price[\s:]+(\\$[\d\.\, ]+)(?:$|\s+)/', $segment);

            if (empty($tot)) {
                $tot = $this->re('/\n\s*(\\$[\d\.\, ]+)\s+Total touring price[\s:]+/', $segment);
            }
            $tot = $this->getTotalCurrency($tot);

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }

            $its[] = $it;

            if (strpos($segment, "All passengers are booked on the following flights:") !== false) {
                $it = ['Kind' => 'T', 'TripSegments' => []];
                $it['RecordLocator'] = CONFNO_UNKNOWN;
                $it['TripNumber'] = $this->pdfComplex->FindSingleNode("//text()[starts-with(normalize-space(.),'Ref Booking')]",
                    null, true, "#:\s+([A-Z\d]+)#");

                $it['Passengers'] = $this->pdfComplex->FindNodes("//text()[starts-with(normalize-space(.),'Passenger ')]/ancestor::p[1]/following::p[normalize-space(.)!=''][1]");

                if (count($it['Passengers']) == 0) {
                    $c = $this->countPBetween('Name(s) as per valid passport(s)', 'Details', $num);
                    $pax = $this->pdfComplex->FindNodes("(//text()[starts-with(normalize-space(.),'Name(s) as per valid passport(s)')]/ancestor::p[1])[{$num}]/following::p[position()<{$c}]//text()[normalize-space(.)!='']");
                    $it['Passengers'] = $pax;
                }
                $text = preg_replace("#Reference\s+Number.+\s+Comments:#s", '',
                    strstr($segment, 'All passengers are booked on the following flights:'));
                $nodes = $this->splitter("#\n(.+?\s+\([A-Z]{3}\)\s+\d+\s+\w+\s+\d+:\d+\s*.+\s+\([A-Z]{3}\)\s+(?:\d+\s+\w+\s+)?\d+:\d{2})#",
                    $text);
                //$nodes = $this->splitter("#\n(.+?\s+\([A-Z]{3}\)\s+\d+\s+\w+\s+\d+:\d+\s*.+\s+\([A-Z]{3}\)\s+(?:\d+\s+\w+\s+)?\d+:\d{2})\s*[A-Z\d]{2}\s*\d+#", $text);
                foreach ($nodes as $node) {
                    $seg = [];

                    if (preg_match("#(.+?)\s+\(([A-Z]{3})\)\s+(\d+\s+\w+\s+\d+:\d+)\s*(.+)\s+\(([A-Z]{3})\)\s+(?:(\d+\s+\w+)\s+)?(\d+:\d{2})\s*([A-Z\d]{2})\s*(\d+)#",
                        $node, $m)) {
                        $seg['DepName'] = $m[1];
                        $seg['DepCode'] = $m[2];
                        $seg['DepDate'] = strtotime($this->normalizeDate($m[3]));
                        $seg['ArrName'] = $m[4];
                        $seg['ArrCode'] = $m[5];

                        if (isset($m[6]) && !empty($m[6])) {
                            $seg['ArrDate'] = strtotime($this->normalizeDate($m[6] . ' ' . $m[7]));
                        } else {
                            $seg['ArrDate'] = strtotime($m[7], $seg['DepDate']);
                        }
                        $seg['AirlineName'] = $m[8];
                        $seg['FlightNumber'] = $m[9];
                    }
                    $it['TripSegments'][] = $seg;
                }
                $flights[] = $it;
            }
        }

        foreach ($flights as $it) {
            $its[] = $it;
        }

        return $its;
    }

    private function parseEmailTransfer()
    {
        $nodes = $this->pdf->XPath->query("descendant::td[starts-with(normalize-space(.),'Transfer:')]");
        $its = [];

        foreach ($nodes as $i => $node) {
            $num = $i + 1;
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['TripCategory'] = TRIP_CATEGORY_TRANSFER;

            $it['RecordLocator'] = CONFNO_UNKNOWN;
            $it['TripNumber'] = $this->pdf->FindSingleNode("descendant::td[starts-with(normalize-space(.),'Ref Booking') or starts-with(normalize-space(.),'Quote ID')]",
                null, true, "#:\s+([A-Z\d]+)#");
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("descendant::td[starts-with(normalize-space(.),'Issue date')]",
                null, true, "#:\s+(.+?)\s*(?:\(|$)#")));

            $c = $this->countTrBetween(['Traveller(s):', 'Title'], 'Details');
            $pax = $this->pdf->XPath->query("descendant::td[normalize-space(.)='Traveller(s):']/ancestor::tr[1][contains(.,'Title') or preceding-sibling::tr[1][contains(.,'Title')]]/following-sibling::tr[position()<{$c}][normalize-space(.)!='']");

            if ($pax->length > 0) {
                foreach ($pax as $p) {
                    $it['Passengers'][] = implode(" ", $this->pdf->FindNodes("./td", $p));
                }
            }

            $c = 20;

            if ($pos1 = $this->pdf->XPath->query("(descendant::td[starts-with(normalize-space(.),'Transfer:')]/ancestor::tr[1])[{$num}]/preceding-sibling::tr")->length) {
                if ($pos2 = $this->pdf->XPath->query("(descendant::td[starts-with(normalize-space(.),'Transfer:')]/ancestor::tr[1])[{$num}]/following-sibling::tr[td[starts-with(normalize-space(.),'Total transfer price:')]][1]/preceding-sibling::tr")->length) {
                    $c = $pos2 - $pos1 + 5;
                }
            }

            $segment = implode("\n",
                $this->pdf->FindNodes("(descendant::td[starts-with(.,'Transfer:')]/ancestor::tr[1])[{$num}]/preceding::tr[1]/following-sibling::tr[position()<{$c}]"));
            $seg = [];
            $seg['Type'] = $this->re("#Type[:\s]+(.+)#", $segment);
            // can be a city code instead of an airport code
            if (preg_match("#Pick[\-\s‐]*up[:\s]+([A-Z]{3})\s+on\s+(\d+\/\d+\/\d+(?:\s*at\s*\d{1,2}(?::\d{1,2})?(?:\s*[ap]m))?)#u",
                    $segment,
                    $m) && empty($this->pdf->FindSingleNode("(//text()[normalize-space(.)='City:']/ancestor::tr[1][contains(.,'" . '(' . $m[1] . ')' . "')])[1]"))
                && empty($this->pdf->FindSingleNode("(//text()[normalize-space(.)='City:']/ancestor::tr[1]/preceding-sibling::tr[normalize-space()][1][contains(.,'" . '(' . $m[1] . ')' . "')])[1]"))) {
                $seg['DepCode'] = $m[1];
                $seg['DepDate'] = strtotime($this->normalizeDate($m[2]));
                $seg['ArrDate'] = MISSING_DATE;
            } elseif (preg_match("#Pick[\-\s‐]*up[:\s]+(.+)\s+on\s+(\d+\/\d+\/\d+(?:\s*at\s*\d{1,2}(?::\d{1,2})?(?:\s*[ap]m))?)#u",
                $segment, $m)) {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['DepName'] = $m[1];
                $seg['DepDate'] = strtotime($this->normalizeDate($m[2]));
                $seg['ArrDate'] = MISSING_DATE;
            }
            // can be a city code instead of an airport code
            if (preg_match("#Drop.off[:\s]+([A-Z]{3})\n#u", $segment,
                    $m) && empty($this->pdf->FindSingleNode("(//text()[normalize-space(.)='City:']/ancestor::tr[1][contains(.,'" . '(' . $m[1] . ')' . "')])[1]"))
                && empty($this->pdf->FindSingleNode("(//text()[normalize-space(.)='City:']/ancestor::tr[1]/preceding-sibling::tr[normalize-space()][1][contains(.,'" . '(' . $m[1] . ')' . "')])[1]"))) {
                $seg['ArrCode'] = $m[1];
            } elseif (preg_match("#Drop.off[:\s]+(.+)\n#u", $segment, $m)) {
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrName'] = $m[1];
            }

            if (preg_match("#Details[: ]+(.+?)[ ]*[\-‐][ ]*(.+?)(?:[ \-‐]+(?:OW.*|RT.*))?\n#u", $segment, $m)
                || preg_match("#\n(.+?)[ ]*[\-‐][ ]*(.+?)(?:[ \-‐]+(?:OW.*|RT.*))?\s*Details[: ]+\n#u", $segment, $m)) {
                if (isset($seg['DepName'])) {
                    $seg['DepName'] .= ', ' . $m[1];
                } else {
                    $seg['DepName'] = $m[1];
                }
                $seg['ArrName'] = $m[2];
            }

            if (preg_match("#Transfer city[\s:]+.*?([A-Z]{3})#", $segment, $m)) {
                $seg['DepCode'] = $m[1];
            }

            $tot = $this->getTotalCurrency($this->re('/Total transfer price[\s*:]+(\$.+)/', $segment));

            if (empty($tot)) {
                $tot = $this->re('/\n\s*(\$.+)\s+Total transfer price[ :]+(\n|$)/', $segment);
            }

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }

            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }

        return $its;
    }

    private function parseEmailTrain()
    {
        $nodes = $this->pdf->XPath->query("descendant::td[starts-with(normalize-space(.),'Rail:')]");
        $its = [];

        foreach ($nodes as $i => $node) {
            $num = $i + 1;
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

            $it['RecordLocator'] = CONFNO_UNKNOWN;
            $it['TripNumber'] = $this->pdf->FindSingleNode("descendant::td[starts-with(normalize-space(.),'Ref Booking') or starts-with(normalize-space(.),'Quote ID')]",
                null, true, "#:\s+([A-Z\d]+)#");
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("descendant::td[starts-with(normalize-space(.),'Issue date')]",
                null, true, "#:\s+(.+?)\s*(?:\(|$)#")));

            $c = $this->countTrBetween(['Traveller(s):', 'Title'], 'Details');
            $pax = $this->pdf->XPath->query("descendant::td[normalize-space(.)='Traveller(s):']/ancestor::tr[1][contains(.,'Title') or preceding-sibling::tr[1][contains(.,'Title')]]/following-sibling::tr[position()<{$c}][normalize-space(.)!='']");

            if ($pax->length > 0) {
                foreach ($pax as $p) {
                    $it['Passengers'][] = implode(" ", $this->pdf->FindNodes("./td", $p));
                }
            }

            $c = 20;

            if ($pos1 = $this->pdf->XPath->query("(descendant::td[starts-with(normalize-space(.),'Rail:')]/ancestor::tr[1])[{$num}]/preceding-sibling::tr")->length) {
                if ($pos2 = $this->pdf->XPath->query("(descendant::td[starts-with(normalize-space(.),'Rail:')]/ancestor::tr[1])[{$num}]/following-sibling::tr[td[starts-with(normalize-space(.),'Total rail price:')]][1]/preceding-sibling::tr")->length) {
                    $c = $pos2 - $pos1 + 3;
                }
            }

            $segment = implode("\n",
                $this->pdf->FindNodes("(descendant::td[starts-with(.,'Rail:')]/ancestor::tr[1])[{$num}]/preceding::tr[1]/following-sibling::tr[position()<{$c}]"));
            $seg = [];
            $seg['DepName'] = $this->re("#Origin \(City\)[ :]+(.+)#", $segment);

            if (empty($seg['DepName'])) {
                $seg['DepName'] = $this->re("#(.+)\s+Origin \(City\)[ :]+\s+Destination \(City\)#", $segment);
            }
            $seg['ArrName'] = $this->re("#Destination \(City\)[ :]+(.+)#", $segment);

            $depDate = $this->re("#Departure[ :]+(\d+\/\d+\/\d+.*)#", $segment);

            if (empty($depDate)) {
                $depDate = $this->re("#\n\s*(\d+\/\d+\/\d+.*)\s+Departure[ :]+#", $segment);
            }

            if (!empty($depDate)) {
                $seg['DepDate'] = strtotime($this->normalizeDate($depDate));
            }
            $arrDate = $this->re("#Arrival[ :]+(\d+\/\d+\/\d+.*)#", $segment);

            if (empty($arrDate)) {
                $arrDate = $this->re("#\n\s*(\d+\/\d+\/\d+.*)\s+Arrival[ :]+#", $segment);
            }

            if (!empty($arrDate)) {
                $seg['ArrDate'] = strtotime($this->normalizeDate($arrDate));
            }
            $seg['FlightNumber'] = $this->re("#Train Number[: ]+(.+)#", $segment);
            $seg['Cabin'] = $this->re("#Standard of Class[: ]+(.+)#", $segment);
            $seg['Duration'] = $this->re("#Countries Included[ :]+.+?(?:(\d .+)|$)#", $segment);

            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            $node = $this->re('/Total rail price[ :]+(\S.+)/', $segment);

            if (empty($node)) {
                $node = $this->re('/\n\s*(\$.+)\s+Total rail price[\s:]+(\n|$)/', $segment);
            }

            $tot = $this->getTotalCurrency($node);

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }

            $it['TripSegments'][] = $seg;
            $its[] = $it;
        }

        return $its;
    }

    private function parseEmailCar()
    {
        $nodes = $this->pdf->XPath->query("descendant::td[starts-with(normalize-space(.),'Car hire:')]");
        $its = [];

        foreach ($nodes as $i => $node) {
            $num = $i + 1;
            /** @var \AwardWallet\ItineraryArrays\CarRental $it */
            $it = ['Kind' => 'L'];
            $it['Number'] = CONFNO_UNKNOWN;
            $it['TripNumber'] = $this->pdf->FindSingleNode("descendant::td[starts-with(normalize-space(.),'Ref Booking') or starts-with(normalize-space(.),'Quote ID')]",
                null, true, "#:\s+([A-Z\d]+)#");
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("descendant::td[starts-with(normalize-space(.),'Issue date')]",
                null, true, "#:\s+(.+?)\s*(?:\(|$)#")));

            $c = 20;

            if ($pos1 = $this->pdf->XPath->query("(descendant::td[starts-with(normalize-space(.),'Car hire:')]/ancestor::tr[1])[{$num}]/preceding-sibling::tr")->length) {
                if ($pos2 = $this->pdf->XPath->query("(descendant::td[starts-with(normalize-space(.),'Car hire:')]/ancestor::tr[1])[{$num}]/following-sibling::tr[td[starts-with(normalize-space(.),'Total car hire price:')]][1]/preceding-sibling::tr")->length) {
                    $c = $pos2 - $pos1 + 5;
                }
            }

            $segment = implode("\n",
                $this->pdf->FindNodes("(descendant::td[starts-with(.,'Car hire:')]/ancestor::tr[1]/following-sibling::tr[1][contains(.,'Company')])[{$num}]/preceding::tr[2]/following-sibling::tr[position()<{$c}]"));

            if (!preg_match("#Pick.?up[\s:]+#u", $segment)) {//it-10998665.eml
                continue;
            }

            if (preg_match("#Pick.?up[\s:]+\d+\/\d+#u", $segment)) {//it-10998671.eml
                continue;
            }

            if (preg_match("#Pick up[\s:]+(.+)\s+(\d \d{3} [\d ]+)\s+on\s+(\d+\/\d+\/\d+.*)#", $segment, $m)) {
                $it['PickupLocation'] = $m[1];
                $it['PickupDatetime'] = strtotime($this->normalizeDate($m[3]));
                $it['PickupPhone'] = $m[2];
            } elseif (preg_match("#Pick up[\s:]+(.+)\s+(?:on\s+)?(\d+\/\d+\/\d+.*)#", $segment, $m)) {
                $it['PickupLocation'] = $m[1];
                $it['PickupDatetime'] = strtotime($this->normalizeDate($m[2]));
            }

            if (preg_match("#Drop off[\s:]+(.+)\s+(\d \d{3} [\d ]+)?\s*on\s+(\d+\/\d+\/\d+.*)#", $segment, $m)) {
                $it['DropoffLocation'] = $m[1];
                $it['DropoffDatetime'] = strtotime($this->normalizeDate($m[3]));

                if (isset($m[2])) {
                    $it['DropoffPhone'] = $m[2];
                }
            }
            $it['Status'] = $this->re("#Booking Status[\s:]+(.+)#", $segment);
            $it['RentalCompany'] = $this->re("#Company[\s:]+(.+)#", $segment);
            $it['CarType'] = $this->re("#Car type[\s:]+(.+)#", $segment);

            // Total
            // Currency
            $tot = $this->re('/Total car hire price[\s:]+(\\$[\d\.\, ]+)(?:$|\s+)/', $segment);

            if (empty($tot)) {
                $tot = $this->re('/\n\s*(\\$[\d\.\, ]+)\s+Total car hire price[\s:]+/', $segment);
            }
            $tot = $this->getTotalCurrency($tot);

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }

            $its[] = $it;
        }

        return $its;
    }

    private function parseEmailHotel()
    {
        $nodes = $this->pdf->XPath->query("descendant::td[starts-with(normalize-space(.),'Accommodation:')]");
        $its = [];

        foreach ($nodes as $i => $node) {
            $num = $i + 1;
            /** @var \AwardWallet\ItineraryArrays\CarRental $it */
            $it = ['Kind' => 'R'];
            $it['ConfirmationNumber'] = CONFNO_UNKNOWN;
            $it['TripNumber'] = $this->pdf->FindSingleNode("descendant::td[starts-with(normalize-space(.),'Ref Booking') or starts-with(normalize-space(.),'Quote ID')]",
                null, true, "#:\s+([A-Z\d]+)#");
            $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("descendant::td[starts-with(normalize-space(.),'Issue date')]",
                null, true, "#:\s+(.+?)\s*(?:\(|$)#")));

            $c = 20;

            if ($pos1 = $this->pdf->XPath->query("(descendant::td[starts-with(normalize-space(.),'Accommodation:')]/ancestor::tr[1])[{$num}]/preceding-sibling::tr")->length) {
                if ($pos2 = $this->pdf->XPath->query("(descendant::td[starts-with(normalize-space(.),'Accommodation:')]/ancestor::tr[1])[{$num}]/following-sibling::tr[td[starts-with(normalize-space(.),'Total accommodation price:')]][1]/preceding-sibling::tr")->length) {
                    $c = $pos2 - $pos1 + 5;
                }
            }

            $segment = implode("\n",
                $this->pdf->FindNodes("(descendant::td[starts-with(.,'Accommodation:')]/ancestor::tr[1]/following-sibling::tr[1][contains(.,'Company') or (contains(.,'Staying at:'))])[{$num}]/preceding::tr[2]/following-sibling::tr[position()<{$c}]"));

            if (preg_match("#Staying at:[ ]*(.+)#", $segment, $m)) {
                $it['HotelName'] = $m[1];
            }

            if (preg_match("#Address:[ ]*(.+)#", $segment, $m)) {
                $it['Address'] = $m[1];
            } elseif (preg_match("#City:[ ]*(.+)#", $segment, $m)) {
                $it['Address'] = $m[1];
            }

            $checkIn = $this->re("#Check in[ :]+(\d+\/\d+\/\d+.*)#", $segment);

            if (empty($checkIn)) {
                $checkIn = $this->re("#\n\s*(\d+\/\d+\/\d+.*)\s+Check in[ :]+#", $segment);
            }

            if (!empty($checkIn)) {
                $it['CheckInDate'] = strtotime($this->normalizeDate($checkIn));
            }

            $checkOut = $this->re("#Check out[ :]+(\d+\/\d+\/\d+.*)#", $segment);

            if (empty($checkOut)) {
                $checkOut = $this->re("#\n\s*(\d+\/\d+\/\d+.*)\s+Check out[ :]+#", $segment);
            }

            if (!empty($checkOut)) {
                $it['CheckOutDate'] = strtotime($this->normalizeDate($checkOut));
            }

            // GuestNames
            $c = $this->countTrBetween(['Traveller(s):', 'Title'], 'Details');
            $pax = $this->pdf->XPath->query("descendant::td[normalize-space(.)='Traveller(s):']/ancestor::tr[1][contains(.,'Title') or preceding-sibling::tr[1][contains(.,'Title')]]/following-sibling::tr[position()<{$c}][normalize-space(.)!='']");

            if ($pax->length > 0) {
                foreach ($pax as $p) {
                    $it['GuestNames'][] = implode(" ", $this->pdf->FindNodes("./td", $p));
                }
            }

            // Rooms
            if (preg_match("#Number of Rooms:[ ]*(\d+)#", $segment, $m)) {
                $it['Rooms'] = $m[1];
            }

            // RoomType
            if (preg_match("#Room type:[ ]*(.+)#", $segment, $m)) {
                $it['RoomType'] = $m[1];
            }

            // Total
            // Currency
            $tot = $this->re('/Total accommodation price[\s:]+(\\$[\d\.\, ]+)(?:$|\s+)/', $segment);

            if (empty($tot)) {
                $tot = $this->re('/\n\s*(\\$[\d\.\, ]+)\s+Total accommodation price[\s:]+/', $segment);
            }
            $tot = $this->getTotalCurrency($tot);

            if (!empty($tot['Total'])) {
                $it['Total'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }

            $its[] = $it;
        }

        return $its;
    }

    private function countTrBetween($first, $second, $order = 1, $startsFirst = false)
    {
        if (is_array($first)) {
            if (count($first) === 2) {
                if ($pos1 = $this->pdf->XPath->query("(descendant::td[normalize-space(.)='{$first[0]}']/ancestor::tr[1][(contains(.,'{$first[1]}') or preceding-sibling::tr[1][contains(.,'{$first[1]}')])])[{$order}]/preceding-sibling::tr")->length) {
                    if ($pos2 = $this->pdf->XPath->query("(descendant::td[normalize-space(.)='{$first[0]}']/ancestor::tr[1][(contains(.,'{$first[1]}') or preceding-sibling::tr[1][contains(.,'{$first[1]}')])])[{$order}]/following-sibling::tr[td[normalize-space(.)='{$second}']][1]/preceding-sibling::tr")->length) {
                        return $pos2 - $pos1;
                    }
                }
            }
        } else {
            if ($startsFirst) {
                $ruleFirst = "starts-with(normalize-space(.),'{$first}')";
            } else {
                $ruleFirst = "normalize-space(.)='{$first}'";
            }

            if ($pos1 = $this->pdf->XPath->query("(descendant::td[{$ruleFirst}]/ancestor::tr[1])[{$order}]/preceding-sibling::tr")->length) {
                if ($pos2 = $this->pdf->XPath->query("(descendant::td[{$ruleFirst}]/ancestor::tr[1])[{$order}]/following-sibling::tr[td[normalize-space(.)='{$second}']][1]/preceding-sibling::tr")->length) {
                    return $pos2 - $pos1;
                }
            }
        }

        return 0;
    }

    private function countPBetween($first, $second, $order = 1)
    {
        if (is_array($first)) {
            if (count($first) === 2) {
                if ($pos1 = $this->pdfComplex->XPath->query("(//text()[starts-with(normalize-space(.),'{$first[0]}') and following::p[normalize-space(.)][position()<4][starts-with(normalize-space(.),'{$first[1]}')]]/ancestor::p[1])[{$order}]/preceding::p")->length) {
                    if ($pos2 = $this->pdfComplex->XPath->query("(//text()[starts-with(normalize-space(.),'{$first[0]}') and following::p[normalize-space(.)][position()<4][starts-with(normalize-space(.),'{$first[1]}')]]/ancestor::p[1])[{$order}]/following::p[starts-with(normalize-space(.),'{$second}')][1]/preceding::p")->length) {
                        return $pos2 - $pos1;
                    }
                }
            }
        } else {
            if ($pos1 = $this->pdfComplex->XPath->query("(//text()[starts-with(normalize-space(.),'{$first}')]/ancestor::p[1])[{$order}]/preceding::p")->length) {
                if ($pos2 = $this->pdfComplex->XPath->query("(//text()[starts-with(normalize-space(.),'{$first}')]/ancestor::p[1])[{$order}]/following::p[starts-with(normalize-space(.),'{$second}')][1]/preceding::p")->length) {
                    return $pos2 - $pos1;
                }
            }
        }

        return 0;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (stripos($body, $this->prov) === false) {
            return false;
        }

        foreach ($this->reBody as $lang => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("$", "AUD", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function tablePdf(\PlancakeEmailParser $parser, $num = 0)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!isset($pdfs[$num])) {
            return false;
        }
        $pdf = $pdfs[$num];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetBody($html);
        $this->pdfHtml = clone $this->http;
        $this->pdfComplex = clone $this->http;
        $NBSP = chr(194) . chr(160);
        $this->pdfComplex->SetBody(str_replace($NBSP, ' ', html_entity_decode($html)));
        $html = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");
        $html .= "<table border='1'>";

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $cols = [];
            $grid = [];
            $prevTop = null;

            foreach ($nodes as $node) {
                $text = implode(' ', $this->pdf->FindNodes(".//text()", $node));

                if (empty(trim($text))) {
                    continue;
                }
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");

                if (isset($prevTop) && abs($prevTop - $top) < 3) {
                    $top = $prevTop;
                } else {
                    $prevTop = $top;
                }
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $grid[$top][$left] = $text;
            }

            ksort($grid);

            foreach ($grid as $row => $c) {
                ksort($c);

                if (preg_match("/^\s*Page\s*\d+\s*of\s*\d+/", implode(" ", $c))) {
                    continue;
                }
                $html .= "<tr>";

                foreach ($c as $col) {
                    $html .= "<td>" . $col . "</td>";
                }
                $html .= "</tr>";
            }
        }
        $html .= "</table>";
        //	echo $html;
        $this->pdf->SetBody($html);

        return true;
    }
}
