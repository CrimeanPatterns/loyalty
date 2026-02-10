<?php

namespace AwardWallet\Engine\deltav\Email;

use AwardWallet\Engine\delta\Email\Airport\Resolver;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourVacationsPlain extends \TAccountChecker
{
    public $mailFiles = "deltav/it-11288287.eml, deltav/it-23171577.eml, deltav/it-23171587.eml, deltav/it-41773291.eml, deltav/it-41789704.eml, deltav/it-7734816.eml, deltav/it-7735632.eml, deltav/it-7736477.eml, deltav/it-7736542.eml, deltav/it-7736543.eml, deltav/it-7736670.eml, deltav/it-7774813.eml, deltav/it-7875409.eml";

    public $reSubject = [
        "en" => ["Itinerary", "E-Invoice"],
    ];

    public $reBody = 'Delta Vacations';
    public $reBody2 = [
        "en" => "BOOKING",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    private $resolver;

    public function parsePlain(Email $email)
    {
        $this->resolver = new Resolver();
        $statuses = ['KK'=>'Confirmed', 'RQ'=>'Requested', 'XX'=>'Canceled'];
        $text = $this->http->Response['body'];
        $text = str_replace(chr(194) . chr(160), ' ', $text);

        //##################
        //##   FLIGHTS   ###
        //##################
        $airs = [];
        $patterns['Flight'] = '/'
                . '(?:FLIGHTS:|Flights[.]*)'
                . '\s*(.*?)$'
                . '\s+^[> ]*$'
                . '\s+^[> ]*(?:Seat|HOTEL|SEAT)'
                . '/ms';
        $stexts = $this->split("#[\n]*?(\s*(?:KK|RQ|XX|GK) [A-Z])#", $this->re($patterns['Flight'], $text));

        foreach ($stexts as $stext) {
            if (count($rows = explode("\n", $stext)) === 2 && preg_match('/\(([A-Z\d]+)\)/', $rows[1], $m)) {
                $rl = $m[1];
            }

            if (preg_match('/\(([A-Z\d]{5,7})\)(?:\s+\(ALL\))?/', $stext, $m)) {
                $rl = $m[1];
            }

            if (empty($rl)) {
                continue;
            }
            $airs[$rl][] = $stext;
        }

        $seatSegments = array_unique($this->split("#\n*([>\s]*(?:KK|RQ|XX|GK) [A-Z])#", $this->re("#SEAT ASGN[:\.]*(\n*.*?)\n[>\s]*\n#msi", $text)));

        $seats = [];
        $passengers = [];

        foreach ($airs as $rl => $segments) {
            foreach ($seatSegments as $seatSegment) {
                $flight = $this->re("/(?:KK|RQ|XX|GK) [A-Z]\s+\d+[^\d\s]+[\d]*?\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d+)/", $seatSegment);

                if (stripos($seatSegment, $rl) !== false) {
                    preg_match_all("/^\s*(.+?)(?:\.*|[^\w\s]*)(?:(\d{1,5}-[A-Z])|no seat.*)$/im", $seatSegment, $m);

                    if (count($m[2]) > 1) {
                        $seats[$flight . ' ' . $rl][] = implode(',', $m[2]);
                    } elseif (count($m[2]) == 1) {
                        $seats[$flight . ' ' . $rl][] = $m[2][0];
                    }

                    if (!empty($m[1])) {
                        $rCode = $this->re('/(?:KK|RQ|XX|GK) [A-Z]\s+.*\s*\(([A-Z\d]{5,7})\)/', $seatSegment);
                        $passengers[$rCode] = array_map("trim", $m[1]);
                    }
                }
            }
            $f = $email->add()->flight();

            $f->general()
                ->confirmation($this->re("#(?:BOOKING:|Booking[\.]+Nbr:)\s+(\d+)#", $text));

            // Passengers
            $passengersNames = preg_split('/\s*[\n,]+[>\s]*/', $this->re("/(?:NAMES:|Names[\.]*)([-)(>,.A-z\d\/\s]+)(?:.+)?\n[>\s]*\n/", $text));
            $it['Passengers'] = [];

            foreach ($passengersNames as $guest) {
                if (preg_match("/^\s*(?:(?:MR|MS|MRS|MISS) )?([.A-z ]+)\/([.A-z ]+)/", $guest, $m)) {
                    $it['Passengers'][] = str_replace('.', ' ', trim($m[2]) . ' ' . trim($m[1]));
                }
            }

            if (!empty($passengers[$rl])) {
                $it['Passengers'] = [];

                foreach ($passengers[$rl] as $guest) {
                    if (preg_match("/^[>\s]*([.A-z ]+)\/([.A-z ]+)/", $guest, $m)) {
                        $f->general()->traveller(str_replace('.', ' ', trim($m[2]) . ' ' . trim($m[1])), true);
                    }
                }
            }

            preg_match_all("/-([A-Z\d\s]{5,})(?:,|$|-|\n)/", $this->re("/(?:NAMES:|Names[.]*)([\s\S]+?)\n[>\s]*\n/", $text), $m);
            $accounts = array_unique(array_filter($m[1]));

            if (count($accounts) > 0) {
                $f->setAccountNumbers($accounts, false);
            }

            foreach ($segments as $stext) {
                $s = $f->addSegment();

                if (!empty($rl)) {
                    $s->setConfirmation($rl);
                }

                $stext = str_replace('>', '', $stext);

                if (preg_match("#(?<Status>KK|RQ|XX|GK) [A-Z] (?<AirlineName>.*?)\s+(?<FlightNumber>\d+)\*?\s+(?<Date>\d+[^\d\s]+(\d{2})?)\/[^\d\s]+\s+(?<DepName>.*?)(?:\s+\((?<DepCode>[A-Z]{3})\))?\s+TO\s+(?<ArrName>.*?)(?:\s+\((?<ArrCode>[A-Z]{3})\))?\s*\n\s*LV\s+(?<DepTime>\d+:\d+[AP])\s+ARR\s+(?<ArrTime>\d+:\d+[AP])#", $stext, $m)
                    || preg_match('/(?<Status>KK|RQ|XX|GK) [A-Z] (?<AirlineName>.*?)\s+(?<FlightNumber>\d+)\*?\D*\s+(?<Date>\d+[^\d\s]+\d{0,2})\/[^\d\s]+\s+(?<DepCode>[A-Z]{3})\/(?<ArrCode>[A-Z]{3})\s+(?<DepTime>\d+:\d+[AP])\s+(?<ArrTime>\d+:\d+[AP])/', $stext, $m)
                ) {
                    if (isset($statuses[$m['Status']])) {
                        $s->extra()
                            ->status($statuses[$m['Status']]);
                    }

                    if (isset($m['Status']) && $m['Status'] == 'XX') {
                        $f->general()
                            ->cancelled();
                    }
                    $date = strtotime($this->normalizeDate($m['Date']));

                    $s->airline()
                        ->number($m['FlightNumber'])
                        ->name($m['AirlineName']);

                    if (!empty($m['DepCode'])) {
                        $s->departure()
                            ->code($m['DepCode']);
                    }

                    if (!empty($m['DepName'])) {
                        $s->departure()
                            ->name($m['DepName']);
                    }

                    if (!empty($m['ArrCode'])) {
                        $s->arrival()
                            ->code($m['ArrCode']);
                    }

                    if (!empty($m['ArrName'])) {
                        $s->arrival()
                            ->name($m['ArrName']);
                    }

                    if (!empty($s->getDepName()) && !empty($s->getArrName())) {
                        $s->departure()
                            ->name(trim($this->re("#(.*?)(?:\s+\(|$)#", $s->getDepName()), ' ,'));

                        $s->arrival()
                            ->name(trim($this->re("#(.*?)(?:\s+\(|$)#", $s->getArrName()), ' ,'));
                    }

                    if (!$s->getDepCode()) {
                        $depCode = $this->resolver->resolve($s->getDepName());

                        if (!empty($depCode)) {
                            $s->departure()
                                ->code($depCode);
                        }
                    }

                    if (!$s->getDepCode()) {
                        $depCode = $this->re("#-([A-Z]{3}),#", $s->getDepName());

                        if (!empty($depCode)) {
                            $s->departure()
                                ->code($depCode);
                        }
                    }

                    if (empty($s->getDepCode())) {
                        $s->departure()
                            ->noCode();
                    }

                    if (!$s->getArrCode()) {
                        $code = $this->resolver->resolve($s->getArrName());

                        if (!empty($code)) {
                            $s->arrival()
                                ->code($code);
                        }
                    }

                    if (!$s->getArrCode()) {
                        $code = $this->re("#-([A-Z]{3}),#", $s->getArrName());

                        if (!empty($code)) {
                            $s->arrival()
                                ->code($code);
                        }
                    }

                    if (empty($s->getArrCode())) {
                        $s->arrival()
                            ->noCode();
                    }

                    $s->departure()
                        ->date(strtotime($this->normalizeDate($m['DepTime']), $date));

                    $s->arrival()
                        ->date(strtotime($this->normalizeDate($m['ArrTime']), $date));

                    if (isset($seats[$s->getFlightNumber() . ' ' . $rl]) && count($seats[$s->getFlightNumber() . ' ' . $rl]) > 0) {
                        $s->extra()
                            ->seats(str_replace('-', '', array_filter(explode(',', $seats[$s->getFlightNumber() . ' ' . $rl][0]))));
                    }
                }
            }
        }

        //################
        //##   HOTEL   ###
        //################
        if (stripos($text, "HOTEL") !== false && stripos($text, 'No hotel booked') === false) {
            $regExpHeader = "(?:KK|RQ|XX|KR)\s+\d+[^\d\s]+(?:\d{2})?\/\d+[^\d\s]+(?:\d{2})?\s+[^\n]*?";

            $patterns['Hotel'] = '/'
                . '^[>\s]*(?:HOTEL[:.]+\s*?|)'
                . '\s*(?:HBSI)?\s*(' . $regExpHeader . '.*?)$'
                . '\s+^[> ]*$'
                . '\s+^'
                . '/ims';
            preg_match_all($patterns['Hotel'], $text, $m);
            $m[1] = preg_replace("/\n\s*\.*Cli.+/", '', $m[1]);

            foreach (array_unique($m[1]) as $htext) {
                $h = $email->add()->hotel();

                // ConfirmationNumber
                $dollar = preg_quote("$");
                $confNbr = $this->re("#Conf Nbr:\s+([\w\-{$dollar}]+)#", $htext);

                if ($confNbr && $confNbr !== 'I') { // it-7734816.eml
                    $h->general()
                        ->confirmation(str_replace('$', 'S', $confNbr));
                }

                // TripNumber
                $h->general()
                    ->confirmation($this->re("#(?:BOOKING|Booking[\.]+\s*Nbr):\s+(\d+)#", $text));

                if (preg_match("#(?<status>KK|RQ|XX|KR)\s+(?<checkin>\d+[^\d\s]+(\d{2})?)\/(?<checkout>\d+[^\d\s]+(\d{2})?)\s+(?<name>.*?),#", $htext, $m)) {
                    // HotelName
                    // Address
                    $h->hotel()
                        ->name($m['name'])
                        ->noAddress();

                    $h->booked()
                        ->checkIn(strtotime($this->normalizeDate($m['checkin'])))
                        ->checkOut(strtotime($this->normalizeDate($m['checkout'])));

                    if (isset($statuses[$m['status']])) {
                        $h->general()
                            ->status($statuses[$m['status']]);
                    }

                    if (isset($m['Status']) && $m['Status'] == 'XX') {
                        $h->general()
                            ->cancelled();
                    }
                }

                // GuestNames
                $GuestNames = preg_split('/\s*[\n,]+[>\s]*/', $this->re("/(?:NAMES:|Names[.]*)([-)(>,.A-z\d\/\s]+)(?:.+)?\n[>\s]*?\n/", $text));

                foreach ($GuestNames as $guest) {
                    if (preg_match("/^\s*(?:(?:MR|MS|MRS|MISS) )?([.A-z ]+)\/([.A-z ]+)/", $guest, $m)) {
                        $h->general()
                            ->traveller(str_replace('.', ' ', trim($m[2]) . ' ' . trim($m[1])), true);
                    }
                }

                // RoomType
                $roomType = $this->re("#Room[:\.]+\s+\w+\s+(.+)#", $htext);

                if (!empty($roomType)) {
                    $room = $h->addRoom();

                    $room->setType($roomType);
                }

                // ConfirmationNumber
                if (empty($h->getConfirmationNumbers()) && !empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
                    $h->general()
                        ->noConfirmation();
                }
            }
        }

        //################
        //##   Car   #####
        //################
        if (stripos($text, 'CAR') !== false) {
            preg_match_all('/(?:Car|CAR)[:\.]+([\S\s]+?\s+[\. ]+Driver:\s+[\w\.\s]+)/', $text, $m);

            foreach ($m[1] as $rText) {
                /** @var \AwardWallet\ItineraryArrays\CarRental $it */
                $r = $email->add()->rental();

                $r->general()
                    ->confirmation($this->re("#Conf Nbr:\s+(\w+)#", $rText));

                if (preg_match('/PU:\s+(.+)\s+[.]*\s*DO:\s+(.+?)(,[ ]+[\w ]+:|\n)/', $rText, $m)) {
                    $r->pickup()
                        ->location(trim($m[1], "' ,"));

                    $r->dropoff()
                        ->location(trim($m[2], "', "));
                }

                $r->general()
                    ->traveller(trim(str_replace('.', ' ', $this->re('/Driver:\s+(.+)[\. ]+is/', $rText))));

                if (preg_match('/(?<status>KK|RQ|XX|KR)\s+\w*\s*(?<checkin>\d+[^\d\s]+\d{0,2})\s+(?<time1>\d+:\d+\s*[AP])\s*\/\s*(?<checkout>\d+[^\d\s]+\d{0,2})\s+(?<time2>\d+:\d+\s*[AP])\s+(?<name>.+)/', $rText, $m)) {
                    $r->setCompany($m['name']);

                    if (isset($statuses[$m['status']])) {
                        $r->general()
                            ->status($statuses[$m['status']]);
                    }
                    $r->pickup()
                        ->date(strtotime($this->normalizeDate($m['checkin']) . ', ' . $this->normalizeDate($m['time1'])));

                    $r->dropoff()
                        ->date(strtotime($this->normalizeDate($m['checkout']) . ', ' . $this->normalizeDate($m['time2'])));
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@deltavacations.com') !== false
            || stripos($from, '@mltvacations.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Delta Vacations') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->SetEmailBody($parser->getPlainBody());

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        if (preg_match("#Gross:\s*(\D{1,5})([\d. ,]+)#", $this->http->Response["body"], $m)) {
            $email->price()
                ->total($this->amount(trim($m[2], ' .')))
                ->currency($this->currency($m[1]));
        }

        $this->parsePlain($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\d\s]+)$#", // 14MAY
            "#^(\d+)([^\d\s]+)(\d{2})$#", // 14MAY17
            "#^(\d+:\d+)([AP])$#", // 10:55A
        ];
        $out = [
            "$1 $2 $year",
            "$1 $2 20$3",
            "$1 $2M",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = trim($r[$i] . $r[$i + 1]);
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
