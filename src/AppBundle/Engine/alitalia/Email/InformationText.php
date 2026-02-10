<?php

namespace AwardWallet\Engine\alitalia\Email;

use AwardWallet\Schema\Parser\Email\Email;

class InformationText extends \TAccountChecker
{
    public $mailFiles = "alitalia/it-33696279.eml, alitalia/it-33944031.eml";
    //33696279 , 33944031

    public $reFrom = ["@alitalia."];
    public $reBody = [
        'en'  => ['The flight', 'is now expected to leave'],
        'en2' => ['flight', 'has been rescheduled'],
    ];
    public $reSubject = [
        'Information about your booking reference',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'pnrRegSubject' => '#Information about your booking reference:? ([A-Z\d]{5,})$#',
        ],
    ];
    private $keywordProv = 'Alitalia';
    private $subject;
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $this->date = strtotime($parser->getDate());

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'www.alitalia.com')]")->length > 0) {
            return $this->assignLang();
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

    private function parseEmail(Email $email)
    {
        // The flight AZ 1428, on 13 Feb 2019, is now expected to leave Turin, Italy TRN on 13 Feb 2019, at 15:30 and arrives in Rome, Italy FCO at 16:42
        $text = $this->http->FindSingleNode("//text()[({$this->starts($this->t('The flight'))}) and ({$this->contains($this->t('is now expected to leave'))})]");

        if (preg_match("#{$this->opt($this->t('The flight'))} (?<airline>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<flight>\d+), on .+?, {$this->opt($this->t('is now expected to leave'))} (?<depName>.+?) (?<depCode>[A-Z]{3}) on (?<depDate>.+?) and arrives in (?<arrName>.+?) (?<arrCode>[A-Z]{3}) (?<arrDate>.+?)\s*\.#",
            $text, $m)) {
            $r = $email->add()->flight();
            $r->general()
                ->confirmation($this->re($this->t('pnrRegSubject'), $this->subject))
                ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, false, "#{$this->opt($this->t('Dear '))}\s*(.+?),#"));
            $s = $r->addSegment();
            $s->airline()
                ->name($m['airline'])
                ->number($m['flight']);

            $date = strtotime($this->normalizeDate($m['depDate']));
            $s->departure()
                ->name($m['depName'])
                ->code($m['depCode'])
                ->date($date);
            $s->arrival()
                ->name($m['arrName'])
                ->code($m['arrCode'])
                ->date(strtotime($this->normalizeDate($m['arrDate']), $date));
        }
        // We would like to inform you that flight AZ 00165 on Apr 19 has been rescheduled
        // The flight is now expected to leave BRUSSELS BRU at 18:00 and arrives in ROMA FCO at 20:05.
        elseif (preg_match("#{$this->opt($this->t('The flight'))} {$this->opt($this->t('is now expected to leave'))} (?<depName>.+?) (?<depCode>[A-Z]{3}) (?<depDate>.+?) and arrives in (?<arrName>.+?) (?<arrCode>[A-Z]{3}) (?<arrDate>.+?)\s*\.#",
            $text, $m)) {
            $text2 = $this->http->FindSingleNode("//text()[({$this->contains($this->t('flight'))}) and ({$this->contains($this->t('has been rescheduled'))})]");

            if (preg_match("#{$this->opt($this->t('flight'))} (?<airline>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<flight>\d+) on (?<depDate>.+?) {$this->opt($this->t('has been rescheduled'))}#",
                $text2, $mm)) {
                $r = $email->add()->flight();
                $r->general()
                    ->confirmation($this->re($this->t('pnrRegSubject'), $this->subject))
                    ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, false, "#{$this->opt($this->t('Dear '))}\s*(.+?),#"));
                $s = $r->addSegment();
                $s->airline()
                    ->name($mm['airline'])
                    ->number($mm['flight']);

                $date = strtotime($this->normalizeDate($mm['depDate']));
                $s->departure()
                    ->name($m['depName'])
                    ->code($m['depCode'])
                    ->date(strtotime($this->normalizeDate($m['depDate']), $date));
                $s->arrival()
                    ->name($m['arrName'])
                    ->code($m['arrCode'])
                    ->date(strtotime($this->normalizeDate($m['arrDate']), $date));
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //21 Feb 2019, at 14:15
            '#^(\d+)\s+(\w+)\s+(\d{4}),\s+at\s+(\d+:\d+)$#u',
            //at 14:15
            '#^\s*at\s+(\d+:\d+)$#u',
            //Apr 19
            '#^(\w+)\s+(\d+)$#',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$1',
            '$2 $1 ' . $year,
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
