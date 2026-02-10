<?php

namespace AwardWallet\Engine\airpremia\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingCompleted extends \TAccountChecker
{
    public $mailFiles = "airpremia/it-500202845.eml, airpremia/it-512515663.eml, airpremia/it-515675385.eml, airpremia/it-522068424.eml";

    private $detectFrom = "noreply@airpremia.com";
    private $detectSubject = [
        // en
       '[AIRPREMIA] Your booking has been completed.'
    ];

    public $lang;
    public static $dictionary = [
        'ko' => [
            'Passenger ltinerary' => '여정 안내서',
            'Ticket/Fare Paymention Information' => '운임 결제 정보',
            'Booking Reference' => '예약번호',
            'Status' => '예약상태',
            'Passenger' => '탑승객',
            'Date/Local Time' => '날짜/시간',
            'Flight Time ' => 'Flight Time',
            'Terminal ' => ['Terminal', '여객터미널'],
            'Class' => '예약등급',
            'Aircraft Type/Flight' => '기종 / 편명',
            'Baggage / Seat No' => ['위탁수하물 / 좌석번호', '좌석번호 / 위탁수하물'],
            'Fare' => '항공운임',
            'Discount' => '할인내역',
            'Total Amount' => '총 금액',
        ],
        'en' => [
            'Passenger ltinerary' => 'Passenger ltinerary',
            'Ticket/Fare Paymention Information' => 'Ticket/Fare Paymention Information',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]airpremia\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && $this->containsText($headers["subject"], ['[AIRPREMIA]', '[에어프레미아]']) === false
        ) {
            return false;
        }
        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['.airpremia.com'], '@href')}]")->length === 0
            || $this->http->XPath->query("//*[{$this->contains(['Air Premia Inc.'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Passenger ltinerary']) && !empty($dict['Ticket/Fare Paymention Information'])
                && $this->http->XPath->query("//*[{$this->contains($dict['Passenger ltinerary'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['Ticket/Fare Paymention Information'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Booking Reference"], $dict["Date/Local Time"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Booking Reference'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dict['Date/Local Time'])}]")->length > 0
                ) {
                    $this->lang = $lang;
                    return true;
                }
            }
        }
        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");
            return $email;
        }
        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//*[self::th or self::td][{$this->eq($this->t('Booking Reference'))}]/following-sibling::td[1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
            ->travellers(preg_split('/\s*,\s*/', $this->http->FindSingleNode("//*[self::th or self::td][{$this->eq($this->t('Passenger'))}]/following-sibling::td[1]",
                null, true, "/^\s*([A-Z\W]+)\s*$/")))
            ->status($this->http->FindSingleNode("//*[self::th or self::td][{$this->eq($this->t('Status'))}]/following-sibling::td[1]"))
        ;

        $xpath = "//text()[{$this->eq($this->t('Date/Local Time'))}]/ancestor::*[.//text()[{$this->eq($this->t('Aircraft Type/Flight'))}]][1]";
        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode(".//*[self::th or self::td][{$this->eq($this->t('Aircraft Type/Flight'))}]/following-sibling::*[self::th or self::td][1]",
                $root, true, "/\\/\s*(.+)/");
            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][1][count(*[normalize-space()]) = 2]/*[normalize-space()][1]",
                    $root, true, "/^\s*([A-Z]{3})\s*$/"))
                ->name($this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][2][count(*[normalize-space()]) = 2]/*[normalize-space()][1]", $root))
            ;
            if (preg_match("/^.+\((?:.*[, ]+)?(\w+ ?{$this->opt($this->t('Terminal'))}|{$this->opt($this->t('Terminal'))} ?\w+)\)\s*$/iu", $s->getDepName(), $m)) {
                $s->departure()
                    ->terminal(preg_replace("/\s*{$this->opt($this->t('Terminal'))}\s*/", '', $m[1]));
            }
            $dateStr = $this->http->FindSingleNode("descendant::*[self::th or self::td][{$this->eq($this->t('Date/Local Time'))}]/following-sibling::td[normalize-space()][1]", $root);
            $this->logger->debug('$dateStr = '.print_r( $dateStr,true));
            if (preg_match("/^\s*(?<date>.+?)\s*\b(?<dTime>\d{1,2}:\d{2}.*?)-(?<aTime>\d{1,2}:\d{2}.*?)\s*(?<overnignt>[-+]\d)?\s*$/", $dateStr, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['date'] . ', '. $m['dTime']));
                $date = $this->normalizeDate($m['date'] . ', '. $m['aTime']);
                if (!empty($m['overnignt']) && !empty($date)) {
                    $date = strtotime($m['overnignt'] . ' days', $date);
                }
                $s->arrival()
                    ->date($date);
            }
            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][1][count(*[normalize-space()]) = 2]/*[normalize-space()][2]",
                    $root, true, "/^\s*([A-Z]{3})\s*$/"))
                ->name($this->http->FindSingleNode("descendant::tr[not(.//tr)][normalize-space()][2][count(*[normalize-space()]) = 2]/*[normalize-space()][2]", $root))
            ;
            if (preg_match("/^.+\((?:.*[, ]+)?(\w+ ?{$this->opt($this->t('Terminal'))}|{$this->opt($this->t('Terminal'))} ?\w+)\)\s*$/iu", $s->getArrName(), $m)) {
                $s->arrival()
                    ->terminal(preg_replace("/\s*{$this->opt($this->t('Terminal'))}\s*/", '', $m[1]));
            }

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode(".//*[self::th or self::td][{$this->eq($this->t('Class'))}]/following-sibling::*[self::th or self::td][1]",
                    $root, true, "/^\s*(.+?)\s*\(/"))
                ->bookingCode($this->http->FindSingleNode(".//*[self::th or self::td][{$this->eq($this->t('Class'))}]/following-sibling::*[self::th or self::td][1]",
                    $root, true, "/^\s*.+?\s*\(([A-Z]{1,2})\)\s*$/"))
                ->aircraft($this->http->FindSingleNode(".//*[self::th or self::td][{$this->eq($this->t('Aircraft Type/Flight'))}]/following-sibling::*[self::th or self::td][1]",
                    $root, true, "/^\s*(.+?)\s*\\/.*$/"))
                ->duration($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Flight Time'))}]/ancestor::*[self::th or self::td][1]",
                    $root, true, "/^\s*{$this->opt($this->t('Flight Time'))}\s*(.+?)\s*$/"))
            ;
            $seats = implode("\n", $this->http->FindNodes(".//*[self::th or self::td][{$this->starts($this->t('Baggage / Seat No'))}]/following-sibling::*[self::th or self::td][1]/descendant::text()[normalize-space()]", $root));
                // $root, true, "/^\s*(\S.+?)\s*\\/\s*.*\s*$/");
            $this->logger->debug('$seats = '.print_r( $seats,true));
            $seatsRe = '\d{1,3}[A-Z](\s*,\s*\d{1,3}[A-Z])*';
            if (preg_match_all("/(^\s*{$seatsRe}\s*\\/|\\/\s*{$seatsRe}\s*$)/m", $seats, $m)) {

                $m[1] = preg_replace("/(^\s*\\/\s*|\s*\\/\s*$)/m", '', $m[1]);
                $this->logger->debug('$m[1] = '.print_r( $m[1],true));
                $s->extra()
                    ->seats(preg_split('/\s*,\s*/', implode(',', $m[1])));
            }

        }

        // Price
        $total = $this->http->FindSingleNode("//*[self::th or self::td][{$this->eq($this->t('Total Amount'))}]/following-sibling::td[1]");
        if (preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)
        ) {
            $currancy = $m['currency'];
            $f->price()
                ->total(PriceHelper::parse($m['amount'], $currancy))
                ->currency($currancy);

            $feeXpath = "//tr[not(.//tr)][preceding::text()[{$this->eq($this->t('Ticket/Fare Paymention Information'))}] and following::text()[{$this->eq($this->t('Discount'))}]][count(*[normalize-space()]) = 4]";
            $feeNodes = $this->http->XPath->query($feeXpath);
            $fees = [];
            foreach ($feeNodes as $root) {
                $name1 = $this->http->FindSingleNode("*[normalize-space()][1]", $root);
                $name2 = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1][count(*[normalize-space()]) = 2]/*[normalize-space()][1]",
                    $root);
                $value = $this->http->FindSingleNode("*[normalize-space()][2]", $root, true, "/^\D*(\d[\d., ]*?)\D*$/");
                $this->logger->debug('$name1 = ' . print_r($name1, true));
                $this->logger->debug('$name2 = ' . print_r($name2, true));
                $this->logger->debug('$value = ' . print_r($value, true));
                if ($value !== '0') {
                    $fees[] = ['name' => $name1 . ' (' . $name2 . ')', 'value' => $value];
                }


                $name1 = $this->http->FindSingleNode("*[normalize-space()][3]", $root);
                $name2 = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1][count(*[normalize-space()]) = 2]/*[normalize-space()][2]",
                    $root);
                $value = $this->http->FindSingleNode("*[normalize-space()][4]", $root, true, "/^\D*(\d[\d., ]*?)\D*$/");
                if ($value !== '0') {
                    $fees[] = ['name' => $name1 . ' (' . $name2 . ')', 'value' => $value];
                }
            }
            foreach ($fees as $fee) {
                if (preg_match("/^\s*{$this->opt($this->t('Fare'))}\s*\(/u", $fee['name'])) {
                    $f->price()
                        ->cost(PriceHelper::parse($fee['value'], $currancy));
                } else {
                    $f->price()
                        ->fee($fee['name'], PriceHelper::parse($fee['value'], $currancy));
                }
            }
        }

        return true;
    }


    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }
        return self::$dictionary[$this->lang][$s];
    }



    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }


    private function eq($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }


    private function starts($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        $this->logger->debug('date begin = ' . print_r( $date, true));
        $in = [
//            // 2023.12.11 (Mon), 00:01
            '/^\s*(\d{4})\.(\d{1,2})\.(\d{1,2})\s*\([[:alpha:]]+\)[,\s]+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/iu',
        ];
        $out = [
            '$1-$2-$3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));
//         if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
//             $monthNameOriginal = $m[0];
//             if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
//                 return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
//             }
//         }

        $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }


    private function opt($field, $delimiter = '/')
    {
        $field = (array)$field;
        if (empty($field)) {
            $field = ['false'];
        }
        return '(?:' . implode("|", array_map(function ($s) use($delimiter) {
                return str_replace(' ', '\s+', preg_quote($s, $delimiter));
            }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }
        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }
        return false;
    }


}