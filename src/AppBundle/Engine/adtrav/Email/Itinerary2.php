<?php

namespace AwardWallet\Engine\adtrav\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary2 extends \TAccountChecker
{
    public $mailFiles = "adtrav/it-207989140.eml";
    public $subjects = [
        'TICKET(S) ISSUED Itinerary for',
        'AWAITING TICKETING Itinerary for',
        'CANCELED Itinerary for',
        'AIRLINE SCHEDULE CHANGE on Itinerary for',
    ];

    public $lang = 'en';
    public $year;
    public $traveller;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@adtrav.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'ADTRAV Government ')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('SUPPLIER RECORD LOCATOR'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Carrier Locator:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]adtrav\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Flight#')]/ancestor::table[1]");

        foreach ($nodes as $root) {
            $f = $email->add()->flight();

            $f->general()
                ->confirmation($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'SUPPLIER RECORD LOCATOR')]", $root, true, "/\-([A-Z\d]{6})$/u"))
                ->status($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Status:')]/following::text()[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Segment'))}\s*(\w+)/"))
                ->traveller($this->traveller, true);

            $ticket = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Ticket #:')]/following::text()[normalize-space()][1]", $root, true, "/^\s*(\d{5,})/");

            if (!empty($ticket)) {
                $f->issued()
                    ->ticket($ticket, false);
            }

            $s = $f->addSegment();

            $s->airline()
                ->carrierName($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Carrier:')]/following::text()[normalize-space()][1]", $root))
                ->number($this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Flight#')]", $root, true, "/[#]\s*(\d{2,4})$/"))
                ->noName();

            $depPointText = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Departs:']/ancestor::tr[1]/descendant::td[2]", $root);

            if (preg_match("/\((?<depCode>[A-Z]{3})\)(?:.*TERMINAL\s(?<depTerminal>.+)\)|$)/u", $depPointText, $m)) {
                $s->departure()
                    ->code($m['depCode']);

                if (isset($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }
            }

            $depDate = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Departs:']/ancestor::tr[1]/descendant::td[3]", $root);
            $s->departure()
                ->date($this->normalizeDate($depDate));

            $arrPointText = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Arrives:']/ancestor::tr[1]/descendant::td[2]", $root);

            if (preg_match("/\((?<arrCode>[A-Z]{3})\)(?:.*TERMINAL\s(?<arrTerminal>.+)\)|$)/u", $arrPointText, $m)) {
                $s->arrival()
                    ->code($m['arrCode']);

                if (isset($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            }

            $arrDate = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Arrives:']/ancestor::tr[1]/descendant::td[3]", $root);
            $s->arrival()
                ->date($this->normalizeDate($arrDate));

            $seats = explode(",", $this->http->FindSingleNode("./descendant::text()[normalize-space()='Seat #:']/ancestor::tr[1]/descendant::td[2]", $root, true, "/([\dA-Z\,\s]+)$/"));

            if (count($seats) > 0) {
                $s->setSeats($seats);
            }

            $cabinText = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Class:']/ancestor::tr[1]/descendant::td[2]", $root);

            if (preg_match("/^(.+)\s+\(([A-Z])\)$/", $cabinText, $m)) {
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2]);
            }

            $aircraft = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Equipment:']/ancestor::td[1]/following::td[1]", $root);

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            $meal = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Meal:']/ancestor::td[1]/following::td[1]", $root);

            if (!empty($meal) && stripos($meal, 'N/A') === false) {
                $s->extra()
                    ->meal($meal);
            }

            $flightInfo = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Info:']/ancestor::td[1]/following::td[1]", $root);

            if (preg_match("/^\s*{$this->opt($this->t('Stops:'))}\s*(?<stops>\d)\,\s*{$this->opt($this->t('Time:'))}\s*(?<duration>[\d\:\.]+)\,\s*{$this->opt($this->t('Miles:'))}\s*(?<miles>\d+)\s*$/", $flightInfo, $m)) {
                $s->extra()
                    ->stops($m['stops'])
                    ->duration($m['duration'])
                    ->miles($m['miles']);
            }

            $accounts = array_filter(explode(",", $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Frequent Flyer:')]/following::text()[normalize-space()][1]", $root, true, "/([A-Z\-\d\,\s]+)/")));

            if (count($accounts) > 0) {
                $f->setAccountNumbers($accounts, false);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //it-207991805.eml
        if ($this->http->XPath->query("//text()[normalize-space()='Awaiting Ticketing']")->length > 0
            || $this->http->XPath->query("//text()[normalize-space()='Pending']")->length > 0) {
            $email->setIsJunk(true);

            return $email;
        }

        $this->year = $this->http->FindSingleNode("//text()[normalize-space()='Date Created:']/following::text()[normalize-space()][1]", null, true, "/(\d{4})$/");
        $this->traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Traveler:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\s]+)/");

        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking Locator:']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]+)$/"));

        $emailPriceText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'TOTAL CHARGES')]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>\D)\s*(?<total>[\d\.,]+)$/u", $emailPriceText, $m)) {
            if ($m['currency'] == '$' && $this->http->XPath->query("//text()[contains(normalize-space(), 'USD')]")->length > 0) {
                $m['currency'] = 'USD';
            }

            $email->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);
        }

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Flight#')]")->length > 0) {
            $this->ParseFlight($email);
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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
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

    private function normalizeDate($str)
    {
        $year = $this->year;
        //$this->logger->warning('IN-'.$str);
        $in = [
            //Monday - November 29 - 5:30 PM
            "#^(\w+)[\s\-]+(\w+)\s*(\d+)[\-\s]+([\d\:]+\s*A?P?M)$#ui",
        ];
        $out = [
            "$1, $3 $2 $year, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
