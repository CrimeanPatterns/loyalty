<?php

namespace AwardWallet\Engine\airasia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightReminder2018 extends \TAccountChecker
{
    public $mailFiles = "airasia/it-15314568.eml, airasia/it-16189874.eml";

    public $reFrom = "@airasia.com";
    public $reBody = [
        'en' => ['Airline PNR', 'This is to remind you that your flight'],
    ];
    public $reSubject = [
        'AirAsia - Flight Reminder',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Depart' => ['Depart', 'Departure'],
            'Arrive' => ['Arrive', 'Arrival'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'airasia.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Airline PNR'))}]/ancestor::td[1]/following-sibling::td[1]"));
        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('This is to remind you that your flight'))}]");

        if (preg_match("#{$this->opt($this->t('This is to remind you that your flight'))}\s+([A-Z\d]{2})[\s\-]+(\d+).*{$this->opt($this->t('from'))}\s+(.*?)\s*\(([A-Z]{3})\)\s*{$this->opt($this->t('to'))}\s+(.*?)\s*\(([A-Z]{3})\)\s*{$this->t('on')}\s+(\d+.+?\d+)#",
            $node, $m)) {
            $date = $this->normalizeDate($m[7]);
            $s = $f->addSegment();
            $s->airline()
                ->name($m[1])
                ->number($m[2]);

            if (isset($m[3]) && !empty($m[3])) {
                $s->departure()
                    ->name($m[3]);
            }
            $s->departure()
                ->code($m[4])
                ->date(strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Depart'))}]/ancestor::td[1]/following-sibling::td[1]"),
                    $date));

            if (isset($m[5]) && !empty($m[5])) {
                $s->arrival()
                    ->name($m[5]);
            }
            $s->arrival()
                ->code($m[6])
                ->date(strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrive'))}]/ancestor::td[1]/following-sibling::td[1]"),
                    $date));

            return true;
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $in = [
            //2-Apr-18
            '#^(\d+)\-(\w+)\-(\d{2})$#u',
        ];
        $out = [
            '$1 $2 20$3 ',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
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
}
