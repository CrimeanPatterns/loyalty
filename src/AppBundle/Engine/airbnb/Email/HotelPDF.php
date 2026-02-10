<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelPDF extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-12260188.eml, airbnb/it-206990413.eml, airbnb/it-89272104.eml";
    public $lang = '';

    public $detectLang = [
        "en" => ["Who’s coming"],
        "fr" => ["Qui vient ?"],
    ];

    public static $dictionary = [
        "en" => [
            //'Who’s coming' => '',
            //'Address' => '',
            'Your host,' => ['Your host,', 'Hosted by'],
            'Confirmation code' => ['Confirmation code', 'Con2rmation code'],
            'guests' => ['guests', 'guest'],
            //'Cancellation policy' => '',
            //'Read more' => '',
            //'Payment details' => '',
            //'Total cost:' => '',
        ],
        "fr" => [
            'Who’s coming' => 'Qui vient ?',
            'Address'      => 'Adresse',
            'Your host,'   => 'Hôte :',
            'Confirmation code' => 'Code de confirmation',
            'guests'            => 'voyageur',
            //'Cancellation policy' => '',
            //'Read more' => '',
            //'Payment details' => '',
            //'Total cost:' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($text);

            if (strpos($text, $this->t('Who’s coming'))
                && strpos($text, $this->t('Address'))
                && $this->containsText($text, $this->t('Your host,'))) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function ParsePDF(Email $email, $text)
    {
        $h = $email->add()->hotel();

        if (preg_match("/^[ ]*({$this->opt($this->t('Confirmation code'))})\s*([-A-Z\d]{5,})$/im", $text, $m)) {
            $m[1] = str_replace('Con2rmation code', 'Confirmation code', $m[1]);
            $h->general()->confirmation($m[2], $m[1]);
        }

        $h->general()
            ->travellers(preg_split("/\s*,\s*/", $this->re("/{$this->opt($this->t('guests'))}\s*\n(.+)/", $text)));

        $cancellation = $this->re("/{$this->opt($this->t('Cancellation policy'))}(.+){$this->opt($this->t('Read more'))}/su", $text);

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation(str_replace("\n", ", ", $cancellation));
        }

        $name = $this->re("/^(.+(?:\n *\S.*){0,3})\n\n/", $text);
        if (preg_match("/([\s\S]+)\n *[[:alpha:]]+: *([\d\(\)\+\- \.]{5,})\s*$/", $name, $m)) {
            $name = $m[1];
            $h->hotel()
                ->phone($m[2]);
        }
        $h->hotel()
            ->name(preg_replace("/\s+/", ' ', $name))
            ->address($this->re("/{$this->opt($this->t('Address'))}\s*\n(.+)/", $text));

        $checkInfo = $this->re("/^\s*\S[^\n]+(?:\n *\S[^\n]*){0,3}\n\n(.+)\n+[ ]*{$this->opt($this->t('Who’s coming'))}\n/s", $text);

        $dates = $this->splitCols($checkInfo, $this->colsPos($this->inOneRow($checkInfo)));

        if (count($dates) == 2) {
            $h->booked()
                ->checkIn($this->normalizeDate(trim($dates[0])))
                ->checkOut($this->normalizeDate(trim($dates[1])));
        }

        $h->booked()
            ->guests($this->re("/(\d+)\s*{$this->opt($this->t('guests'))}/", $text));

        $paymentDetails = $this->re("/\n[ ]*{$this->opt($this->t('Payment details'))}[\S\s]*\n[ ]*{$this->opt($this->t('Total cost:'))}\s*(.*\d.*)(?:\n|$)/i", $text);

        if (empty($paymentDetails)) {
            $paymentDetails = $this->re("/{$this->opt(preg_replace("/\W*$/u", '', $this->t('Total cost:')))}\s*(.*\d.*)(?:\n|$)/i", $text);
        }

        if (stripos($paymentDetails, 'unit') == false && (
            preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $paymentDetails, $matches)
            || preg_match('/^\D*(?<amount>\d[,.\'\d ]*) *(?<currency>[A-Z]{3})$/', $paymentDetails, $matches)
        )) {
            // $941.39
            $h->price()
                ->currency($matches['currency'])
                ->total($this->normalizeAmount($matches['amount']));
        }
        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->assignLang($text);
            $this->ParsePDF($email, $text);
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

    public function assignLang($text)
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/Free cancellation before (?<time>[\d\:]+\s*[AP]M) on (?<month>\w+) (?<day>\d+)/us', $cancellationText, $m)) {
            if (!isset($m['year']) && !empty($h->getCheckInDate())) {
                $deadline = EmailDateHelper::parseDateRelative($m['day'] . ' ' . $m['month'], $h->getCheckInDate());
            } else {
                $deadline = $m['day'] . ' ' . $m['month'] . ' ' . $m['year'];
            }
            if (!empty($deadline)) {
                $h->booked()->deadline(strtotime($m['time'], $deadline));
            }
        }
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.$date);

        $in = [
            // ven. 25 févr. 2022,15:00; Sat, 3 Dec, 2022, 12:00 pm
            "/^\s*[[:alpha:]]+[.,\s]+(\d+)\s*([[:alpha:]]+)[.,]?\s*(\d{4})\s*\,?\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }
        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (strpos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && strpos($text, $needle) !== false) {
            return true;
        }
        return false;
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

    private function colsPos($table, $delta = 5): array
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
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $delta) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
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
