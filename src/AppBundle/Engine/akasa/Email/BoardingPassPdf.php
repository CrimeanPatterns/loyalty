<?php

namespace AwardWallet\Engine\akasa\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "akasa/it-328013848.eml, akasa/it-690554377.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'pageSeparator' => ['Web Boarding Pass'],
            'from'          => ['From:', 'From :'],
            'to'            => ['To:', 'To :'],
            'confNumber'    => ['PNR:', 'PNR :'],
            'flight'        => ['Flight:', 'Flight :'],
            'seat'          => ['Seat:', 'Seat :'],
            'date'          => ['Date:', 'Date :'],
            'departure'     => ['Departure:', 'Departure :'],
            'bpEnd'         => ['Point to remember:', 'Point to remember :'],
            'depTerminal'   => ['Departure Terminal:', 'Departure Terminal :'],
        ],
    ];

    private $subjects = [
        'en' => ['Boarding Pass for PNR'],
    ];

    private $pdfPattern = '.*pdf';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@travel-akasaair.in') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Akasa Air') === false) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($textPdf, 'AkasaAir.com') === false) {
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
        $textPdfFull = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($textPdf)) {
                $textPdfFull .= "\n\n" . $textPdf;
            }
        }

        if (!$textPdfFull) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('BoardingPassPdf' . ucfirst($this->lang));

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // VARMA/AKSHAY
        ];

        $BPs = $this->splitText($textPdfFull, "/^([ ]*{$this->opt($this->t('pageSeparator'))}(?:[ ]{2}|$))/m", true);

        foreach ($BPs as $bpText) {
            // $bp = $email->add()->bpass(); // need airport codes!

            $bpText = preg_replace("/^(.+?)\n+[ ]*{$this->opt($this->t('bpEnd'))}(?:[ ]{2}|\n).+/s", '$1', $bpText);

            $tablePos = [0];

            if (preg_match("/^(.{50,} ){$this->opt($this->t('depTerminal'))}/", $bpText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($bpText, $tablePos);

            if (count($table) !== 2) {
                $this->logger->debug('Wrong main table!');
                $email->add()->flight();

                continue;
            }

            $conf = $this->re("/^[ ]*{$this->opt($this->t('confNumber'))}[: ]*([A-Z\d]{5,})$/m", $table[1]);

            foreach ($email->getItineraries() as $it) {
                if ($it->getType() === 'flight' && in_array($conf, array_column($it->getConfirmationNumbers(), 0)) === true) {
                    $f = $it;

                    break;
                }
            }

            if (!isset($f)) {
                $f = $email->add()->flight();

                if (preg_match("/^[ ]*({$this->opt($this->t('confNumber'))})[: ]*([A-Z\d]{5,})$/m", $table[1], $m)) {
                    $f->general()->confirmation($m[2], rtrim($m[1], ': '));
                }
            }

            $s = $f->addSegment();

            $traveller = $this->re("/^\s*{$this->opt($this->t('depTerminal'))}.*((?:\n+[ ]*{$patterns['travellerName']}){1,2}?(?:\s*\/\s*{$patterns['travellerName']}){1,2}?)\n+[ ]*{$this->opt($this->t('from'))}/u", $table[1]);
            $traveller = preg_replace('/\s+/', ' ', trim($traveller));
            $traveller = preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", '$2 $1', $traveller);

            if (empty($traveller) || !in_array($traveller, array_column($f->getTravellers(), 0))) {
                $f->general()
                    ->traveller($traveller, true);
            }

            $airportDep = $this->re("/^[ ]*{$this->opt($this->t('from'))}[: ]*((?:[ ]*.{2,}\n+){1,3}?)[ ]*{$this->opt($this->t('to'))}/m", $table[1]);
            $airportDep = preg_replace('/\s+/', ' ', trim($airportDep));

            if (preg_match($pattern = "/^(?<name>.{2,}?)\s*\(\s*(?<terminal>T[^)(]+)\s*\)$/", $airportDep, $m)) {
                $airportDep = $m['name'];
                $terminalDep = $m['terminal'];
            } else {
                $terminalDep = null;
            }

            $s->departure()->name($airportDep)->terminal($terminalDep, false, true);

            $airportArr = $this->re("/^[ ]*{$this->opt($this->t('to'))}[: ]*((?:[ ]*.{2,}\n+){1,3}?)[ ]*{$this->opt($this->t('confNumber'))}/m", $table[1]);
            $airportArr = preg_replace('/\s+/', ' ', trim($airportArr));

            if (preg_match($pattern, $airportArr, $m)) {
                $airportArr = $m['name'];
                $terminalArr = $m['terminal'];
            } else {
                $terminalArr = null;
            }

            $s->arrival()->name($airportArr)->terminal($terminalArr, false, true);

            if (preg_match("/^[ ]*({$this->opt($this->t('flight'))})[: ]*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)$/m", $table[1], $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $seat = $this->re("/^[ ]*{$this->opt($this->t('seat'))}[: ]*(\d+[A-Z])$/m", $table[1]);
            $s->extra()->seat($seat, false, true, $traveller);

            if (preg_match("/^[ ]*{$this->opt($this->t('date'))}[: ]*(?<date>.{6,}?)[ ]*{$this->opt($this->t('departure'))}[: ]*(?<time>\d{1,2}[: ]*\d{2})(?:[ ]*Hrs)?$/im", $table[0], $m)) {
                $s->departure()->date(strtotime($m['time'], strtotime($m['date'])));
                $s->arrival()->noDate();
            }

            if (!empty($s->getDepName()) && !empty($s->getArrName()) && !empty($s->getDepDate())) {
                $s->departure()->noCode();
                $s->arrival()->noCode();
            }

            foreach ($f->getSegments() as $key => $seg) {
                if ($seg->getId() !== $s->getId()) {
                    if (serialize(array_diff_key($seg->toArray(),
                            // ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                            ['seats' => [], 'assignedSeats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => [], 'assignedSeats' => []]))) {
                        if (!empty($s->getAssignedSeats())) {
                            foreach ($s->getAssignedSeats() as $seat) {
                                $seg->extra()
                                    ->seat($seat[0], false, false, $seat[1]);
                            }
                        } elseif (!empty($s->getSeats())) {
                            foreach ($s->getSeats() as $seat) {
                                $seg->extra()->seats(array_unique(array_merge($seg->getSeats(),
                                    $s->getSeats())));
                            }
                        }
                        $f->removeSegment($s);

                        break;
                    }
                }
            }
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

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['pageSeparator']) || empty($phrases['from'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['pageSeparator']) !== false
                && $this->strposArray($text, $phrases['from']) !== false
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
