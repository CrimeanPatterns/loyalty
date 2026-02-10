<?php

namespace AwardWallet\Engine\flysafair\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class BookingReference extends \TAccountChecker
{
    public $mailFiles = "flysafair/it-47594925.eml, flysafair/it-47775501.eml, flysafair/it-51058132.eml, flysafair/it-70657258.eml";
    public $From = 'noreply@flysafair.co.za';
    public $Subject = ['FlySafair Confirmation', 'Thanks For Booking with FlySafair'];

    private static $dictionary = [
        'en' => [
            "first" => [
                "Thank you for choosing to fly with us and we look forward to having you onboard with us soon",
                "Thanks for choosing FlySafair!",
            ],
            "last"     => ["FlySafair is a brand operated and owned by Safair Operations", "@flysafair"],
            "Outbound" => ["Outbound", "Inbound"],
            // PDF
            "segmentsEnd" => ["Quantity:", "HAND LUGGAGE ONLY FOR THIS FLIGHT"],
        ],
    ];
    private $lang = 'en';

    public function parseHTML(Email $email)
    {
        $flight = $email->add()->flight();

        $confNo = $this->http->FindSingleNode("//div[starts-with(normalize-space(), 'BOOKING REFERENCE:')]",
            null, true, "/BOOKING REFERENCE:\s*([A-Z\d]{5,})\z/");

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("//text()[contains(normalize-space(.), \"booking\")]/ancestor::tr[1]/following-sibling::tr[1]",
                null, true, "/[A-Z\d]{5,}\z/");
        }

        if (!empty($confNo)) {
            $flight->general()->confirmation($confNo);
        }

        $travellers = array_unique($this->http->FindNodes("//table[starts-with(normalize-space(), 'Passengers:')]/descendant::tr/td[1][not(normalize-space()='Passengers:')]",
            null));

        if (!empty($travellers)) {
            $flight->general()->travellers($travellers);
        }
        $currency = $this->http->FindSingleNode("//td[starts-with(normalize-space(), 'Air Fare')]/following::td[1]",
            null, true, '/[A-Z]/');
        $cost = $this->http->FindSingleNode("//td[starts-with(normalize-space(), 'Air Fare')]/following::td[1]", null,
            true, '/[0-9]{1,2}.[0-9]{1,4}\.[0-9]{2}/');

        $total = $this->http->FindSingleNode("//td[starts-with(normalize-space(), 'Total Incl VAT')]/following::td[1]",
            null, true, '/[0-9]{1,2}.[0-9]{1,4}\.[0-9]{2}/');

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("//td[starts-with(normalize-space(), 'Amount:')]/following::td[4]",
                null, true, '/[A-Z]/');
            $total = $this->http->FindSingleNode("//td[starts-with(normalize-space(), 'Amount:')]/following::td[4]",
                null, true, '/[0-9]{1,2}.[0-9]{1,4}\.[0-9]{2}/');
        }

        if (!empty($currency)) {
            $flight->price()->currency($this->normalizeCurrency($currency));
        }

        if ($cost !== null) {
            $flight->price()->cost($this->normalizeAmount($cost));
        }

        if ($total !== null) {
            $flight->price()->total($this->normalizeAmount($total));
        }

        $fees = $this->http->XPath->query("//tr[contains(normalize-space(),'Air Fare') and not(.//tr)]/following-sibling::tr[not(contains(.,'Total'))]");

        foreach ($fees as $fee) {
            $name = $this->http->FindSingleNode('td[2]', $fee);
            $charge = $this->http->FindSingleNode('td[3]', $fee, true, '/(\d[,.\'\d ]*)$/');

            if ($charge !== null) {
                $flight->price()
                    ->fee($name, $charge);
            }
        }

        $xpathType1 = "//table[contains(normalize-space(), 'Depart')]";
        $nodesType1 = $this->http->XPath->query($xpathType1);

        $xpathType2 = "//img[contains(@src,'plane_tale')]/ancestor::table[1]";
        $nodesType2 = $this->http->XPath->query($xpathType2);

        if ($nodesType1->length > 0) {
            $this->parseSegmentType1($flight, $nodesType1);
        } elseif ($nodesType2->length > 0) {
            $this->parseSegmentType2($flight, $nodesType2);
        }

        return true;
    }

    public function parsePDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        if ($datePaid = strtotime(str_replace("/", ".", $this->re("/Date paid\:\s+(\d+\/\d+\/\d{4})/u", $text)))) {
            $f->general()
                ->date($datePaid);
        }

        $f->general()
            ->confirmation($this->re("/BOOKING REFERENCE\:\s*([A-Z\d]{6})/", $text), 'BOOKING REFERENCE');

        if (preg_match_all("/\s+\n(\s*Depart\s+Arrive\s.+?Status\s+.+?)\s+{$this->opt($this->t("segmentsEnd"))}.+(?:{$this->opt($this->t("segmentsEnd"))})/s", $text, $segmentMatches)
            || preg_match_all("/\s+\n(\s*Depart\s+Arrive\s.+?Status\s+.+?)\s+{$this->opt($this->t("segmentsEnd"))}/s", $text, $segmentMatches)
        ) {
            $segmentsText = implode("\n", $segmentMatches[1]);
            $segments = $this->split("/(\s*Depart.+\n)/", $segmentsText);
            $travellers = '';

            foreach ($segments as $segment) {
                $s = $f->addSegment();

                if (preg_match("/(?<date>\d+\/\d+\/\d{4})\s*(?<depTime>[\d:]+)\s*(?<arrTime>[\d:]+)\s*(?<depCode>[A-Z]{3})\s*(?<arrCode>[A-Z]{3})\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[-\s]*(?<number>\d+)\s*(?<status>\w+)/u", $segment, $m)) {
                    $s->airline()
                        ->name($m['name'])
                        ->number($m['number']);

                    $s->departure()
                        ->code($m['depCode'])
                        ->date(strtotime(str_replace("/", ".", $m['date']) . ', ' . $m['depTime']));

                    $s->arrival()
                        ->code($m['arrCode'])
                        ->date(strtotime(str_replace("/", ".", $m['date']) . ', ' . $m['arrTime']));

                    $s->extra()
                        ->status($m['status']);
                }

                $paxTable = $this->SplitCols($this->re("/Passengers:\s+Seat\n+(.+)/s", $segment));

                $seats = array_filter(preg_split('/[ ]*\n+[ ]*/', $paxTable[2]), function ($item) {
                    return preg_match('/^\d+[A-z]$/', $item) > 0;
                });

                if (count($seats)) {
                    $s->extra()->seats($seats);
                }

                $travellers = $travellers . "\n" . $paxTable[0];
            }
            $f->general()
                ->travellers(array_filter(array_unique(explode("\n", $travellers))), true);

            //Price
            if (preg_match("/Total\s*Incl\s*VAT\s*(?<currency>\S)\s*(?<total>\d[,.\'\d ]*)$/m", $text, $matches)) {
                $f->price()
                    ->currency($this->normalizeCurrency($matches['currency']))
                    ->total($this->normalizeAmount($matches['total']))
                ;

                if (preg_match('/Total EX VAT\s*(?:' . preg_quote($matches['currency'], '/') . ')?\s*(?<amount>\d[,.\'\d ]*)$/m', $text, $m)) {
                    $f->price()->cost($this->normalizeAmount($m['amount']));
                }
            }

            if (preg_match("/^(\s*VAT.+)Quantity/msu", $text, $m)
                || preg_match("/^(\s*VAT.+)Total Incl VAT/msu", $text, $m)) {
                $feeRows = explode("\n", $m[1]);

                foreach ($feeRows as $feeRow) {
                    if (preg_match("/\s*(?:\dx)?\s*(.+)\s*(\S)\s([\d\.\,]+)\s*/", $feeRow, $m)) {
                        $f->price()->fee($m[1], $this->normalizeAmount($m[3]));
                    }
                }
            }
        }

        return true;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $words) {
            if (isset($words["first"], $words["last"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['first'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['last'])}]")->length > 0
                ) {
                    $this->parseHTML($email);
                } else {
                    $pdfs = $parser->searchAttachmentByName(".*pdf");

                    if (isset($pdfs) && count($pdfs) > 0) {
                        foreach ($pdfs as $pdf) {
                            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
                            $this->parsePDF($email, $text);
                        }
                    }
                }
            }
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach (self::$dictionary as $words) {
            if (isset($words["first"], $words["last"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['first'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['last'])}]")->length > 0
                ) {
                    return true;
                }
            }
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'Thank you for choosing to fly with us and we look forward to having you onboard') !== false
                && strpos($text, 'Passengers:') !== false
                && strpos($text, 'BOOKING REFERENCE:') !== false
                // && strpos($text, 'FlySafair') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@]flysafair\.co\.za/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->Subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }
    }

    private function parseSegmentType1(Flight $flight, \DOMNodeList $nodes)
    {
        foreach ($nodes as $root) {
            $segment = $flight->addSegment();
            $detailNode = $this->http->XPath->query("./following::table[starts-with(normalize-space(), 'Passengers:')][1]",
                $root)->item(0);
            $seats = $this->http->FindNodes(".//tr/td[last()][not(normalize-space()='Seat')]", $detailNode,
                '/\d+[A-z]/');

            foreach ($seats as $seat) {
                if (!empty($seat)) {
                    $segment->extra()->seat($seat);
                }
            }
            $dDate = $this->http->FindSingleNode("./descendant::tr[3]/td[1]", $root);
            $dDate = str_replace('/', '-', $dDate);
            $aDate = $this->http->FindSingleNode("./descendant::tr[3]/td[1]", $root);
            $aDate = str_replace('/', '-', $aDate);
            $dTime = $this->http->FindSingleNode("./descendant::tr[3]/td[2]", $root);
            $aTime = $this->http->FindSingleNode("./descendant::tr[3]/td[3]", $root);
            $dd = strtotime($dDate . ' ' . $dTime);
            $ad = strtotime($aDate . ', ' . $aTime);
            $dFrom = $this->http->FindSingleNode("./descendant::tr[3]/td[4]", $root);
            $aTo = $this->http->FindSingleNode("./descendant::tr[3]/td[5]", $root);
            $segment->departure()
                ->date($dd)
                ->code($dFrom);
            $segment->arrival()
                ->date($ad)
                ->code($aTo);
            $segment->airline()
                ->number($this->http->FindSingleNode("./descendant::tr[3]/td[6]", $root, true, '/[0-9]{3}/'))
                ->name($this->http->FindSingleNode("./descendant::tr[3]/td[6]", $root, true, '/[A-Z]{2}/'));
            $flight->general()
                ->status($this->http->FindSingleNode("./descendant::tr[3]/td[7]", $root));
        }
    }

    private function parseSegmentType2(Flight $f, \DOMNodeList $nodes)
    {
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airline = $this->http->FindSingleNode("./descendant::td[2]", $root);

            if (!empty($airline)) {
                if (preg_match("/(.+)\s-\s[A-Z]{2}\s([\d]+)/", $airline, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }
            }

            $date = $this->http->FindSingleNode("./descendant::td[1]", $root, true,
                "/^" . $this->opt($this->t("Outbound")) . ".+?(\d{1,2}\s[A-z]+\s\d{4})$/");

            //Departure
            $depTime = $this->http->FindSingleNode("./descendant::tr[3]/td[1]/descendant::text()[3]", $root, true,
                "/^(\d{1,2}:\d{1,2})$/");

            if (!empty($date)) {
                if (!empty($depTime)) {
                    $s->departure()->date(strtotime($date . " " . $depTime));
                } else {
                    $s->departure()->day(strtotime($date));
                }
            }
            $s->departure()
                ->name($this->http->FindSingleNode("./descendant::tr[3]/td[1]/descendant::text()[1]", $root))
                ->code($this->http->FindSingleNode("./descendant::tr[3]/td[1]/descendant::text()[2]", $root, true,
                    "/^[A-Z]{3}$/"));

            // Arrival
            $arrTime = $this->http->FindSingleNode("./descendant::tr[3]/td[2]/descendant::text()[3]", $root, true,
                "/^(\d{1,2}:\d{1,2})$/");

            if (!empty($date)) {
                if (!empty($arrTime)) {
                    $s->arrival()->date(strtotime($date . " " . $arrTime));
                } else {
                    $s->arrival()->day(strtotime($date));
                }
            }
            $s->arrival()
                ->name($this->http->FindSingleNode("./descendant::tr[3]/td[2]/descendant::text()[1]", $root))
                ->code($this->http->FindSingleNode("./descendant::tr[3]/td[2]/descendant::text()[2]", $root, true,
                    "/^[A-Z]{3}$/"));
        }
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
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

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US Dollar'],
            'ZAR' => ['R'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }
}
