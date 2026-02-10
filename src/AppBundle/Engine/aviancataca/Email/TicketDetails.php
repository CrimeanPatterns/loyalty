<?php

namespace AwardWallet\Engine\aviancataca\Email;

// parsers with similar PDF-formats: airtransat/BoardingPass, asiana/BoardingPassPdf, aviancataca/BoardingPass, czech/BoardingPass, lotpair/BoardingPass, sata/BoardingPass, tamair/BoardingPassPDF(object), tapportugal/AirTicket, luxair/YourBoardingPassNonPdf, saudisrabianairlin/BoardingPass

class TicketDetails extends \TAccountChecker
{
    public $mailFiles = "aviancataca/it-5257567.eml, aviancataca/it-5325326.eml";
    private $pdfBody = '';
    private static $detectBody = [
        'pt' => ['Documentos de Viagem', 'INFORMAÇÕES DE VIAGEM'],
    ];
    private $subject = 'Seu Cartao de embarque Avianca';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->checkEmail()) {
            $its = $this->parseEmail();
        } elseif ($this->checkEmail() === false && $parser->getAttachments()) {
            $its = $this->parsePDF();
        } else {
            $its = [];
        }

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->checkEmail()) {
            $body = $parser->getHTMLBody();

            if ($this->checkEmail() !== false && stripos($body, 'Avianca') !== false) {
                return true;
            }

            return false;
        } elseif ($parser->getAttachments() && $this->checkEmail() === false) {
            return $this->detectBody($parser);
        } else {
            return false;
        }
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'avianca.com') !== false
        && isset($headers['subject']) && stripos($headers['subject'], $this->subject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'avianca.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detectBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectBody);
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = [];
        $it['Kind'] = 'T';
        $it['RecordLocator'] = $this->http->FindSingleNode("(//span[contains(text(), 'Embarque')]/following-sibling::span[1])[1]", null, true, "#^\s*([A-Z\d]{5,7})\s*#");
        $it['Passengers'][] = $this->http->FindSingleNode("//span[contains(text(), 'Passageiro')]/following-sibling::span[1]");

        $xpath = "//span[contains(text(), 'Origem')]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $depInfo = $this->http->FindNodes("descendant::span[not(contains(text(), 'Origem')) and position() != last()]", $root);
            $seg['DepName'] = $depInfo[0];

            if (!empty($depInfo[1]) && preg_match('/terminal\s+(\d{1})/i', $depInfo[1], $m)) {
                $seg['DepartureTerminal'] = $m[1];
            }
            $depDate = $this->http->FindSingleNode('descendant::span[last()]', $root);

            if (stripos($depDate, '-') !== false) {
                $depDate = str_replace('-', ',', $depDate);
            }
            $seg['DepDate'] = strtotime($depDate);
            $arrInfo = $this->http->FindNodes("following-sibling::tr[2]/descendant::span[not(contains(text(), 'Destino')) and position() != last()]", $root);
            $seg['ArrName'] = $arrInfo[0];

            if (!empty($arrInfo[1]) && preg_match('/terminal\s+(\d{1})/i', $arrInfo[1], $m)) {
                $seg['ArrivalTerminal'] = $m[1];
            }
            $arrDate = $this->http->FindSingleNode('following-sibling::tr[2]/descendant::span[last()]', $root);

            if (stripos($arrDate, '-') !== false) {
                $arrDate = str_replace('-', ',', $arrDate);
            }
            $seg['ArrDate'] = strtotime($arrDate);
            $flight = $this->http->FindSingleNode("preceding-sibling::tr[1]/descendant::span[contains(text(), 'Voo')]/following-sibling::span[1]", $root);

            if (preg_match('/([A-Z]+)\s*(\d+)/', $flight, $m)) {
                $seg['FlightNumber'] = $m[2];
                $seg['AirlineName'] = $m[1];
            }

            if (isset($seg['DepDate']) && isset($seg['ArrDate']) && isset($seg['FlightNumber'])) {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }
            $seg['Cabin'] = $this->http->FindSingleNode("preceding-sibling::tr[1]/descendant::span[contains(text(), 'Voo')]/following-sibling::span[3]", $root);
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function parsePDF()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];
        $body = $this->pdfBody;
        $recordLoc = $this->cutText('BOOKING REF', 'TICKET', $body);

        if (preg_match('/([A-Z\d]{5,7})/', $recordLoc, $m)) {
            $it['RecordLocator'] = $m[1];
        }
        $accountNum = $this->cutText('ETKT', 'PRÓXIMOS', $body);

        if (preg_match('/(\d+).*/', $accountNum, $m)) {
            $it['AccountNumbers'][] = $m[1];
        }

        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];
        /**
         * artão de Embarque | Boarding Pass
         * Park / Cheol Woo Mr
         * DE | FROM                        PARA | TO
         * DECOLAGEM | DEPARTURE                                          POUSO | ARRIVAL
         * 22 Aug 2015
         * 17:30
         * SDU                                  CGH                           18:30
         * 22 Aug 2015
         * Rio De Janeiro Santos Du                                   Sao Paulo Congonhas
         * VOO | FLIGHT      ASSENTO | SEAT         EMBARQUE | BOARDING        PORTÃO | GATE       GRUPO | GROUP
         * O66013           8D                    16:50                     Check monitors                 B.
         */
        $segInfo = $this->cutText('Cartão de Embarque', 'INFORMAÇÕES DE VIAGEM', $body);
        $re = '/';
        $re .= '(?<Passenger>[\w\s]+) (?:Mr|Miss)[\s\D]+(?<DepDate>\d{2} \w+ \d{4})\s+(?<DepTime>\d+:\d+)\s+';
        $re .= '(?<DepCode>[A-Z]{3})\s+(?<ArrCode>[A-Z]{3})\s+(?<ArrTime>\d+:\d+)\s+(?<ArrDate>\d{2} \w+ \d{4})';
        $re .= '[\s\D\d]+\s+(?<AirlineName>[A-Z]+)\s*(?<Number>\d+)\s+(?<Seat>\w+)\s+.+(?<Class>[A-Z]{1})';
        $re .= '/i';

        if (preg_match($re, $segInfo, $m)) {
            $it['Passengers'][] = trim($m['Passenger']);
            $seg['DepDate'] = strtotime($m['DepDate'] . ' ' . $m['DepTime']);
            $seg['DepCode'] = $m['DepCode'];
            $seg['ArrCode'] = $m['ArrCode'];
            $seg['ArrDate'] = strtotime($m['ArrDate'] . ' ' . $m['ArrTime']);
            $seg['AirlineName'] = $m['AirlineName'];
            $seg['FlightNumber'] = $m['Number'];
            $seg['Seats'] = $m['Seat'];
            $seg['BookingClass'] = $m['Class'];
        }
        $cabin = $this->cutText('CLASS OF TRAVEL', 'LOCALIZADOR', $body);

        if (preg_match('/(economy)/i', $cabin, $m)) {
            $seg['Cabin'] = $m[1];
        }
        $it['TripSegments'][] = $seg;

        return [$it];
    }

    private function cutText($start, $finish, $text)
    {
        if (!empty($start) && !empty($finish) && !empty($text)) {
            $text = stristr(stristr($text, $start), $finish, true);

            return substr($text, strlen($start));
        }

        return false;
    }

    private function checkEmail()
    {
        if ($this->http->XPath->query("//span[contains(text(), 'Detalhes da Viagem')]")->length > 0) {
            return true;
        }

        return false;
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (empty($pdfs)) {
            return false;
        }
        $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));
        $this->pdfBody = $body;

        foreach (self::$detectBody as $detect) {
            if (is_array($detect)) {
                if (stripos($body, $detect[0]) !== false && stripos($body, $detect[1]) !== false) {
                    return true;
                }
            } elseif (is_string($detect) && stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }
}
