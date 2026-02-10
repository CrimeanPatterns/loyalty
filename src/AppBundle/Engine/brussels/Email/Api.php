<?php

namespace AwardWallet\Engine\brussels\Email;

class Api extends \TAccountChecker
{
    public $mailFiles = "brussels/it-10291642.eml, brussels/it-10299581.eml, brussels/it-10346845.eml, brussels/it-10407183.eml";

    public $reFrom = "noreply@notifications.brusselsairlines.com";

    public $reSubject = [
        "en"=> "Get ready for your trip on",
    ];

    public $lang = "en";

    private $type = 'UnknownType';

    /** @var \PlancakeEmailParser */
    private $parser;

    public function parseLink(&$itineraries)
    {
        $pdfs = $this->parser->searchAttachmentByName('e\-ticket_\d+_[A-Z_]+\.pdf');
        // GetReadyForYourTrip
        $link = $this->http->FindSingleNode("(//a[contains(@href, 'manage-booking/details.aspx')])[1]/@href");

        if (preg_match("#lastname=(?<lastname>.*?)&pnr=(?<pnr>.*?)&#", $link, $m) && empty($pdfs)) {
            $this->type = 'GetReadyForYourTrip';
            $this->parseApi($itineraries, $this->parseProps($m['lastname'], $m['pnr']));

            return;
        }

        // go to parse by ETicketConfirmation
        if (!empty($pdfs)) {
            return;
        }

        $pdfs = $this->parser->searchAttachmentByName("EMD \d+.pdf");

        if (isset($pdfs[0])) {
            $pdf = \PDF::convertToText($this->parser->getAttachmentBody($pdfs[0]));
        } else {
            return false;
        }

        // PaymentDocumentPdfNl, PaymentDocumentPdfFr
        $dict = [
            'nl' => [
                'detect'             => "Dit document is je bewijs van betaling en kan gebruikt worden voor fiscale en wettelijke",
                'passenger'          => 'PASSAGIER',
                'reservation number' => 'Reservatienummer',
            ],
            'fr' => [
                'detect'             => "Ce document est votre preuve de paiement",
                'passenger'          => 'NOM DU PASSAGER',
                'reservation number' => 'Référence de réservation',
            ],
        ];

        foreach ($dict as $lang=>$trans) {
            if (strpos($pdf, $trans['detect']) !== false) {
                $this->type = 'PaymentDocumentPdf' . ucfirst($lang);
                $data = [];

                foreach ($pdfs as $pdf) {
                    $pdf = \PDF::convertToText($this->parser->getAttachmentBody($pdfs[0]));

                    if (count($table = $this->splitCols($this->re("#(?:\n|^)(\s*" . $trans['passenger'] . ".*?)\n\n#ms", $pdf))) != 2) {
                        $this->logger->info("incorrect parse table");

                        return;
                    }
                    $lastname = $this->re("#" . $trans['passenger'] . "\s+([A-Z]+)/#ms", $table[0]);
                    $pnr = $this->re("#" . $trans['reservation number'] . "\s+([A-Z\d]{6})\n#ms", $table[1]);

                    if (empty($lastname) || empty($pnr)) {
                        $this->logger->info("lastname or pnr not matched");

                        return;
                    }
                    $data[$lastname . '-' . $pnr] = ['lastname'=>$lastname, 'pnr'=>$pnr];
                }

                foreach ($data as $d) {
                    $this->parseApi($itineraries, $this->parseProps($d['lastname'], $d['pnr']));
                }

                return;
            }
        }
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
        $pdfs = $parser->searchAttachmentByName("EMD \d+.pdf");

        if (isset($pdfs[0])) {
            $pdf = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));
        }

        if (stripos($body, 'brusselsairlines') === false
        && stripos($body, 'Brussels Airlines') === false
        && (!isset($pdf) || stripos($pdf, 'brusselsairlines') === false)) {
            return false;
        }

        // GetReadyForYourTrip
        if (preg_match("#lastname=(?<lastname>.*?)&pnr=(?<pnr>.*?)&#", $this->http->FindSingleNode("(//a[contains(@href, 'manage-booking/details.aspx')])[1]/@href"), $m)) {
            return true;
        }

        // PaymentDocumentPdf
        $detect = [
            'nl' => "Dit document is je bewijs van betaling en kan gebruikt worden voor fiscale en wettelijke",
            'fr' => "Ce document est votre preuve de paiement",
        ];

        foreach ($detect as $word) {
            if (isset($pdf) && strpos($pdf, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->parser = $parser;

        $this->http->FilterHTML = true;
        $itineraries = [];

        $this->parseLink($itineraries);

        $result = [
            'emailType'  => $this->type,
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return ["en", "nl", "fr"];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    private function parseProps($lastname, $pnr)
    {
        $this->http->GetUrl("https://www.brusselsairlines.com/en-be/practical-information/manage-booking/details.aspx?lastname=" . $lastname . "&pnr=" . $pnr . "&utm_source=sn&utm_medium=email&utm_campaign=pre_departure&utm_content=flight");

        if ($this->http->Response['code'] == 500) {
            return null;
        }
        $props = ['lastname'=>$lastname, 'pnr'=>$pnr];
        $props['publicationID'] = $this->http->FindSingleNode("//input[@id='pubID']/@value");
        $props['country'] = $this->http->FindSingleNode("//input[@id='country']/@value");
        $props['locale'] = $this->http->FindSingleNode("//input[@id='locale']/@value");
        $props['currency'] = $this->http->FindSingleNode("//input[@id='currency']/@value");
        $props['language'] = 'EN';

        return $props;
    }

    private function parseApi(&$itineraries, $params)
    {
        if (count(array_filter($params)) != 7) {
            $this->logger->info("incorrect api params");

            return;
        }
        $this->http->GetUrl("https://www.brusselsairlines.com/api/Booking/GetExtendedBookingData/?" . http_build_query($params));

        if ($this->http->Response['code'] != 200) {
            $this->logger->info('HTTP Code: ' . $this->http->Response['code']);
            $this->logger->info("Response: " . $this->http->Response['body']);

            return;
        }

        if (!$xml = simplexml_load_string($this->http->Response['body'], "SimpleXMLElement", 0, "", false)) {
            $this->logger->info("Response reading failed");

            return;
        }
        $ns = $xml->getNameSpaces(true);

        if ($xml->ApiBooking != null && isset($ns["d2p1"])) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $params['pnr'];

            // TripNumber
            // Passengers
            // TicketNumbers
            // AccountNumbers
            $it['Passengers'] = $it['TicketNumbers'] = $it['AccountNumbers'] = [];

            if (!empty($xml->ApiBooking->children($ns["d2p1"]))
            && !empty($xml->ApiBooking->children($ns["d2p1"])->Passengers->children()->Passenger)) {
                foreach ($xml->ApiBooking->children($ns["d2p1"])->Passengers->children()->Passenger as $p) {
                    $it['Passengers'][] = (string) $p->User->FirstName . ' ' . (string) $p->User->LastName;
                    $it['TicketNumbers'][] = (string) $p->eTicket->eTicketNumber;
                    $it['AccountNumbers'][] = (string) $p->User->FrequentFlyerDetails->MemberID;
                }
            }
            $it['AccountNumbers'] = array_filter($it['AccountNumbers']);

            // Cancelled
            // TotalCharge
            $it['TotalCharge'] = $this->amount((string) $xml->ApiBooking->children($ns["d2p1"])->PriceInformation->TotalAmount);

            // BaseFare
            // Currency
            $it['Currency'] = $this->currency((string) $xml->ApiBooking->children($ns["d2p1"])->PriceInformation->TotalAmount);

            // Tax
            $it['Tax'] = $this->amount((string) $xml->ApiBooking->children($ns["d2p1"])->PriceInformation->Taxes);

            // SpentAwards
            // EarnedAwards
            // Status
            $it['Status'] = (string) $xml->FlightStates->BookingFlightState->Status;

            // ReservationDate
            // NoItineraries
            // TripCategory
            foreach ($xml->ApiBooking->children($ns["d2p1"])->ItineraryParts->children()->ItineraryPart as $ItPart) {
                foreach ($ItPart->Sectors->Sector as $sector) {
                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = (string) $sector->Flight->FlightNumber;

                    // DepCode
                    $itsegment['DepCode'] = (string) $sector->Flight->Origin;

                    // DepName
                    $itsegment['DepName'] = (string) $sector->Flight->OriginName;

                    // DepartureTerminal
                    // DepDate
                    $itsegment['DepDate'] = strtotime((string) $sector->Flight->departureDateTime);

                    // ArrCode
                    $itsegment['ArrCode'] = (string) $sector->Flight->Destination;

                    // ArrName
                    $itsegment['ArrName'] = (string) $sector->Flight->DestinationName;

                    // ArrivalTerminal
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime((string) $sector->Flight->arrivalDateTime);

                    // AirlineName
                    $itsegment['AirlineName'] = (string) $sector->Flight->CarrierCodeName;

                    // Operator
                    // Aircraft
                    $itsegment['Aircraft'] = (string) $sector->Flight->AircraftDescription;

                    // TraveledMiles
                    // AwardMiles
                    // Cabin
                    $itsegment['Cabin'] = (string) $sector->CabinClass;

                    // BookingClass
                    $itsegment['BookingClass'] = (string) $sector->BookingClass;

                    // PendingUpgradeTo
                    // Seats
                    $itsegment['Seats'] = [];

                    foreach ($ItPart->Passengers->ItineraryPassenger as $p) {
                        foreach ($p->Flights->PassengerFlight as $pf) {
                            $itsegment['Seats'][] = $this->re("#^(\d+\w)$#", (string) $pf->Seat);
                        }
                    }
                    $itsegment['Seats'] = array_filter($itsegment['Seats']);

                    // Duration
                    // Meal
                    // Smoking
                    // Stops
                    $itsegment['Stops'] = (string) $sector->Flight->Stops;

                    $it['TripSegments'][] = $itsegment;
                }
            }

            $itineraries[] = $it;
        }
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }
}
