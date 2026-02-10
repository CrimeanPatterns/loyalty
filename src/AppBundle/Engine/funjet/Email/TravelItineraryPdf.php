<?php

namespace AwardWallet\Engine\funjet\Email;

// use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelItineraryPdf extends \TAccountChecker
{
    public $mailFiles = "funjet/it-201782168.eml, funjet/it-37290195.eml, funjet/it-379863662.eml, funjet/it-48647792.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            "Passengers"    => ["Passengers", "Travelers"],
            "Reservation #" => ["Reservation #", "Reservation Number"],
        ],
    ];

    private static $detectHeaders = [
        'appleva' => [
            'from' => [],
            'subj' => [],
        ],
        'travimp' => [
            'from' => ['@travimp.com'],
            'subj' => [
                'Travel Impressions',
            ],
        ],
        'mileageplus' => [
            'from' => [],
            'subj' => [],
        ],
        'etihad' => [
            'from' => ['@etihad.'],
            'subj' => [
                'Etihad Holidays',
            ],
        ],
        'funjet' => [
            'from' => [
                '@funjetvacations.com',
                '@tntvacations.com', '@blueskytours.com', // unknown providers
            ],
            'subj' => [
                'Funjet',
            ],
        ],
        'rapidrewards' => [
            'from' => [
                '@southwestvacations.com',
            ],
            'subj' => [
                'Southwest Vacations Travel Itinerary',
            ],
        ],
        // funjet - last item!
    ];
    private $detectSubject = [
        'en' => 'Travel Itinerary',
    ];

    private $providerCode;
    private $detectProvider = [
        'appleva' => [
            'Thank you for choosing Apple Vacations',
        ],
        'travimp' => [
            'Travel Impressions',
        ],
        'mileageplus' => [
            'Thank you for choosing United Vacations',
        ],
        'etihad' => [
            'Thank you for choosing Etihad Holidays',
        ],
        'funjet' => [
            'Thank you for choosing Funjet Vacations',
            'Universal Studios Hollywood',
            'Thank you for choosing Blue Sky Tours', // unknown provider
        ],
        'rapidrewards' => [
            'Thank you for choosing Southwest Vacations',
        ],
        // funjet - last item!
    ];

    private $detectBody = [
        "en" => ["Travel Itinerary", "E-Travel Document"],
    ];
    private $pdfPattern = ".*\.pdf";
    private $keywords = [
        'hertz' => [
            'Hertz',
        ],
        'alamo' => [
            'Alamo',
        ],
    ];
    private $passengers;

    private $patterns = [
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$detectHeaders);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectHeaders as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['subject'])) {
            return false;
        }

        foreach (self::$detectHeaders as $code => $arr) {
            foreach ($this->detectSubject as $dSubject) {
                if (strpos($headers['subject'], $dSubject) !== false) {
                    if (isset($arr['from'])) {
                        foreach ($arr['from'] as $f) {
                            if (!empty($headers['from']) && stripos($headers['from'], $f) !== false) {
                                $this->providerCode = $code;

                                return true;
                            }
                        }
                    }

                    if (isset($arr['subj'])) {
                        foreach ($arr['subj'] as $s) {
                            if (strpos($headers['subject'], $s) !== false) {
                                $this->providerCode = $code;

                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);
        $text = '';

        foreach ($pdfs as $pdf) {
            if (($text .= \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }
        }

        if (empty($this->getProvider($parser, $text))) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($text, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }

            foreach ($this->detectBody as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($text, $dBody) !== false) {
                        $this->lang = $lang;

                        if (!$this->parsePdf($email, $text)) {
                            $this->logger->info("parsePdf is failed'");

                            return null;
                        }
                        $this->providerCode = $this->getProvider($parser, $text);

                        if (!empty($this->providerCode)) {
                            $email->setProviderCode($this->providerCode);
                        }

                        break;
                    }
                }
            }
        }

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

    private function getProvider(PlancakeEmailParser $parser, $text)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (!empty($this->providerCode)) {
            return $this->providerCode;
        }

        foreach ($this->detectProvider as $code => $detectProvider) {
            foreach ($detectProvider as $dProvider) {
                if (stripos($text, $dProvider) !== false) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function parsePdf(Email $email, string $text): bool
    {
        $segments = $this->split("#\n[ ]*(" . $this->preg_implode(['Flight Details', 'FLIGHT INFORMATION', 'Hotel Details', 'HOTEL INFORMATION', 'Additional Travel Information', 'Ground Transportation', 'Additional Services', 'Travel Value', 'FEATURES AND INFORMATION', 'HOTEL DETAILS', 'Terms & Conditions / Things to know before you go']) . "\n)#iu", $text, false);

        // Travel Agency
        $email->obtainTravelAgency();

        if (!empty($trip = $this->re("/{$this->opt($this->t('Reservation #'))}[ ]*([A-Z\d]{5,})\s+/", $segments[0] ?? ''))) {
            $email->ota()->confirmation($trip);
        }

        $passengersText = $this->re("#\n\s*" . $this->preg_implode($this->t("Passengers")) . "\n\s*([\s\S]+?)\n\n#", $segments[0] ?? '');
        $this->passengers = array_map(function ($v) {return preg_replace("#(.+?)(\s*\(.*|$)#", '$1', $v); },
                preg_split("#(?:\s{2,}|\s*\n\s*)#", trim($passengersText)));

        foreach ($segments as $stext) {
            switch (true) {
                case $this->re("#^\s*(FLIGHT INFORMATION|Flight Details)#", $stext):
                    $this->flights($email, $stext);

                    break;

                case $this->re("#^\s*(HOTEL INFORMATION|Hotel Details|HOTEL DETAILS)#", $stext):
                    $this->hotels($email, $stext);

                    break;

                case $this->re("#^\s*(Ground Transportation)#i", $stext):
                    $this->cars($email, $stext);

                    break;

                default: break;
            }
        }

        return true;
    }

    private function flights(Email $email, string $text): void
    {
        $routes = $this->split("#\n[ ]*(" . $this->preg_implode(['Departure', 'Arrival', 'Depart', 'Return', '- Connecting To -']) . "(?:[: ].* \d{4}\b.*\n|\s*\n))#iu", $text);

        $flights = [];

        foreach ($routes as $key => $route) {
            $date = $this->normalizeDate($this->re("#^\s*" . $this->preg_implode(['Departure', 'Arrival', 'Depart', 'Return']) . "[: ]+(.+)#", $route));
            $segmentsL = $this->split("#(?:^|\n)(.+\n(?:.*Operated By.+\n)?\s*Flight\#:)#iu", $route);
            $flights = array_merge($flights, array_map(function ($v) use ($date) {return ["date" => $date, "text" => $v]; }, $segmentsL));
        }

        if (!empty($flights)) {
            $f = $email->add()->flight();

            if (!empty($this->passengers)) {
                $passengersText = $this->re("#\n\s*" . $this->preg_implode($this->t("Passengers")) . "\n\s*([\s\S]+?)(?:\n\n|\n" . $this->preg_implode(['Departure', 'Arrival', 'Depart', 'Return']) . ")#", $text);
                $this->passengers = array_map(function ($v) {return preg_replace("#(.+?)(\s*\(.*|$)#", '$1', $v); },
                        preg_split("#(?:\s{2,}|\s*\n\s*)#", trim($passengersText)));
            }
            $f->general()
                ->noConfirmation()
                ->travellers($this->passengers, true);
        }

        $lastDate = '';

        foreach ($flights as $key => $segText) {
            $stext = $segText['text'];

            $s = $f->addSegment();

            // Airline
            if (preg_match("#(.+)\n(?:.*Operated By (.+)\n)?\s*Flight\#?:[ ]*(\d{1,5})\s*#i", $stext, $m)) {
                $s->airline()
                    ->name($this->re("#\s*(.+?)(?:[ ]{2,}|\n|$)#", $m[1]))
                    ->number($m[3]);

                if (!empty($m[2])) {
                    if (preg_match("#(.+) -- ([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d{1,5})\s*$#", $m[2], $mat)) {
                        $m[2] = trim($mat[1]);
                        $s->airline()
                            ->carrierName($mat[2])
                            ->carrierNumber($mat[3]);
                    }
                    $s->airline()
                        ->operator(trim($this->re("#(.+?)(?: DBA |$)#", $m[2])));
                }
            }

            if (preg_match("#Airline Confirmation: ([A-Z\d]{5,7})\s+#", $stext, $m)) {
                $s->airline()
                    ->confirmation($m[1]);
            }

            $table = $this->splitCols($stext, [0, strlen($this->re("#\n(.+ )Departing:#", $stext))]);

            // Departure
            if (preg_match("#Departing: ([\s\S]+?)\s*\(([A-Z]{3})\)\s*(\d{1,2}:\d{2}(?:\s*[APap][Mm])?)\s*\n#", $table[1] ?? '', $m)) {
                $s->departure()->name(preg_replace('/\s+/', ' ', $m[1]))->code($m[2]);

                if (!empty($segText['date'])) {
                    $s->departure()
                        ->date(strtotime($this->normalizeTime($m[3]), $segText['date']));
                    $lastDate = $segText['date'];
                } else {
                    $s->departure()
                        ->date(strtotime($this->normalizeTime($m[3]), $lastDate));
                }
            }

            // Arrival
            if (preg_match("#A?r?r?i?ving: ([\s\S]+?)\s*\(([A-Z]{3})\)\s*(\d{1,2}:\d{2}(?:\s*[APap][Mm])?)\s*\n#", $table[1] ?? '', $m)) {
                $s->arrival()->name(preg_replace('/\s+/', ' ', $m[1]))->code($m[2]);

                if (!empty($segText['date'])) {
                    $s->arrival()
                        ->date(strtotime($this->normalizeTime($m[3]), $segText['date']));
                    $lastDate = $segText['date'];
                } else {
                    $s->arrival()
                        ->date(strtotime($this->normalizeTime($m[3]), $lastDate));
                }
            }

            // Extra
            if (preg_match("#Seats: ([\s\S]+?)\s*-#", $table[0] ?? '', $mat)) {
                $seats = array_filter(array_map(function ($v) {
                    if (preg_match("#^\s*(\d{1,3}[A-Z])\s*$#", $v, $m)) {
                        return $m[1];
                    }

                    return null;
                }, explode(",", $mat[1])));

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }

            if (preg_match("#N?u?m?b?er of Stops: (\d+)(?:\s+|$)#", $table[1] ?? '', $m)) {
                $s->extra()->stops($m[1]);
            }

            if (preg_match("#Class: ([A-Z]{1,2})(?: (.+))?#", $table[0] ?? '', $m)) {
                $s->extra()
                    ->bookingCode($m[1])
                    ->cabin($m[2] ?? null, true, true)
                ;
            }
        }
    }

    private function hotels(Email $email, string $text): void
    {
        $h = $email->add()->hotel();

        // General
        if (empty($this->passengers)) {
            if (preg_match("#\n\s*Reserved For:[ ]*(.+?)[ ]+-[ ]+#", $text, $m)) {
                $h->general()
                    ->travellers($m[1]);
            }
        } else {
            $h->general()
                ->travellers($this->passengers, true);
        }

        // Hotel
        if (preg_match("/^[ ]*Check-in[: ]+[\s\S]{1,100}\d{4}\n{0,1}((?:\n[ ]*.{2,}){1,3}?)\n{1,2}[ ]{0,20}(?:Reserved For:|Room Type:|.*\S{2}[ ]{2,}\S{2})/m", $text, $m)) {
            $h->hotel()->name(preg_replace('/\s+/', ' ', trim($m[1])));
        }

        // Booked
        $text = preg_replace("/(All Inclusive)\n*(Earn Rapid Rewards)/", "$1 $2", $text);

        if (!empty($h->getHotelName()) && preg_match("#\n\s*Check-in[: ]+(.+?)[ ]*-[ ]*Check-out[: ]+(.+\d{4})\s*{$h->getHotelName()}#su", $text, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m[1]))
                ->checkOut($this->normalizeDate($m[2]));
        }

        if (preg_match("/Reserved For:.* (\d{1,3})[ ]{1,4}Adult/i", $text, $m)) {
            $h->booked()
                ->guests($m[1]);
        }

        if (preg_match("/Reserved For:.* (\d{1,3})[ ]{1,4}Child/i", $text, $m)) {
            $h->booked()
                ->kids($m[1]);
        }

        $tableText = $this->re("/\n([ ]*Room Type:(?:.*\n){1,12})/", $text);

        // it-379863662.eml, it-48647792.eml
        $tableText = preg_replace("/^([ ]*Room Type:[^[:lower:]]{25,35}?) ((?:\d+ )?[[:upper:]][[:lower:]].*)$/m", '$1' . str_repeat(' ', 2) . '$2', $tableText);

        // it-201782168.eml
        $tableText = preg_replace("/^([ ]{0,20}[^[:lower:]]{30,40}?) ([[:upper:]][[:lower:]].*)$/m", '$1' . str_repeat(' ', 90) . '$2', $tableText);

        $tablePos = [0];
        $pos2Variants = [];

        if (preg_match("/^([ ]*Room Type:[ ]{0,2}\S.{0,40}?\S[ ]{2,})(\S.+)$/m", $tableText, $matches) && strpos($matches[2], '  ') === false) {
            $pos2Variants[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^([ ]*Hotel Confirmation:[ ]{0,2}\S.{1,20}?\S[ ]{2,})(\S.+)$/m", $tableText, $matches) && strpos($matches[2], '  ') === false) {
            $pos2Variants[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.{30,}) {$this->patterns['phone']}$/m", $tableText, $matches)) {
            $pos2Variants[] = mb_strlen($matches[1]);
        }

        sort($pos2Variants);

        if (count($pos2Variants) > 0) {
            $tablePos[] = array_shift($pos2Variants);
        }
        $table = $this->splitCols($tableText, $tablePos);

        if (preg_match("#Room Type:\s*([\s\S]+?)(?:\n[ ]{0,20}(?:[A-Za-z]+( [A-Za-z]+){0,4}):|$)#", $table[0] ?? null, $m)) {
            if (preg_match("#^(.+?),\s*(.+)#s", $m[1], $mat)) {
                $h->addRoom()
                    ->setType(preg_replace("/(\s*\n\s*)/", " ", $mat[1]))
                    ->setDescription($mat[2]);
            } else {
                $h->addRoom()
                    ->setType(preg_replace("/(\s*\n\s*)/", " ", $m[1]));
            }
        }

        if (!empty($table[0]) && (
                preg_match("#\n[ ]*(Hotel Confirmation):[ ]*(\w+(?: \w+){0,2})(?:[ ]*\n|\s*$)#i", $table[0], $m)
                || preg_match("#\n[ ]*(Hotel Confirmation):[ ]*(\w+(?: \w+){0,2})(?:[ ]*\n|\s*$)#i", $table[1], $m)
                || preg_match("#\n[ ]*(Vacation Provider Hotel Confirmation):[ ]*(\w+(?: \w+){0,2})(?:[ ]*\n|\s*$)#i", $table[0], $m)
            )
        ) {
            $h->general()->confirmation(str_replace(' ', '', $m[2]), $m[1]);
        } elseif (!preg_match("#Hotel Confirmation:#", $text, $m)) {
            $h->general()->noConfirmation();
        }

        if (!empty($table[1])
            && preg_match("/^\s*(?<address>(.+\n){1,4})[ ]*(?<phone>{$this->patterns['phone']})(?:\n|\s*$)/", $table[1], $m)
        ) {
            $h->hotel()->address(preg_replace('/\s+/', ' ', trim($m['address'])))->phone($m['phone']);
        } elseif (!empty($table[1])
            && preg_match("/^\s*(?<address>(.+(?:\n|$)){1,4}?)(?:\n|\s*$)/", $table[1], $m)
        ) {
            $h->hotel()->address(preg_replace('/\s+/', ' ', trim($m['address'])));
        } else {
            $h->hotel()->noAddress();
        }
    }

    private function cars(Email $email, string $text): void
    {
        if (preg_match("#Pick up Date:#", $text) == 0) {
            $this->logger->debug("skip");

            return;
        }

        $r = $email->add()->rental();

        if (preg_match("#^.+\s+(\w+)\s+([^\-]+)#", $text, $m)) {
            $company = $m[1];
            $type = $m[2];
        }

        $r->general()->traveller($this->re("#Reserved For:[ ]*(.+)#", $text));

        $mainInfo = strstr($text, 'Reserved For:');

        if (!empty($mainInfo) && preg_match('/PLEASE TAKE NOTE:/', $mainInfo)) {
            $mainInfo = strstr($mainInfo, 'PLEASE TAKE NOTE:', true);
            $block = $this->re("/^(.+)Pick up Date:/mu", $mainInfo);
            $mainInfo = $block . $mainInfo;
        } elseif (!empty($mainInfo) && stripos($mainInfo, 'Please take note:') !== false) {
            $mainInfo = strstr($mainInfo, 'Please take note:', true);
        }

        if (empty($mainInfo)) {
            $this->logger->debug('other format rental');

            return;
        }

        $tablePos = [0];
        $pos2Variants = [];

        if (preg_match("/^([ ]*Pick up Date:[ ]{0,2}\S.{0,40}?\S[ ]{2,})Pick up and Drop off Address:$/m", $mainInfo, $matches)) {
            $pos2Variants[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^([ ]*Drop off Date:[ ]{0,2}\S.{0,40}?\S[ ]{2,})(\S.+)$/m", $mainInfo, $matches) && strpos($matches[2], '  ') === false) {
            $pos2Variants[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^([ ]*Days confirmed:[ ]{0,2}\d{1,3}[ ]+)(\S.+)$/m", $mainInfo, $matches) && strpos($matches[2], '  ') === false) {
            $pos2Variants[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^([ ]*Car Confirmation:[ ]{0,2}\S.{1,20}?\S[ ]{2,})(\S.+)$/m", $mainInfo, $matches) && strpos($matches[2], '  ') === false) {
            $pos2Variants[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(.{30,} ){$this->patterns['phone']}$/m", $mainInfo, $matches)) {
            $pos2Variants[] = mb_strlen($matches[1]);
        }

        sort($pos2Variants);

        if (count($pos2Variants) > 0) {
            $tablePos[] = array_shift($pos2Variants);
        }
        $table = $this->splitCols($mainInfo, $tablePos);

        if (count($table) !== 2) {
            $this->logger->debug("other format rental table-info");

            return;
        }

        if (preg_match("#^[ ]*(Car Confirmation):[ ]*([-A-z\d]{4,})[ ]*$#m", $table[0], $m)) {
            $r->general()->confirmation($m[2], $m[1]);
        }

        // Pick Up
        $r->pickup()
            ->date(strtotime($this->re("#Pick up Date:[ ]*(.+)#", $table[0])));

        if (preg_match("#{$this->preg_implode('Pick up and Drop off Address')}:\s+(.+)#s", trim($table[1]), $m)) {
            if (preg_match("/^(?<address>[\s\S]+?)\n+[ ]*(?<phone>{$this->patterns['phone']})(?:[ ]*\n|\s*$)/", $m[1], $m)
                && strlen(preg_replace("/[^\d]+/", '', $m['phone'])) > 5
            ) {
                $m[1] = trim($m[1]);
                $r->pickup()->phone($m[2]);
            }
            $r->pickup()->location(preg_replace("/([ ]*\n+[ ]*)+/", ', ', trim($m['address'])));
            $r->dropoff()->same();
        } elseif (stripos($table[1], 'Pick up Address:') !== false) {
            $r->pickup()->location(preg_replace('/\s+/', ' ', $this->re("/Pick up Address:\s*(.{3,}?)\s*Drop off Address:/s", $table[1])));
            $r->dropoff()->location(preg_replace('/\s+/', ' ', $this->re("/Drop off Address:\s*(.{3,})/s", $table[1])));
        } else {
            // no examples yet
            $this->logger->debug("!!!new format rental!!!");

            return;
        }

        // Drop Off
        $r->dropoff()
            ->date(strtotime($this->re("#Drop off Date:[ ]*(.+)#", $table[0])));

        // Car
        if (isset($type)) {
            $r->car()->type(preg_replace("/\s+/", ' ', $type));
        }

        // Extra
        if (isset($company)) {
            $r->extra()->company($company);
        }

        if (!empty($keyword = $r->getCompany())) {
            $rentalProvider = $this->getRentalProviderByKeyword($keyword);

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
            } else {
                $r->extra()->company($keyword);
            }
        }
    }

    private function getRentalProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
            }
        }

        return null;
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
        $str = str_replace("\n", "", $str);
        //$this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // Thursday, June 15, 2023
            "/^\s*\w+[,\s]\s*([[:alpha:]]+)\s+(\d{1,2})[,\s]\s*(\d{4})\s*$/",
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        // if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#s", $str, $m)) {
        //     if ($en = MonthTranslate::translate($m[1], $this->lang)) {
        //         $str = str_replace($m[1], $en, $str);
        //     }
        // }
        // $this->logger->debug('$str = '.print_r( $str,true));

        return strtotime($str);
    }

    private function normalizeTime($str)
    {
        $str = preg_replace("#\s+#", ' ', $str);
        $in = [
            "#^\s*(\d+:\d+)([AP])\s*$#i", //11:40P
        ];
        $out = [
            "$1 $2M",
        ];
        $str = preg_replace($in, $out, $str);

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

    private function split($re, $text, $shiftFirst = true)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            if ($shiftFirst == true || ($shiftFirst == false && empty($r[0]))) {
                array_shift($r);
            } else {
                $ret[] = array_shift($r);
            }

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function rowColsPos($row): array
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

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
