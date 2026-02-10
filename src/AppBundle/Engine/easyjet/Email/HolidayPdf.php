<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class HolidayPdf extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-137086637.eml, easyjet/it-141576457.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Holiday reference'     => ['Holiday reference'],
            'Guest details'         => ['Guest details', 'Guest DETAILS', 'GUEST DETAILS'],
            'Important information' => ['Important information', 'Important Information'],
            'paymentStart'          => ['Payment Details'],
            'paymentEnd'            => ['Guest details', 'Guest DETAILS'],
            'guestsStart'           => ['Guest details', 'Guest DETAILS'],
            'guestsEnd'             => ['Your holiday summary', 'Your Holiday Summary'],
            'holidaySummaryStart'   => ['Your holiday summary', 'Your Holiday Summary'],
            'holidaySummaryEnd'     => ['Flights'],
            'flightsStart'          => ['Flights'],
            'flightsEnd'            => ['Included Luggage'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@easyjet.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/easyJet holidays booking [-A-Z\d]{5,}\s*:/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = $this->detectEmailFromProvider($parser->getHeader('from')) === true;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($detectProvider === false && stripos($textPdf, 'easyJet holidays') === false) {
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
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
        ];

        // remove garbage
        $text = preg_replace("/\n[ ]*{$this->opt($this->t('Page'))}[ ]+\d{1,3}\b.*/", '', $text);

        if (preg_match("/^([\s\S]+?)\n+[ ]*{$this->opt($this->t('Important information'))}\n/", $text, $m)) {
            $text = $m[1];
        }

        $type = '';

        if (preg_match("/(?:^|\n)[ ]*({$this->opt($this->t('Holiday reference'))})[ ]*[:]+[ ]*([-A-Z\d]{5,})(?:[ ]{2}|\n)/", $text, $m)) {
            // it-137086637.eml
            $type = '1';
            $email->ota()->confirmation($m[2], $m[1]);
        } elseif (preg_match("/(?:^|\n)[ ]*({$this->opt($this->t('Holiday reference'))})[: ]*(?:[ ]{2}.+)?\n+[ ]*([-A-Z\d]{5,})(?:[ ]{2}|\n)/", $text, $m)) {
            // it-141576457.eml
            $type = '2';
            $email->ota()->confirmation($m[2], $m[1]);
        }
        $email->setType('HolidayPdf' . $type . ucfirst($this->lang));

        $h = $email->add()->hotel();
        $f = $email->add()->flight();

        if (preg_match("/[ ]{2}({$this->opt($this->t('Flight reference'))})[ ]*[:]+[ ]*([-A-Z\d]{5,})\n/", $text, $m)
            || preg_match("/[ ]{2}({$this->opt($this->t('Flight reference'))})[: ]*\n+[ ]*[-A-Z\d]{5,}[ ]{2,}([-A-Z\d]{5,})\n/", $text, $m)
        ) {
            $f->general()->confirmation($m[2], $m[1]);
        } elseif (preg_match("/Login to your account to view your flight references and find out how to check-in/i", $text)
            || !preg_match("/{$this->opt($this->t('Flight reference'))}/", $text)
        ) {
            $f->general()->noConfirmation();
        }

        if ($type === '2') {
            $tablePos = [0];

            if (preg_match("/^([ ]*{$this->opt($this->t('Cost of your holiday'))}[ ]{2,}){$this->opt($this->t('Flights'))}$/m", $text, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($text, $tablePos);

            if (count($table) === 2) {
                $text = $table[0] . "\n\n" . $table[1];
            }
        }

        $paymentText = $this->re("/\n[ ]*{$this->opt($this->t('paymentStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('paymentEnd'))}\n/", $text);
        $totalPrice = $this->re("/^[ ]*{$this->opt($this->t('Total price'))}[: ]+(.*\d.*)$/m", $paymentText);

        if (preg_match('/^\d[,.\'\d ]*$/', $totalPrice)) {
            $email->price()->total(PriceHelper::parse($totalPrice));
        }

        $guestNames = $travellers = [];
        $guestDetails = $this->re("/\n[ ]*{$this->opt($this->t('guestsStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('guestsEnd'))}\n/", $text);

        if ($type === '1') {
            $guestNames = preg_split("/\s*[,]+\s*/", preg_replace('/\s+/', ' ', $guestDetails));
        } else {
            $guestNames = preg_split("/[ ]*\n+[ ]*/", $guestDetails);
        }

        foreach ($guestNames as $gName) {
            if (preg_match("/^{$patterns['travellerName']}$/u", $gName)) {
                $travellers[] = $gName;
            } else {
                $travellers = [];

                break;
            }
        }

        if (count($travellers) > 0) {
            $h->general()->travellers($travellers, true);
            $f->general()->travellers($travellers, true);
        }

        $holidaySummary = $this->re("/\n[ ]*{$this->opt($this->t('holidaySummaryStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('holidaySummaryEnd'))}\n/", $text);

        if ($type === '1') {
            $tablePos = [0];

            if (preg_match("/^([ ]*\S.*?[ ]{2})\S.*$/m", $holidaySummary, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($holidaySummary, $tablePos);

            if (count($table) === 2) {
                $holidaySummary = $table[0] . "\n\n" . $table[1];
            }
        }

        if (preg_match("/^\s*(?<firstLine>.{2,})\n+[ ]*(?<address>[\s\S]{3,}?)\n+.*\d[ ]*(?:{$this->opt($this->t('night'))}|{$this->opt($this->t('guest'))})/", $holidaySummary, $matches)) {
            $hotelName = $matches['firstLine'];
            $address = preg_replace('/[ ]*\n+[ ]*/', ', ', $matches['address']);

            if ($type === '1' && preg_match("/^(.{2,}?)\s*,\s*(.{3,})$/", $matches['firstLine'], $m)) {
                $hotelName = $m[1];
                $address = $m[2] . ', ' . $address;
            }
            $h->hotel()->name($hotelName)->address($address);
        }

        if (preg_match("/^[ ]*(?<nights>\d{1,3})\s*nights?, from\s+(?<date>.{6,})$/m", $holidaySummary, $m)) {
            $dateCheckIn = strtotime($m['date']);

            if ($dateCheckIn) {
                $h->booked()->checkIn($dateCheckIn)->checkOut(strtotime('+' . $m['nights'] . ' days', $dateCheckIn));
            }
        }

        if (preg_match("/^[ ]*(?<guests>\d{1,3})[ ]*guest\(s\) staying in (?<rooms>\d{1,3}) room\(s\)$/m", $holidaySummary, $m)) {
            $h->booked()->guests($m['guests'])->rooms($m['rooms']);
        }

        $flights = $this->re("/\n[ ]*{$this->opt($this->t('flightsStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('flightsEnd'))}\n/", $text);

        if ($type === '1') {
            $tablePos = [0];

            if (preg_match("/^(.+[ ]{2}){$this->opt($this->t('Return'))}$/m", $flights, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($flights, $tablePos);

            if (count($table) === 2) {
                $flights = $table[0] . "\n\n" . $table[1];
            }
        }

        /*
            Heraklion, Nikos Kazan
            Airport (HER)
            20:55
        */
        $patterns['airport'] = "/^\s*(?<name>[\s\S]{2,}?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s+(?<time>{$patterns['time']})/";

        $segments = $this->splitText($flights, "/(.{2,}\([ ]*(?:[A-Z]{3}|[A-Z][A-Z\d]|[A-Z\d][A-Z])\d+[ ]*\))/", true);

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            if (preg_match("/^\s*.{2,}\([ ]*(?<name>[A-Z]{3}|[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d+)[ ]*\)(?:[ ]*{$this->opt($this->t('Operated by'))}[ ]+(?<operator>.{2,}))?\n+[ ]*(?<date>.*\d.*)\n+(?<airports>[\s\S]+)/", $sText, $m)) {
                /*
                    easyJet (EZY6951) Operated by easyJet UK Limited
                    Tue 24th May 2022
                    . . .
                */
                if (in_array($m['name'], ['EZY', 'EJU', 'EZS'])) {
                    // ‘EZY’ are operated by easyJet UK Limited, ‘EJU’ are operated by easyJet Europe Airline GmbH and ‘EZS’ are operated by easyJet Switzerland SA
                    $m['name'] = 'U2';
                }
                $s->airline()->name($m['name'])->number($m['number']);

                if (!empty($m['operator'])) {
                    $s->airline()->operator($m['operator']);
                }
                $dateValue = $m['date'];
                $airportsText = $m['airports'];
            } else {
                $dateValue = $airportsText = '';
            }

            $tablePos = [0];

            if (preg_match("/^(.+ ){$patterns['time']}$/m", $airportsText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($airportsText, $tablePos);

            if (count($table) !== 2) {
                continue;
            }

            if (preg_match($patterns['airport'], $table[0], $m)) {
                $s->departure()->name(preg_replace('/\s+/', ' ', $m['name']))->code($m['code'])->date(strtotime($m['time'], strtotime($dateValue)));
            }

            if (preg_match($patterns['airport'], $table[1], $m)) {
                $s->arrival()->name(preg_replace('/\s+/', ' ', $m['name']))->code($m['code'])->date(strtotime($m['time'], strtotime($dateValue)));
            }
        }

        $h->general()->noConfirmation();
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Holiday reference']) || empty($phrases['Guest details'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Holiday reference']) !== false
                && $this->strposArray($text, $phrases['Guest details']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray($text, $phrases)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = strpos($text, $phrase);

            if ($result !== false) {
                return $result;
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
        preg_match($re, $str, $m);

        if (!empty($m[$c])) {
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
