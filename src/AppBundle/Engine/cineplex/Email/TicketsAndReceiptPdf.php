<?php

namespace AwardWallet\Engine\cineplex\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class TicketsAndReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "cineplex/it-644042329.eml, cineplex/it-642394510.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'      => ['Booking ID:', 'Booking ID :'],
            'filmPerformance' => ['Film / Performance', 'Film/Performance'],
            'feeNames'        => ['Booking Fee (non-refundable)', 'Processing Fee', 'Tax'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]cineplex\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your Cineplex e-Ticket Purchase Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (stripos($textPdf, 'www.cineplex.com') === false
                && stripos($textPdf, 'using Cineplex.com ') === false
            ) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('TicketsAndReceiptPdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, $text): void
    {
        $patterns = [
            'date'          => '.+\b\d{4}\b', // Saturday May 7, 2022
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $ev = $email->add()->event();
        $ev->type()->show();

        $receiptText = $this->re("/^[ ]*{$this->opt($this->t('Transaction Receipt'))}\n+(.+)/ims", $text);

        if (empty($receiptText)) {
            $this->logger->debug('Transaction Receipt not found!');

            return;
        }

        if (preg_match("/^[ ]*({$this->opt($this->t('confNumber'))})[: ]*([-A-Z\d]{5,})\n/m", $receiptText, $m)) {
            $ev->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $table1Text = $this->re("/^([ ]*{$this->opt($this->t('filmPerformance'))} .+\n+[\s\S]+)\n+.*{$this->opt($this->t('Tickets'))}[ ]+{$this->opt($this->t('Quantity'))} /im", $receiptText);

        $table1Pos = $this->rowColsPos($this->re('/(.{2,})/', $table1Text));

        if (count($table1Pos) !== 4) {
            $this->logger->debug('Wrong table-1!');

            return;
        }

        if (!empty($table1Pos[2])) {
            $table1Pos[2] -= 4;
        }

        $table1 = $this->splitCols($table1Text, $table1Pos);

        if (preg_match("/^\s*{$this->opt($this->t('filmPerformance'))}[:\s]+(.+)$/s", $table1[0], $m)) {
            $ev->place()->name(preg_replace('/\s+/', ' ', $m[1]));
        }

        if (preg_match("/^\s*{$this->opt($this->t('Date'))}[:\s]+({$patterns['date']})\s*$/s", $table1[1], $mD)
            && preg_match("/^\s*{$this->opt($this->t('Time'))}[:\s]+({$patterns['time']})/", $table1[2], $mT)
        ) {
            $ev->booked()->start(strtotime($mT[1], strtotime(preg_replace('/\s+/', ' ', $mD[1]))))->noEnd();
        }

        if (preg_match("/^(?<thead>.+ {$this->opt($this->t('Tickets'))}[ ]+{$this->opt($this->t('Quantity'))} .+)\n+(?<tbody>[\s\S]+?)(?:\n{2}|\n[ ]{85,}\S)/m", $receiptText, $m)) {
            $table2Pos = $this->rowColsPos($m['thead']);

            if (count($table2Pos) > 0 && preg_match("/^(.+ {$this->opt($this->t('Price'))})[ ]+{$this->opt($this->t('Amount'))}$/", $m['thead'], $matches)) {
                $table2Pos[count($table2Pos) - 1] = mb_strlen($matches[1]) + 1;
            }

            $table2Rows = $this->splitText($m['tbody'], "/((?:^|.+ ){$this->opt($this->t('Seat'))} ?:)/m", true);
        } else {
            $table2Pos = $table2Rows = [];
        }

        if (count($table2Pos) < 5) {
            $this->logger->debug('Wrong table-2!');

            return;
        }

        $seats = $currencies = $amounts = [];

        foreach ($table2Rows as $t2Row) {
            $table2 = $this->splitCols($t2Row, $table2Pos);

            if (count($table2) < 5) {
                $this->logger->debug('Wrong table-2 row!');
                $email->add()->flight(); // for 100% fail

                break;
            }

            $seatParts = [];

            if (preg_match("/\b{$this->opt($this->t('Row'))}\s*[:]+\s*([A-Z]{1,2})\b/", $table2[count($table2) - 5], $m)) {
                $seatParts[] = $m[1];
            }

            if (preg_match("/\b{$this->opt($this->t('Seat'))}\s*[:]+\s*(\d+)\b/", $table2[count($table2) - 5], $m)) {
                $seatParts[] = $m[1];
            }

            if (count($seatParts) > 0) {
                $seats[] = implode('', $seatParts);
            }

            if (preg_match('/^\s*(?<currency>[^\-\d)(]+?)?[ ]*(?<amount>\d[,.‘\'\d ]*?)\s*$/u', $table2[count($table2) - 1], $m)) {
                if (!empty($m['currency'])) {
                    $currencies[] = $m['currency'];
                }

                $amounts[] = $m['amount'];
            }
        }

        if (count($seats) > 0) {
            $ev->booked()->seats($seats);
        }

        $totalPrice = $this->re("/^[ ]*{$this->opt($this->t('Total:'))}[: ]*(.+)/m", $receiptText);

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // $28.35
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $ev->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            if (count(array_unique($currencies)) === 1 && $currencies[0] === $matches['currency']) {
                $ev->price()->cost(array_sum(
                    array_map(function ($item) use ($currencyCode) {
                        return PriceHelper::parse($item, $currencyCode);
                    }, $amounts)
                ));
            }

            $discount = $this->re("/^[ ]*{$this->opt($this->t('Scene+ Discount:'))}[-: ]*(.+)/m", $receiptText);

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $discount, $m)) {
                $ev->price()->discount(PriceHelper::parse($m['amount'], $currencyCode));
            }

            preg_match_all("/^[ ]*({$this->opt($this->t('feeNames'))}) ?[:]+[ ]*(.+)/m", $receiptText, $feeMatches, PREG_SET_ORDER);

            foreach ($feeMatches as $feeM) {
                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeM[2], $m)) {
                    $ev->price()->fee($feeM[1], PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }

        $table3Text = $this->re("/^([ ]*{$this->opt($this->t('Purchaser Info'))} .+\n+[\s\S]+?)(?:\n{2}|{$this->opt($this->t('Charges for this transaction will appear'))})/im", $receiptText);

        $table3Pos = $this->rowColsPos($this->re('/(.{2,})/', $table3Text));

        if (count($table3Pos) !== 3) {
            $this->logger->debug('Wrong table-3!');

            return;
        }

        $table3 = $this->splitCols($table3Text, $table3Pos);

        if (preg_match("/{$this->opt($this->t('Purchaser Info'))}[: ]*\n+[ ]*({$patterns['travellerName']})\n+[ ]*{$this->opt($this->t('Email'))}[ ]*:/u", $table3[0], $m)) {
            $ev->general()->traveller($m[1], true);
        }

        $theatreInfo = $this->re("/{$this->opt($this->t('Theatre Info'))}[: ]*\n+[ ]*(.+)/s", $table3[2]);

        if (preg_match("/^((?:.+\n+){1,4}?)(?:[ ]*{$this->opt($this->t('Phone'))}\s*:|$)/", $theatreInfo, $m)) {
            $ev->place()->address(preg_replace('/\s+/', ' ', trim($m[1])));
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Phone'))}\s*[:]+\s*({$patterns['phone']})$/m", $table3[2], $m)) {
            $ev->place()->phone($m[1]);
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['filmPerformance'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['confNumber']) !== false
                && $this->strposArray($text, $phrases['filmPerformance']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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
}
