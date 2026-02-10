<?php

namespace AwardWallet\Engine\egyptair\Email;

use AwardWallet\Engine\MonthTranslate;

class ConfirmationForReservationPlainText extends \TAccountChecker
{
    public $mailFiles = "egyptair/it-7279465.eml";

    public $reFrom = "egyptair.com";

    public $reSubject = [
        "en" => ["Confirmation for reservation"],
    ];

    public static $dictionary = [
        "en" => [
            //			'Booking reservation number' => '',
            //			'Trip status' => '',
            //			'TRAVELLER INFORMATION' => '',
            //			'Contact Information' => '',
            //			'FLIGHT PAYMENT AND TICKET' => '',
            //			'Payment:' => '',
            //			'Flight' => '',
            //			'Airline' => '',
            //			'Departure' => '',
            //			'terminal' => '',
            //			'Arrival' => '',
            //			'Aircraft' => '',
            //			'Fare type' => '',
            //			'Duration' => '',
            //			'Seat request:' => '',
            //			'RESERVATION OFFICE' => '',
        ],
    ];

    public $lang = "en";

    public $text;

    protected $langDetectors = [
        'en' => [
            'YOUR TRIP SUMMARY',
        ],
    ];

    protected $regexps = [
        'date' => [
            'en' => '#(?<month>[^\d\s]+)\s+(?<day>\d{1,2}),\s+(?<year>\d{4})\s*#', // Saturday, September 06, 2014
        ],
    ];

    public function parseEmail()
    {
        $text = $this->text;
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#" . $this->t("Booking reservation number") . ":\s+(\w{5,6})\s+#", $text);

        // Status
        $it['Status'] = $this->re("#" . $this->t("Trip status") . ":\s+(\w+)\s+#", $text);

        // TripNumber
        // Passengers
        $passbegin = strpos($text, $this->t('TRAVELLER INFORMATION'));
        $passend = strpos($text, $this->t('Contact Information'));
        preg_match_all("#\n\s*M[irs]+\s*(.*)\s*\n#", substr($text, $passbegin, $passend - $passbegin), $Passengers);
        $it['Passengers'] = array_unique($Passengers[1]);

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // BaseFare

        // TotalCharge
        // Currency
        $totalbegin = strpos($text, $this->t('FLIGHT PAYMENT AND TICKET'));
        $totalend = strpos($text, $this->t('Payment:'));

        if (preg_match("#\n\s*([\d\.,]+)\s*([A-Z]{3})#", substr($text, $totalbegin, $totalend - $totalbegin), $m)) {
            $it['Currency'] = $m[2];
            $it['TotalCharge'] = $this->amount($m[1]);
        }

        // Tax
        // SpentAwards
        // EarnedAwards
        // ReservationDate
        // NoItineraries
        // TripCategory

        $segments = $this->split("#(" . $this->t('Flight') . "\s+\d+\s+-)#", $text);

        foreach ($segments as $stext) {
            $itsegment = [];

            $datestr = $this->re("#" . $this->t('Flight') . "\s+\d+\s+-\s*(.+)\n#", $stext);

            if (preg_match($this->regexps['date'][$this->lang], $datestr, $matches)) {
                $day = $matches["day"];
                $month = $matches["month"];
                $year = $matches["year"];
                $date = strtotime($day . ' ' . MonthTranslate::translate($month, $this->lang) . ' ' . $year);
            }

            // FlightNumber
            // AirlineName
            if (preg_match("#" . $this->t('Airline') . "\s*:\s*.*?\s+(\w{2})(\d+)\n#", $stext, $m)) {
                $itsegment['AirlineName'] = $m[1];
                $itsegment['FlightNumber'] = $m[2];
            }

            $depature = "#" . $this->t('Departure') . "\s*:\s*(\d{2}:\d{2})\s*-\s*(.*?)(,\s+" . $this->t('terminal') . " (.+))?\n#";

            if (preg_match($depature, $stext, $m)) {
                // DepName
                $itsegment['DepName'] = $m[2];
                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                // DepartureTerminal
                if (!empty($m[4])) {
                    $itsegment['DepartureTerminal'] = $m[4];
                }
                $timeDep = $m[1];
            }

            $arrival = "#" . $this->t('Arrival') . "\s*:\s*(\d{2}:\d{2})\s*-\s*(.*?)(,\s+" . $this->t('terminal') . " (.+))?\n#";

            if (preg_match($arrival, $stext, $m)) {
                // ArrName
                $itsegment['ArrName'] = $m[2];
                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                // ArrivalTerminal
                if (!empty($m[4])) {
                    $itsegment['ArrivalTerminal'] = $m[4];
                }
                $timeArr = $m[1];
            }

            if (isset($date) && isset($timeDep)) {
                // DepDate
                $itsegment['DepDate'] = strtotime($timeDep, $date);
            }

            if (isset($date) && isset($timeDep) && isset($timeArr)) {
                // ArrDate
                $itsegment['ArrDate'] = strtotime($timeArr . (!empty($overnight) ? ' +' . $overnight . ' days' : ''), $date);
            }

            // Operator
            // Aircraft
            $itsegment['Aircraft'] = $this->re("#" . $this->t('Aircraft') . "\s*:\s*(.+)#", $stext);

            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->re("#" . $this->t('Fare type') . "\s*:\s*(.+)#", $stext);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            $itsegment['Duration'] = $this->re("#" . $this->t('Duration') . "\s*:\s*(.+)#", $stext);
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }

        $seats = [];
        $specialBegin = strpos($text, $this->t('Seat request:'));

        if ($specialBegin > 1) {
            $specialEnd = strpos($text, $this->t('RESERVATION OFFICE'));
            $special = substr($text, $specialBegin, $specialEnd - $specialBegin);

            if (preg_match_all("#" . $this->t('Flight') . "\s*\d+\s*:\s*([a-z\d ]+)\s*-\s*([a-z\d ]+)\s*:\s*((\d+[a-z])(\s*,\s*\d+[a-z])*)#i", $special, $m)) {
                foreach ($m[0] as $i => $value) {
                    $find = false;

                    foreach ($seats as $key => $fly) {
                        if ($fly["dep"] == trim($m[1][$i]) and $fly["arr"] == trim($m[2][$i])) {
                            $seats[$key]["num"][] = $m[3][$i];
                            $find = true;

                            break;
                        }
                    }

                    if ($find === false) {
                        $st = [];
                        $st["dep"] = trim($m[1][$i]);
                        $st["arr"] = trim($m[2][$i]);
                        $st["num"][] = $m[3][$i];
                        $seats[] = $st;
                    }
                }
            }
        }

        foreach ($seats as $key => $seat) {
            $seats[$key]["num"] = implode(',', $seat["num"]);
        }

        foreach ($seats as $key => $fly) {
            foreach ($it['TripSegments'] as $key2 => $segment) {
                if (stripos($segment["DepName"], $fly["dep"]) !== false && stripos($segment["ArrName"], $fly["arr"]) !== false) {
                    $it['TripSegments'][$key2]["Seats"] = $fly["num"];

                    break;
                }
            }
        }

        return $it;
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

        foreach ($this->reSubject as $subjects) {
            foreach ($subjects as $subject) {
                if (strpos($headers["subject"], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (strpos($body, "<table>") !== false) {
            return false;
        }

        if (strpos($body, $this->reFrom) === false) {
            return false;
        }

        foreach ($this->langDetectors as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos(html_entity_decode($body), $phrase) !== false && stripos($body, $this->reFrom) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->text = $parser->getPlainBody();

        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($this->text, $phrase) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }
        $it = $this->parseEmail();
        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => [$it],
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

    private function t($word)
    {
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
