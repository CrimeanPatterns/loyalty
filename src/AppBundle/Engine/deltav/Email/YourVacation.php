<?php

namespace AwardWallet\Engine\deltav\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourVacation extends \TAccountChecker
{
    public $mailFiles = "deltav/it-303156381.eml, deltav/it-307611544.eml, deltav/it-307614048.eml, deltav/it-410673600.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $hotelName = [
        'Ramada by Wyndham Istanbul Grand Bazaar',
    ];

    public static $dictionary = [
        "en" => [
            'check-in after'            => ['check-in after', 'check-in time is', 'Check-in time is'],
            'Check-out time is between' => ['Check-out time is between', 'check-out time is', 'Check-out time is'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if ((strpos($text, 'Delta Vacations') !== false
                    && strpos($text, 'www.deltavacations.com/booking') !== false
                    && strpos($text, 'FLIGHT INFORMATION') !== false)

                    || (strpos($text, 'HOTEL INFORMATION') !== false
                        && strpos($text, '# of Nights') !== false
                        && strpos($text, 'You have purchased the Travel Protection Plan') !== false
                    )
                ) {
                    return true;
                }
            }
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for choosing Delta Vacations'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('You have purchased an electronic ticket (e-ticket)'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('www.deltavacations.com'))}]")->length > 0
                && ($this->http->XPath->query("//text()[{$this->contains($this->t('Carrier'))}]")->length > 0
                || $this->http->XPath->query("//text()[{$this->contains($this->t('Check In'))}]")->length > 0);
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $this->logger->debug(__METHOD__);
        $f = $email->add()->flight();

        $travellerInfo = $this->re("/{$this->opt($this->t('TRAVELER INFORMATION'))}\n(.+)\n*{$this->opt($this->t('FLIGHT INFORMATION'))}/su", $text);

        if (!empty($travellerInfo)) {
            $travellerTable = $this->splitCols($travellerInfo);
            $travellers = explode("\n", preg_replace("/(?:Miss\s|Mr\s|Ms\s|\s\(.+\))/", "", $this->re("/{$this->opt($this->t('Passenger Name'))}\s*\n(.+)/su", $travellerTable[1])));
            $f->general()
                ->travellers(array_filter($travellers));

            $confs = array_unique(explode("\n", $this->re("/{$this->opt($this->t('Confirmation #'))}\s*\n(.+)/su", $travellerTable[2])));

            foreach (array_filter($confs) as $conf) {
                $f->general()
                    ->confirmation($conf);
            }

            $tickets = array_filter(array_unique(explode("\n", $this->re("/{$this->opt($this->t('E-ticket #'))}\s*\n(.+)/su", $travellerTable[3]))));

            foreach ($tickets as $ticket) {
                if (preg_match("/^\s+\d+\s+(?<pax>.+)\s+[A-Z\d]{6}\s+$ticket/mu", $travellerInfo, $m)) {
                    $f->addTicketNumber($ticket, false, preg_replace("/(?:Miss\s|Mr\s|Ms\s|\s\(.+\))/", "", $m['pax']));
                } else {
                    $f->addTicketNumber($ticket, false);
                }
            }

            $accounts = array_filter(array_unique(explode("\n", $this->re("/{$this->opt($this->t('Frequent Flyer #'))}\s*\n(.+)/su", $travellerTable[4]))));

            foreach ($accounts as $account) {
                if (preg_match("/^\s+\d+\s+(?<pax>.+)\s+[A-Z\d]{6}\s+\d{10,}\s*$account(?:\s|\n|$)/mu", $travellerInfo, $m)) {
                    $f->addAccountNumber($account, false, preg_replace("/(?:Miss\s|Mr\s|Ms\s|\s\(.+\))/", "", $m['pax']));
                } else {
                    $f->addAccountNumber($account, false);
                }
            }
        }

        $flightsInfo = $this->re("/\n\s*{$this->opt($this->t('FLIGHT INFORMATION'))}\n(.+)\n\s*{$this->opt($this->t('You have purchased an electronic ticket (e-ticket)'))}/s", $text);

        if (!empty($flightsInfo)) {
            $flightRows = $this->splitter("/\n\s*(\d+[A-z]+\d+)/", $flightsInfo);

            if (count($flightRows) > 0) {
                foreach (array_filter($flightRows) as $flightRow) {
                    $flightTable = $this->splitCols($flightRow);

                    $date = $flightTable[0];

                    $s = $f->addSegment();

                    $s->airline()
                        ->name($this->re("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])/", $flightTable[2]))
                        ->number($this->re("/[#]\s*(\d{1,4})/", $flightTable[2]))
                        ->carrierName($flightTable[1]);

                    $depTime = $this->re("/(\d+\:\d+\s*A?P?M?)/", $flightTable[5]);

                    $s->departure()
                        ->name(preg_replace("/(?:\n|[ ]{2,})/", " ", $this->re("/(.+)\s+\([A-Z]{3}\)/s", $flightTable[4])))
                        ->code($this->re("/\(([A-Z]{3})\)/", $flightTable[4]))
                        ->date($this->normalizeDate($date . ', ' . $depTime));

                    $arrTime = $flightTable[7];

                    $s->arrival()
                        ->name(preg_replace("/(?:\n|[ ]{2,})/", " ", $this->re("/(.+)\s+\([A-Z]{3}\)/s", $flightTable[6])))
                        ->code($this->re("/\(([A-Z]{3})\)/", $flightTable[6]));

                    if (stripos($arrTime, '+1') !== false) {
                        $s->arrival()
                            ->date(strtotime('+1 day', $this->normalizeDate($date . ', ' . $arrTime)));
                    } else {
                        $s->arrival()
                            ->date($this->normalizeDate($date . ', ' . $arrTime));
                    }

                    $s->extra()
                        ->cabin($this->re("/^(.+)\n/", $flightTable[3]));

                    if (preg_match_all("/\d+\/(\d+\-[A-Z])/", $flightTable[9], $m)) {
                        $s->extra()
                            ->seats($m[1]);
                    }

                    if (stripos($flightTable[8], 'NS') !== false) {
                        $s->extra()
                            ->stops(0);
                    }
                }
            }
        }
    }

    public function ParseHotelPDF(Email $email, $text)
    {
        $this->logger->debug(__METHOD__);

        $hotels = $this->re("/\n\s*{$this->opt($this->t('HOTEL INFORMATION'))}\n(.+)\n\s*{$this->opt($this->t('Hotel rooms are usually available for'))}/s", $text);

        if (empty($hotels)) {
            $hotels = $this->re("/\n\s*{$this->opt($this->t('HOTEL INFORMATION'))}\n(.+)\n\s*{$this->opt($this->t('TRAVEL PROTECTION'))}/s", $text);
        }

        if (!empty($hotels)) {
            $hotelRows = array_filter($this->splitter("/^\s*(\d+[A-z]+\d+)/m", $hotels));

            foreach ($hotelRows as $hotelRow) {
                $h = $email->add()->hotel();

                foreach ($this->hotelName as $hotelName) {
                    if (stripos($hotelRow, $hotelName) !== false) {
                        $hotelRow = preg_replace("/({$hotelName})/", "$1  ", $hotelRow);
                    }
                }

                $hotelTable = $this->splitCols($hotelRow);

                if (preg_match("/^(?<hotelName>.+)\n(?<address>(?:.+\n){1,})\s*(?<phone>[\d\-]+)\n\s*{$this->opt($this->t('Confirmation #:'))}\s*(?<conf>[A-z\d]+)/", $hotelTable[3], $m)) {
                    $h->hotel()
                        ->name($m['hotelName'])
                        ->address(preg_replace("/(?:\n|\s+)/", " ", $m['address']))
                        ->phone($m['phone']);

                    $h->general()
                        ->confirmation($m['conf']);
                }

                $inTime = $this->re("/{$this->opt($this->t('check-in after'))}\s*([\d\:]+\s*A?P?M)/iu", $text);
                $outTime = $this->re("/{$this->opt($this->t('Check-out time is between'))}\s*([\d\:]+\s*(?:A?P?M|noon))/iu", $text);

                if (!empty($inTime) && !empty($outTime)) {
                    $h->booked()
                        ->checkIn($this->normalizeDate($this->re("/^\s*(\d+[A-z]+\d+)/", $hotelTable[0]) . ' ' . $inTime))
                        ->checkOut($this->normalizeDate($this->re("/^\s*(\d+[A-z]+\d+)/", $hotelTable[1]) . ' ' . $outTime));
                } else {
                    $h->booked()
                        ->checkIn($this->normalizeDate($hotelTable[0]))
                        ->checkOut($this->normalizeDate($hotelTable[1]));
                }
                $h->addRoom()->setType($this->re("/^(.+)\n+/", $hotelTable[4]));

                $travellersText = $this->re("/{$this->opt($this->t('Passengers:'))}\s*(.+)/", $text);

                if (preg_match_all("/\(\d+\)\s*(?:Ms|Miss)?\s*(\D+)(?:\s*\(age\s*\d+\))?\s*(?:\,|$)/", $travellersText, $m)) {
                    $h->general()
                        ->travellers($m[1], true);
                }
            }
        }
    }

    public function ParseFlightHTML(Email $email)
    {
        $f = $email->add()->flight();

        $confArray = array_unique($this->http->FindNodes("//text()[normalize-space()='Passenger Name']/ancestor::tr[1]/following-sibling::tr/td[3]", null, "/^([A-Z\d]{6})$/"));

        foreach ($confArray as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        $travellers = $this->http->FindNodes("//text()[normalize-space()='Passenger Name']/ancestor::tr[1]/following-sibling::tr/td[2]");
        $f->general()
            ->travellers(preg_replace("/^(?:Mrs\s*|Mr\s*)/", "", $travellers), true);

        $tickets = $this->http->FindNodes("//text()[normalize-space()='Passenger Name']/ancestor::tr[1]/following-sibling::tr/td[4]", null, "/^\d{12,}$/");

        if (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
        }

        $accounts = $this->http->FindNodes("//text()[normalize-space()='Passenger Name']/ancestor::tr[1]/following-sibling::tr/td[5]", null, "/^(DL\s*\d{10,})$/");

        if (!empty($accounts)) {
            $f->setAccountNumbers($accounts, false);
        }

        $xpath = "//tr[starts-with(normalize-space(), 'Date') and contains(normalize-space(), 'Carrier') and contains(normalize-space(), 'Flight #')]/following-sibling::tr[not(contains(normalize-space(), 'Check in') or contains(normalize-space(), '.com') or contains(normalize-space(), 'OPERATED BY'))]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
               ->name($this->http->FindSingleNode("./td[3]", $root, true, "/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*[#]/"))
               ->number($this->http->FindSingleNode("./td[3]", $root, true, "/[#]\s*(\d{1,4})/"))
               ->carrierName($this->http->FindSingleNode("./td[2]", $root));

            $seats = $this->http->FindNodes("./td[last()]/descendant::text()[normalize-space()]", $root, "/^\d+\/(\d+\-[A-Z])$/u");

            if (count($seats) > 0) {
                $s->extra()
                   ->seats($seats);
            }

            $cabin = $this->http->FindSingleNode("./td[4]", $root);

            if (!empty($cabin)) {
                $s->extra()
                   ->cabin($cabin);
            }

            $date = $this->http->FindSingleNode("./td[1]", $root);
            $depTime = $this->http->FindSingleNode("./td[6]", $root, true, "/^([\d\:]+\s*A?P?M?)$/");

            $s->departure()
               ->code($this->http->FindSingleNode("./td[5]", $root, true, "/\(([A-Z]{3})\)/"))
               ->date($this->normalizeDate($date . ', ' . $depTime));

            $arrTime = $this->http->FindSingleNode("./td[8]", $root, true, "/^([\d\:]+\s*A?P?M?[+]*\d?)$/");
            $s->arrival()
              ->code($this->http->FindSingleNode("./td[7]", $root, true, "/\(([A-Z]{3})\)/"));

            if (stripos($arrTime, '+1') !== false) {
                $s->arrival()
                    ->date(strtotime('+1 day', $this->normalizeDate($date . ', ' . $arrTime)));
            } else {
                $s->arrival()
                   ->date($this->normalizeDate($date . ', ' . $arrTime));
            }

            $stop = $this->http->FindSingleNode("./td[9]", $root);

            if (stripos($stop, 'NS') !== false) {
                $s->extra()
                   ->stops(0);
            }
        }
    }

    public function ParseHoteltHTML(Email $email)
    {
        $xpath = "//tr[starts-with(normalize-space(), 'Check In') and contains(normalize-space(), 'Check Out') and contains(normalize-space(), 'Hotel')]/following-sibling::tr/td[5]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $travellersText = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), 'Passengers:')][1]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Passengers:'))}\s*(.+)/u");

            if (preg_match_all("/\(\d+\)\s*(?:Mrs|Mr)?\s*(\D+)\s*(?:\,|$)/", $travellersText, $m)) {
                $h->general()
                    ->travellers($m[1], true);
            }

            $h->general()
                ->confirmation($this->http->FindSingleNode("./descendant::td[4]", $root, true, "/{$this->opt($this->t('Confirmation #:'))}\s*(\d{6,})/s"));

            $hotelInfo = implode("\n", $this->http->FindNodes("./td[4]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<hotelName>.+)\n(?<address>(?:.+\n){1,})(?<phone>[\d\-]+)\nConfirmation #:/", $hotelInfo, $m)) {
                $h->hotel()
                    ->name($m['hotelName'])
                    ->address(str_replace("\n", " ", $m['address']))
                    ->phone($m['phone']);
            }

            $inTime = $this->http->FindSingleNode("//text()[{$this->contains($this->t('check-in after'))}]", null, true, "/{$this->opt($this->t('check-in after'))}\s*([\d\:]+\s*a?p?m)/iu");

            $outTime = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-out time is between'))}]", null, true, "/{$this->opt($this->t('Check-out time is between'))}\s*([\d\:]+\s*a?p?m)/iu");

            if (!empty($inTime) && !empty($outTime)) {
                $h->booked()
                    ->checkIn($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root) . ' ' . $inTime))
                    ->checkOut($this->normalizeDate($this->http->FindSingleNode("./td[2]", $root) . ' ' . $outTime));
            } else {
                $h->booked()
                    ->checkIn($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root)))
                    ->checkOut($this->normalizeDate($this->http->FindSingleNode("./td[2]", $root)));
            }
            $h->addRoom()->setType($this->http->FindSingleNode("./td[5]", $root, true, "/^(.+)/"));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (strpos($text, 'FLIGHT INFORMATION') !== false) {
                    $this->ParseFlightPDF($email, $text);
                }

                if (strpos($text, 'HOTEL INFORMATION') !== false) {
                    $this->ParseHotelPDF($email, $text);
                }
            }
        } else {
            $otaConf = array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Booking #: ')]", null, "/{$this->opt($this->t('Booking #: '))}\s*(\d{8,})/"));

            foreach ($otaConf as $conf) {
                $email->ota()
                    ->confirmation($conf);
            }

            if ($this->http->XPath->query("//tr[starts-with(normalize-space(), 'Date') and contains(normalize-space(), 'Carrier') and contains(normalize-space(), 'Flight #')]")->length > 0) {
                $this->ParseFlightHTML($email);
            }

            if ($this->http->XPath->query("//tr[starts-with(normalize-space(), 'Check In') and contains(normalize-space(), 'Check Out') and contains(normalize-space(), 'Hotel')]")->length > 0) {
                $this->ParseHoteltHTML($email);
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)(\D+)(\d+)\s*\,?\s*([\d\:]+\s*A?P?M?)[+]*\d?\s*$#su", //29Mar23, 12:20 AM
        ];
        $out = [
            "$1 $2 20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
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
