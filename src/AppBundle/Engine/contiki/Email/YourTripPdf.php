<?php

namespace AwardWallet\Engine\contiki\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourTripPdf extends \TAccountChecker
{
    public $mailFiles = "contiki/it-374461383.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'      => ['Booking Reference'],
            'passengerNames'  => ['Passenger Names'],
            'supplierDetails' => ['Supplier Details'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]contiki\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return array_key_exists('subject', $headers)
            && stripos($headers['subject'], 'CONTIKI - Travel Documents for ') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (stripos($textPdf, '@contiki.com') === false
                && stripos($textPdf, " Contiki US Holdings Inc\n") === false
                && stripos($textPdf, " Contiki Holidays (Canada) Ltd\n") === false
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

        $email->setType('YourTripPdf' . ucfirst($this->lang));

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
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'date'          => '\b(?:[-[:alpha:]]+\s+)?\d{1,2}-[[:alpha:]]+-\d{2,4}\b', // Mon 22-May-23
        ];

        if (preg_match_all("/^[ ]*({$this->opt($this->t('confNumber'))})[ ]*[:]+\s*([-_A-Z\d ]{5,35})$/m", $text, $confMatches)) {
            $confMatches[2] = preg_replace('/\s+/', '', $confMatches[2]);

            if (count(array_unique($confMatches[2])) === 1) {
                $otaConfirmation = array_shift($confMatches[2]);
                $otaTitle = array_shift($confMatches[1]);
                $email->ota()->confirmation($otaConfirmation, $otaTitle);
            }
        }

        $travellers = [];

        if (preg_match("/^(?<head>[ ]*{$this->opt($this->t('passengerNames'))} .+)\n+(?<body>[\s\S]+?)\n(?:\n{3}|[ ]*Please ensure you)/m", $text, $m)) {
            $tablePos = [0];

            if (preg_match("/^(.+) {$this->opt($this->t('Room'))} .+/", $m['head'], $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($m['body'], $tablePos);

            $passengerNames = preg_replace('/\s+/', ' ', $this->splitText($table[0], "/^[ ]*\d{1,4}[ ]{0,2}([[:alpha:]])/u", true));

            foreach ($passengerNames as $pName) {
                if (preg_match("/^{$patterns['travellerName']}$/u", $pName)) {
                    $travellers[] = $pName;
                } else {
                    $travellers = [];

                    break;
                }
            }
        }

        $itineraryList = [];

        if (preg_match_all("/\n(?<head>.+ {$this->opt($this->t('supplierDetails'))})\n+(?<body>[\s\S]+?)\n[ ]*{$this->opt($this->t('confNumber'))}/", $text, $itListMatches, PREG_SET_ORDER)) {
            foreach ($itListMatches as $m) {
                $tablePos = [0];

                if (preg_match("/^(.+) {$this->opt($this->t('Date'))} .+/", $m['head'], $matches)) {
                    $tablePos[] = mb_strlen($matches[1]);
                }

                if (preg_match("/^(.+) {$this->opt($this->t('Location'))} .+/", $m['head'], $matches)) {
                    $tablePos[] = mb_strlen($matches[1]);
                }

                if (preg_match("/^(.+) {$this->opt($this->t('Nts'))} .+/", $m['head'], $matches)) {
                    $tablePos[] = mb_strlen($matches[1]);
                }

                if (preg_match("/^(.+) {$this->opt($this->t('supplierDetails'))}$/", $m['head'], $matches)) {
                    $tablePos[] = mb_strlen($matches[1]);
                }

                if (count($tablePos) !== 5) {
                    $this->logger->debug('Wrong itinerary list table!');
                    $itineraryList = [];

                    break;
                }

                $itListRows = $this->splitText($m['body'], "/^(.{1,25}{$patterns['date']}.*)/mu", true);

                foreach ($itListRows as $itListRow) {
                    $table = $this->splitCols($itListRow, $tablePos);
                    $itineraryList[] = array_map('trim', $table);
                }
            }
        }

        foreach ($itineraryList as $it) {
            if (preg_match("/^\s*{$this->opt($this->t('END OF TOUR'))}/i", $it[4])) {
                break;
            }

            $h = $email->add()->hotel();
            $h->general()->noConfirmation();

            if (count($travellers) > 0) {
                $h->general()->travellers($travellers);
            }

            $date = strtotime($it[1]);
            $h->booked()->checkIn($date);

            $nights = $this->re("/^(\d{1,3})\b/", $it[3]);

            if ($nights !== null && !empty($h->getCheckInDate())) {
                $h->booked()->checkOut(strtotime('+' . $nights . ' days', $h->getCheckInDate()));
            }

            if (preg_match("/^(?<name>.{2,})\n*(?<address>(?:\n[^:\n]+){1,5})(?:\n+[ ]*{$this->opt($this->t('TEL'))}|\n+[ ]*{$this->opt($this->t('FAX'))}|$)/i", $it[4], $m)) {
                $h->hotel()->name($m['name'])->address(preg_replace('/\s+/', ' ', trim($m['address'])));
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('TEL'))}[: ]+({$patterns['phone']})(?:[ ]+{$this->opt($this->t('FAX'))}|$)/m", $it[4], $m)) {
                $h->hotel()->phone($m[1]);
            }

            if (preg_match("/(?:^[ ]*| ){$this->opt($this->t('FAX'))}[: ]+({$patterns['phone']})$/m", $it[4], $m)) {
                $h->hotel()->fax($m[1]);
            }
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['passengerNames']) || empty($phrases['supplierDetails'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['confNumber']) !== false
                && $this->strposArray($text, $phrases['passengerNames']) !== false
                && $this->strposArray($text, $phrases['supplierDetails']) !== false
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
