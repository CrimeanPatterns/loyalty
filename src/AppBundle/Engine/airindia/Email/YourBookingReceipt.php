<?php

namespace AwardWallet\Engine\airindia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class YourBookingReceipt extends \TAccountChecker
{
    public $mailFiles = "airindia/it-21225130.eml, airindia/it-21225303.eml, airindia/it-21869179.eml, airindia/it-22755014.eml, airindia/it-24477919.eml, airindia/it-25401129.eml, airindia/it-95420632.eml";

    public $reFrom = ["Air India Express Reservation", "@airindiaexpress.in"];
    public $reBody = [
        'en'  => ['Confirmation number', 'Journey 1'],
        'en2' => ['Confirmation Number', 'Itinerary From/To'],
    ];
    public $reSubject = [
        'Your Booking Receipt',
    ];
    public $lang = '';
    public $providerCode;
    public $pdfNamePattern = ".*pdf";
    public static $detectProvider = [
        'airindia' => [
            'from' => ['@airindiaexpress.in'],
            'subject' => ['Your Booking Receipt'],
            'pdfBody' => ['airindiaexpress.in'],
        ],
        'skyexp' => [
            'from' => ['@skyexpress.gr'],
            'subject' => ['Your Sky Express Reservation:'],
            'pdfBody' => ['www.skyexpress.gr'],
        ],
    ];
    public static $dict = [
        'en' => [
            'Confirmation number' => ['Confirmation number', 'Confirmation Number'],
            'Departing'           => ['Departing', 'Departure Time'],
            'Arriving'            => ['Arriving', 'Arrival Time'],
            'Duration'            => ['Duration', 'Flight Duration'],
            'passengersStart'     => ['Passengers'],
            'passengersEnd'       => ['Optional extras', 'Optional Charges'],
            'passengersTd2'       => ['Fare/Taxes and Fees', 'Fare type'],
            'fees'                => ['Special Services:', 'Optional extras:', 'GST Total:', 'Penalty fee:'],
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = 'Html';
        $needParseByAttach = false;

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language (body)');
            $needParseByAttach = true;
        } elseif (!$this->parseEmail($email)) {
            $this->logger->debug('can\'t parse by body. go to see attach');
            $needParseByAttach = true;
        }

        if ($needParseByAttach) {
            $type = 'Pdf';
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

            if (isset($pdfs) && count($pdfs) > 0) {
                foreach ($pdfs as $pdf) {
                    if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                        if (!$this->assignLang($text)) {
                            $this->logger->debug('can\'t determine a language (attach)');

                            continue;
                        }
                        foreach (self::$detectProvider as $code => $param) {
                            if (!isset($param['pdfBody'])) {
                                continue;
                            }

                            foreach ($param['pdfBody'] as $reBody) {
                                if (stripos($text, $reBody) !== false) {
                                    $this->providerCode = $code;
                                }
                            }
                        }

                        if (!$this->parseEmailPdf($text, $email)) {
                            return null;
                        }
                    }
                }
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }
        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'AirIndiaExpress')] | //a[contains(@href,'airindiaexpress.in')] | //text()[contains(normalize-space(),'airindiaexpress.in')]")->length > 0) {
            if ($this->assignLang()) {
                return true;
            }
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach (self::$detectProvider as $code => $param) {
                if (!isset($param['pdfBody'])) {
                    continue;
                }

                foreach ($param['pdfBody'] as $reBody) {
                    if (stripos($text, $reBody) !== false && $this->assignLang($text)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            foreach (self::$detectProvider as $code => $param) {
                $flag = false;

                if (!isset($param['from']) || !isset($param['subject'])) {
                    continue;
                }

                foreach ($param['from'] as $reFrom) {
                    if (stripos($headers['from'], $reFrom) !== false) {
                        $flag = true;
                        $this->providerCode = $code;
                        break;
                    }
                }

                if ($flag) {
                    foreach ($param['subject'] as $reSubject) {
                        if (stripos($headers["subject"], $reSubject) !== false) {
                            return true;
                        }
                    }
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
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $formats = 2; // html | attach-pdf
        $cnt = $formats * count(self::$dict);

        return $cnt;
    }

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmailPdf($textPDF, Email $email): bool
    {
        $noPrice = false;
        $priceBlock = strstr($textPDF, 'Cost breakdown');

        if (empty($priceBlock)) {
            $priceBlock = strstr($textPDF, 'Reservation Totals');
        }

        if (empty($priceBlock) && empty($str = strstr($textPDF, 'Payment details', true))
            && empty($str = strstr($textPDF, 'Payment Summary', true))
        ) {
            $noPrice = true;
        }

        if (empty($priceBlock) && $noPrice !== true) {
            $this->logger->debug('other format (price) attach');

            return false;
        }
        //delete unnecessary text
        if (!empty($str = strstr($priceBlock, 'Conditions of Carriage', true))) {
            $priceBlock = $str;
        }

        if (!empty($str = strstr($textPDF, 'Payment details', true))
            || !empty($str = strstr($textPDF, 'Payment Summary', true))
            || !empty($str = strstr($textPDF, 'Cost breakdown', true))
        ) {
            $textPDF = $str;
        }
        $textPDF = preg_replace("/^ *https?:.+\n.+?Air India Express email/m", '', $textPDF); //garbage between pages

        $flights = $this->splitter("/^( *{$this->opt($this->t('Flight no.'))} +{$this->opt($this->t('Departing'))})/m",
            $textPDF);

        if (empty($flights)) {
            $this->logger->debug('not found any flights in attach');

            return false;
        }

        if ($noPrice !== true) {
            $tot = $this->re("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Airfare:'))}\s+(.+)$/m", $priceBlock);
            $tot = $this->getTotalCurrency($tot);
            $email->price()
                ->cost($tot['Total'])
                ->currency($tot['Currency']);

            $tot = $this->re("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Tax:'))}\s+(.+)$/m", $priceBlock);
            $tot = $this->getTotalCurrency($tot);
            $email->price()
                ->tax($tot['Total'])
                ->currency($tot['Currency']);

            $tot = $this->re("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Total:'))}\s+(.+)$/m", $priceBlock);
            $tot = $this->getTotalCurrency($tot);
            $email->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);

            $fees = (array)$this->t('fees');

            foreach ($fees as $fee) {
                $tot = $this->re("/(?:^[ ]*|[ ]{2}){$this->opt($fee)}\s+(.+)$/m", $priceBlock);
                $tot = $this->getTotalCurrency($tot);

                if ($tot['Total'] !== null) {
                    $email->price()
                        ->fee(trim($fee, " :"), $tot['Total']);
                }
            }
        }

        $dateRes = $this->normalizeDate($this->re("/{$this->opt($this->t('Booked on'))}[ ]+(.{6,})/", $textPDF));

        if (empty($dateRes)) {
            $dateRes = $this->normalizeDate($this->re("/{$this->opt($this->t('Booked on'))}\n.+? {3,}(.+)/", $textPDF));
        }
        $confNo = $this->re("/{$this->opt($this->t('Confirmation number'))}[\s:]+([A-Z\d]{5,})\b/", $textPDF);

        foreach ($flights as $rootFlight) {
            $r = $email->add()->flight();
            $r->general()
                ->confirmation($confNo)
                ->date($dateRes);

            $paxText = preg_match("/\n([ ]*{$this->opt($this->t('passengersStart'))}.*\n+[\s\S]+?)\n+[ ]*{$this->opt($this->t('passengersEnd'))}/", $rootFlight, $m) ? $m[1] : null;

            $pos[0] = 0;
            $posTd2Variants = [];

            if (preg_match("/^(.+? ){$this->opt($this->t('passengersTd2'))}/", $paxText, $m)) {
                $posTd2Variants[] = mb_strlen($m[1]);
            }

            if (preg_match_all("/^( *.+? )(?:ECONOMY|\w+ *\/.+[\/\)])/m", $paxText, $pos1Matches)) {
                $posTd2Variants = array_merge($posTd2Variants, array_map('strlen', $pos1Matches[1]));
            }

            if (count($posTd2Variants)) {
                $pos[1] = min($posTd2Variants);
            }

            if (count($pos) == 2) {
                $arr = $this->splitCols($paxText, $pos);
                $colPax = array_shift($arr);
                $tabPax = array_filter(array_map("trim",
                    explode("\n\n", strstr($colPax, "\n"))));
                $pax = [];

                foreach ($tabPax as $value) {
                    $pax[] = trim(preg_replace("/\s+/", ' ',
                        $this->re("/^[ ]*([[:alpha:]][-.\'’[:alpha:]\s]*?[[:alpha:]])\s*(?:Adult|ADT|CHD|Child|$)/mu", $value)));
                }

                if (count($pax)) {
                    $r->general()->travellers($pax);
                }
            }

            $seatsByPassenger = [];
            $seatsText = strstr($rootFlight, 'Optional extras');
            if (empty($seatsText)) {
                $seatsText = strstr($rootFlight, 'Optional Charges');
            }

            if (!empty($str = strstr($seatsText, 'Journey ', true))) {
                $seatsText = $str;
            }
            if (!empty($str = strstr($seatsText, 'Payment Summary', true))) {
                $seatsText = $str;
            }
            if (!empty($str = strstr($seatsText, 'IMPORTANT INFORMATION', true))) {
                $seatsText = $str;
            }
            $tablePos = [0];

            if (preg_match("/^(.+? )(?:Extras|Charge Description)/m", $seatsText, $m)) {
                $tablePos[] = mb_strlen($m[1]);
            }

            if (preg_match("/^(.+? ){$this->opt($this->t('Seat'))}/m", $seatsText, $m)) {
                $tablePos[] = mb_strlen($m[1]);
            }

            if (preg_match("/^(.+? ){$this->opt($this->t('Total'))}/m", $seatsText, $m)) {
                $tablePos[] = mb_strlen($m[1]);
            }
            $patterns['seat'] = "(?:\d{1,3}[A-Z]|(?i)Unassigned(?-i))";

            $passengersRows = $this->splitter("/^(.{80,}[ ]{2}(?:{$patterns['seat']}[ ,]*)+(?:[ ]{2}.*|$))/m", $seatsText);

            foreach ($passengersRows as $pRow) {
                $seatsTable = $this->splitCols($pRow, $tablePos);

                if (!empty($seatsTable[2]) && preg_match("/^\s*((?:{$patterns['seat']}[ ,]*)+)/", $seatsTable[2], $m)) {
                    $seatsByPassenger[] = array_map('trim', preg_split('/\s*,\s*/', $m[1]));
                }
            }

            if (count($seatsByPassenger) && count($r->getTravellers())
                && count($seatsByPassenger) !== count($r->getTravellers())
            ) {
                $seatsByPassenger = [];
            }

            $textSegments = strstr($rootFlight, 'Passengers', true);

            // 11:20
            $patterns['time'] = '\d{1,2}:\d{2}';

            // 11:20 25/ August /2018    |    Sat - 16 Oct 2021 16:00
            $patterns['timeDate'] = "({$patterns['time']}[ ]+\d{1,2}[\/ ]{1,3}[[:alpha:]]{3,}[\/ ]{1,3}\d{2,4}|[-[:alpha:]]+\s+-\s+\d{1,2}\s+[[:alpha:]]{3,}\s+\d{2,4}\s+{$patterns['time']})";

            $headersPos = [0];

            if (preg_match("/^(.+? ){$this->opt($this->t('Departing'))}/m", $textSegments, $m)) {
                $headersPos[] = mb_strlen($m[1]);
            }

            if (preg_match("/^(.+? ){$this->opt($this->t('Arriving'))}/m", $textSegments, $m)) {
                $headersPos[] = mb_strlen($m[1]);
            }

            if (preg_match("/^(.+? ){$this->opt($this->t('Duration'))}/m", $textSegments, $m)) {
                $headersPos[] = mb_strlen($m[1]);
            }
            $segments = $this->splitter("/^((?: *\d+ *{$this->opt($this->t('Hour'))}.*\n)? *(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])[ \-]+\d+ {3,})/m",
                $textSegments);

            foreach ($segments as $i => $rootSeg) {
                $s = $r->addSegment();

                $tablePos = $headersPos;

                if (preg_match("/^((.+)\b{$patterns['timeDate']}[ ]+){$patterns['timeDate']}/mu", $rootSeg, $m)) {
                    $tablePos[1] = mb_strlen($m[2]);
                    $tablePos[2] = mb_strlen($m[1]);
                }

                $table = $this->splitCols($rootSeg, $tablePos);

                if (count($table) !== 4) {
                    $this->logger->debug('other format attach (table segment) - if on two pages, segment - it\'s better to skip it email');

                    return false;
                }

                $this->parseSegment($s, $table);

                foreach ($seatsByPassenger as $seats) {
                    if (!empty($seats[$i]) && preg_match('/^\d+[A-Z]$/', $seats[$i])) {
                        $s->extra()->seat($seats[$i]);
                    }
                }
            }
        }

        return true;
    }

    private function parseEmail(Email $email): bool
    {
        $xpath = "//text()[normalize-space()='Departing']/ancestor::tr[1][contains(.,'Duration')]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->debug('not found any flights in body');

            return false;
        }
        $this->logger->debug('[XPATH-flights]: ' . $xpath);

        $rootPrice = $this->http->XPath->query("//text()[{$this->eq($this->t('Cost breakdown'))}]/ancestor::tr[1]/following::table[1][{$this->contains($this->t('Total'))}]");

        if ($rootPrice->length !== 1) {
            $this->logger->debug('other format (price) body');

            return false;
        } else {
            $rootPrice = $rootPrice->item(0);
            $tot = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Airfare:'))}]/following::text()[normalize-space()!=''][1]",
                $rootPrice);
            $tot = $this->getTotalCurrency($tot);
            $email->price()
                ->cost($tot['Total'])
                ->currency($tot['Currency']);

            $tot = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Tax:'))}]/following::text()[normalize-space()!=''][1]",
                $rootPrice);
            $tot = $this->getTotalCurrency($tot);
            $email->price()
                ->tax($tot['Total'])
                ->currency($tot['Currency']);

            $tot = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Total:'))}]/following::text()[normalize-space()!=''][1]",
                $rootPrice);
            $tot = $this->getTotalCurrency($tot);
            $email->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);

            $fees = (array) $this->t('fees');

            foreach ($fees as $fee) {
                $tot = $this->http->FindSingleNode("./descendant::text()[{$this->eq($fee)}]/following::text()[normalize-space()!=''][1]",
                    $rootPrice);
                $tot = $this->getTotalCurrency($tot);

                if ($tot['Total'] !== null) {
                    $email->price()
                        ->fee(trim($fee, " :"), $tot['Total']);
                }
            }
        }

        $node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Contact Center'))}]");

        if (preg_match_all("/([\+ \d\(\)\-]{7,})/", $node, $m)) {
            foreach ($m[1] as $value) {
                if (!empty(trim($value))) {
                    $email->ota()->phone(trim($value));
                }
            }
        }

        $dateRes = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booked on'))}]",
            null, false, "/{$this->opt($this->t('Booked on'))}\s*(.+)/"));
        $confNo = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation number'))}]/following::text()[normalize-space()!=''][1]");
        $email->ota()->confirmation($confNo);

        foreach ($nodes as $rootFlight) {
            $rootSeats = $this->http->XPath->query("./following::table[2][contains(.,'Seat')]", $rootFlight);

            if ($rootSeats->length !== 1) {
                $this->logger->debug('other format (seats)');
                //clear all, that parsed before
                foreach ($email->getItineraries() as $it) {
                    $email->removeItinerary($it);
                }

                return false;
            }
            $seatNodes = $this->http->FindNodes(".//td[3]", $rootSeats->item(0));
            $seatsArr = [];

            foreach ($seatNodes as $value) {
                $seatsPax = array_map("trim", explode(',', $value));

                foreach ($seatsPax as $i => $sp) {
                    $seatsArr[$i][] = $sp;
                }
            }
            $r = $email->add()->flight();
            $r->general()
                ->confirmation($confNo)
                ->travellers($this->http->FindNodes("./following::table[1][{$this->starts($this->t('Passengers'))}]//td[1]/descendant::text()[normalize-space()!=''][1]",
                    $rootFlight))
                ->date($dateRes);

            $xpathSeg = "./following-sibling::tr";
            $nodeSeg = $this->http->XPath->query($xpathSeg, $rootFlight);
            $this->logger->debug('[XPATH-seg]: ' . $xpathSeg);

            foreach ($nodeSeg as $i => $root) {
                $s = $r->addSegment();

                if (isset($seatsArr[$i])) {
                    $seats = array_filter($seatsArr[$i], function ($s) {
                        return preg_match("/^[-A-Z\d\/\\\]{1,7}$/", $s) > 0;
                    });

                    if (!empty($seats)) {
                        $s->extra()->seats($seats);
                    }
                }

                $table = [];

                for ($k = 1; $k <= 4; $k++) {
                    $table[$k - 1] = $this->http->FindSingleNode("./td[{$k}]", $root);
                }
                $this->parseSegment($s, $table);
            }//foreach $root - seg
        }//foreach $rootFlights

        return true;
    }

    private function parseSegment(FlightSegment $s, array $table): void
    {
        $table = array_map(function ($s) {
            return trim(preg_replace("/\s+/", ' ', $s));
        }, $table);

        if (preg_match("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])[ \-]+(\d+)$/", trim($table[0]), $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
        }

        /*
            Delhi International Airport, T3, DEL
            Terminal 3
            07:00 26/ August /2020
        */
        $pattern1 = "/^"
            . "(?<name>.+?),(?:(?<terminal>[^,]+),)?\s*(?<code>[A-Z]{3})"
            . "(?<terminal2>\s+Terminal[- ]+[A-z\d]+)?"
            . "\s+(?<timeDate>\d+.{6,})"
            . "/";

        /*
            Mumbai International Airport, T2, SAHAR
            13:10 03/ September /2018
        */
        $pattern2 = "/^"
            . "(?<name>.+?),(?:(?<terminal>[^,]+),)?\s*(?:.*?)"
            . "(?<terminal2>\s+Terminal[- ]+[A-z\d]+)?"
            . "\s+(?<timeDate>\d+.{6,})"
            . "/";

        /*
            Makedonia Airport ( SKG)
            Sat - 16 Oct 2021 16:00
        */
        $pattern3 = "/^"
            . "(?<name>.+?)[ ]*\([ ]*(?<code>[A-Z]{3})[ ]*\)"
            . "\s+[-[:alpha:]]+[ ]+-[ ]+(?<timeDate>\d.{6,})"
            . "/u"
        ;

        if (preg_match($pattern1, $table[1], $m)) {
            $s->departure()
                ->name($m['name'])
                ->code($m['code'])
                ->date($this->normalizeDate($m['timeDate']));

            if (!empty($m['terminal'])) {
                $s->departure()->terminal($this->normalizeTerminal($m['terminal']));
            } elseif (!empty($m['terminal2'])) {
                $s->departure()->terminal($this->normalizeTerminal($m['terminal2']));
            }
        } elseif (preg_match($pattern2, $table[1], $m)) {
            $s->departure()
                ->name($m['name'])
                ->noCode()
                ->date($this->normalizeDate($m['timeDate']));

            if (!empty($m['terminal'])) {
                $s->departure()->terminal($this->normalizeTerminal($m['terminal']));
            } elseif (!empty($m['terminal2'])) {
                $s->departure()->terminal($this->normalizeTerminal($m['terminal2']));
            }
        } elseif (preg_match($pattern3, $table[1], $m)) {
            $s->departure()
                ->name($m['name'])
                ->code($m['code'])
                ->date($this->normalizeDate($m['timeDate']))
            ;
        }

        if (preg_match($pattern1, $table[2], $m)) {
            $s->arrival()
                ->name($m['name'])
                ->code($m['code'])
                ->date($this->normalizeDate($m['timeDate']));

            if (!empty($m['terminal'])) {
                $s->arrival()->terminal($this->normalizeTerminal($m['terminal']));
            } elseif (!empty($m['terminal2'])) {
                $s->arrival()->terminal($this->normalizeTerminal($m['terminal2']));
            }
        } elseif (preg_match($pattern2, $table[2], $m)) {
            $s->arrival()
                ->name($m['name'])
                ->noCode()
                ->date($this->normalizeDate($m['timeDate']));

            if (!empty($m['terminal'])) {
                $s->arrival()->terminal($this->normalizeTerminal($m['terminal']));
            } elseif (!empty($m['terminal2'])) {
                $s->arrival()->terminal($this->normalizeTerminal($m['terminal2']));
            }
        } elseif (preg_match($pattern3, $table[2], $m)) {
            $s->arrival()
                ->name($m['name'])
                ->code($m['code'])
                ->date($this->normalizeDate($m['timeDate']));
        }
        $s->extra()->duration($table[3]);
    }

    private function normalizeTerminal(string $str): string
    {
        if (preg_match("/^\s*T(\w)\s*$/", $str, $m)) {
            return $m[1];
        }

        if (preg_match("/^\s*Terminal\s+(\w+)\s*$/i", $str, $m)) {
            return $m[1];
        }

        return trim($str);
    }

    private function normalizeDate(?string $date)
    {
        $in = [
            //18:00 07/ August /2018
            '#^(\d+:\d+)\s+(\d+)[\s\/]+(\D+?)[\s\/]+(\d{4})$#u',
            //27/ August /2018
            '#^(\d+)[\s\/]+(\D+?)[\s\/]+(\d{4})$#u',
            // Tue - 01 Jun 2021
            '/^[-[:alpha:]]+[ ]+-[ ]+(\d{1,2})[ ]+([[:alpha:]]+)[ ]+(\d{4})$/u',
        ];
        $out = [
            '$2 $3 $4, $1',
            '$1 $2 $3',
            '$1 $2 $3',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, trim($date))));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body = null): bool
    {
        if (isset($this->reBody)) {
            if (isset($body)) {
                foreach ($this->reBody as $lang => $reBody) {
                    if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                        $this->lang = substr($lang, 0, 2);

                        return true;
                    }
                }
            } else {
                foreach ($this->reBody as $lang => $reBody) {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                        && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                    ) {
                        $this->lang = substr($lang, 0, 2);

                        return true;
                    }
                }
            }
        }

        return false;
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

    private function getTotalCurrency($node): array
    {
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];

            if (in_array($cur, ['BHD'])) {
                $tot = PriceHelper::cost($m['t'], '.', ',');
            } else {
                $tot = PriceHelper::cost($m['t']);
            }
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
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

    private function rowColsPos($row)
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
}
