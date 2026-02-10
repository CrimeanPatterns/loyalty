<?php

namespace AwardWallet\Engine\airnewzealand\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TransferPDF extends \TAccountChecker
{
    public $mailFiles = "airnewzealand/it-499886802.eml, airnewzealand/it-514398703.eml";
    public $subjects = [
        "/Booking Confirmation\s*\d{4}\-\d{4}\-\d{4}/",
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'taxi@airnz.co.nz') !== false) {
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, 'Air New Zealand') !== false
                && stripos($textPdf, 'Service Type:') !== false
                && stripos($textPdf, 'Transfers') !== false) {
                return true;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/taxi[@.]airnz\.co\.nz$/', $from) > 0;
    }

    public function parsePDF(Email $email, string $textPdf): void
    {
        $transferText = $this->re("/(Service Information\s*Departure Point\s*Arrival Point\s*Booking Information\n+(?:.+\n){5,15}\n*Provider\s*Information)/", $textPdf);
        $transferTable = $this->SplitCols($transferText);

        $otaConf = $this->re("/BOOKING REFERENCE:([\d\-]+)\n/", $textPdf);

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        $t = $email->add()->transfer();
        $traveller = str_replace("\n", " ", $this->re("/Passenger Name:\s*(.+)Number of Passengers:/s", $transferTable[3]));

        $t->general()
            ->confirmation($this->re("/\s+Reference:\s*([\dA-Z]+)\n/", $textPdf))
            ->traveller(preg_replace("/^(?:Mrs|Mr|Ms)\s+/", "", $traveller));

        $price = $this->re("/TOTAL\s*(\D{1,3}[\d\.\,]+)\n/", $textPdf);

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)$/", $price, $m)) {
            $currency = $m['currency'];
            $nzdDollar = $this->re("/\s+(NZD)\s+[$]\d+/", $textPdf);

            if (!empty($nzdDollar)) {
                $currency = $nzdDollar;
            }

            $t->price()
                ->currency($currency)
                ->total(PriceHelper::parse($m['total'], $currency));
        }

        $s = $t->addSegment();

        $depName = $this->re("/Departure Point\n+((?:.+\n){1,5})(?:\n\n|\n\d+\:\d+)/", $transferTable[1]);
        $s->departure()
            ->name(str_replace("\n", " ", $depName));

        $arrName = $this->re("/Arrival Point\n+((?:.+\n){1,4})\n\n/", $transferTable[2]);
        $s->arrival()
            ->name(str_replace("\n", " ", $arrName));

        //it-514398703.eml
        if (preg_match("/Date:\s*\w+\s*(?<day>\d+)\D{1,2}\s*(?<month>\w+)\s*(?<year>\d{4}).+Flight Arrival:\s*(?<time>[\d\:]+\s*a?p?m)/s", $transferTable[3], $m)) {
            $date = strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time']);
            $s->departure()
                ->date($date);

            $s->arrival()
                ->noDate();
        }

        //it-499886802.eml
        if (preg_match("/Date:\s*\w+\s*(?<day>\d+)\D{1,2}\s*(?<month>\w+)\s*(?<year>\d{4}).+Flight Departure:\s*(?<time>[\d\:]+\s*a?p?m)/s", $transferTable[3], $m)) {
            $depTime = $this->re("/\s*([\d\:]+\s*a?p?m)\s*Pick Up/", $transferTable[1]);
            $depDate = strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $depTime);
            $s->departure()
                ->date($depDate);

            $arrDate = strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time']);
            $s->arrival()
                ->date(strtotime('-1 hours', $arrDate));
        }

        $s->extra()
            ->adults($this->re("/Number of Passengers:\s*(\d+)AD/", $transferTable[3]));
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->parsePDF($email, $textPdf);
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
