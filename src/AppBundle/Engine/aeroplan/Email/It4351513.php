<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers aeroplan/Changes (in favor of aeroplan/It4351513)

class It4351513 extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-33987417.eml, aeroplan/it-34098538.eml, aeroplan/it-34382394.eml, aeroplan/it-34452083.eml, aeroplan/it-42411082.eml, aeroplan/it-4351513.eml, aeroplan/it-6126513.eml";
    public $reFrom = "@aircanada.ca";
    public $reSubject = [
        "en"  => "is sending you the itinerary for your next trip from",
        "en1" => "You're now tracking",
        "en2" => "Gate *Assigned*:",
        "en3" => "Flight *Changed*:",
        "en4" => "Flight details changed:",
    ];
    public $reBody = 'Air Canada';
    public $reBody2 = [
        "en"  => "The itinerary for your next trip from",
        "en1" => "now signed up to the following flight with",
        "en2" => "the gate has been *assigned* for the flight",
        "en3" => "We just wanted to let you know that the flight",
    ];
    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";
    private $text;

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
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->text = $parser->getPlainBody();
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseHtml($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $formats = 2;
        $cnt = $formats * count(self::$dictionary);

        return $cnt;
    }

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        if (preg_match_all("#Flight\s+(?<AirlineName>\w{2})(?<FlightNumber>\d+)(?::[ ]*(?<Operator>.+?))?\.\n" .
                "From\s+(?<DepName>.*?)\s+\((?<DepCode>[A-Z]{3})\)\s+To\s+(?<ArrName>.*?)\s+\((?<ArrCode>[A-Z]{3})\)\.\n" .
                "Departing:\s+(?<DepDate>.*?)(?:\s*-\s*Terminal (?<DepartureTerminal>\w+))?\.\n" .
                "Arriving:\s+(?<ArrDate>.*?)(?:\s*-\s*Terminal (?<ArrivalTerminal>\w+))?\.\n" .
                "Aircraft:\s+(?<Aircraft>.*?)\.\n" .
                "Stops:\s+(?<Stops>.*?)\.\n" .
                "Fare Type:\s+.*?\s+(?<BookingClass>\w)\.#", $this->text, $segments, PREG_SET_ORDER) > 0
        ) {
            $this->logger->debug('Format 1');
            $f->general()
                ->confirmation($this->re("#Booking Reference Number\s*:\s*(\w+)#"));

            if (preg_match_all("#Passenger\s+\d:\s+Adult\.\s*\n\s*Name:\s+(.*?)\.#", $this->text, $passangers)) {
                $f->general()->travellers($passangers[1]);
            }

            foreach ($segments as $segment) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($segment['AirlineName'])
                    ->number($segment['FlightNumber']);

                if (isset($segment['Operator']) && !empty($segment['Operator'])) {
                    $s->airline()->operator($segment['Operator']);
                }

                $s->departure()
                    ->name($segment['DepName'])
                    ->code($segment['DepCode'])
                    ->date(strtotime($this->normalizeDate($segment['DepDate'])));

                if (isset($segment['DepartureTerminal']) && !empty($segment['DepartureTerminal'])) {
                    $s->departure()->terminal($segment['DepartureTerminal']);
                }

                $s->arrival()
                    ->name($segment['ArrName'])
                    ->code($segment['ArrCode'])
                    ->date(strtotime($this->normalizeDate($segment['ArrDate'])));

                if (isset($segment['ArrivalTerminal']) && !empty($segment['ArrivalTerminal'])) {
                    $s->arrival()->terminal($segment['ArrivalTerminal']);
                }

                $s->extra()
                    ->aircraft($segment['Aircraft'])
                    ->stops($segment['Stops'])
                    ->bookingCode($segment['BookingClass']);
//                $keys = [
//                    "FlightNumber",
//                    "AirlineName",
//                    "Operator",
//                    "DepName",
//                    "DepCode",
//                    "ArrName",
//                    "ArrCode",
//                    "Aircraft",
//                    "Stops",
//                    "BookingClass",
//                    "DepartureTerminal",
//                    "ArrivalTerminal",
//                ];
//                foreach($keys as $key){
//                    if(!empty($segment[$key]))
//                        $itsegment[$key] = $segment[$key];
//                }
//
//                $keys = [
//                    "DepDate",
//                    "ArrDate",
//                ];
//                foreach($keys as $key){
//                    $itsegment[$key] = strtotime($this->normalizeDate($segment[$key]));
//                }
//
//
//                $it['TripSegments'][] = $itsegment;
            }
        } else /*
Flight Number: AC0566

Departing Vancouver at Terminal INT, Gate E86
Arriving in San Francisco at Terminal INT, Gate G91

*REVISED*
Departing Vancouver (YVR) on 22-Feb-2019 @ 14:35
Arriving in San Francisco (SFO) on 22-Feb-2019 @ 16:30
-----------------------------------------------------
Flight Number: AC0882

Departing Toronto Pearson at Terminal T1, Gate E72
Arriving in Copenhagen at Terminal 2, Gate C36

Scheduled:
Departing Toronto Pearson (YYZ) on 23-Feb-2019 @ 21:00
Arriving in Copenhagen (CPH) on 24-Feb-2019 @ 10:35
-----------------------------------------------------
Flight Number: AC1670

Scheduled:
Departing Toronto Pearson (YYZ) on 04-Mar-2019 @ 06:20
Arriving in Orlando (MCO) on 04-Mar-2019 @ 09:08
-----------------------------------------------------
Flight Number: AC0834

What's changed?
Now departing Montréal Trudeau on 05-Mar-2019 @ 22:00

*REVISED*
Departing Montréal Trudeau (YUL) on 05-Mar-2019 @ 22:00
-- Departure Gate A50
Arriving in Geneva (GVA) on 06-Mar-2019 @ 10:43
-- Arrival Terminal 1, Gate N/A
-----------------------------------------------------
Flight Number: AC8575

What's changed?
Now departing Saskatoon on 25-Jul-2019 @ 17:45
Now arriving in Vancouver on 25-Jul-2019 @ 18:45

Reason for delay:
This flight is delayed due to scheduling issues.

Revised details:
Departing Saskatoon (YXE) on 25-Jul-2019 @ 17:45
-- Departure Gate 5
Arriving in Vancouver (YVR) on 25-Jul-2019 @ 18:45
-- Arrival Terminal DTB, Gate C39
-----------------------------------------------------

             * */ {
            if (preg_match("#Flight Number:\s+(?<AirlineName>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<FlightNumber>\d+)\n\n" .
                    "(?:Departing\s+(.+)\s+at(?:\s+Terminal\s+(?<DepTerminal1>.+),)?\s+Gate\s+.+\n" .
                    "Arriving\s+in\s+(.+)\s+at(?:\s+Terminal\s+(?<ArrTerminal1>.+),)?\s+Gate\s+.+\n\n|What's changed\?\n(?:.+\n){1,2}\n(?:Reason for delay:\n(?:.+\n){1,2}\n)?)?" .
                    "(?:\*REVISED\*|Scheduled:|Previously scheduled:|Revised details:)\nDeparting\s+(?<DepName>.+)\s+\((?<DepCode>[A-Z]{3})\)\s+on\s+(?<DepDate>.+)\n" .
                    "(?:\-\-\s+Departure(?:\s+Terminal\s+(?<DepTerminal2>.+),)?\s+Gate.+\n)?" .
                    "Arriving\s+in\s+(?<ArrName>.+)\s+\((?<ArrCode>[A-Z]{3})\)\s+on\s+(?<ArrDate>.+)\n" .
                    "(?:\-\-\s+Arrival\s+Terminal\s+(?<ArrTerminal2>.+),\s+Gate)?#", $this->text,
                    $segment) > 0
            ) {
                $this->logger->debug('Format 2');
                $f->general()
                    ->noConfirmation();

                $s = $f->addSegment();

                $s->airline()
                    ->name($segment['AirlineName'])
                    ->number($segment['FlightNumber']);

                $s->departure()
                    ->name($segment['DepName'])
                    ->code($segment['DepCode'])
                    ->date(strtotime($this->normalizeDate($segment['DepDate'])));

                if (!empty($segment['DepTerminal1'])) {
                    $s->departure()->terminal($segment['DepTerminal1']);
                } elseif (!empty($segment['DepTerminal2'])) {
                    $s->departure()->terminal($segment['DepTerminal2']);
                }

                $s->arrival()
                    ->name($segment['ArrName'])
                    ->code($segment['ArrCode'])
                    ->date(strtotime($this->normalizeDate($segment['ArrDate'])));

                if (!empty($segment['ArrTerminal1'])) {
                    $s->arrival()->terminal($segment['ArrTerminal1']);
                } elseif (!empty($segment['ArrTerminal2'])) {
                    $s->arrival()->terminal($segment['ArrTerminal2']);
                }
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\s+(\d+)-(\w+)\s+(\d{4})\s+at\s+(\d+:\d+)$#",
            //04-Mar-2019 @ 06:20
            "#^\s*(\d+)\-(\w+)\-(\d{4})\s+@\s+(\d+:\d+)\s*$#",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    private function re($re, $str = false, $c = 1)
    {
        if ($str === false) {
            $str = $this->text;
        }
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
