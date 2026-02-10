<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2592383 extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-2592383.eml, aeroplan/it-33854598.eml, aeroplan/it-34045201.eml, aeroplan/it-4139448.eml, aeroplan/it-4199498.eml, aeroplan/it-4332144.eml, aeroplan/it-6536002.eml";
    public $reBodyAlt = [
        "en"  => "IT'S TIME TO CHECK IN ONLINE",
        "en2" => "FLIGHT BOOKING CONFIRMATION",
        "fr"  => "IL EST MAINTENANT TEMPS DE COMPLÉTER VOTRE ENREGISTREMENT EN LIGNE",
    ];

    public $reSubject = [
        '#Check in online now#',
        '#Enregistrez-vous en ligne maintenant#',
        '#Check in for (?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d+\b#',
        '#Enregistrez-vous (?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d+\b#',
        '#Booking - Important Reminder#',
    ];
    public $reFrom = ['aircanada.'];
    public $keywordProv = 'Air Canada';
    public $lang = '';
    public static $dict = [
        "en" => [
            "Booking Reference" => "Booking Reference",
            "Flight"            => ["FLIGHT ", "Flight"],
        ],
        "fr" => [
            "Booking Reference"    => "Numéro de réservation",
            "Flight"               => ["VOL "],
            "PASSENGERS"           => "PASSAGERS",
            "Departure"            => "Départ",
            "Departing at"         => "Départ à",
            "Arriving at"          => "Arrivée à",
            "YOU MAY ALSO WISH TO" => "VOUS POUVEZ ÉGALEMENT",
        ],
    ];
    private $date;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'aircanada.com')]")->length > 0) {
            foreach ($this->reBodyAlt as $alt) {
                if ($this->http->XPath->query("//img[@alt=\"" . $alt . "\"]")->length > 0) {
                    return $this->assignLang();
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
                if (($fromProv && preg_match($reSubject, $headers["subject"]) > 0)
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
        $r = $email->add()->flight();

        $paxText = implode("\n",
            $this->http->FindNodes("//text()[{$this->contains($this->t('PASSENGERS'))}]/ancestor::table[{$this->contains($this->t('YOU MAY ALSO WISH TO'))}]/descendant::text()[normalize-space()!='']"));
        $paxText = $this->re("#\n[ ]*{$this->opt($this->t('PASSENGERS'))}[^\n]*\n(.+?){$this->opt($this->t('YOU MAY ALSO WISH TO'))}#s",
            $paxText);

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference'))}]/following::text()[normalize-space()!=''][1]"),
                $this->t('Booking Reference'));

        if ($pax = array_filter(array_map("trim", explode("\n", $paxText)))) {
            $r->general()->travellers($pax, true);
        }

        $date = $this->normalizeDate($this->http->FindSingleNode("//text()[({$this->starts($this->t('Departure'))}) and not({$this->contains($this->t('Departing at'))})]/following::text()[normalize-space()!=''][1]",
            null, false, "#(.+? \d{4})#"));

        if ($date) {
            $this->date = $date;
        }

        $xpath = "//td[{$this->contains($this->t('Flight'))}]/following-sibling::td[{$this->contains($this->t('Departing at'))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $r->addSegment();

            $node = $this->http->FindSingleNode("./td[normalize-space(.)!=''][1]", $root);

            if (preg_match("#{$this->opt($this->t('Flight'))}\s*(?<airline>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<flight>\d+)#",
                $node, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flight']);
            }
            $node = implode(' ',
                $this->http->FindNodes("./td[normalize-space(.)!=''][2]/descendant::text()[string-length(normalize-space())>2]",
                    $root));

            if (preg_match("#\b(?<code>[A-Z]{3})\b\s*(?<name>.+)\s*{$this->opt($this->t('Departing at'))}[\s:]+(?<date>.*?)\s*(?<time>(?i)\d+:\d+(?:\s*[ap]m)?)#",
                $node, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name($m['name']);

                if (isset($m['date']) && !empty($m['date'])) {
                    $s->departure()->date(strtotime($m['time'], $this->normalizeDate($m['date'])));
                } else {
                    $s->departure()->date(strtotime($m['time'], $date));
                }
            }

            $node = implode(' ',
                $this->http->FindNodes("./td[normalize-space(.)!=''][3]/descendant::text()[string-length(normalize-space())>2]",
                    $root));

            if (preg_match("#\b(?<code>[A-Z]{3})\b\s*(?<name>.+)\s*{$this->opt($this->t('Arriving at'))}[\s:]+(?<date>.*?)\s*(?<time>(?i)\d+:\d+(?:\s*[ap]m)?)#",
                $node, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name($m['name']);

                if (isset($m['date']) && !empty($m['date'])) {
                    $s->arrival()->date(strtotime($m['time'], $this->normalizeDate($m['date'])));
                } else {
                    $s->arrival()->date(strtotime($m['time'], $date));
                }
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Sun Mar 3
            '#^\s*([^\d\s]+)\s+(\w+)\s+(\d{1,2})\s*$#u',
            //26 février 2019
            '#^\s*(\d+)\s+(\w+)\s+(\d{4})\s*$#u',
            //Jeudi le 18 décembre 2014
            '#^\s*\D+?\s+(\d+)\s+(\w+)\s+(\d{4})\s*$#u',
            //Thursday, August 6, 2015
            '#^\s*([\-\w]+),\s+(\w+)\s+(\d+),\s+(\d{4})\s*$#u',
            //February 18, 2019
            '#^\s*(\w+)\s+(\d{1,2}),\s+(\d{4})\s*$#u',
        ];
        $out = [
            '$3 $2 ' . $year,
            '$1 $2 $3',
            '$1 $2 $3',
            '$3 $2 $4',
            '$2 $1 $3',
        ];
        $outWeek = [
            '$1',
            '',
            '',
            '',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = preg_replace($in, $out, $date);
            $str = strtotime($this->dateStringToEnglish($str));
        }

        return $str;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Flight"], $words["Booking Reference"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Flight'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Booking Reference'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
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

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }
}
