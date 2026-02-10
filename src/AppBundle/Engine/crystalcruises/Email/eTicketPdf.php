<?php

namespace AwardWallet\Engine\crystalcruises\Email;

use AwardWallet\Schema\Parser\Email\Email;

class eTicketPdf extends \TAccountChecker
{
    public $mailFiles = "crystalcruises/it-686080592.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'EMBARK PORT' => ['EMBARK PORT'],
            'DEBARK DATE' => ['DEBARK DATE'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]crystalcruises\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return array_key_exists('subject', $headers) && stripos($headers['subject'], 'Your Crystal eticket is enclosed') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (stripos($textPdf, '@crystalcruises.com') === false
                && !preg_match("/^[ ]*COMPANY[ ]*[:]+[ ]*CRYSTAL CRUISES(?:[ ]{2}|$)/im", $textPdf)
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

        $email->setType('eTicketPdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, string $text): void
    {
        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:]\s]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $c = $email->add()->cruise();

        // travellers

        $travellers = [];
        $travellersText = $this->re("/^[ ]*{$this->opt($this->t('SUMMARY'))}\n+([\s\S]+?)\n+^[ ]*{$this->opt($this->t('BOOKING DETAILS'))}$/m", $text);
        $tablePos = [0];

        if (preg_match("/^(([ ]{7,}){$this->opt($this->t('NAME'))}[ ]+){$this->opt($this->t('BIRTHDATE'))}/m", $travellersText, $matches)) {
            $tablePos[] = mb_strlen($matches[2]);
            $tablePos[] = mb_strlen($matches[1]);
        }

        $travellerRows = $this->splitText($travellersText, "/^([ ]*{$this->opt($this->t('GUEST'))}[- ]*\d{1,3}\b)/m", true);

        foreach ($travellerRows as $tRow) {
            $table = $this->splitCols($tRow, $tablePos);

            if (count($table) !== 3) {
                $travellers = [];
                $this->logger->debug('Wrong travellers table!');

                break;
            }

            if (preg_match("/^\s*({$patterns['travellerName']})\s*$/u", $table[1], $m)) {
                $travellers[] = preg_replace('/\s+/', ' ', $m[1]);
            }
        }

        if (count($travellers) > 0) {
            $c->general()->travellers($travellers, true);
        }

        // misc

        if (preg_match("/(?:^[ ]*|[ ]{2})({$this->opt($this->t('BOOKING ID'))})[ ]*[:]+[ ]*([-A-Z\d]{4,})(?:[ ]{2}|$)/m", $text, $m)) {
            $c->general()->confirmation($m[2], $m[1]);
        }

        $bookingDetails = $this->re("/^[ ]*{$this->opt($this->t('BOOKING DETAILS'))}\n+([\s\S]+?)\n+^[ ]*{$this->opt($this->t('VOYAGES DETAIL'))}$/m", $text);
        $tablePos = [0];

        if (preg_match("/^(.{15,}[ ]{2}){$this->opt($this->t('SHIP'))}[ ]*:/m", $bookingDetails, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        $table = $this->splitCols($bookingDetails, $tablePos);
        $bookingDetailsText = implode("\n", $table);

        if (preg_match("/^[ ]*{$this->opt($this->t('SHIP'))}[ ]*[:]+\s*([\s\S]{2,}?)\n+[ ]*{$this->opt($this->t('EMBARK PORT'))}/m", $bookingDetailsText, $m)) {
            $c->details()->ship(preg_replace('/\s+/', ' ', $m[1]));
        }

        $voyagesDetail = $this->re("/^[ ]*{$this->opt($this->t('VOYAGES DETAIL'))}\n+([\s\S]+?)\n+^[ ]*{$this->opt($this->t('TRAVEL ADVISOR'))}$/m", $text);
        $tablePos = [0];

        if (preg_match("/^(.{15,}[ ]{2}){$this->opt($this->t('DEBARK DATE'))}[ ]*:/m", $voyagesDetail, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        $table = $this->splitCols($voyagesDetail, $tablePos);
        $voyagesDetailText = implode("\n", $table);

        $suite = preg_match("/^[ ]*{$this->opt($this->t('SUITE'))}[ ]*[:]+\s*([\s\S]{2,}?)\n+[ ]*{$this->opt($this->t('DEBARK PORT'))}/m", $voyagesDetailText, $m)
            ? preg_replace('/\s+/', ' ', $m[1]) : null;
        $c->details()->description($suite, false, true);

        // segments

        $portEmbark = preg_match("/^[ ]*{$this->opt($this->t('EMBARK PORT'))}[ ]*[:]+\s*([\s\S]{2,}?)\n+[ ]*{$this->opt($this->t('EMBARK DATE'))}/m", $voyagesDetailText, $m)
            ? preg_replace('/\s+/', ' ', $m[1]) : null;

        $portDebark = preg_match("/^[ ]*{$this->opt($this->t('DEBARK PORT'))}[ ]*[:]+\s*([\s\S]{2,}?)\n+[ ]*{$this->opt($this->t('DEBARK DATE'))}/m", $voyagesDetailText, $m)
            ? preg_replace('/\s+/', ' ', $m[1]) : null;

        $dateEmbark = preg_match("/^[ ]*{$this->opt($this->t('EMBARK DATE'))}[ ]*[:]+\s*([\s\S]{2,}?)\n+[ ]*(?:{$this->opt($this->t('SUITE'))}|{$this->opt($this->t('DEBARK PORT'))})/m", $voyagesDetailText, $m)
            ? strtotime(preg_replace('/\s+/', ' ', $m[1])) : null;

        $dateDebark = preg_match("/^[ ]*{$this->opt($this->t('DEBARK DATE'))}[ ]*[:]+\s*([\s\S]{2,})/m", $voyagesDetailText, $m)
            ? strtotime(preg_replace('/\s+/', ' ', $m[1])) : null;

        $preCruiseInfo = preg_match("/\n[ ]*{$this->opt($this->t('PRE-CRUISE INFORMATION'))}\n+([\s\S]+?)(?:\n\n|\n+[ ]*{$this->opt($this->t('Local Contact'))}\n)/", $text, $m)
            ? preg_replace('/\s+/', ' ', $m[1]) : null;

        $postCruiseInfo = preg_match("/\n[ ]*{$this->opt($this->t('POST-CRUISE INFORMATION'))}\n+([\s\S]+?)(?:\n\n|\n+[ ]*{$this->opt($this->t('Local Contact'))}\n)/", $text, $m)
            ? preg_replace('/\s+/', ' ', $m[1]) : null;

        $s = $c->addSegment();

        $timeEmbark = $this->re("/{$this->opt($this->t('Guests will be permitted to embark the vessel from approximately'))}\s+({$patterns['time']})/i", $preCruiseInfo);

        if ($timeEmbark) {
            $dateEmbark = strtotime($timeEmbark, $dateEmbark);
        }

        $s->setName($portEmbark)->setAboard($dateEmbark);

        $s = $c->addSegment();

        $timeDebark = $this->re("/{$this->opt($this->t('Guests will be permitted to disembark the vessel from approximately'))}\s+({$patterns['time']})/i", $postCruiseInfo);

        if ($timeDebark) {
            $dateDebark = strtotime($timeDebark, $dateDebark);
        }

        $s->setName($portDebark)->setAshore($dateDebark);
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['EMBARK PORT']) || empty($phrases['DEBARK DATE'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['EMBARK PORT']) !== false
                && $this->strposArray($text, $phrases['DEBARK DATE']) !== false
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
