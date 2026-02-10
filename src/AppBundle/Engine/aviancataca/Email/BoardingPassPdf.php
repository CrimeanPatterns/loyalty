<?php

namespace AwardWallet\Engine\aviancataca\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPassPdf extends \TAccountChecker
{
    public $mailFiles = "aviancataca/it-49085712.eml, aviancataca/it-68656841.eml";

    public $reFrom = ["@avianca.com"];
    public $reBody = [
        'en' => ['www.avianca.com', 'BOARDING PASS'],
        'es' => ['www.avianca.com', 'PASE DE ABORDAR'],
    ];
    public $reBodyHtml = [
        'en' => ['Thank you for using our Mobile Check In service'],
        'es' => ['Gracias por usar nuestro servicio de Mobile Check In'],
    ];
    public $reSubject = [
        'Tu pase de abordar Avianca',
        'Your Avianca Boarding Pass',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'FLIGHT'             => 'FLIGHT',
            'TRAVEL INFORMATION' => 'TRAVEL INFORMATION',
            //            "Arrival time:" => '',
        ],
        'es' => [
            'FLIGHT'             => 'FLIGHT',
            'TRAVEL INFORMATION' => 'TRAVEL INFORMATION',
            //            "Arrival time:" => '',
            "Arrival time:" => 'Hora de llegada:',
        ],
    ];
    private $keywordProv = 'Avianca';
    private $date;
    private $parser;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parser = $parser;
        $this->date = strtotime($parser->getDate());

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->detectBody($text) && $this->assignLang($text)) {
                        $this->parseEmailPdf($text, $email);
                    }
                }
            }
        }

        if (empty($pdfs) && !empty($this->http->FindSingleNode("//a[contains(@href, 'https://checkin.si.amadeus.net/')]"))) {
            foreach ($this->reBodyHtml as $lang => $rBody) {
                if (!empty($this->http->FindSingleNode("//text()[" . $this->contains($rBody) . "]"))) {
                    $this->lang = $lang;
                    $this->parseEmailHtml($email);

                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, $this->keywordProv) !== false)
                && $this->detectBody($text)
                && $this->assignLang($text)
            ) {
                return true;
            }
        }

        if (empty($pdfs) && !empty($this->http->FindSingleNode("//a[contains(@href, 'https://checkin.si.amadeus.net/')]"))) {
            foreach ($this->reBodyHtml as $lang => $rBody) {
                if (!empty($this->http->FindSingleNode("//text()[" . $this->contains($rBody) . "]"))) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
                ) {
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

    private function parseEmailPdf($textPDF, Email $email)
    {
        $reservations = $this->splitter("/([^\n*\/[ ]*{$this->t('BOARDING PASS')}[ ]*)/", $textPDF);

        foreach ($reservations as $reservation) {
            $r = $email->add()->flight();
            $r->general()->noConfirmation();

            if (null !== ($str = $this->strstrArr($reservation, $this->t('TRAVEL INFORMATION'), true))) {
                $reservation = $str;
            }

            if (preg_match("/(.+?)\n(([^\n]*[ ]){$this->t('AT GATE')}[ ]+{$this->t('SEAT')}[ ]+{$this->t('CABIN')}.+)/s",
                $reservation, $m)) {
                $pos = mb_strlen($m[3]) - 1;
                $header = $m[1];
                $table = $this->splitCols($m[2], [0, $pos]);

                $this->logger->debug($header);
                $this->logger->debug(var_export($table, true));
                $s = $r->addSegment();

                if (preg_match("/\/[ ]*{$this->t('FLIGHT')}[ ]{2,}.+\s+(?<airline>[A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(?<flight>\d+)[ ]{3,}.+?(?:[ ]{3,}(?<seat>\d+[A-z]))?[ ]*\n/",
                    $header, $m)) {
                    $s->airline()
                        ->name($m['airline'])
                        ->number($m['flight']);
                    $flight = $m['airline'] . $m['flight'];
                    $arrTime = $this->http->FindSingleNode("//text()[starts-with(translate(normalize-space(),' ',''),'{$flight}')]/following::text()[normalize-space()!=''][3][{$this->eq($this->t('Arrival time:'))}]/following::text()[normalize-space()!=''][1]",
                        null, "/^\d+:\d+(?:[ap]m)?$/i");

                    if (isset($m['seat'])) {
                        $s->extra()->seat($m['seat']);
                    }
                }

                if (preg_match("/\/[ ]*{$this->t('NAME')}[ ]{2,}([\w\/ ]+?)(?:[ ]{3,}|\n)/", $table[0], $m)) {
                    $r->general()->traveller($m[1]);
                }

                if (preg_match("/\n(?<acc>.+?)[ ]{3,}.+\/[ ]*{$this->t('BOOKING')}\b/", $table[0], $m)) {
                    $r->program()->account($m['acc'], false);
                }

                if (preg_match("/\n{$this->t('TKT')}[ ]+(\d{7,})/", $table[0], $m)) {
                    $r->issued()->ticket($m[1], false);
                }

                if (preg_match("/\/[ ]*{$this->t('FROM')}[ ]+(?<name>.+?)[ ]*\/[ ]*(?<code>[A-Z]{3})[ ]+.*\/[ ]*{$this->t('CABIN')}[ ]+(?<cabin>.+)\s+.+\/[ ]*TERMINAL:\s+(?<term>.+)/",
                    $table[0], $m)) {
                    $s->departure()
                        ->name($m['name'])
                        ->code($m['code']);

                    if (!preg_match("/.+\/[ ]*{$this->opt('TO')}/", $m['term'])) {
                        $s->departure()->terminal($m['term']);
                    }

                    if (preg_match("/^[A-Z]{1,2}$/", $m['cabin'])) {
                        $s->extra()->bookingCode($m['cabin']);
                    } else {
                        $s->extra()->cabin($m['cabin']);
                    }
                }

                if (preg_match("/\/[ ]*{$this->t('TO')}[ ]+(?<name>.+?)[ ]*\/[ ]*(?<code>[A-Z]{3})[ ]+.*\/[ ]*{$this->t('DATE')}[ ]+(?<date>.+)\s+.+\/[ ]*{$this->t('DEPARTURE')}\s+(?<time>\d+:\d+)[ ]{2,}/",
                    $table[0], $m)) {
                    $s->arrival()
                        ->name($m['name'])
                        ->code($m['code']);
                    $date = $this->normalizeDate($m['date']);
                    $s->departure()->date(strtotime($m['time'], $date));

                    if (isset($arrTime) && !empty($arrTime)) {
                        $s->arrival()->date(strtotime($arrTime, $date));
                    } else {
                        $s->arrival()->noDate();
                    }
                }
            } else {
                $this->logger->debug('other format');

                return false;
            }
        }

        return true;
    }

    private function parseEmailHtml(Email $email)
    {
        $text = implode("\n", $this->http->FindNodes("//a[contains(@href, 'amadeus')]/following::text()[following::*[" . $this->contains($this->t("Arrival time:")) . "] or preceding::*[1][" . $this->contains($this->t("Arrival time:")) . "] or " . $this->contains($this->t("Arrival time:")) . "]"));

        $segments = $this->splitter("/(\n\s*" . $this->opt($this->t("Arrival time:")) . "\s*.+)/", $text, false);

        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation();

        foreach ($segments as $sText) {
            if (preg_match("/(?:^|\n)\s*([[:alpha:]\- ]+)\s*- \w+/u", $sText, $m)) {
                $travellers[] = $m[1];
            }

            $s = $f->addSegment();

            if (preg_match("/(?:^|\n)\s*([A-Z\d][A-Z]|[A-Z][A-Z\d]) (\d{1,5}) - (\S.+)/u", $sText, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
                $s->departure()
                    ->noCode()
                    ->noDate()
                    ->name($m[3])
                ;
                $s->arrival()
                    ->noCode()
                    ->noDate()
                ;
            }
        }

        if (!empty($travellers)) {
            $f->general()
                ->travellers(array_unique($travellers), true);
        }

        return true;
    }

    private function normalizeDate($date)
    {
        if (preg_match('/^(\d+)\s+(\w+)$/u', $date)) {
            return EmailDateHelper::calculateDateRelative($date, $this, $this->parser);
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["FLIGHT"], $words["TRAVEL INFORMATION"])) {
                if ($this->striposArr($body, $words["FLIGHT"]) && $this->striposArr($body,
                        $words["TRAVEL INFORMATION"])
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function splitter($regular, $text, $deleteFirst = true)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($deleteFirst == true) {
            array_shift($array);
        }

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function striposArr($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function strstrArr(string $haystack, $needle, bool $before_needle = false): ?string
    {
        $needles = (array) $needle;

        foreach ($needles as $needle) {
            $str = strstr($haystack, $needle, $before_needle);

            if (!empty($str)) {
                return $str;
            }
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'starts-with(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }
}
