<?php

namespace AwardWallet\Engine\aviancataca\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPass2 extends \TAccountChecker
{
    public $mailFiles = "aviancataca/it-175330617.eml, aviancataca/it-96171143.eml";

    public $lang = 'en';
    public $pdfNamePattern = ".*\.pdf";
    public $files;

    public static $dictionary = [
        "en" => [
        ],
        "es" => [
            'Before your flight'                    => 'Antes de tu vuelo',
            'YOUR SIZE INCLUDE'                     => 'TU TALLA INCLUYE',
            'Baggage information'                   => 'Información del equipaje',
            'Download Avianca App for free'         => 'Descarga Avianca App gratis',
            'Observations'                          => 'Observaciones',
            'Check the gate on the airport screens' => 'Verifica la sala en las pantallas del aeropuerto',
            'Operated by:'                          => 'Operado por:',
            'Booking:'                              => 'Reserva:',
            'E-ticket:'                             => 'E-ticket:',
            'Frequent flyer:'                       => 'Viajero frecuente:',
        ],
    ];

    protected $detectLang = [
        "es" => ['Antes de tu vuelo'],
        "en" => ['Before your flight'],
    ];
    private $date;

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($text);

            if (strpos($text, 'Avianca') !== false && strpos($text, $this->t('Before your flight')) !== false && strpos($text, $this->t('YOUR SIZE INCLUDE')) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParseFlight(Email $email, $text): void
    {
        $patterns = [
            'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?|[ ]*noon|[ ]*午[前後])?', // 4:19PM    |    2:00 p. m.    |    3pm    |    12 noon    |    3:10 午後
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();
        $b = $email->add()->bpass();

        if (preg_match("/^(.+?)\n[ ]*{$this->opt($this->t('Baggage information'))}[ ]+{$this->opt($this->t('Download Avianca App for free'))}/s", $text, $m)) {
            $text = $m[1];
        }

        $tablePos = [0];

        if (preg_match("/^(.+ ){$this->opt($this->t('Observations'))}$/m", $text, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($text, $tablePos);

        if (count($table) === 2) {
            $text = $table[0];
        }

        $topText = preg_match("/^\n*(.+?)\n+[ ]*{$this->opt($this->t('Check the gate on the airport screens'))}(?:[ ]{2}|\n|$)/s", $text, $m) ? $m[1] : null;
        $middleText = preg_match("/(?:^|\n)[ ]*{$this->opt($this->t('Check the gate on the airport screens'))}\n(.+?)\n+[ ]*{$this->opt($this->t('YOUR SIZE INCLUDE'))}(?:[ ]{2}|\n|$)/s", $text, $m) ? $m[1] : null;
        $bottomText = preg_match("/(?:^|\n)[ ]*{$this->opt($this->t('YOUR SIZE INCLUDE'))}\n+(.+?)\s*$/s", $text, $m) ? $m[1] : null;
        $tablePos = [0];

        if (preg_match("/^(.+ ){$this->opt($this->t('Operated by:'))}/m", $bottomText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($bottomText, $tablePos);

        if (count($table) === 2) {
            $bottomText = $table[0] . "\n\n" . $table[1];
        }

        $traveller = $this->re("/(?:^\s*|{$this->opt($this->t('Before your flight'))}\n+[ ]*)({$patterns['travellerName']})\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+(?:[ ]{2}|\n)/u", $topText);
        $confirmation = $this->re("/{$this->opt($this->t('Booking:'))}\s*([A-Z\d]{5,})\n/", $bottomText);
        $f->general()
            ->traveller($traveller, true)
            ->confirmation($confirmation)
        ;
        $b->setTraveller($traveller);
        $b->setRecordLocator($confirmation);

        $ticket = $this->re("/{$this->opt($this->t('E-ticket:'))}\s*({$patterns['eTicket']})(?:[ ]{2}|\n|$)/", $bottomText);

        if (!empty($ticket)) {
            $f->issued()
                ->ticket($ticket, false);
        }

        $s = $f->addSegment();

        $s->airline()
            ->name($this->re("/\s([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+(?:[ ]{2}|\n)/", $topText))
            ->number($this->re("/\s(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)(?:[ ]{2}|\n)/", $topText))
        ;

        $operator = preg_replace('/\s+/', ' ', $this->re("/{$this->opt($this->t('Operated by:'))}\s*([^:]+?)\s+(?:Sold as:|Status:)/", $bottomText));

        if (!empty($operator)) {
            $s->airline()
                ->operator($operator);
        }

        $b->setFlightNumber($s->getAirlineName() . ' ' . $s->getFlightNumber());

        if (preg_match("/(?:^|\n)[ ]*(?<depCode>[A-Z]{3})\s.+\s(?<depDate>\w+,\s*\d+\s*\w+\s*\|\s*{$patterns['time']})\n+(?<depName>.+)\n+[ ]*(?<arrCode>[A-Z]{3})\s.+\s(?<arrDate>\w+,\s*\d+\s*\w+\s*\|\s*{$patterns['time']})(?:\s*[-+]\d)?\n+(?<arrName>.+)/su", $middleText, $m)) {
            $s->departure()
                ->code($m['depCode'])
                ->date($this->normalizeDate($m['depDate']))
            ;

            if (preg_match("/,\s*{$this->opt($this->t('Terminal'))}\s+([-A-z\d\s]+?)(?:[ ]{2}|$)/is", $m['depName'], $matches)) {
                $s->departure()->terminal(preg_replace('/\s+/', ' ', $matches[1]));
            }

            $s->arrival()
                ->code($m['arrCode'])
                ->date($this->normalizeDate($m['arrDate']))
            ;

            if (preg_match("/,\s*{$this->opt($this->t('Terminal'))}\s+([-A-z\d\s]+?)(?:[ ]{2}|$)/is", $m['arrName'], $matches)) {
                $s->arrival()->terminal(preg_replace('/\s+/', ' ', $matches[1]));
            }
        }
        $b->setDepCode($s->getDepCode());
        $b->setDepDate($s->getDepDate());

        $seat = $this->re("/\n[ ]*\d{2}:\d{2}[ ]{2,}\D+[ ]{2,}(\d+[A-Z])$/", $topText);

        if (!empty($seat)) {
            $s->extra()
                ->seat($seat);
        }

        if ($s->getAirlineName() && preg_match("/{$this->opt($this->t('Frequent flyer:'))}\s*(?:" . $s->getAirlineName() . "-)?([A-Z\d]{5,})(?:$|\n)/", $bottomText, $m)) {
            $f->program()->account($m[1], false);
        }

        $b->setAttachmentName($this->files);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $header = $parser->getAttachmentHeader($pdf, 'Content-Type');
            $this->files = $this->re('/name=["\']*(.+\.pdf)[\'"]*/i', $header);
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($text);

            if (strpos($text, 'Avianca') !== false && strpos($text, $this->t('Before your flight')) !== false && strpos($text, $this->t('YOUR SIZE INCLUDE')) !== false) {
                $flights = $this->splitText($text, "/^([ ]*(?:.+[ ]{2})?{$this->t('Before your flight')})$/m", true);

                foreach ($flights as $fText) {
                    $this->ParseFlight($email, $fText);
                }
            }
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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
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

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '#^\w+\,\s*(\d+)\s*(\w+)\s*\|\s*([\d\:]+)$#u', //Mon, 07 Jun |19:57
        ];
        $out = [
            "$1 $2 $year, $3",
        ];
        $str = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignLang($text)
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
}
