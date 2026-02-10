<?php

namespace AwardWallet\Engine\dice\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourTickets extends \TAccountChecker
{
    public $mailFiles = "dice/it-640914554.eml, dice/it-649516447.eml";
    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Price' => ['Price', 'Total price'],
        ],
    ];
    private $date;

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'DICE FM')]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('Ticket details'))}]")->length > 0
             || $this->http->XPath->query("//text()[{$this->contains($this->t('Event information'))}]")->length > 0)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Download the DICE app'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]dice\.fm$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->ParseEvent($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseEvent(Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(EVENT_SHOW);

        if ($this->http->XPath->query("//text()[normalize-space()='Ticket type']")->length > 0) {
            $e->setNotes(implode(": ", $this->http->FindNodes("//text()[normalize-space()='Ticket type']/ancestor::tr[1]/descendant::td")));
        }

        $e->general()
            ->noConfirmation();

        $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Name on ticket']/ancestor::tr[1]/descendant::td[2]");

        if (!empty($traveller)) {
            $e->general()
                ->traveller($traveller);
        }

        $guests = $this->http->FindSingleNode("//text()[normalize-space()='Quantity']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\d+)$/");

        if (!empty($guests)) {
            $e->setGuestCount($guests);
        }

        $e->setName($this->http->FindSingleNode("//text()[normalize-space()='Venue']/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][1]"))
            ->setAddress($this->http->FindSingleNode("//text()[normalize-space()='Venue']/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][2]"));

        $startDate = $this->http->FindSingleNode("//text()[normalize-space()='Date & time']/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][1]", null, true, "/^(.+)\s+GMT/");

        if (empty($startDate)) {
            $startDate = $this->http->FindSingleNode("//text()[normalize-space()='Date & time']/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][1]");
        }
        $e->setStartDate($this->normalizeDate($startDate))
            ->setNoEndDate(true);

        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)/", $price, $m)) {
            $e->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Tax'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D{1,3}\s*([\d\.\,]+)/");

            if (!empty($tax)) {
                $e->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ticket price'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D{1,3}\s*([\d\.\,]+)/");

            if (!empty($cost)) {
                $e->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }
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
        $year = date("Y", $this->date);
        // $this->logger->debug('$str = '.print_r( $str,true));

        $in = [
            //Thu 01 Dec, 5:00 PM
            "#^(\w+)\s*(\d+)\s*(\w+)\,\s*([\d\:]+\s*A?P?M)$#iu",
        ];

        $out = [
            "$1, $2 $3 $year, $4",
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
