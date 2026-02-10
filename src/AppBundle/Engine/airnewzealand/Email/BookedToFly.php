<?php

namespace AwardWallet\Engine\airnewzealand\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookedToFly extends \TAccountChecker
{
    public $mailFiles = "airnewzealand/it-400937819.eml, airnewzealand/it-79874359.eml, airnewzealand/it-88443535.eml";
    public $subjects = [
        "/You're all booked to fly\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+\s*on\s*\w+\s*\d+\s*\w+\s*\d{4},\s*\w+\s*[A-Z\d]+/",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'statusTexts'          => ['your flight,', 'your flights,'],
            'Something not right?' => ['Something not right?', 'See attached itinerary for details'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@airnz.co.nz') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $textPdfFull = '';
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (stripos($textPdf, 'airnewzealand.co.nz') === false && strpos($textPdf, 'Air New Zealand Limited') === false) {
                continue;
            }

            if (preg_match("/[ ]{2,}Depart[ ]{2,}Arrive[ ]{2,}Flight Details$/m", $textPdf)) {
                $textPdfFull .= $textPdf . "\n\n";
            } elseif (preg_match("/^[ ]{2,}Departs[ ]{2,}Arrives$/m", $textPdf)) {
                $textPdfFull .= $textPdf . "\n\n";
            }
        }

        if (stripos($textPdfFull, 'New Zealand') !== false
            && stripos($textPdfFull, 'Check in & Bag Drop') !== false
            && stripos($textPdfFull, 'Flight Number') !== false) {
            return true;
        } else {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Air New Zealand')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your flight'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Something not right?'))}]")->length > 0;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airnz\.co\.nz$/', $from) > 0;
    }

    public function parserHtml(Email $email, array $flightsInfo, string $textPdf): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();
        $f->general()->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking reference')]/following::text()[normalize-space()][1]"), 'Booking reference');

        $travellersPdf = $ticketNumbersPdf = [];

        if (preg_match_all("/^[ ]*(.+)\n+.*[ ]{2,}Depart[ ]{2,}Arrive[ ]{2,}Flight Details$/m", $textPdf, $travellerMatches)) {
            foreach ($travellerMatches[1] as $tRow) {
                if (preg_match("/^(?<traveller>{$patterns['travellerName']})[ ]+{$this->opt($this->t('Tkt No.'))}[ ]+(?<ticket>{$patterns['eTicket']})$/u", $tRow, $m)) {
                    // MS DENISE BRAY Tkt No. 0862199163850
                    $travellersPdf[] = $m['traveller'];
                    $ticketNumbersPdf[] = $m['ticket'];
                } elseif (preg_match("/^{$patterns['travellerName']}$/u", $tRow)) {
                    // MS DENISE BRAY
                    $travellersPdf[] = $tRow;
                }
            }
        }

        if (count($travellersPdf)) {
            $f->general()->travellers(array_unique($travellersPdf));
        } else {
            $f->general()->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Kia ora')]", null, true, "/{$this->opt($this->t('Kia ora'))}\s*([A-Z\s]+)\,/"));
        }

        if (count($ticketNumbersPdf)) {
            $f->issued()->tickets(array_unique($ticketNumbersPdf), false);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('We’ve'))} and {$this->contains($this->t('statusTexts'))}]", null, true, "/{$this->opt($this->t('We’ve'))}\s+([[:alpha:]]+)\s+{$this->opt($this->t('statusTexts'))}/u");

        if (!empty($status)) {
            $f->general()
                ->status($status);
        }

        $account = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Airpoints number:')]", null, true, "/{$this->opt($this->t('Airpoints number:'))}\s*(\d+)$/");

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        $xpath = "//img[contains(@src, 'arrow-right')]/ancestor::table[3]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $xpath = "//text()[normalize-space()='Departure']/following::img[1]/ancestor::table[3]";
            $segments = $this->http->XPath->query($xpath);
        }

        foreach ($segments as $key => $root) {
            $s = $f->addSegment();

            $arrTime = $this->http->FindSingleNode("./descendant::table/descendant::text()[normalize-space()='Arrival']/following::text()[normalize-space()][1]", $root);
            $arrDate = $this->http->FindSingleNode("./descendant::table/descendant::text()[normalize-space()='Arrival']/following::text()[normalize-space()][2]", $root);
            $arrName = $this->http->FindNodes("./descendant::table[last()]/descendant::text()[normalize-space()]", $root);
            $s->arrival()
                ->name($arrName[0])
                ->code($arrName[1])
                ->date(strtotime($arrDate . ', ' . $arrTime));

            $depTime = $this->http->FindSingleNode("./descendant::table/descendant::text()[normalize-space()='Departure']/following::text()[normalize-space()][1]", $root);
            $depDate = $this->http->FindSingleNode("./descendant::table/descendant::text()[normalize-space()='Departure']/following::text()[normalize-space()][2]", $root);
            $depName = $this->http->FindNodes("./descendant::table[last()]/preceding::table[1]/descendant::text()[normalize-space()]", $root);
            $s->departure()
                ->name($depName[0])
                ->code($depName[1])
                ->date(strtotime($depDate . ', ' . $depTime));

            if (!empty($flightsInfo[$key])) {
                $s->airline()->name($flightsInfo[$key]['name'])->number($flightsInfo[$key]['number']);
            } elseif (preg_match("/ Depart[ ]+{$depDate}[ ]+Arrive[ ]+{$arrDate}[ ]+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\n/i", $textPdf, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            } else {
                $s->airline()->noName()->noNumber();
            }
        }

        if (preg_match_all("/\n[ ]*{$this->opt($this->t('PAYMENT'))}(?:[ ]{2}.+)?\n/", $textPdf, $paymentMatches) && count($paymentMatches[0]) === 1
            && preg_match("/\n[ ]*{$this->opt($this->t('PAYMENT'))}(?:[ ]{2}.+)?\n+([\s\S]+)/", $textPdf, $paymentTexts)
        ) {
            if (preg_match_all("/^[ ]*{$this->opt($this->t('TOTAL PAYMENT'))}[ ]{2,}(.*\d.*)$/m", $paymentTexts[1], $totalMatches) && count($totalMatches[0]) === 1
                || preg_match_all("/^[ ]*{$this->opt($this->t('TOTAL'))}[ ]{2,}(.*\d.*)$/m", $paymentTexts[1], $totalMatches) && count($totalMatches[0]) === 1
            ) {
                if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalMatches[1][0], $matches)) {
                    // NZD 1200.00
                    $f->price()
                        ->currency($matches['currency'])
                        ->total($this->normalizeAmount($matches['amount']));
                }
            }
        }
    }

    public function parsePDF(Email $email, string $textPdf): void
    {
        $f = $email->add()->flight();

        $f->general()->confirmation($this->re("/Booking Reference\:\s*([A-Z\d]{6})\s*\n/iu", $textPdf), 'Booking reference');

        if (preg_match_all("/Tkt No\.\s*(\d{10,})/", $textPdf, $m)) {
            $f->setTicketNumbers(array_unique($m[1]), false);
        }

        if (preg_match_all("/Itinerary\n+(\D+)[ ]{10,}Booking Reference\:/", $textPdf, $m)) {
            $m[1] = preg_replace("/\s*\(Child\)\s*/", '', $m[1]);
            $f->general()->travellers($m[1]);
        }

        $account = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Airpoints number:')]", null, true, "/{$this->opt($this->t('Airpoints number:'))}\s*(\d+)$/");

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        if (preg_match_all("/\n\n(\D+[ ]{5,}[A-Z]{3}[\s\-]+[A-Z]{3}\n+\s*Check in & Bag Drop.*\n+^\s*Departs\s*Arrives\n*(?:.+\n){2,}\n*\s*Operator.+\n.+)/mu", $textPdf, $m)) {
            foreach (array_unique($m[1]) as $seg) {
                $s = $f->addSegment();

                if (preg_match("/\s(?<depCode>[A-Z]{3})\s+\-\s+(?<arrCode>[A-Z]{3})/", $seg, $m)) {
                    $s->departure()
                        ->code($m['depCode']);

                    $s->arrival()
                       ->code($m['arrCode']);
                }

                $s->airline()
                    ->name($this->re("/Flight\s*Number.+\n.*[ ]{10}([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+/", $seg))
                    ->number($this->re("/Flight\s*Number.+\n.*[ ]{10}(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/", $seg));

                $duration = $this->re("/Flight Duration.+\n.*[ ]{10}(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+\s*([\dhm\s]{2,7})(?:[ ]{10,}|\n|$)/", $seg);

                if (!empty($duration)) {
                    $s->extra()
                        ->duration($duration);
                }

                $bookingCode = $this->re("/Booking Class:\s*([A-Z])\s+/", $seg);

                if (!empty($bookingCode)) {
                    $s->extra()
                        ->bookingCode($bookingCode);
                }

                $status = $this->re("/Status:\s*(.+)/", $seg);

                if (!empty($status)) {
                    $s->setStatus($status);
                }

                $operator = $this->re("/Flight Duration.+\n\s*(.*)[ ]{10}(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+\s*/", $seg);

                if (!empty($operator)) {
                    $s->airline()
                        ->operator($operator);
                }

                $flightInfo = $this->re("/\n+(\s+Departs.+)\n+\s*Operator/s", $seg);
                $flightTable = $this->SplitCols($flightInfo, [0, 35, 70]);

                if (preg_match("/(?<depTime>[\d\:]+a?p?m)\n(?<depDate>\w+\,\s*\d+.*\d{4})/", $flightTable[0], $m)) {
                    $s->departure()
                        ->date(strtotime($m['depDate'] . ', ' . $m["depTime"]));

                    $depTerminal = $this->re("/Terminal\s*(.+)/i", $flightTable[0]);

                    if (!empty($depTerminal)) {
                        $s->departure()
                            ->terminal($depTerminal);
                    }
                }

                if (preg_match("/(?<arrTime>[\d\:]+a?p?m)\n(?<arrDate>\w+\,\s*\d+.*\d{4})/", $flightTable[1], $m)) {
                    $s->arrival()
                        ->date(strtotime($m['arrDate'] . ', ' . $m["arrTime"]));

                    $arrTerminal = $this->re("/Terminal\s*(.+)/i", $flightTable[1]);

                    if (!empty($arrTerminal)) {
                        $s->arrival()
                            ->terminal($arrTerminal);
                    }
                }

                if (preg_match("/^\s*(?<cabin>\w+)\s*$/su", $flightTable[2], $m)) {
                    $s->extra()
                        ->cabin($m['cabin']);
                }
            }
        }
    }

    public function parsePDF2(Email $email, string $textPdf): void
    {
        $f = $email->add()->flight();

        $f->general()->confirmation($this->re("/BOOKING REF\.\s*([A-Z\d]{6})\s*\n/iu", $textPdf), 'Booking reference');

        if (preg_match_all("/Tkt No\.\s*(\d{10,})/", $textPdf, $m)) {
            $f->setTicketNumbers(array_unique($m[1]), false);
        }

        if (preg_match_all("/\n+^(\D+)\s+Tkt No\.\s*\d+/m", $textPdf, $m)) {
            $f->general()->travellers(array_unique($m[1]));
        }

        $account = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Airpoints number:')]", null, true, "/{$this->opt($this->t('Airpoints number:'))}\s*(\d+)$/");

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        if (preg_match_all("/\n[ ]*{$this->opt($this->t('PAYMENT'))}(?:[ ]{2}.+)?\n/", $textPdf, $paymentMatches) && count($paymentMatches[0]) === 1
            && preg_match("/\n[ ]*{$this->opt($this->t('PAYMENT'))}(?:[ ]{2}.+)?\n+([\s\S]+)/", $textPdf, $paymentTexts)
        ) {
            if (preg_match_all("/^[ ]*{$this->opt($this->t('TOTAL PAYMENT'))}[ ]{2,}(.*\d.*)$/m", $paymentTexts[1], $totalMatches) && count($totalMatches[0]) === 1
                || preg_match_all("/^[ ]*{$this->opt($this->t('TOTAL'))}[ ]{2,}(.*\d.*)$/m", $paymentTexts[1], $totalMatches) && count($totalMatches[0]) === 1
            ) {
                if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalMatches[1][0], $matches)) {
                    // NZD 1200.00
                    $f->price()
                        ->currency($matches['currency'])
                        ->total($this->normalizeAmount($matches['amount']));
                }
            }
        }

        $flightArray = [];

        if (preg_match_all("/\n+^(\s*NO CHECKED BAG.+(?:.+\n){5,}.+A?P?M)/mu", $textPdf, $m)) {
            foreach (array_unique($m[1]) as $seg) {
                $flightTable = $this->SplitCols($seg);

                if (preg_match("/^(?<airlineName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<flightNumber>\d+)\n\s*Operated by:\s*(?<operator>.+)\n(?<cabin>\D+)\-\s*.*\nBooking Class:\s*(?<bookingCode>[A-Z])\nDURATION:\s*(?<duration>.+)\n*$/", $flightTable[3], $m)) {
                    if (!in_array($m['airlineName'] . $m['flightNumber'], $flightArray)) {
                        $s = $f->addSegment();

                        $s->airline()
                            ->name($m['airlineName'])
                            ->number($m['flightNumber'])
                            ->operator($m['operator']);

                        $s->extra()
                            ->cabin($m['cabin'])
                            ->bookingCode($m['bookingCode'])
                            ->duration($m['duration']);

                        $flightArray[] = $m['airlineName'] . $m['flightNumber'];
                    }
                }

                if (preg_match("/Depart\s*(?<depDate>\w+\s*\d+.*\d{4})\n(?<depName>.+)\n(?<depTime>[\d\:]+\s*A?P?M)$/su", $flightTable[1], $m)) {
                    $s->departure()
                        ->name(str_replace("\n", "", $m['depName']))
                        ->date(strtotime(str_replace("\n", "", $m['depDate'] . ', ' . $m['depTime'])));

                    $depName = $s->getDepName();
                    $firstSymbol = $this->re("/^([A-Z])/", $depName);
                    $depName = preg_replace("/^([a-z])/", $firstSymbol, mb_strtolower($depName));

                    $depCode = array_unique($this->http->FindNodes("//text()[{$this->eq($depName)}]/ancestor::td[1]", null, "/\s*([A-Z]{3})$/"));

                    if (count($depCode) > 0) {
                        $s->departure()
                            ->name($depName)
                            ->code($depCode[0]);
                    } else {
                        $s->departure()
                            ->noCode();
                    }
                }

                if (preg_match("/Arrive\s*(?<arrDate>\w+\s*\d+.*\d{4})\n(?<arrName>.+)\n(?<arrTime>[\d\:]+\s*A?P?M)$/su", $flightTable[2], $m)) {
                    $s->arrival()
                        ->name(str_replace("\n", "", $m['arrName']))
                        ->date(strtotime(str_replace("\n", "", $m['arrDate'] . ', ' . $m['arrTime'])));

                    $arrName = $s->getArrName();
                    $firstSymbol = $this->re("/^([A-Z])/", $arrName);
                    $arrName = preg_replace("/^([a-z])/", $firstSymbol, mb_strtolower($arrName));

                    $arrCode = array_unique($this->http->FindNodes("//text()[{$this->eq($arrName)}]/ancestor::td[1]", null, "/\s*([A-Z]{3})$/"));

                    if (count($arrCode) > 0) {
                        $s->arrival()
                            ->name($arrName)
                            ->code($arrCode[0]);
                    } else {
                        $s->arrival()
                            ->noCode();
                    }
                }

                if (preg_match("/\s(?<depCode>[A-Z]{3})\s+\-\s+(?<arrCode>[A-Z]{3})/", $seg, $m)) {
                    $s->departure()
                        ->code($m['depCode']);

                    $s->arrival()
                        ->code($m['arrCode']);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $flightsInfo = [];

        if (preg_match("/all booked to fly\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\s*on/", $parser->getSubject(), $m)) {
            $flightsInfo[0] = ['name' => $m['name'], 'number' => $m['number']];
        }

        $textPdfFull = '';
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (stripos($textPdf, 'airnewzealand.co.nz') === false && strpos($textPdf, 'Air New Zealand Limited') === false) {
                continue;
            }

            if (preg_match("/[ ]{2,}Depart[ ]{2,}Arrive[ ]{2,}Flight Details$/m", $textPdf)) {
                $textPdfFull .= $textPdf . "\n\n";
            } elseif (preg_match("/^[ ]{2,}Departs[ ]{2,}Arrives$/m", $textPdf)) {
                $textPdfFull .= $textPdf . "\n\n";
            }
        }

        if (!empty($textPdfFull) && stripos($textPdfFull, 'Operated by:') === false) {
            $this->parsePDF($email, $textPdfFull);
        } elseif (!empty($textPdfFull) && stripos($textPdfFull, 'Operated by:') !== false) {
            $this->parsePDF2($email, $textPdfFull);
        } else {
            $this->parserHtml($email, $flightsInfo, $textPdfFull);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
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

    private function SplitCols($text, $pos = false)
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

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
