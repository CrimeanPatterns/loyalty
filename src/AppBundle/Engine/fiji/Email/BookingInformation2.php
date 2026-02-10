<?php

namespace AwardWallet\Engine\fiji\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class BookingInformation2 extends \TAccountChecker
{
    public $mailFiles = "fiji/it-127721108.eml, fiji/it-128425348.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'     => ['Booking Reference:'],
            'depart'         => ['DEPARTS'],
            'arrive'         => ['ARRIVES'],
            'statusVariants' => ['Confirmed', 'Confirm', 'Changed', 'Change', 'Flown'],
            'operatedBy'     => ['Operated By:', 'Operated by:'],
            'passengers'     => ['PASSENGERS', 'Passengers'],
        ],
    ];

    private $subjects = [
        'en' => ['Booking Information'],
    ];

    private $detectors = [
        'en' => ['FLIGHT DETAILS', 'Flight Details', 'Flight details'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'fijiairways.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Fiji Airways') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".fijiairways.com/") or contains(@href,"schedulechange.fijiairways.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing to fly with Fiji Airways")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('BookingInformation' . ucfirst($this->lang));

        $textPdfFull = '';

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('confNumber'))}(?:[ ]{2}|[ ]*\n|\s*$)/im", $textPdf)) {
                $textPdfFull .= $textPdf . "\n\n";
            }
        }

        $this->parseFlight($email, $textPdfFull);

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

    private function parseFlight(Email $email, string $textPdf): void
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：.","dddddddddd::"),"d:dd")';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $f = $email->add()->flight();

        $confirmationText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]");

        if (preg_match("/^({$this->opt($this->t('confNumber'))})[:\s]+([A-Z\d]{5,})$/", $confirmationText, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
            $this->parsePrice($f, $m[2], $textPdf);
        }

        /*
            Wed 29 Dec 2021
            10:45PM
            (22:45)
            Los Angeles
        */
        $patterns['dateTime'] = "/^(?<date>.*\d.*)\n+(?<time>{$patterns['time']})(?:\n|$)/";

        $segments = $this->http->XPath->query("//table[ descendant::tr[not(.//tr) and *[{$this->starts($this->t('depart'))}]/following-sibling::*[{$this->starts($this->t('arrive'))}]] and following-sibling::*[normalize-space()][1][self::table]/descendant::text()[{$xpathTime}][2] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("descendant::tr[not(.//tr) and normalize-space()][1]/*[2]", $segment);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $xpathCodes = "descendant::tr[ *[normalize-space()][1][string-length(normalize-space())=3]/following-sibling::*[normalize-space()][last()][string-length(normalize-space())=3] ]";

            $s->departure()->code($this->http->FindSingleNode($xpathCodes . "/*[normalize-space()][1]", $segment, true, '/^[A-Z]{3}$/'));
            $s->arrival()->code($this->http->FindSingleNode($xpathCodes . "/*[normalize-space()][last()]", $segment, true, '/^[A-Z]{3}$/'));

            $xpathDates = "following-sibling::*[normalize-space()][1]/descendant-or-self::*[ count(*[normalize-space()])=2 and *[normalize-space()][1]/descendant::text()[{$xpathTime}] and *[normalize-space()][1]/descendant::text()[{$xpathTime}] ][1]";

            $dateDep = implode("\n", $this->http->FindNodes($xpathDates . "/*[normalize-space()][1]/descendant::text()[normalize-space()]", $segment));

            if (preg_match($patterns['dateTime'], $dateDep, $m)) {
                $s->departure()->date(strtotime($m['time'], strtotime($m['date'])));
            }
            $dateArr = implode("\n", $this->http->FindNodes($xpathDates . "/*[normalize-space()][2]/descendant::text()[normalize-space()]", $segment));

            if (preg_match($patterns['dateTime'], $dateArr, $m)) {
                $s->arrival()->date(strtotime($m['time'], strtotime($m['date'])));
            }

            $xpathExtra = "following-sibling::*[normalize-space()][2]";
            $xpathCabin = $xpathExtra . "/descendant-or-self::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->contains($this->t('Class:'))}] ]";

            $cabin = $this->http->FindSingleNode($xpathCabin . "/*[normalize-space()][1]", $segment, true, "/^(.{2,}?)\s+{$this->opt($this->t('Class:'))}$/");
            $status = $this->http->FindSingleNode($xpathCabin . "/*[normalize-space()][2]", $segment, true, "/^[:\s]*({$this->opt($this->t('statusVariants'))})$/i");
            $s->extra()->cabin($cabin)->status($status);

            $operator = $this->http->FindSingleNode($xpathExtra . "/descendant-or-self::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->contains($this->t('operatedBy'))}] ]/*[normalize-space()][2]", $segment);
            $s->airline()->operator($operator);

            if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber()) && !empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $seats = $meals = [];
                $flightDetailRows = $this->http->XPath->query("//tr[{$this->starts($s->getAirlineName() . $s->getFlightNumber() . ' ' . $s->getDepCode() . ' - ' . $s->getArrCode())}]");

                foreach ($flightDetailRows as $fdRow) {
                    $fdBelowRows = $this->http->XPath->query("following-sibling::*[normalize-space()]", $fdRow);

                    foreach ($fdBelowRows as $fdbRow) {
                        $fdbText = $this->http->FindSingleNode('.', $fdbRow);

                        if (preg_match("/(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\d+\s+[A-Z]{3}\s*-\s*[A-Z]{3}\s*:/", $fdbText) > 0) {
                            continue 2;
                        }

                        if (preg_match("/^{$this->opt($this->t('Seat'))}[:\s]+(\d[ ,A-Z\d]*[A-Z])[,\s]*(?:\(|$)/", $fdbText, $m)) {
                            $seatValues = preg_split('/\s*[,]+\s*/', $m[1]);
                            $seats = array_merge($seats, $seatValues);
                        }

                        if (preg_match("/^{$this->opt($this->t('Meal'))}[:\s]+(.{2,})$/", $fdbText, $m)) {
                            $meals[] = $m[1];
                        }
                    }
                }

                if (count($seats) > 0) {
                    $s->extra()->seats(array_unique($seats));
                }

                if (count($meals) > 0) {
                    $s->extra()->meals(array_unique($meals));
                }
            }
        }

        $travellers = $accounts = [];
        $travellerRows = $this->http->XPath->query("//tr[{$this->eq($this->t('passengers'))}]/following-sibling::tr/descendant::tr[not(.//tr) and *[2] and normalize-space()][1][count(*[descendant::img][1]/following-sibling::*[normalize-space()])=1]");

        foreach ($travellerRows as $tRow) {
            $tName = $this->http->FindSingleNode("*[descendant::img][1]/following-sibling::*[normalize-space()]", $tRow, true, "/^({$patterns['travellerName']})(?i)(?:\s*\(\s*INFANT\s*\))?$/u");
            $travellers[] = $tName;
            $membershipNumbers = array_filter($this->http->FindNodes("following-sibling::*/descendant::text()[{$this->contains($this->t('Membership:'))}]", $tRow, "/{$this->opt($this->t('Membership:'))}[:\s]+([- A-Z\d]{5,})$/"));

            if (count($membershipNumbers)) {
                $accounts = array_merge($accounts, $membershipNumbers);
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, true);
        }

        if (count($accounts) > 0) {
            $f->program()->accounts(array_unique($accounts), false);
        }
    }

    private function parsePrice(Flight $f, string $confNumber, string $textPdf): void
    {
        $baseFareCharges = $baseFareCurrencies = $taxCharges = $taxCurrencies = $amountPaidCharges = $amountPaidCurrencies = [];
        $pdfPages = $this->splitText($textPdf, "/^([ ]*{$this->opt($this->t('confNumber'))}(?:[ ]{2}|[ ]*\n|\s*$))/im", true);

        foreach ($pdfPages as $pageText) {
            $table1Text = $this->re("/^([ ]*{$this->opt($this->t('confNumber'))}(?:[ ]{2}|[ ]*\n).+?)\n+[ ]*{$this->opt($this->t('PRICE BREAKDOWN'))}(?:[ ]*\n|\s*$)/ims", $pageText);

            if (!preg_match("/^[ ]{0,12}{$this->opt($confNumber)}(?:[ ]{2}|\n)/m", $table1Text)) {
                continue;
            }

            $table2Text = $this->re("/\n([ ]*{$this->opt($this->t('Base Fare'))}(?:[ ]{2}|[ ]*\n).+?)\n+[ ]*{$this->opt($this->t('Depart'))}[ ]+{$this->opt($this->t('Arrive'))}[ ]+{$this->opt($this->t('Details'))}/is", $pageText);

            $tablePos = [0];

            if (preg_match("/^(.+ ){$this->opt($this->t('Taxes & Fees'))}(?:[ ]{2}|\n)/i", $table2Text, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            if (preg_match("/^(.+ ){$this->opt($this->t('Amount Paid'))}(?:[ ]{2}|\n)/i", $table2Text, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            $table = $this->splitCols($table2Text, $tablePos);

            if (count($table) !== 3) {
                continue;
            }

            if (preg_match("/^\s*.+\n+[ ]*(.+)/", $table[0], $m)
                && preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)$/u', $m[1], $matches)
            ) {
                // 33.30AUD
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $baseFareCharges[] = PriceHelper::parse($matches['amount'], $currencyCode);
                $baseFareCurrencies[] = $matches['currency'];
            }

            if (preg_match("/^\s*.+\n+[ ]*(.+)/", $table[1], $m)
                && preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)$/u', $m[1], $matches)
            ) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $taxCharges[] = PriceHelper::parse($matches['amount'], $currencyCode);
                $taxCurrencies[] = $matches['currency'];
            }

            if (preg_match("/^\s*.+\n+[ ]*(.+)/", $table[2], $m)
                && preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)$/u', $m[1], $matches)
            ) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $amountPaidCharges[] = PriceHelper::parse($matches['amount'], $currencyCode);
                $amountPaidCurrencies[] = $matches['currency'];
            }
        }

        if (count(array_unique($amountPaidCurrencies)) === 1 && count($amountPaidCharges) > 0) {
            $f->price()->total(array_sum($amountPaidCharges))->currency($amountPaidCurrencies[0]);

            if (count(array_unique($baseFareCurrencies)) === 1 && $baseFareCurrencies[0] === $amountPaidCurrencies[0]
                && count($baseFareCharges) > 0
            ) {
                $f->price()->cost(array_sum($baseFareCharges));
            }

            if (count(array_unique($taxCurrencies)) === 1 && $taxCurrencies[0] === $amountPaidCurrencies[0]
                && count($taxCharges) > 0
            ) {
                $f->price()->tax(array_sum($taxCharges));
            }
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['arrive'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['arrive'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
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
}
