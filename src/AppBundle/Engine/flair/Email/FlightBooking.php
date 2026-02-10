<?php

namespace AwardWallet\Engine\flair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightBooking extends \TAccountChecker
{
    public $mailFiles = "flair/it-186365261.eml";
    public $subjects = [
        'Your Flair Booking -',
    ];

    public $lang = 'en';
    public $year;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@flyflair.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Flair Airlines')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('see you soon'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('flight info'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flyflair\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Thanks for booking your trip with us!']/preceding::text()[normalize-space()][1]"), true)
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='reservation number']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6,})/"));

        $xpath = "//text()[normalize-space()='arrive']/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $segmentText = $this->http->FindSingleNode("./descendant::tr[normalize-space()][not(contains(normalize-space(), 'arrive'))][1]", $root);
            //$this->logger->debug($segmentText);
            //depart | october 22, 2022 departs 1:30 pm | arrives 3:30 pm YYZ toronto nonstop F8176 LAS las vegas
            if (preg_match("/^(?<duration>.+)\s+(?<depCode>[A-Z]{3})\s+(?<depTime>[\d\:]+)\s+(?<fName>[A-Z\d]{2})(?<fNumber>\d{2,4})\s+(?<arrCode>[A-Z]{3})\s+(?<arrTime>[\d\:]+)$/u", $segmentText, $m)) {
                $s->airline()
                    ->name($m['fName'])
                    ->number($m['fNumber']);

                $depDate = $this->http->FindSingleNode("./descendant::text()[normalize-space()][(contains(normalize-space(), '('))][1]", $root);

                $s->departure()
                    ->date($this->normalizeDate($depDate . ', ' . $m['depTime']))
                    ->code($m['depCode']);

                $arrDate = $this->http->FindSingleNode("./descendant::text()[normalize-space()][(contains(normalize-space(), '('))][2]", $root);

                $s->arrival()
                    ->date($this->normalizeDate($arrDate . ', ' . $m['arrTime']))
                    ->code($m['arrCode']);
            }
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='total']/following::text()[normalize-space()][1]/ancestor::*[1]");

        if (preg_match("/^(?<currency>\D)(?<total>[\d\.]+)$/us", $total, $m)) {
            $f->price()
                ->currency($m['currency'])
                ->total($m['total']);
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
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

    private function normalizeDate($str)
    {
        //$this->logger->debug($str);

        $year = date("Y", $this->date);
        $in = [
            "#^(\w+)\s*(\d+)\s*\((\w+)\)\,\s*([\d\:]+)$#u", //AUGUST 8 (SUNDAY), 20:35
        ];
        $out = [
            "$3, $2 $1 $year, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^(?<week>[\w\.]+), (?<date>\d+ \w+ .+|\d+-\d+-.+)#u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
