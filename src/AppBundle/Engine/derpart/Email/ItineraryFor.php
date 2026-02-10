<?php

namespace AwardWallet\Engine\derpart\Email;

class ItineraryFor extends \TAccountChecker
{
    public $mailFiles = "derpart/it-5497979.eml, derpart/it-5497984.eml, derpart/it-5498068.eml, derpart/it-5498084.eml";

    public $reBody = [
        'de' => ['Reiseplan', 'Buchungsnummer'],
    ];
    public $reSubject = [
        '#Reiseplan\s+fuer.+?Abreise#',
    ];
    public $lang = '';
    public $date;
    public static $dict = [
        'de' => [
            'RecordLocator' => 'Buchungsnummer',
            'Airline-PNR'   => 'Airline-Buchungsnr.',
        ],
    ];

    public $dateTimeToolsMonths = [
        "en" => [
            "january"   => 0,
            "february"  => 1,
            "march"     => 2,
            "april"     => 3,
            "may"       => 4,
            "june"      => 5,
            "july"      => 6,
            "august"    => 7,
            "september" => 8,
            "october"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
        "de" => [
            "januar"    => 0, "jan" => 0,
            "februar"   => 1, "feb" => 1,
            "mae"       => 2, "maerz" => 2, "märz" => 2, "mrz" => 2,
            "apr"       => 3, "april" => 3,
            "mai"       => 4,
            "juni"      => 5, "jun" => 5,
            "jul"       => 6, "juli" => 6,
            "august"    => 7, "aug" => 7,
            "september" => 8, "sep" => 8,
            "oktober"   => 9, "okt" => 9,
            "nov"       => 10, "november" => 10,
            "dez"       => 11, "dezember" => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach ($this->reBody as $lang => $reBody) {
            if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $this->date = strtotime($parser->getDate());
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "ItineraryFor",
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(.,'DERPART')]")->length > 0) {
            $body = $parser->getHTMLBody();

            foreach ($this->reBody as $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
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
        return stripos($from, "DERPART") !== false;
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

            if ($translatedMonthName = $this->translateMonth($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function translateMonth($month, $lang)
    {
        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month])) {
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        }

        return false;
    }

    private function parseEmail()
    {
        $its = [];

        $tripNum = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('RecordLocator') . "')]/ancestor::tr[1]//td[3]/span");

        if ($tripNum === null) {
            $tripNum = $this->http->FindSingleNode("//text()[contains(.,'" . $this->t('RecordLocator') . "')]/ancestor::td[1]/following-sibling::td[2]/descendant-or-self::span[1]");
        }

        $pax = array_filter($this->http->FindNodes("//*[contains(text(),'Reiseplan')]/ancestor::tr[1]/following-sibling::tr/descendant::table[1]//tr[1]/td[1]/text()[not(contains(.,'Vielflieger')) and not(contains(.,'Ticketnummer'))]"));
        $node = $this->http->FindSingleNode("//*[contains(text(),'Datum')]/ancestor::td[contains(.,'Zeit')][1]");

        if (preg_match("#Datum\s*:\s+(\d+\.\d+\.\d+)\s+Zeit\s*:\s+(\d+:\d+)#", $node, $m)) {
            $this->date = strtotime($m[1] . ' ' . $m[2]);
        }
        //###########
        //# FLIGHT ##
        //###########
        $rls = $this->http->FindNodes("//text()[contains(.,'" . $this->t('Airline-PNR') . "')]/ancestor::tr[1]//td[3]/text()");
        $xpath = "//*[contains(text(),'Flug') and contains(.,'durchgeführt von')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        if (!$nodes->length) {
            $xpath = "//*[contains(text(),'Flug') and contains(.,'durchgef')]/ancestor::table[1]"; //something with codepage
            $nodes = $this->http->XPath->query($xpath);
        }

        $airs = [];

        foreach ($nodes as $root) {
            $airlinename = $this->http->FindSingleNode(".", $root, true, "#Flug\s+([A-Z\d]{2})#");
            $flag = false;

            foreach ($rls as $rl) {
                if ($airlinename == substr($rl, 0, 2)) {
                    $airs[substr($rl, 3)][] = $root;
                    $flag = true;

                    break;
                }
            }

            if (!$flag) {
                $airs[$tripNum][] = $root;
            }
        }

        foreach ($airs as $rl => $nodes) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['TripNumber'] = $tripNum;
            $it['Passengers'] = $pax;

            foreach ($nodes as $root) {
                $seg = [];
                $node = $this->http->FindSingleNode(".", $root, true, "#Flug\s+([A-Z\d]{2}\s+\d+)#");

                if (preg_match("#([A-Z\d]{2})\s+(\d+)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[1]//tr[2]/td[2]//text()[1]", $root)));

                if ($date < $this->date) {
                    $date = strtotime("+1 year", $date);
                }
                $node = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[1]//tr[2]/td[2]//text()[2]", $root);

                if (preg_match("#(\d+:\d+)#", $node, $m)) {
                    $seg['DepDate'] = strtotime($m[1], $date);
                }
                $node = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[1]//tr[3]/td[2]//text()[1]", $root);

                if (preg_match("#(\d+:\d+).*?(\+\d+|$)#", $node, $m)) {
                    $seg['ArrDate'] = strtotime($m[1], $date);

                    if (!empty($m[2])) {
                        $seg['ArrDate'] = strtotime($m[2] . ' days', $seg['ArrDate']);
                    }
                } else {
                    $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[1]//tr[3]/td[2]//text()[1]", $root)));

                    if ($date < $this->date) {
                        $date = strtotime("+1 year", $date);
                    }
                    $node = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[1]//tr[3]/td[2]//text()[2]", $root);

                    if (preg_match("#(\d+:\d+)#", $node, $m)) {
                        $seg['ArrDate'] = strtotime($m[1], $date);
                    }
                }
                $seg['DepName'] = implode(' ', array_filter($this->http->FindNodes("./following-sibling::table[1]/descendant::table[1]//tr[2]/td[3]//text()[not(contains(.,'TERMINAL'))]", $root)));
                $node = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[1]//tr[2]/td[3]//text()[contains(.,'TERMINAL')]", $root);

                if (!empty($node)) {
                    $seg['DepartureTerminal'] = $node;
                }
                $seg['ArrName'] = implode(' ', array_filter($this->http->FindNodes("./following-sibling::table[1]/descendant::table[1]//tr[3]/td[3]//text()[not(contains(.,'TERMINAL'))]", $root)));
                $node = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[1]//tr[3]/td[3]//text()[contains(.,'TERMINAL')]", $root);

                if (!empty($node)) {
                    $seg['ArrivalTerminal'] = $node;
                }
                $node = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[2]//tr[contains(.,'Klasse')]/td[3]", $root);

                if (preg_match("#([A-Z]{1,2})\s*-\s*(.+?)(?:,\s+(.+)|$)#", $node, $m)) {
                    $seg['Cabin'] = $m[2];
                    $seg['BookingClass'] = $m[1];

                    if (isset($m[3]) && !empty($m[3])) {
                        $it['Status'] = $m[3];
                    }
                }
                $seg['Duration'] = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[2]//tr[contains(.,'Flugdauer')]/td[3]", $root);
                $seg['Seats'] = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[2]//tr[contains(.,'Sitzplatz')]/td[3]", $root);
                $seg['Meal'] = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[2]//tr[contains(.,'An Board')]/td[3]", $root);
                $seg['Aircraft'] = implode(' ', $this->http->FindNodes("./following-sibling::table[1]/descendant::table[2]//tr[contains(.,'Flugzeug')]/td[3]/text()", $root));

                $seg = array_filter($seg);
                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }
        //##########
        //# HOTEL ##
        //##########
        $xpath = "//td[contains(text(),'Hotel')]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = ['Kind' => 'R'];
            $it['ConfirmationNumber'] = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[2]//tr[contains(.,'Bestätigungsnr')]/td[3]", $root);
            $it['HotelName'] = $this->http->FindSingleNode(".", $root, true, "#Hotel\s+(.+)#");
            $it['Address'] = implode(' ', $this->http->FindNodes("./following-sibling::table[1]/descendant::table[1]//tr[2]/td[3]/text()", $root));
            $it['Phone'] = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[1]//tr[2]/td[3]/span[./img[@alt='Fon' or contains(@src,'phone')]]", $root);
            $it['Fax'] = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[1]//tr[2]/td[3]/span[./img[@alt='Fax' or contains(@src,'fax')]]", $root);
            $it['TripNumber'] = $tripNum;
            $it['GuestNames'] = $pax;
            $it['Rate'] = implode(' ', $this->http->FindNodes("./following-sibling::table[1]/descendant::table[2]//tr[contains(.,'Preis')]/td[3]/text()", $root));
            $it['Status'] = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[2]//tr[contains(.,'Buchungsstatus')]/td[3]", $root);
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[1]//tr[2]/td[2]/text()[1]", $root)));
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[1]//tr[2]/td[2]/text()[2]", $root)));

            $it['ConfirmationNumber'] = $this->http->FindSingleNode("./following-sibling::table[1]/descendant::table[2]//tr[contains(.,'Bestätigungsnr')]/td[3]", $root);

            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '#[\S\s]*(\d{2})[\.\/]*\s*(\S+)#',
        ];
        $out = [
            '$1 $2 ' . $year,
        ];

        return $this->dateStringToEnglish(mb_strtolower(preg_replace($in, $out, $date)));
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
