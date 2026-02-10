<?php

namespace AwardWallet\Engine\flyplay\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class YourBoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "flyplay/it-647332790.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang = '';
    public $emailSubject;
    public $travellerStorage = [];
    public static $dictionary = [
        'en' => [
            //            'Confirmation' => 'Confirmation',
        ],
    ];

    private $detectFrom = "noreply@flyplay.com";
    private $detectSubject = [
        // en
        '[PLAY] Your boarding pass for flight',
    ];
    private $detectBody = [
        'en' => [
            'Boarding Pass',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]flyplay\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && stripos($headers["subject"], '[PLAY]') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->strposArray($text, [' OG']) !== false && $this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $textPdfFull = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($textPdf)) {
                $textPdfFull .= $textPdf . "\n\n";
            }
        }

        if (empty($textPdfFull)) {
            return $email;
        }

        $email->setType('YourBoardingPassPdf' . ucfirst($this->lang));

        $pdfDocuments = $this->splitText($textPdfFull, "/(^[ ]*{$this->opt($this->t('Boarding Pass'))}(?:[ ]{2}|\n))/m", true);
        $pdfDocumentsSorted = [];

        foreach ($pdfDocuments as $i => $text) {
            $dateDep = $this->parsePdfFlight($text, null, null, true);
            $pdfDocumentsSorted[(int) $dateDep . '.' . $i] = $text;
        }
        ksort($pdfDocumentsSorted);

        $this->emailSubject = $parser->getSubject();

        foreach ($pdfDocumentsSorted as $text) {
            $this->parsePdfDocument($email, $text);
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

    private function parsePdfFlight(string $text, ?Flight $f, ?FlightSegment $s, bool $dateDepOnly = false): ?int
    {
        $tablePos = [0];

        if (preg_match("/^(.* ){$this->opt($this->t('PASSENGER'))}/m", $text, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->createTable($text, $tablePos);

        if (count($table) !== 2) {
            $this->logger->debug('Wrong flight table!');

            return null;
        }

        $patterns = [
            'date' => '\b\d{1,2}\/\d{1,2}\/\d{4}\b', // 22/02/2024
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];

        if (preg_match("/\n.+[ ]+{$this->opt($this->t('DEPARTURE'))}[ ]{2,}{$this->opt($this->t('ARRIVAL'))}\n+.*[ ]{2}({$patterns['time']})[ ]{3,}({$patterns['time']})\n/", $table[0], $m)) {
            $timeDep = $m[1];
            $timeArr = $m[2];
        } else {
            $timeDep = $timeArr = null;
        }

        $date = strtotime($this->normalizeDate($this->re("/\n[ ]*{$this->opt($this->t('DEPARTURE'))}\b.*\n+[ ]*.*?·[ ]*({$patterns['date']})(?:[ ]{3}|\n)/", $table[1])));

        if ($date && $timeDep) {
            $dateDep = strtotime($timeDep, $date);

            if ($dateDepOnly) {
                return $dateDep;
            }

            $s->departure()->date($dateDep);
        }

        if ($date && $timeArr) {
            $s->arrival()->date(strtotime($timeArr, $date));
        }

        if (preg_match("/\n\s*{$this->opt($this->t('FLIGHT'))} #.*\n+ *(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+)\s+/", $table[0], $m)) {
            $s->airline()->name($m['al'])->number($m['fn']);
        }

        $passenger = preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", "$2 $1", $this->re("/\n\s*{$this->opt($this->t('PASSENGER'))}.*\n+(.+?)\d*\n/", $table[1]));

        if ($passenger && !in_array($passenger, $this->travellerStorage)) {
            $f->general()->traveller($passenger, true);
            $this->travellerStorage[] = $passenger;
        }

        if (preg_match("/\n[ ]*{$this->opt($this->t('FLIGHT'))} {2,}.+\n+ *(?<dCode>[A-Z]{3}) +- +(?<aCode>[A-Z]{3}) {2,}/", $table[1], $m)) {
            $s->departure()->code($m['dCode']);
            $s->arrival()->code($m['aCode']);
        }

        $s->extra()->seat($this->re("/ {2,}{$this->opt($this->t('SEAT'))}\n+.+ {3,}(\d{1,3}[A-Z])\n/", $table[0]));

        return null;
    }

    private function parsePdfDocument(Email $email, ?string $text = null): void
    {
        foreach ($email->getItineraries() as $it) {
            /** @var \AwardWallet\Schema\Parser\Common\Flight $f */
            $f = $it;
        }

        if (!isset($f)) {
            $f = $email->add()->flight();

            // General
            if (preg_match("/({$this->opt($this->t('Booking Ref'))})\s*:\s*([A-Z\d]{5,7})$/", $this->emailSubject, $m)) {
                $f->general()->confirmation($m[2], $m[1]);
            } else {
                $f->general()
                    ->noConfirmation();
            }
        }

        $s = $f->addSegment();

        $this->parsePdfFlight($text, $f, $s);

        $segments = $f->getSegments();

        foreach ($segments as $segment) {
            if ($segment->getId() !== $s->getId()) {
                if (serialize(array_diff_key($segment->toArray(),
                        ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                    if (!empty($s->getSeats())) {
                        $segment->extra()->seats(array_unique(array_merge($segment->getSeats(),
                            $s->getSeats())));
                    }
                    $f->removeSegment($s);

                    break;
                }
            }
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset($this->detectBody, $this->lang)) {
            return false;
        }

        foreach ($this->detectBody as $lang => $phrases) {
            if (!is_string($lang) || !is_array($phrases)) {
                continue;
            }

            if ($this->strposArray($text, $phrases) !== false) {
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

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    // additional methods

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
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

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function normalizeDate(?string $date): string
    {
        // $this->logger->debug('date begin = ' . print_r($date, true));
        $in = [
            '/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s*$/', // 21/05/2023
        ];
        $out = [
            '$1.$2.$3',
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('date end = ' . print_r($date, true));
        return $date;
    }

    private function opt($field, $delimiter = '/'): string
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
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
