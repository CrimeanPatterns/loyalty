<?php

namespace AwardWallet\Engine\flightcentre\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightChange2023 extends \TAccountChecker
{
    public $mailFiles = "";

    public $date;
    public $lang;
    public static $dictionary = [
        'en' => [
            'Booking reference -' => 'Booking reference -',
        ],
    ];

    private $detectFrom = "changes@alerts.flightcentre.com.au";
    private $detectSubject = [
        // en
        'Flight Change Notification - ',
        'Reminder - Urgent Flight Change Notification - ',
        'Urgent Flight Change Notification - ',
        'REMINDER - Flight Change Notification - ',
    ];
    private $detectBody = [
        'en' => [
            'Flight change notification',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]flightcentre\.com/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
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
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['.flightcentre.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Flight Centre Travel Group Limited'])}]")->length === 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        //html entity &shy; replace
        $this->http->SetEmailBody(str_replace("­", " ", $this->http->Response['body']));
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $this->date = strtotime($parser->getDate());
        $this->parseEmailHtml($email);

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Booking reference -"]) && $this->http->XPath->query("//*[{$this->contains($dict['Booking reference -'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference -'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*\W?([\dA-Z]{5,})\W?\s*$/u"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]",null, true,
                "/^\s*{$this->opt($this->t('Hi '))}\s*([A-Za-z\- \']+),\s*$/"))
        ;

        $timeXpath = "starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'd:dd') or starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd')";

        $xpath = "//*[count(*[normalize-space()]) = 3][*[normalize-space()][2]//tr[{$timeXpath}] and *[normalize-space()][3]//tr[{$timeXpath}]]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $col1 = implode("\n", $this->http->FindNodes("*[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})(?:\n|$)/u", $col1, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }
            $col2 = implode("\n", $this->http->FindNodes("*[normalize-space()][2]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s+(?<date>.+)/s", $col2, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']));
            }
            $col3 = implode("\n", $this->http->FindNodes("*[normalize-space()][3]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<name>.+?)\s*\(\s*(?<code>[A-Z]{3})\s*\)\s+(?<date>.+)/s", $col3, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']));
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = ' . print_r($date, true));
        $year = date('Y', $this->date);

        if (empty($date) || empty($this->date)) {
            return null;
        }
        $in = [
            // 5:50PM Fri, Dec 22
            '/^\s*(\d{1,2}:\d{2}(?:[ap]m)?)\s+([[:alpha:]]{2,})\s*[.,\s]\s*([[:alpha:]]{3,})\s+(\d{1,2})[.]?\s*$/iu',
        ];
        $out = [
            '$2, $4 $3 ' . $year . ', $1',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\w+,\s*\d+\s+([[:alpha:]]{3,})\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('$date = ' . print_r($date, true));

        if (preg_match("#^(?<week>[[:alpha:]]{2,}), (?<date>\d+ [[:alpha:]]{3,} .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
