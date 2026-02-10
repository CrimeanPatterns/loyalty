<?php

namespace AwardWallet\Engine\china\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryReceipt extends \TAccountChecker
{
    public $mailFiles = "china/it-58162572.eml, china/it-62628353.eml, china/it-69821915.eml, china/it-69986950.eml, china/it-650459386.eml";

    public $detectFrom = '@service.china-airlines.com';

    public $detectSubject = [
        // en
        'China Airlines Itinerary Receipt',
        'Important Information about your flight from China Airlines Group',
        // zh
        '中華航空 電子機票收據',
    ];
    public $detectBody = [
        'en' => [
            'Your e-Ticket receipt and itinerary',
            'Flight Notification',
        ],
        'zh' => [
            '您的電子機票收據已使用',
        ],
    ];
    public $detectPdfBody = [
        'en' => [
            'Electronic Ticket Receipt',
        ],
        'zh' => [
            '電子機票收據',
        ],
    ];

    public $lang = "en";
    public static $dictionary = [
        "en" => [
            // Html
            //            'Booking reference' => '',
            'Passenger details' => ['Passenger details', 'Passengers'],
            //            'Itinerary details' => '',
            //            'Departs' => '',
            //            'Arrives' => '',
            //            'Your Previous Itinerary' => '',

            // Common
            //            'MEMBERSHIP' => '',
            //            'Terminal' => '',
            //            'Non-Stop' => '',

            // Html + Pdf
            //            'Electronic Ticket Receipt' => '',
            //            'BOOKING REFERENCE:' => '',
            //            'TICKET NUMBER:' => '',
            //            'PASSENGER:' => '',
            //            'FLIGHT' => '',
            //            'Terminal' => '',
            //            'FROM' => '',
            //            'CLASS' => '',
            //            'SEAT' => '',
            //            'AIRCRAFT TYPE' => '',
            //            'Non-Stop' => '',
            //            'OPERATED BY' => '',
            //            'Fare:' => '',
            //            'Total Tax' => '',
            //            'Total amount:' => '',
        ],
        "zh" => [
            // Html
            'Booking reference' => '訂位代號',
            'Passenger details' => '旅客資訊',
            'Itinerary details' => '行程資訊',
            'Departs'           => '出發',
            'Arrives'           => '抵達',

            // Html + Pdf
            'MEMBERSHIP' => '會員卡號:',
            'Terminal'   => '航廈',
            'Non-Stop'   => '直飛',

            // Pdf
            'Electronic Ticket Receipt' => '電子機票收據',
            'BOOKING REFERENCE:'        => '訂位代號',
            'TICKET NUMBER:'            => '電子機票號碼:',
            'PASSENGER:'                => '旅客姓名:',
            'FLIGHT'                    => '航班',
            'FROM'                      => '出發地',
            'CLASS'                     => '訂位艙等',
            'SEAT'                      => '座位',
            'AIRCRAFT TYPE'             => '機型',
            'OPERATED BY'               => '承運方',
            'Fare:'                     => '票面價:',
            'Total Tax'                 => ['稅金及航空公司收', '金 及 航 空 公 司 收', '金及航空公司收 費總額額:'],
            'Total amount:'             => '總額:',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $parsePdf = false;
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, '.china-airlines.com') === false) {
                continue;
            }

            foreach ($this->detectPdfBody as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($text, $dBody) === false) {
                        $parsePdf = $this->parsePdf($email, $text);
                    }
                }
            }
        }

        if (!$parsePdf) {
            $email->clearItineraries();

            foreach ($this->detectBody as $lang => $detectBody) {
                if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                    $this->lang = $lang;

                    break;
                }
            }

            $this->parseHtml($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['subject'])) {
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
        if ($this->http->XPath->query("//a[contains(@href, '.china-airlines.com')] | //img[contains(@src, '.china-airlines.com')]")->length < 3) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return true;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2; // html + pdf
    }

    private function parseHtml(Email $email): void
    {
        $xpathAirportCode = 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"';

        $f = $email->add()->flight();

        // General
        $confirmation = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking reference")) . "]/following::text()[normalize-space()][1]", null, true, '/^\s*([A-Z\d]{6})\s*$/');
        $confDesc = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking reference")) . "]", null, true, '/^(.+?)[\s:：]*$/u');

        $f->general()
            ->confirmation($confirmation, $confDesc)
            ->travellers($this->http->FindNodes("//text()[" . $this->starts($this->t("Passenger details")) . "]/ancestor::table[1]/descendant::tr/td[2][not(" . $this->contains($this->t("Passenger details")) . ")]", null, '/^\s*([[:alpha:] \-\/]+)(\s+\(|$)/'), true);

        // Accounts
        $accountNumbers = $this->http->FindNodes("//text()[" . $this->starts($this->t("Passenger details")) . "]/ancestor::table[1]/descendant::tr/td[2][not(" . $this->contains($this->t("Passenger details")) . ")]", null, '/' . $this->opt($this->t("MEMBERSHIP")) . '[:]*\s+\D+(\d{6,})/i');

        if (count(array_filter($accountNumbers)) > 0) {
            $f->setAccountNumbers($accountNumbers, false);
        }

        $xpath = "//text()[{$this->eq($this->t("Departs"))}]/ancestor::tr[{$this->contains($this->t("Arrives"))}][1][not(preceding::text()[{$this->eq($this->t("Your Previous Itinerary"))}])]";
        $nodes = $this->http->XPath->query($xpath);
//        $this->logger->debug('XPATH-'.$xpath);
        foreach ($nodes as $root) {
            if ($this->http->XPath->query("descendant::text()[normalize-space()][not(ancestor::*[contains(translate(@style,' ',''), 'text-decoration:line-through')])]", $root)->length === 0) {
                // Previous Itinerary
                continue;
            }
            $s = $f->addSegment();

            $addInfoXpath = "preceding::text()[normalize-space()][1]/ancestor::*[descendant-or-self::*[count(*[{$xpathAirportCode}])=2] and not({$this->contains($this->t("Departs"))})][1]";
//            $this->logger->debug('XPATH addInfo - '.$addInfoXpath);

            // Airline
            $text = implode("\n", $this->http->FindNodes("../tr/*[2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/\n *(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}) *\n(?<ac>.+)\n(?<st>.+)/u", $text, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
                $s->extra()
                    ->aircraft($m['ac'])
                    ->status($m['st'])
                ;
            } elseif (preg_match("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,5}) *\n(?<cabin>.+)\((?<bc>[A-Z]{1,2})\)\s*$/u", $text, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bc'])
                ;
            }

            $routeRe = "\s+(?<date>[\s\S]+\n.*\d{1,2}:\d{2}.*)(?:\s+[-+]\s*\d\s*)?\n(?<name>[\s\S]+?)(?:.*" . $this->opt($this->t("Terminal")) . "|\s*$)";

            // Departure
            $text = implode("\n", $this->http->FindNodes("../tr/*[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^\s*" . $this->opt($this->t("Departs")) . $routeRe . "/u", $text, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m['date']))
                    ->name($m['name'])
                ;
            }

            if (preg_match($patternTerm = "/^[( ]*(.*{$this->opt($this->t("Terminal"))}.*?)[ )]*$/mu", $text, $m)) {
                $s->departure()
                    ->terminal(trim(preg_replace(["/\s*" . $this->opt($this->t("Terminal")) . "\s*/", '/\s+/'], ' ', $m[1])));
            }

            $s->departure()
                ->code($this->http->FindSingleNode($addInfoXpath . "/descendant-or-self::tr[1]/td[1]", $root, true, "/^\s*([A-Z]{3})\s*$/"));

            // Arrival
            $text = implode("\n", $this->http->FindNodes("../tr/*[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^\s*" . $this->opt($this->t("Arrives")) . $routeRe . "/u", $text, $m)) {
                $s->arrival()
                    ->date($this->normalizeDate($m['date']))
                    ->name($m['name'])
                ;
            }

            if (preg_match($patternTerm, $text, $m)) {
                $s->arrival()
                    ->terminal(trim(preg_replace(["/\s*" . $this->opt($this->t("Terminal")) . "\s*/", '/\s+/'], ' ', $m[1])));
            }

            $s->arrival()
                ->code($this->http->FindSingleNode($addInfoXpath . "/descendant-or-self::tr[1]/td[3]", $root, true, "/^\s*([A-Z]{3})\s*$/"));

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode($addInfoXpath . "/descendant-or-self::tr[1]/td[2]", $root, true, "/\b((\d{1,2} ?h)?\s+\d{1,2}m)\s*(?:\)|$)/"))
                ->cabin($s->getCabin() ?? $this->http->FindSingleNode($addInfoXpath . "/tr[2]/td[1]", $root, true, "/(.+)\(\s*[A-Z]{1,2}\s*\)/"), true, true)
                ->bookingCode($s->getBookingCode() ?? $this->http->FindSingleNode($addInfoXpath . "/tr[2]/td[1]", $root, true, "/.+\(\s*([A-Z]{1,2})\s*\)/"), true, true)
            ;

            if (preg_match("/^\s*(" . $this->opt($this->t("Non-Stop")) . ")\s*\(/",
                $this->http->FindSingleNode($addInfoXpath . "/tr[1]/td[2]", $root))) {
                $s->extra()->stops(0);
            }
        }
    }

    private function parsePdf(Email $email, $text): bool
    {
        $receipts = $this->split("/" . $this->opt($this->t("Electronic Ticket Receipt")) . "\n(\s*(?:[ ]{20,}.+\n+|\s*){0,2}\s*" . $this->opt($this->t("BOOKING REFERENCE:")) . ")/",
            $text);

        $f = $email->add()->flight();

        $confirmations = [];
        $tickets = [];
        $travellers = [];
        $accounts = [];
        $totals = [];
        $currency = [];
        $taxes = [];
        $fares = [];

        foreach ($receipts as $receiptText) {
            $confirmations[] = $this->re("/" . $this->opt($this->t("BOOKING REFERENCE:")) . ".*\n+(?: {10,}.*\n){0,2}[ ]{0,5}([A-Z\d]{5,7})\s+/u", $receiptText);

            $travellers[] = trim($this->re("/" . $this->opt($this->t("PASSENGER:"), true) . ".*\n+(?: {10,}.*\n){0,5}[ ]{0,5}([[:alpha:] \-]+\/[[:alpha:] \-]+)\(.*\)/u", $receiptText));
            $tickets[] = $this->re("/" . $this->opt($this->t("TICKET NUMBER:"), true) . "(?:.*\n){0,3}?.{20,}[ ]{3,}(\d{13})\s+/u", $receiptText);
            $accounts[] = $this->re("/" . $this->opt($this->t("MEMBERSHIP"), true) . "[^\d\n]*(\d{6,})\s+/u", $receiptText);

            $totals[] = $this->amount($this->re("/" . $this->opt($this->t("Total amount:"), true) . "[ ]+(\d[\d,.]*)[ ]*[A-Z]{3}\s+/u", $receiptText));
            $currency[] = $this->re("/" . $this->opt($this->t("Total amount:"), true) . "[ ]+\d[\d,.]*[ ]*([A-Z]{3})\s+/u", $receiptText);
            $taxes[] = $this->amount($this->re("/" . $this->opt($this->t("Total Tax")) . "[ ]+(\d[\d,.]*)[ ]*[A-Z]{3}\s+/u", $receiptText));
            $fares[] = $this->amount($this->re("/" . $this->opt($this->t("Fare:"), true) . "[ ]+(\d[\d,.]*)[ ]*[A-Z]{3}\s+/u", $receiptText));

            // Segments
            $segments = $this->split("/\n([ ]{0,10}" . $this->opt($this->t("FLIGHT")) . "[ ]{3,}" . $this->opt($this->t("FROM")) . "[ ]{3,}.+)/", $receiptText);

            foreach ($segments as $stext) {
                $s = $f->addSegment();
                $stext = $this->re("/([\s\S]+?\n.+ {3,}" . $this->opt($this->t('OPERATED BY')) . "(?:.*\n){0,2})/", $stext);

                $tabletext = $this->re("/([\s\S]+)\n.+ {3,}" . $this->opt($this->t('OPERATED BY')) . "/", $stext);

                $tableHeaderPos = $this->TableHeadPos($this->inOneRow($tabletext));

                if (count($tableHeaderPos) !== 6) {
                    $this->logger->debug($this->logger->info("incorrect parse table"));

                    return false;
                }

                // Row 1
                $table1 = $this->SplitCols($this->re("/^((.*\n)+?)\s*" . $this->opt($this->t('CLASS')) . " +" . $this->opt($this->t('SEAT')) . "/u",
                    $tabletext), $tableHeaderPos);

                // Column 1
                if (preg_match("/^\s*.+\n+(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,5})(?:\n|$)/u", $table1[0],
                    $m)) {
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn']);
                }
                // Column 2
                if (preg_match("/^\s*.+\n+([\s\S]+?)(?:\n.*" . $this->opt($this->t('Terminal')) . "|$)/u", $table1[1],
                    $m)) {
                    if (in_array($this->lang, ['zh'])) {
                        //     柏林
                        //   柏林-泰格爾              ->             柏林-泰格
                        //     泰格爾
                        $m[1] = preg_replace('/(\w+)\n\s*\1\b/u', '$1', $m[1]);
                    }
                    $s->departure()
                        ->name($m[1])
                        ->noCode();
                }

                if (preg_match("/\n(.*" . $this->opt($this->t('Terminal')) . ".*)(?:\n|$)/ui", $table1[1], $m)) {
                    $s->departure()
                        ->terminal(trim(preg_replace("/\s*" . $this->opt($this->t('Terminal')) . "\s*/ui", ' ',
                            $m[1])));
                }
                // Column 3
                if (preg_match("/^\s*.+\n+([\s\S]+?)(?:\n.*" . $this->opt($this->t('Terminal')) . "|$)/u", $table1[2],
                    $m)) {
                    if (in_array($this->lang, ['zh'])) {
                        //     柏林
                        //   柏林-泰格爾              ->             柏林-泰格
                        //     泰格爾
                        $m[1] = preg_replace('/(\w+)\n\s*\1\b/u', '$1', $m[1]);
                    }
                    $s->arrival()
                        ->name($m[1])
                        ->noCode();
                }

                if (preg_match("/\n(.*" . $this->opt($this->t('Terminal')) . ".*)(?:\n|$)/ui", $table1[2], $m)) {
                    $s->arrival()
                        ->terminal(trim(preg_replace("/\s*" . $this->opt($this->t('Terminal')) . "\s*/ui", ' ',
                            $m[1])));
                }
                // Column 4
                if (preg_match("/^.+\n+(.+\s+\d{1,2}:\d{2}.*?)(?:[-+] *\d+)?(?:\n|$)/u", $table1[3], $m)) {
                    $s->departure()
                        ->date($this->normalizeDate($m[1]));
                }

                // Column 5
                if (preg_match("/^.+\n+(.+\s+\d{1,2}:\d{2}.*?)(?:[-+] *\d+)?(?:\n|$)/u", $table1[4], $m)) {
                    $s->arrival()
                        ->date($this->normalizeDate($m[1]));
                }

                // Row 2
                $table2 = $this->SplitCols($this->re("/\n(\s*" . $this->opt($this->t('CLASS')) . " +" . $this->opt($this->t('SEAT')) . "[\s\S]+)/u",
                    $tabletext), $tableHeaderPos);

                // Column 1
                if (preg_match("/" . $this->opt($this->t('CLASS')) . "\s+(.+)\( ?([A-Z]{1,2}) ?\)/u", $table2[0], $m)) {
                    $s->extra()
                        ->cabin($m[1])
                        ->bookingCode($m[2]);
                }

                // Column 2
                if (preg_match("/\n" . $this->opt($this->t('SEAT')) . "\n+(\d{1,5}[A-Z])\n/u", $table2[1], $m)) {
                    // no examples
                    $s->extra()
                        ->seat($m[1]);
                }
                // Column 3
                if (preg_match("/\n" . $this->opt($this->t('AIRCRAFT TYPE')) . "\n+(.+)\n/u", $table2[2], $m)) {
                    $s->extra()
                        ->aircraft($m[1]);
                }

                if (preg_match("/\n" . $this->opt($this->t('Non-Stop')) . "\n/ui", $table2[2], $m)) {
                    $s->extra()
                        ->stops(0);
                }
            }
        }

        // General
        $confirmations = array_unique($confirmations);

        foreach ($confirmations as $conf) {
            $f->general()->confirmation($conf);
        }

        $f->general()->travellers(array_unique($travellers));

        // Issued
        $f->issued()->tickets(array_unique($tickets), false);

        // Price
        if (count(array_unique($currency)) == 1) {
            $f->price()
                ->total((!in_array(null, $totals)) ? array_sum($totals) : null)
                ->currency($currency[0])
                ->cost((!in_array(null, $fares)) ? array_sum($fares) : null)
                ->tax((!in_array(null, $taxes)) ? array_sum($taxes) : null);
        }

        return true;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $zhCorrect = false): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }
        $field = array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field);

        if ($zhCorrect == true && in_array($this->lang, ['zh'])) {
//            убирает дублирование и перенос на новую строку последнего буквенного символа в слове
//            訂位代號                     電子機票號碼
//                                           碼:
//            P2VART                   2972413771046
            $field = preg_replace('/(\w)([^\w\s]{1,2})$/u', '(?:$1|$1\n *$1)$2', $field);
        }

        return '(?:' . implode('|', $field) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('IN ' . $str);
        $in = [
            //2020/06/19  23:20
            "/^\s*(\d{4})\/(\d{2})\/(\d{1,2})\s+(\d{1,2}:\d{2})\s*(?:\s+[-+]\s*\d{1})?\s*$/u",
            // 04/Dec/2020  12:05
            "/^\s*(\d{2})\/([[:alpha:]]{1,5})\/(\d{4})\s+(\d{1,2}:\d{2})\s*(?:\s+[-+]\s*\d{1})?\s*$/u",
        ];
        $out = [
            "$3.$2.$1, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
//        $this->logger->debug('OUT ' . $str);
        return strtotime($str);
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

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function TableHeadPos($row)
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

    private function SplitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
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

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
