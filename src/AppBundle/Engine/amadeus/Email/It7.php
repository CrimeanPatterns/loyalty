<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class It7 extends \TAccountCheckerExtended
{
    public $mailFiles = "amadeus/it-129006981.eml, amadeus/it-1750650.eml, amadeus/it-1976550.eml, amadeus/it-208311050.eml, amadeus/it-2355256.eml, amadeus/it-2355259.eml, amadeus/it-24.eml, amadeus/it-25.eml, amadeus/it-2504765.eml, amadeus/it-26.eml, amadeus/it-27.eml, amadeus/it-2918420.eml, amadeus/it-32986670.eml, amadeus/it-3319585.eml, amadeus/it-378338621.eml, amadeus/it-4875781.eml, amadeus/it-6307762.eml, amadeus/it-7.eml, amadeus/it-8.eml, amadeus/it-8288536.eml, amadeus/it-8288545.eml"; // +1 bcdtravel(plain)[en]
    public $reFrom = [".amadeus.", "@amadeus."];
    public $reSubject = [
        '/[A-Z]+\/[A-Z]+(?: [A-Z]*)?(?: [A-Z]*)? \d+[A-Z]{3}/',
        '/[A-Z]+\/[A-Z]+(?: [A-Z]*)?\(ID [A-Z\d]+/',
    ];
    public $detectLang = [
        'en' => ['DATE', 'BOOKING REF'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [],
    ];
    private $keywordProv = 'amadeus';
    private $reBody = [
        ['#SERVICE\s+DATE\s+FROM\s+TO\s+DEPART\s+ARRIVE#', 3000], // AIR
        ['#\bCHECK-IN[ ]*:[\s\S]+\bCHECK-OUT[ ]*:[\s\S]+\bADDRESS[ ]*:[\s\S]+#', 3000], // HOTEL
        ['#INVOICE[^D]+DATE\s+\d+\D+\d+[^B]+BOOKING REF[ ]+[A-Z\d]+#', 3000],
    ];

    private $patterns = [
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
    ];

    private $accountNumber;
    private $recordLocator;
    private $reservationDate;
    private $subject;
    private $year;
    private $fromPos;
    private $toPos;
    private $depPos;
    private $text;
    private $travellersSubject;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }
        $this->subject = $parser->getSubject();

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $body = $parser->getHTMLBody();

        // format HTML source
        $patternPre = '[Pp][Rr][Ee]'; // pre

        if (preg_match_all("/<\/{$patternPre}[ ]*>(\s*)<{$patternPre}\b.*?>/", $body, $spacesMatches)
            && count(array_filter($spacesMatches[1], function ($item) { return strpos($item, "\n") === false; })) > 0
        ) {
            // it-208311050.eml, it-378338621.eml
            $this->http->DOM->loadHTML($parser->getHTMLBody(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $this->http->DOM->formatOutput = true;
            $body = $this->http->DOM->saveHTML();
        }

        // convert HTML to Plain-text
        $body = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $body); // only <br> tags
        $body = htmlentities(strip_tags($body));
        $body = htmlspecialchars_decode($body);
        $body = str_replace("&nbsp;", " ", $body);
        $body = str_ireplace(['&#160;', '&#43;', '&#58;', '&#39;'], [' ', '+', ':', '\''], $body);
        $this->text = $body;

        if (!$this->parseEmail($email)) {
            return null;
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $parser->getPlainBody() . "\n" . $parser->getHTMLBody();

        foreach ($this->reBody as $reBody) {
            $subj = substr($text, 0, $reBody[1]);

            if (preg_match($reBody[0], $subj)) {
                return $this->assignLang();
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

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && preg_match($reSubject, $headers["subject"]) > 0)
                    || strpos($headers["subject"], $this->keywordProv) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 4; //flight | car | 2 hotels
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function IsEmailAggregator()
    {
        return true;
    }

    private function parseEmail(Email $email)
    {
        $info = $this->re("#^(.*?)SERVICE[^D]+DATE[^F]+FROM#ms", $this->text);

        if (empty($info)) {
            $info = $this->re("#^(.*?)\n *HOTEL {2,}#ms", $this->text);
        }

        if (empty($info)) {
            $info = $this->re("#^(.*?)\s+ACCOUNT NUMBER#s", $this->text);
        }

        if (preg_match("/(([A-Z ]+)\/([A-Z]+(?: [A-Z]+)?)) (?:\d|[A-Z]+\(|\()/", $this->subject, $m)) {
            $name = trim(preg_quote($m[1], '/'));

            if (preg_match("/(?:^| {3,})\s*({$name})\s*$/um", $info)) {
                $m[3] = preg_replace("/ (Mr|MS|MRS|Miss)\s*$/i", '', $m[3]);
                $this->travellersSubject = [trim($m[3]) . ' ' . trim($m[2])];
            }
        } elseif (preg_match("/(?:^|:)\s*([A-Z ]+)\/([A-Z]+(?: [A-Z]+)?(?: & [A-Z]+(?: [A-Z]+)?)?) +(?:\d|[A-Z]+\(|\()/", $this->subject, $m)) {
            $names = array_map('trim', explode('&', $m[2]));

            foreach ($names as $name) {
                if (preg_match("/(?:^| {2,})\s*{$m[1]}\/({$name}( [A-Z]+)*)\s*$/um", $info, $mat)) {
                    $this->travellersSubject[] = $mat[1] . ' ' . $m[1];
                }
            }
        }

        $this->accountNumber = trim($this->re("#\s+ACCOUNT\s+NUMBER\s*([A-Z\d\-]+)#", $this->text));

        $this->recordLocator = trim($this->re("#\s+BOOKING\s+REF\s*([A-Z\d\-]+)#", $info));

        if (!empty($reservationDate = strtotime($this->re("#\sDATE\s*(\d+\s*\D+\s*\d+)#", $info)))) {
            $this->reservationDate = $reservationDate;
        }

        $patterns['tripEnd'] = "(?:[=]{3,}\s+)?(?:RESERVATION NUMBER|PLEASE CHECK WITH|MISCELLANEOUS|RETENTION|-{10,})";

        $regExp = "#(SERVICE[^\w]+DATE[^\w]+FROM[^\w]+TO[^\w]+DEPART[^\w]+ARRIVE)\s*\n(.+?)\s*{$patterns['tripEnd']}#is";
        $regExp2 = "#(.+)\n+( *CAR (?:[^\n]*\n){1,13} *PICK-UP *:[^\n]*\n.+?)\s*{$patterns['tripEnd']}#is"; // it-378338621.eml
        $regExp3 = "#(.+)\n+( *HOTEL [^\n]*\n *CHECK-IN *:[^\n]*\n.+?)\s*{$patterns['tripEnd']}#is"; // it-208311050.eml

        if (preg_match($regExp, $this->text, $m)
            || preg_match($regExp2, $this->text, $m)
            || preg_match($regExp3, $this->text, $m)
        ) {
            $trip = $m[2];
            $head = $m[1];
        }

        if (empty($trip)) {
            $trip = $this->re("/\s+ACCOUNT\s+NUMBER\s*[-A-Z\d]+\s*\n+(.{2,}?)(?:\n+[>\s]*[-]{10}|$)/s", $this->text);
        } elseif (isset($head)) {
            $this->fromPos = $fromPos = strpos($head, "FROM");
            $this->toPos = $toPos = strpos($head, "TO", $fromPos);
            $this->depPos = strpos($head, "DEPART", $toPos);
        }

        $hotels = [];
        $cars = [];

        // if email contains hotels or cars
        while (preg_match("#^(.*?\n)?[ ]*((HOTEL|CAR)\s+.*?)(?:(?:[\r\n][ ]*){3,}(.+))?$#s", $trip, $m)) {
            $trip = $m[1] . ($m[4] ?? '');

            if ($m[3] === "HOTEL") {
                $hotels[] = $m[2];
            } elseif ($m[3] === "CAR") {
                $cars[] = $m[2];
            }
        }

        $array = preg_split("#\={3,}\s+#", $trip, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $array = array_merge($array, $hotels);
        $array = array_merge($array, $cars);

        foreach ($array as $text) {
            if (preg_match("#^CAR#", $text)) {
                if (!$this->parseCar($email, $text)) {
                    return false;
                }
            } elseif (preg_match("#^HOTEL#", $text)) {
                if (preg_match("#^HOTEL[ ]+CHECK-IN#", $text)) {
                    if (!$this->parseHotel1($email, $text)) {
                        return false;
                    }
                } elseif (!$this->parseHotel2($email, $text)) {
                    return false;
                }
            } elseif (
                preg_match("/ {2,}AIRCRAFT:/u", $text)
            ) {
                if (!$this->parseFlight($email, $text)) {
                    return false;
                }
            } elseif (preg_match("/^[> ]*PLEASE CHECK/m", $text)) {
                continue;
            } else {
                $this->logger->debug('unknown type reservation');

                return false;
            }
        }

        return true;
    }

    private function parseFlight(Email $email, $text): bool
    {
//        $this->logger->debug('$text = '.print_r( $text,true));
        $r = $email->add()->flight();

        $confNo = $this->re("#Confirmation\s+Number\s+([A-Z\d]+)#", $this->text);

        if (empty($confNo) && isset($this->recordLocator)) {
            $confNo = $this->recordLocator;
        }
        $r->general()->confirmation($confNo);

        $patterns = [
            'traveler' => '/^([A-Z]+\/[A-Z]+[ ]+[A-Z]+|[A-Z]+\/[A-Z]+)/',
        ];

        // ETKT:HU 880 4978608757 HOU/JUNNING MR
        //without text() not worked don't no why
        if (preg_match_all('/^[ ]*ETKT[ ]*:[ ]*[A-Z]*([\d\/ ]+\d{5}[\d\/ ]+) ([A-Z]+( [A-Z]+)*\/[A-Z]+[A-Z ]*)$/m',
            text($this->text),
            $passengerMatches)) {
            $r->general()
                ->travellers($passengerMatches[2]);
            $r->issued()
                ->tickets(array_map("trim", $passengerMatches[1]), false);
        } else {
            if (preg_match_all("/^[ ]*ETKT[ ]*:[ ]*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(\d+[ \d\-]*)$/m", text($this->text), $passengerMatches)) {
                $r->issued()->tickets($passengerMatches[1], false);
            }

            $blueColorVariants = ['blue', 'BLUE', '#00f', '#00F', '#0000ff', '#0000FF', '#0070C0'];
            $htmlFormat = implode(' or ', array_map(function ($s) {
                return 'normalize-space(@color)="' . $s . '"';
            }, $blueColorVariants));
            $cssFormat = implode(' or ', array_map(function ($s) {
                return 'contains(normalize-space(@style),"color:' . $s . '")';
            }, $blueColorVariants));
            $xpathFragment1 = '(//font[' . $htmlFormat . '])[1]';
            $xpathFragment2 = '(//span[' . $cssFormat . '])[1]';
            //						$travelerNodes = $this->http->FindNodes($xpathFragment1 . ' | ' . $xpathFragment2, null, $patterns['traveler']);
            $travelerNodes = $this->http->FindNodes($xpathFragment1 . ' | ' . $xpathFragment2);
            $travelerNames = $travelerValues = [];

            foreach ($travelerNodes as $value) {
                if (preg_match("#^(.*)(\s+\d{1,2}[A-Z]+\s+\w+)?$#U", $value, $m)) {
                    $travelerNames = explode("&", $m[1]);
                }
            }

            foreach ($travelerNames as $name) {
                if (preg_match("#^\s*([A-Z\s\/\-]+)($|\s|\()#", $name, $m)) {
                    $travelerValues[] = trim($m[1]);
                }
            }
            $travelerValues = array_values(array_filter($travelerValues));

            if (!empty($travelerValues[0])) {
                $travelers = array_unique($travelerValues);
            }

            if (isset($travelers)) {
                $r->general()
                    ->travellers($travelers);
            } else {
                $traveler = $this->re('/\n\s*FOR:\s*([A-Z\d\/ ]+)(?:$|\n)/', $this->text);

                if (!empty($traveler)) {
                    $r->general()
                        ->traveller($traveler);
                } elseif (!empty($this->travellersSubject)) {
                    $r->general()
                        ->travellers($this->travellersSubject);
                }
            }
        }

        if (!empty($this->accountNumber)) {
            $r->program()
                ->account($this->accountNumber, false);
        }

        if (preg_match("/^\s*(?:INVOICE )?TOTAL[: ]+(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d ]*?)\s*$/m", $this->text, $matches)) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $r->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            if (preg_match('/^\s*(?:AIR )?FARE[: ]+(?:\w[ ]+)?(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)\s*$/m', $this->text, $m)) {
                // FARE   F USD      6806.00
                $r->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            if (preg_match('/^\s*(?<name>TAXES AND AIRLINE IMPOSED FEES)[: ]+(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<charge>\d[,.\'\d ]*?)\s*$/m', $this->text, $m)) {
                $r->price()->fee($m['name'], PriceHelper::parse($m['charge'], $currencyCode));
            } elseif (preg_match_all('/\n\s*TAX\s+' . preg_quote($matches['currency'], '/') . '\s+(?<charge>\d[,.\'\d ]*?)(?<name>[A-Z][A-Z\d])\s*$/im', $this->text, $taxMatches, PREG_SET_ORDER)) {
                foreach ($taxMatches as $m) {
                    $r->price()->fee($m['name'], PriceHelper::parse($m['charge'], $currencyCode));
                }
            }

            if (preg_match('/^\s*(?<name>SERVICE FEE)[: ]+(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<charge>\d[,.\'\d ]*?)\s*$/m', $this->text, $m)) {
                $r->price()->fee($m['name'], PriceHelper::parse($m['charge'], $currencyCode));
            }
        }

        if (!empty($this->reservationDate)) {
            $r->general()->date($this->reservationDate);
        }

//        if (preg_match_all("/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]+FREQUENT FLYER[ ]([A-Z\d]+)/m", $this->text, $m)) {
//            $r->program()
//                ->accounts($m[1], false);
//        }

        if (preg_match_all("/(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\/(?<RecordLocator>\w+)/",
            $this->re("#" . $this->t("RESERVATION NUMBER\(S\)") . "\s+([^\n]+)#", $this->text), $m, PREG_SET_ORDER)) {
            foreach ($m as $i) {
                $rls[$i['AirlineName']] = $i['RecordLocator'];
            }
        }

        $segments = preg_split("#(\n[\r ]*){2,}#ims", $text);

        if (strpos(current($segments), 'MISCELLANEOUS') !== false) {
            array_shift($segments);
        }

        if (!trim(end($segments))) {
            array_pop($segments);
        }

        foreach ($segments as $segment) {
            if (preg_match("/^\s*TOUR(?:[ ]{2}|\s*\n)/", $segment)) {
                // it-129006981.eml
                continue;
            }

            $s = $r->addSegment();

            if (preg_match('/(\d+:\d+)\s*DURATION/', $segment, $matches)) {
                $s->extra()->duration($matches[1]);
            }

            $patternFragment1 = '(?<dateDep>\d{1,2}[A-Z]{3}) (?<airports>.*?)[ ]+(?<timeDep>\d{3,4}[AaPpN]?)[ ]+(?<timeArr>\d{3,4}[AaPpN]?)';
            $patternFragment2 = '(?:.+?\b(?<dateArr>\d{1,2}[A-Z]{3}|)\s*)$';

            $pattern1 = '^.*?[ ]+' . $patternFragment1 . '\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<flightNumber>\d+) ' . $patternFragment2;
            $pattern2 = '^(?:[\w ]+ - |[ ]+)?(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<flightNumber>\d+)\s+(?<operator>[ ]*-[ ]*OPERATED[ ]*BY[ ]*[-A-Z\d\s]+?(?: - (?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+)?\s+)?' . $patternFragment1 . '\s*' . $patternFragment2;

            if (preg_match("/{$pattern1}/ms", $segment, $m) || preg_match("/{$pattern2}/msu", $segment, $m)) {
                $fromPos = $this->fromPos;
                $toPos = $this->toPos;
                $depPos = $this->depPos;

                if (!empty($m['airports']) && preg_match('/^(.+?)' . preg_quote($m['airports'], '/') . '/s', $segment, $m2) && strpos($m2[1], "\n") !== false) {
                    // it-8288536.eml
                    $fromPos = strpos($segment, $m['airports']);

                    if (preg_match("/^(.{3,}?[ ]{2,}).{3,}$/", $m['airports'], $m2)
                        || preg_match("/^(.{3,} )(?:MIAMI FL|TORONTO ON)$/", $m['airports'], $m2) // it-129006981.eml
                        || preg_match("/^(\S.{12}\S )\S.{2,}$/", $m['airports'], $m2) // standard column width `FROM`
                    ) {
                        $toPos = $fromPos + mb_strlen($m2[1]);
                    } else {
                        $toPos = null;
                    }
                    $depPos = strpos($segment, $m['timeDep']);
                }
                // if (!$depPos && !empty($m['timeDep']) && preg_match('/^(.+? )' . preg_quote($m['timeDep'], '/') . '/m', $segment, $m2)) {
                //     $depPos = strpos($segment, $m['timeDep']);
                // }
                $fromLength = $toPos - $fromPos;
                $toLength = $depPos - $toPos;

                if (preg_match("#.*[ ]{2,}(.*?TERMINAL.*?)[ ]{2}(.*?TERMINAL.*?)(?:[ ]{2,}|\n)#", $segment, $mat)) {
                    $s->departure()->terminal(trim(preg_replace("#\s*TERMINAL\s*#", '', $mat[1])));
                    $s->arrival()->terminal(trim(preg_replace("#\s*TERMINAL\s*#", '', $mat[2])));
                } elseif (substr_count($segment, 'TERMINAL') === 1 && preg_match("#\n(.*[ ]{2,})(.*?TERMINAL.*?)(?:[ ]{2,}|\n)#", $segment, $mat)) {
                    if (abs($this->fromPos - strlen($mat[1])) < 10) {
                        $s->departure()->terminal(trim(preg_replace("#\s*TERMINAL\s*#", '', $mat[2])));
                    } else {
                        $s->arrival()->terminal(trim(preg_replace("#\s*TERMINAL\s*#", '', $mat[2])));
                    }
                }

                $s->departure()
                    ->date(strtotime($this->correct12h($m['timeDep']),
                        EmailDateHelper::parseDateRelative($m['dateDep'], $this->reservationDate)))
                    ->name(trim(substr($segment, $fromPos, $fromLength)))
                    ->noCode();

                if (empty($m['dateArr'])) {
                    $m['dateArr'] = $m['dateDep'];
                }

                $s->arrival()
                    ->date(strtotime($this->correct12h($m['timeArr']),
                        EmailDateHelper::parseDateRelative($m['dateArr'], $this->reservationDate)))
                    ->name(trim(substr($segment, $fromPos + $fromLength, $toLength)))
                    ->noCode();

                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber']);

                if (isset($rls[$m['airline']])) {
                    $s->airline()->confirmation($rls[$m['airline']]);
                }
            }

            if (!empty($m['operator']) && ($operator = trim($this->re('/OPERATED BY\s*(.+)/s', $m['operator'])))) {
                if (!$s->getAirlineName()) {
                    $s->airline()->name($operator);
                } else {
                    if (preg_match("#(.+) - ([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,5})\s*$#s", $operator, $m)) {
                        $s->airline()
                            ->operator($m[1])
                            ->carrierName($m[2])
                            ->carrierNumber($m[3])
                        ;
                    } else {
                        $s->airline()->operator($operator);
                    }
                }
            } elseif ($operator = trim($this->re('/ OPERATED BY (.+)/', $segment))) {
                $s->airline()->operator($operator);
            }

            $s->extra()
                ->aircraft(trim($this->re('/AIRCRAFT\s*:\s*([^\n]+)/', $segment)), true);

            if (preg_match('/\n\s*([A-Z])\s+([A-Z]{3,})/', $segment, $m)) {
                $s->extra()
                    ->bookingCode($m[1])
                    ->cabin($m[2]);
            }

            if (preg_match('/\s+SEATS\s+(\d{1,3}[A-Z](?:\/\d{1,3}[A-Z])*)\b/', $segment, $m)) {
                $s->extra()->seats(explode('/', $m[1]));
            } elseif (preg_match('/\s+SEAT\s+(\d{1,3}[A-Z])\b/', $segment, $m)) {
                $s->extra()->seat($m[1]);
            }
        }

        return true;
    }

    private function parseCar(Email $email, $text): bool
    {
        $r = $email->add()->rental();

        $r->general()
            ->traveller($this->re("/^([A-Z\s]+\/\D+)\s+\d{2}\w{3}\s*CAR/mu", $this->text));

        $r->general()
            ->confirmation($this->re("#CONFIRMATION:\s*([^\n]+)#ms", $text));

        $account = $this->re("/ACCOUNT NUMBER (\d+)/", $this->text);

        if (!empty($account)) {
            $r->setAccountNumbers([$account], false);
        }

        $r->car()
            ->type($this->re("#TELEPHONE:\s*[^\n]+\s+([^\n]+)#ms", $text));

        if (preg_match("#^CAR\s+(\d{2}\w{3})\s+(.*?)\s{2,}(.*?)\s+(\d{2}\w{3})#", $text, $m)
        || preg_match("#^CAR\s+(\d{2}\w{3})\s+(.*?)\s{1,}(.*?)\n*(\d{2}\w{3})#", $text, $m)) {
            $pickup = EmailDateHelper::parseDateRelative($m[1], $this->reservationDate);
            $dropoff = EmailDateHelper::parseDateRelative($m[4], $this->reservationDate);

            $r->extra()->company($m[2]);

            if (preg_match("/PICK-UP:\s*(\d{1,2})(\d{2})([AaPp])\s+(.*?)\s*({$this->patterns['phone']})*\s+DROP-OFF:\s*(\d{1,2})(\d{2})([AaPp])\s+(.*?)\s*({$this->patterns['phone']})*\s+(?:RATE:|RATE GUARANTEED:)/s", $text, $m)) {
                $pickup = !empty($pickup) ? strtotime($m[1] . ':' . $m[2] . $m[3] . 'M', $pickup) : null;
                $dropoff = !empty($dropoff) ? strtotime($m[6] . ':' . $m[7] . $m[8] . 'M', $dropoff) : null;

                if (empty($m[5])) {
                    $m[5] = $this->re("/\s*TELEPHONE:\s*([\d\s]+)\n/u", $text);
                }

                $r->pickup()
                    ->phone($m[5])
                    ->location($this->nice($m[4]));

                if (empty($m[10])) {
                    $m[10] = $this->re("/\s*TELEPHONE:\s*([\d\s]+)\n/u", $text);
                }

                $r->dropoff()
                    ->phone($m[10])
                    ->location($this->nice($m[9]));
            }

            $r->pickup()->date($pickup);
            $r->dropoff()->date($dropoff);
        }

        if (preg_match("/ESTIMATED TOTAL:\s*(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)\s*\-/", $text, $m)) {
            $r->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['total'], $m['currency']));
        }

        return true;
    }

    private function parseHotel1(Email $email, $text): bool
    {
        $r = $email->add()->hotel();
        $r->general()
            ->confirmation($this->re("#CONFIRMATION:\s+(\w+)#", $text));

        $right = [];

        foreach (array_filter(explode("\n", $this->re("#(.*?)TELEPHONE:#ms", $text))) as $row) {
            $right[] = substr($row, $this->fromPos);
        }

        if (!isset($right[0])) {
            return false;
        }

        $r->hotel()->name($right[0]);

        $r->booked()
            ->checkIn(EmailDateHelper::parseDateRelative($this->re("#CHECK-IN\s*:\s*(\d+[A-Z]+)#", $text), $this->reservationDate))
            ->checkOut(EmailDateHelper::parseDateRelative($this->re("#CHECK-OUT\s*:\s*(\d+[A-Z]+)#", $text), $this->reservationDate))
        ;

        unset($right[0]);
        $r->hotel()
            ->address($this->nice(implode(" ", $right)))
            ->phone(trim($this->re("#TELEPHONE:\s+(.+)#", $text)));

        $traveler = $this->re('/\n\s*FOR:\s*([A-Z\d\/ ]+)(?:$|\n)/', $this->text);

        if (!empty($traveler)) {
            $traveler = $this->travellersSubject;
            $r->general()
                ->traveller($traveler);
        } elseif (!empty($this->travellersSubject)) {
            $r->general()
                ->travellers($this->travellersSubject);
        }

        $rate = trim($this->re("#([\d\,\.]+\s+[A-Z]{3} PER NIGHT)#", $text));

        if (!empty($rate)) {
            $room = $r->addRoom();
            $room->setRate($rate);
        }

        $right = [];

        foreach (array_filter(explode("\n", $this->re("#(\n[> ]*CANCELLATION POLICY:.+)#s", $text))) as $row) {
            $right[] = substr($row, $this->fromPos);
        }

        if (!isset($right[0])) {
            return false;
        }
        $cancellationPolicy = $this->nice(implode("; ", array_filter(array_map("trim", $right))));

        if (!empty($cancellationPolicy)) {
            $r->general()->cancellation($cancellationPolicy);
            $this->detectDeadLine($r, $cancellationPolicy);
        }

        $total = $this->getTotalCurrency($this->re("#TOTAL\s*-\s*\(MAY NOT INCL TAX\):\s+([\d\,\.]+\s+[A-Z]{3})\s#",
            $text));

        if ($total['Total'] !== null) {
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }

        return true;
    }

    private function parseHotel2(Email $email, $text): bool
    {
        $r = $email->add()->hotel();

        if (isset($this->travellersSubject)) {
            $r->general()->travellers($this->travellersSubject);
        }

        $r->general()
            ->confirmation($this->re("#\n*\s+CONFIRMATION:\s*([A-Z\d]+)#i", $text));

        if (!empty($this->accountNumber)) {
            $r->program()
                ->account($this->accountNumber, false);
        }

        $r->hotel()
            ->name($this->nice($this->re("#HOTEL\s+([^\n]+)#", $text)))
            ->address($this->nice($this->re("#\n*\s+ADDRESS:\s*(.*?)\s+TELEPHONE:#ms", $text)))
            ->phone($this->re("#\n*\s+TELEPHONE:\s*([\d\-+ \(\)]+)#", $text))
            ->fax($this->re("#\n*\s+FAX:\s*([\d\-+ \(\)]+)#", $text));

        $r->booked()
            ->checkIn(EmailDateHelper::parseDateRelative($this->re("#CHECK-IN\s*:\s*(\d+\w{3})#", $text), $this->reservationDate))
            ->checkOut(EmailDateHelper::parseDateRelative($this->re("#CHECK-OUT\s*:\s*(\d+\w{3})#", $text), $this->reservationDate))
            ->guests($this->re("#(\d+)\s+GUESTS?\s+#", $text));

        $room = $r->addRoom();
        $room->setType($this->re("#\d+\s+GUESTS?\s+([^\n]+)#", $text));

        $cancellationText = $this->nice($this->re("#CANCELLATION POLICY:\s*(.*?)(?:\n[> ]*[A-Z][A-Z ]+:|\s+[-]{10,}\s+INVOICE TOTAL|$)#s",
            $text));

        if (!empty($cancellationText)) {
            $r->general()->cancellation($cancellationText);
            $this->detectDeadLine($r, $cancellationText);
        }

        $total = $this->re("#\n[> ]*TOTAL\s*[^:]+:\s*(\d[,.\'\d]*?\s*[A-Z]{3}\b)#", $text);

        if ($total) {
            $total = $this->getTotalCurrency($total);
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText): void
    {
        if (preg_match("/(?<hour>\d+) HR CANCELLATION REQUIRED, GUARANTEE GIVEN/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['hour'] . ' hours');
        }

        $h->booked()
            ->parseNonRefundable("/^CANCELS ALWAYS CHARGED-FEE [\d\.\,]+ [A-Z]{3}\b/");
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->detectLang as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $cur) ? $cur : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function glue($str, $with = ", ")
    {
        $source = is_array($str) ? $str : explode("\n", $str);

        return implode($with, $source);
    }

    private function nice($str, $with = ", ")
    {
        $source = trim($this->glue($str, $with));
        $source = preg_replace("/\s+/", ' ', $source);
        $source = preg_replace("/\s+,\s+/", ', ', $source);

        return $source;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    // 1200N, 224P, 700A
    private function correct12h($date)
    {
        $date = str_replace(':', '', $date);

        if (preg_match('/\d{3,4}N$/', $date)) {
            $date = substr_replace($date, 'PM', -1, 1);
        } else {
            if (preg_match('/\d{3,4}(P|A)$/', $date)) {
                $date = $date . 'M';
            }
        }

        if (preg_match('/(\d{3,4})[AP]M$/', $date, $matches)) {
            $date = substr_replace($date, ':', -4, 0);
        }

        return $date;
    }
}
