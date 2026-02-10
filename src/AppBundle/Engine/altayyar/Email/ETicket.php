<?php

namespace AwardWallet\Engine\altayyar\Email;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "altayyar/it-11038926.eml, altayyar/it-11075312.eml, altayyar/it-8716273.eml";

    public $reFrom = "altayyaronline.com";
    public $reBody = [
        'en' => ['Al Tayyar Online Electronic Ticket', 'Passenger Itinerary Receipt'],
    ];
    public $reSubject = [
        'FLIGHT VOUCHER',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ETicket' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'altayyaronline.com')] | //text()[contains(normalize-space(.),'Al Tayyar')]")->length > 0) {
            return $this->AssignLang();
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

    private function parseEmail()
    {
        $its = [];
        $tripNum = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Reservation Number')]/following::text()[normalize-space(.)!=''][1]", null, true, "#^\s*([A-Z\d]+)\s*$#");
        $pax = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'Name')]/ancestor::tr[1][contains(.,'Ticket No')]/following-sibling::tr/td[1]");
        $ticket = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'Name')]/ancestor::tr[1][contains(.,'Ticket No')]/following-sibling::tr/td[2]");
        $resDate = $this->normalizeDate($this->http->FindSingleNode("(//text()[starts-with(normalize-space(.),'Name')]/ancestor::tr[1][contains(.,'Ticket No')]/following-sibling::tr/td[4])"));

        $airs = [];
        $xpath = "//text()[starts-with(normalize-space(.),'Depart')]/ancestor::table[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./preceding-sibling::table[1]//text()[contains(.,'Airline reference number')]", $root, true, "#[\s:]+([A-Z\d]+)#");

            if (!empty($rl)) {
                $airs[$rl][] = $root;
            } else {
                $airs[CONFNO_UNKNOWN][] = $root;
            }
        }

        foreach ($airs as $rl => $nodes) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['TripNumber'] = $tripNum;
            $it['Passengers'] = $pax;
            $it['TicketNumbers'] = $ticket;
            $it['ReservationDate'] = $resDate;

            foreach ($nodes as $root) {
                $seg = [];
                $node = $this->http->FindNodes("./descendant::tr[count(descendant::tr)=0]/td[2]//text()[normalize-space(.)!='']", $root);

                if (count($node) == 2) {
                    if (preg_match("#\[([A-Z]{3})\]\s+(.+)[\s,]*$#s", $node[0], $m)) {
                        $seg['DepCode'] = $m[1];
                        $seg['DepName'] = $m[2];
                    }

                    if (preg_match("#,\s+(?:Terminal\s*\((\w+)\))?[\s,]*(.+)#s", $node[1], $m)) {
                        if (isset($m[1]) && !empty($m[1])) {
                            $seg['DepartureTerminal'] = $m[1];
                        }
                        $seg['DepDate'] = $this->normalizeDate($m[2]);
                    }
                } else {
                    $node = implode("\n", $node);

                    if (preg_match("#\[([A-Z]{3})\]\s+(.+?)[\s,]*(?:Terminal\s*\((\w+)\))?[\s,]+(\S+\s+\w+\s+\d+[\s,]+\d+[\s,]+\d+:\d+\s+(?i)[ap]m)$#s", $node, $m)) {
                        $seg['DepCode'] = $m[1];
                        $seg['DepName'] = $m[2];

                        if (isset($m[3]) && !empty($m[3])) {
                            $seg['DepartureTerminal'] = $m[3];
                        }
                        $seg['DepDate'] = $this->normalizeDate($m[4]);
                    }
                }
                $node = $this->http->FindNodes("./following-sibling::table[1]/descendant::tr[count(descendant::tr)=0]/td[2]//text()[normalize-space(.)!='']", $root);

                if (count($node) == 2) {
                    if (preg_match("#\[([A-Z]{3})\]\s+(.+)[\s,]*#s", $node[0], $m)) {
                        $seg['ArrCode'] = $m[1];
                        $seg['ArrName'] = $m[2];
                    }

                    if (preg_match("#,\s+(?:Terminal\s*\((\w+)\))?[\s,]*(.+)#s", $node[1], $m)) {
                        if (isset($m[1]) && !empty($m[1])) {
                            $seg['ArrivalTerminal'] = $m[1];
                        }
                        $seg['ArrDate'] = $this->normalizeDate($m[2]);
                    }
                } else {
                    $node = implode("\n", $node);

                    if (preg_match("#\[([A-Z]{3})\]\s+(.+?)[\s,]*(?:Terminal\s*\((\w+)\))?[\s,]+(\S+\s+\w+\s+\d+[\s,]+\d+[\s,]+\d+:\d+(?:\s+(?i)[ap]m)?)$#s", $node, $m)) {
                        $seg['ArrCode'] = $m[1];
                        $seg['ArrName'] = $m[2];

                        if (isset($m[3]) && !empty($m[3])) {
                            $seg['ArrivalTerminal'] = $m[3];
                        }
                        $seg['ArrDate'] = $this->normalizeDate($m[4]);
                    }
                }
                $node = $this->http->FindSingleNode("./preceding-sibling::table[1]/descendant::tr[count(descendant::tr)=0][1]/td[1]", $root);

                if (preg_match("#([A-Z\d]{2})\s*(\d+)$#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $node = $this->http->FindSingleNode("./preceding-sibling::table[1]/descendant::tr[count(descendant::tr)=0][1]/td[2]", $root);

                if (preg_match("#(.+?)\s*(?:\(([A-Z]{1,2})\))?$#", $node, $m)) {
                    $seg['Cabin'] = $m[1];

                    if (isset($m[2]) && !empty($m[2])) {
                        $seg['BookingClass'] = $m[2];
                    }
                }
                $node = $this->http->FindSingleNode("./preceding-sibling::table[1]/descendant::tr[count(descendant::tr)=0][1]/td[3]", $root);

                if (preg_match("#Status[\s:]+([^\(]+)#i", $node, $m)) {
                    $it['Status'] = $m[1];
                }

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\w+)\s+(\d+),\s+(\d+)$#', //SEP 24,2017
            '#^\S+\s+(\w+)\s+(\d+)[\s,]+(\d+)[\s,]+(\d+:\d+\s+[AP]M)$#i', //Fri, Oct 06, 2017, 03:45 PM
        ];
        $out = [
            '$2 $1 $3',
            '$2 $1 $3, $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
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
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
