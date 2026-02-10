<?php

namespace AwardWallet\Engine\delta\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightChanged extends \TAccountChecker
{
    public $mailFiles = "delta/it-110325586.eml";
    public $subjects = [
        '/Update: Your Flight Schedule Has Changed/',
    ];

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@g.delta.com') !== false) {
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
        if ($this->http->XPath->query("//a[contains(@href, 'delta.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Your New Flight') and contains(normalize-space(), 'Info')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('FLIGHT/DATE'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('UPDATE - CHANGE'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]g\.delta\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Trip Confirmation')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Trip Confirmation'))}\s*[#]([A-Z\d]+)/"), 'Trip Confirmation');

        $travellersText = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Hello')]", null, true, "/{$this->opt($this->t('Hello'))}\s*(\D+)\,/");
        $travellers = [];

        if (stripos($travellersText, 'and') !== false) {
            $travellers = explode('and', $travellersText);
        }

        if (count($travellers) > 0) {
            $f->general()
                ->travellers($travellers);
        } else {
            $f->general()
                ->traveller($travellersText);
        }

        $account = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Hello')]/preceding::text()[starts-with(normalize-space(), 'SkyMiles')]/preceding::text()[normalize-space()][1][contains(normalize-space(), '*')]");

        if (!empty($account)) {
            $f->program()
                ->account($account, true);
        }

        $nodes = $this->http->XPath->query("//text()[normalize-space()='UPDATE - CHANGE']/ancestor::tr[1]/following::text()[contains(normalize-space(), ':') and ((contains(normalize-space(), ' am')) or contains(normalize-space(), 'pm'))]/ancestor::td[1][not(contains(@style, 'line-through;'))]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $flightInfo = $this->http->FindSingleNode("./descendant::td[1]", $root);
            $depDate = '';

            if (preg_match("/^(\D+)\s+(\d{2,4})\s*(\w+\,\s*\w+\s*\d+)$/", $flightInfo, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                $depDate = $m[3];
            }

            $depInfo = $this->http->FindSingleNode("./descendant::td[2]", $root);

            if (preg_match("/^(\D+)\s*([\d\:]+\s*a?p?m)$/", $depInfo, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($depDate . ' ' . $m[2]))
                    ->name($m[1])
                    ->noCode();
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::td[3]", $root);

            if (preg_match("/^(\D+)\s*([\d\:]+\s*a?p?m)$/", $arrInfo, $m)
                || preg_match("/^(\D+)\s*([\d\:]+\s*a?p?m)\s*[*]+\s*(\w+\,\s*\w+\s*\d+)$/", $arrInfo, $m)) {
                if (!isset($m[3])) {
                    $s->arrival()
                        ->date($this->normalizeDate($depDate . ' ' . $m[2]))
                        ->name($m[1])
                        ->noCode();
                } else {
                    $s->arrival()
                        ->date($this->normalizeDate($m[3] . ' ' . $m[2]))
                        ->name($m[1])
                        ->noCode();
                }
            }

            $seats = explode(",", $this->http->FindSingleNode("//text()[normalize-space()='SEAT']/following::text()[normalize-space()='{$s->getAirlineName()} {$s->getFlightNumber()}']/ancestor::tr[1]/descendant::td[last()]"));

            if (count($seats) > 0) {
                $s->setSeats($seats);
            }

            $cabin = $this->http->FindSingleNode("//text()[normalize-space()='SEAT']/following::text()[normalize-space()='{$s->getAirlineName()} {$s->getFlightNumber()}']/ancestor::tr[1]/descendant::td[normalize-space()][2]");

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $operator = $this->http->FindSingleNode("//text()[contains(normalize-space(), '{$s->getAirlineName()} {$s->getFlightNumber()} is operated by')]", null, true, "/{$this->opt($this->t('is operated by'))}\s*(.+)/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        $this->ParseFlight($email);

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

    private function normalizeDate($date)
    {
        //$this->logger->warning($date);
        $year = date('Y', $this->date);
        $in = [
            '/^(\w+)\,\s*(\w+)\s*(\d+)\s*([\d\:]+\s*a?p?m)$/u', // Tue, November 16 8:20 am
        ];
        $out = [
            '$1, $3 $2 ' . $year . ', $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }
}
