<?php

namespace AwardWallet\Engine\flightcentre\Email;

use AwardWallet\Schema\Parser\Email\Email;

class JetstarPdf extends \TAccountChecker
{
    public $mailFiles = "flightcentre/it-27816446.eml, flightcentre/it-27816501.eml";

    public $reBody = [
        'en'  => ['This is not a boarding pass', 'Your flight itinerary'],
        'en2' => ['Jetstar Flight Itinerary for', 'Your flight itinerary'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [],
    ];
    private $code;
    private $bodies = [
        'flightcentre' => [
            'flightcentre.com.au',
            'Flight Centre Travel',
        ],
        'jetstar' => [
            'Jetstar',
        ],
    ];
    private static $headers = [
        'flightcentre' => [
            'from' => ['flightcentre.com.au'],
            'subj' => [],
        ],
        'jetstar' => [
            'from' => ['jetstar.com'],
            'subj' => [],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language');

                        continue;
                    } else {
                        if (null !== ($this->code = $this->getProviderByText($text))) {
                            $code = $this->code;

                            if (!$this->parseEmailPdf($text, $email)) {
                                return null;
                            }
                        }
                    }
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        if (!isset($code)) {
            $code = $this->getProvider($parser);
        }

        if (isset($code)) {
            $email->setProviderCode($code);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (null !== ($code = $this->getProviderByText($text))) {
                if ($this->assignLang($text)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        return $this->getProviderByText($this->http->Response['body']);
    }

    private function getProviderByText($text)
    {
        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (strpos($text, $search) !== false) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $textPDF = preg_replace("/^ *\d+\/\d+\/\d+ [^\n]+ \d+\/\d+\/\d+ *$/m", '', $textPDF);
        $textPDF = preg_replace("/^ *https?:\/\/[^\n]+ \d+\/\d+ *$/m", '', $textPDF);

        $infoBlock = $this->re("/( +{$this->opt($this->t('Booking reference'))}.+?){$this->opt($this->t('Booking Contact Details'))}/s",
            $textPDF);

        if (empty($infoBlock)) {
            $this->logger->debug('other format');

            return false;
        }
        $table = $this->splitCols($infoBlock, $this->colsPos($infoBlock));

        if (count($table) !== 2) {
            $this->logger->debug('other format');

            return false;
        }

        $r = $email->add()->flight();
        $r->general()
            ->date(strtotime($this->re("/{$this->opt($this->t('Itinerary issue date'))}:\s+(.+)/", $table[0])))
            ->confirmation($this->re("/{$this->opt($this->t('Booking reference'))}\s+([A-Z\d]+)/", $table[1]));

        $itBlock = strstr($textPDF, $this->t('Passenger:'), true);
        $itBlock = $this->re("/(Date +Flight number +[^\n]+.+)/s", $itBlock);
        $segments = $this->splitter("/(.+? \d{4} {2,}(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d+)/", $itBlock);

        if (count($segments) > 1) {
            $this->logger->debug('need to check format segments-seats-pax'); //TODO: need more examples

            return false;
        }

        $s = $r->addSegment();
        $paxBlock = strstr($textPDF, 'Times are local times at the relevant airport', true);
        $paxBlock = $this->re("/{$this->opt($this->t('Passenger:'))}[^\n]+\n(.+)/s", $paxBlock);

        if (preg_match_all("/^([A-Z ]+)\s+\((.+?)\).+?{$this->opt($this->t('Meal'))} +([^\n]+)/sm", $paxBlock, $m)) {
            $r->general()
                ->travellers($m[1]);
            $s->extra()
                ->seats(array_filter(array_map("trim", $m[2]), function ($s) {
                    return preg_match("/^\d+[A-Z]$/", $s);
                }))
                ->meal(preg_replace("/[^\w\s,\.\-\|]/", '', implode('|', array_unique(array_map("trim", $m[3])))));
        }

        if (preg_match_all("/Frequent\s+Flyer\s+number\s+([A-Z\d]+)/", $paxBlock, $m)) {
            $r->program()->accounts($m[1], false);
        }

        foreach ($segments as $segment) {
//            $s = $r->addSegment();
            $table = $this->splitCols($segment, $this->colsPos($segment));

            if (count($table) !== 4) {
                $this->logger->debug('other format segment');

                return false;
            }

            if (preg_match("/(?<name>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<num>\d+)\s+(?<aircraft>.+)/", $table[1], $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['num']);
                $s->extra()->aircraft($m['aircraft']);
            }
            $duration = $this->re("/{$this->opt($this->t('Flight duration'))}:\s+(.+)/", $table[1]);

            if (!empty($duration)) {
                $s->extra()->duration($duration);
            }

            if (preg_match("/(?<name>[^\n]+)\s+(?<date>[^\n]+)\s+.+? \/ (?<time>\d+:\d+)\s+(?<terminal>.+)/s",
                $table[2], $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m['name'])
                    ->date(strtotime($m['date'] . ' ' . $m['time']));

                if (preg_match("/(.+?)[­ ]{2,}T(\w+) Domestic/", $m['terminal'], $v)) {
                    $s->departure()
                        ->name($v[1])
                        ->terminal($v[2]);
                }
            }

            if (preg_match("/(?<name>[^\n]+)\s+(?<date>[^\n]+)\s+.+? \/ (?<time>\d+:\d+)\s+(?<terminal>.+)/s",
                $table[3], $m)) {
                $s->arrival()
                    ->noCode()
                    ->name($m['name'])
                    ->date(strtotime($m['date'] . ' ' . $m['time']));

                if (preg_match("/(.+?)[­ ]{2,}T(\w+) Domestic/", $m['terminal'], $v)) {
                    $s->arrival()
                        ->name($v[1])
                        ->terminal($v[2]);
                }
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
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

    private function rowColsPos($row)
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

    private function colsPos($table, $correct = 5)
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
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }
}
