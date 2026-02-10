<?php

namespace AwardWallet\Engine\celebrated\Email;

use AwardWallet\Schema\Parser\Email\Email;

class HotelConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "celebrated/it-176193801.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Confirmation Number' => ['Confirmation Number'],
            'Hotel Address'       => ['Hotel Address'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@celebratedexperiences.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Documents from Celebrated Experiences! (Ref Name:') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = $this->detectEmailFromProvider($parser->getHeader('from')) === true
            || stripos($parser->getSubject(), 'Documents from Celebrated Experiences') !== false;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($detectProvider === false && stripos($textPdf, '@celebratedexperiences.com') === false
                && stripos($textPdf, 'www.celebratedexperiences.com') === false
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
                $travelSegments = $this->splitText($textPdf, "/^[ ]*{$this->opt($this->t('HOTEL CONFIRMATION'))}\n+/m");

                foreach ($travelSegments as $key => $sText) {
                    $type = null;

                    if (preg_match("/(?:{$this->opt($this->t('Hotel'))}|{$this->opt($this->t('Hotel Address'))}|{$this->opt($this->t('Hotel Phone'))})[ ]*:/", $sText)) {
                        $type = 'HOTEL';
                        $this->parseHotel($email, $sText);
                    } elseif (!preg_match("/\S[ ]*[:]+[ ]*\S/", $sText)) {
                        $type = 'Garbage';
                    } else {
                        $type = 'Unknown';
                        $email->add()->hotel(); // for 100% fail
                    }

                    $this->logger->debug('travel-segment-' . $key . ': ' . $type);
                }
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('HotelConfirmationPdf' . ucfirst($this->lang));

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

    private function parseHotel(Email $email, $text): void
    {
        $patterns = [
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
        ];

        $h = $email->add()->hotel();

        $reservationDate = $this->re("/[ ]{2}{$this->opt($this->t('Date'))}[ ]*[:]+[ ]*(.*\d.*)\n/", $text);
        $h->general()->date2($this->normalizeDate($reservationDate));

        if (preg_match("/\n[ ]*({$this->opt($this->t('Confirmation Number'))})[ ]*[:]+[ ]*([-A-Z\d]{5,})\n/", $text, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        $travellers = [];
        $guestVal = $this->re("/\n[ ]*{$this->opt($this->t('Guest'))}[ ]*[:]+[ ]*([^:]{2,}?)\n+[ ]*{$this->opt($this->t('Hotel'))}[ ]*:/", $text);

        if ($guestVal) {
            $guestRows = preg_split('/[ ]*\n+[ ]*/', $guestVal);

            foreach ($guestRows as $gRow) {
                if (preg_match("/^([^,&]+,)\s*([^,&]+?)\s*&\s*([^,&]+)$/", $gRow, $m)) {
                    // Kraninger, Mr. Daniel & Mrs. Christine
                    $travellers = array_merge($travellers, [$m[1] . ' ' . $m[2], $m[1] . ' ' . $m[3]]);
                } else {
                    $travellers = array_merge($travellers, preg_split('/\s+[&]+\s+/', $gRow));
                }
            }
        }
        $h->general()->travellers(preg_replace("/^([^,]+?)\s*,\s*(?:(?:Mstr|Miss|Mrs|Mr|Ms|Dr)[.\s]+)?\s*(\S.+)$/i", '$2 $1', $travellers));

        $hotelName = $this->re("/\n[ ]*{$this->opt($this->t('Hotel'))}[ ]*[:]+[ ]*([\s\S]{2,}?)\n+[ ]*{$this->opt($this->t('Hotel Address'))}[ ]*:/", $text);

        if ($hotelName) {
            $hotelName = preg_replace('/\s+/', ' ', $hotelName);
        }
        $h->hotel()->name($hotelName);

        $hotelTableText = $this->re("/\n([ ]*{$this->opt($this->t('Hotel Address'))}[ ]*[:]+[ ]*[\s\S]{2,}?)\n+[ ]*{$this->opt($this->t('Arrival Date'))}[ ]*:/", $text);
        $tablePos = [0];

        if (preg_match("/^([ ]*{$this->opt($this->t('Hotel Address'))}[ ]*[:]+[ ]*)/m", $hotelTableText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/^((.{3,} ){$this->opt($this->t('Hotel Phone'))}[ ]*[:]+[ ]*)/m", $hotelTableText, $matches)) {
            $tablePos[] = mb_strlen($matches[2]);
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($hotelTableText, $tablePos);

        if (count($table) > 1) {
            $h->hotel()->address(preg_replace('/[ ]*\n+[ ]*/', ', ', $table[1]));
        }

        if (count($table) > 3 && preg_match("/^\s*({$patterns['phone']})\s*$/", $table[3], $m)) {
            $h->hotel()->phone($m[1]);
        }

        if (preg_match("/\n[ ]*{$this->opt($this->t('Arrival Date'))}[ ]*[:]+[ ]*(.*\d.*?)[ ]+{$this->opt($this->t('Departure Date'))}[ ]*[:]+[ ]*(.*\d.*)\n/", $text, $m)) {
            $h->booked()->checkIn2($this->normalizeDate($m[1]))->checkOut2($this->normalizeDate($m[2]));
        }

        $h->booked()
            ->guests($this->re("/\n\s*{$this->opt($this->t('Occupancy:'))} *(\d+)(?:\s{3,}|\n)/", $text));

        $roomType = $this->re("/\n[ ]*{$this->opt($this->t('Room Type'))}[ ]*[:]+[ ]*(.{2,})\n/", $text);
        $roomRate = $this->re("/\n[ ]*{$this->opt($this->t('Room Rate'))}[ ]*[:]+[ ]*(.*\d.*)\n/", $text);

        if ($roomType || $roomRate) {
            $room = $h->addRoom();

            if ($roomType) {
                $room->setType($roomType);
            }

            if ($roomRate) {
                $room->setRate(preg_replace('/\s+/', ' ', $roomRate));
            }
        }

        $cancellation = $this->re("/\n[ ]*{$this->opt($this->t('Cancellation Policy'))}[ ]*[:]+[ ]*(.*\n(?: {10,}.{2,}\n+){0,6})(?: {10,}\W|\n|$)/", $text);

        if ($cancellation) {
            $cancellation = preg_replace('/\s+/', ' ', $cancellation);
        }
        $h->general()->cancellation($cancellation);

        if (preg_match("/^Cancell?ation with (?<prior>\d{1,3} days?) prior to arrival 1 night penalty will apply/i", $cancellation, $m) // en
            || preg_match("/^Cancell?ations within (?<prior>\d{1,3} hours?) prior to arrival will be charged full accommodation/i", $cancellation, $m) // en
        ) {
            $h->booked()->deadlineRelative($m['prior'], '00:00');
        }
    }

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 08/Apr/22
            '/^(\d{2})\s*\/\s*([[:alpha:]]{3})\s*\/\s*(\d{2,4})$/u',
        ];
        $out = [
            '$1-$2-$3',
        ];

        return preg_replace($in, $out, $text);
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Confirmation Number']) || empty($phrases['Hotel Address'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Confirmation Number']) !== false
                && $this->strposArray($text, $phrases['Hotel Address']) !== false
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
