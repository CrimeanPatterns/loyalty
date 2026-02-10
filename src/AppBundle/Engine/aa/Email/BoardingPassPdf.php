<?php

namespace AwardWallet\Engine\aa\Email;

class BoardingPassPdf extends \TAccountCheckerAa
{
    public $mailFiles = "aa/it-10464679.eml, aa/it-41772506.eml, aa/it-94417080.eml";

    public $reFrom = ["americanairlines@aa.com"];
    public $reBody = [
        'en' => ['Boarding Pass', 'Departing at'],
    ];
    public $reSubject = [
        'American Airlines Boarding Pass(es)',
    ];
    public $pdfNamePattern = ".*[A-Z\d]{5,6}\.pdf";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null
                    && $this->detectBody($text)
                ) {
                    $its = array_merge($its, $this->parseEmail($text));
                }
            }
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'BoardingPassPdf',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, ' aa.com/') !== false)
                && $this->detectBody($text)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && self::detectEmailFromProvider($headers['from']) !== false && isset($this->reSubject)) {
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
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseEmail(string $text)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];

        if (preg_match("#Record Locator:\s+\b([A-Z\d]{5,8})\b#", $text, $m)) {
            $it["RecordLocator"] = $m[1];
        }

        $bps = $this->splitter("#([ ]*Boarding [Pp]ass\s+Record Locator:)#", $text);

        foreach ($bps as $bp) {
            if (preg_match('/Seat ?: .+\n\s*(\w+) \/ (.+?)(MS|MR|CHILD)?( {3,}|\n)/', $bp, $m)) {
                $it["Passengers"][] = trim($m[2]) . ' ' . trim($m[1]);
            } elseif (preg_match('/Record Locator:\s+\b[A-Z\d]{5,8}.*\n(?: {20,}.*\n)+ {0,20}(.+) \/ (.+?)(MS|MR|CHILD)?[ ]{2,}/', $bp, $m)) {
                $it["Passengers"][] = trim($m[2]) . ' ' . trim($m[1]);
            }

            if (preg_match("#Frequent Flyer Number:[ ]+([A-Z\d]+)\b#", $bp, $m)) {
                $it['AccountNumbers'][] = $m[1];
            }

            if (preg_match("#Ticket:[ ]+([\d\-]+)\b#", $bp, $m)) {
                $it['TicketNumbers'][] = $m[1];
            }
        }

        if (isset($it["Passengers"])) {
            $it["Passengers"] = array_unique($it["Passengers"]);
        }

        if (isset($it["AccountNumbers"])) {
            $it["AccountNumbers"] = array_unique($it["AccountNumbers"]);
        }

        if (isset($it["TicketNumbers"])) {
            $it["TicketNumbers"] = array_unique($it["TicketNumbers"]);
        }

        foreach ($bps as $bp) {
//             $this->logger->debug('$bp = '. print_r($bp, true));
            if (preg_match("#^[ ]+([A-Z]{3})[ ]+([A-Z]{3})\s+(.+?)[ ]+to[ ]+(.+?)[ ]{3,}Departing:[ ](.+)#m", $bp,
                $m)) {
                $num = $this->searchSegment($it['TripSegments'], $m[1], $m[2]);

                if (null !== $num) {
                    if (preg_match("#Seat ?:[ ](\d+[A-z])#", $bp, $m)) {
                        $it['TripSegments'][$num]['Seats'][] = $m[1];
                    }

                    continue;
                }
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                $seg['DepCode'] = $m[1];
                $seg['ArrCode'] = $m[2];
                $seg['DepName'] = $m[3];
                $seg['ArrName'] = $m[4];

                if (preg_match("/\d{4}/", $m[5])) {
                    $date = strtotime($m[5]);
                } elseif (preg_match("#[ ]{3,}Departing:[ ].+\n.{50,}[ ]{3,}(20\d{2})(?: {3,}|\n)#", $bp,
                    $mat)) {
                    $date = strtotime($m[5] . ' ' . $mat[1]);
                }

                if (preg_match("#Seat ?:[ ](\d+[A-z])#", $bp, $m)) {
                    $seg['Seats'][] = $m[1];
                }

                if (preg_match("#Departing at[: ]+((?i)\d+:\d+(?:[ ]*[ap]m)?)#", $bp, $m)) {
                    $seg['DepDate'] = strtotime($m[1], $date);
                }

                if (preg_match("#Arriving at[: ]+(?:(?i)\d+:\d+(?:[ ]*[ap]m)?)(?: *\([A-Z]{2,5}\))? +(\w{1,5}[ ,]{1,2}\w{1,5}[ ,]{1,2}\d{4})(?: {3,}|\n)#", $bp, $m)) {
                    $date = strtotime($m[1]);
                }

                if (preg_match("#Arriving at[: ]+((?i)\d+:\d+(?:[ ]*[ap]m)?)#", $bp, $m)) {
                    $seg['ArrDate'] = strtotime($m[1], $date);
                } elseif (preg_match("#Arriving at[: ]{0,3}(?: {10,}.*)?\n {20,}((?i)\d+:\d+(?:[ ]*[ap]m)?)(?: {3,}| \(|\n|$)#", $bp, $m)) {
                    $seg['ArrDate'] = strtotime($m[1], $date);
                }

                if (preg_match("#More Flight Details[: ]+(\d+h(?:[ ]\d+m)?)[ ]*\n#", $bp, $m)) {
                    $seg['Duration'] = $m[1];
                }

                if (preg_match("#\n[ ]*Terminal (?!\-)(.+)#", $bp, $m)) {
                    $seg['DepartureTerminal'] = $m[1];
                }

                if (preg_match("#\n[ ]+([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d+)[ ]{3,}#", $bp, $m)
                || preg_match("#\n[ ]{0,10}\w{1,3} {3,}([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d+)[ ]{3,}\d{1,3}[A-Z]\s+#", $bp, $m)
                ) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $it['TripSegments'][] = $seg;
            }
        }

        return [$it];
    }

    private function searchSegment(array $segs, string $dep, string $arr): ?int
    {
        foreach ($segs as $i => $seg) {
            if (isset($seg['DepCode'], $seg['ArrCode'])
                && $seg['DepCode'] === $dep && $seg['ArrCode'] === $arr
            ) {
                return $i;
            }
        }

        return null;
    }

    private function detectBody($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
}
