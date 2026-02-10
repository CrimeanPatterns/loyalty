<?php

namespace AwardWallet\Engine\aegean\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MobileBoardingPass extends \TAccountChecker
{
    public $mailFiles = "aegean/it-119619448.eml, aegean/it-5194739.eml";
    public $subjects = [
        'AEGEAN AIRLINES S.A. - Mobile check-in Confirmation',
    ];

    public $lang = '';
    public $detectLang = [
        'en' => ['Mobile Boarding Pass'],
    ];

    public $date;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aegeanair.com') !== false) {
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
        $this->assignLang();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Enjoy your flight with Aegean')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Mobile Boarding Pass'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aegeanair\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference'))}]/following::text()[normalize-space()][1]", null, true, "/([A-Z\d]+)/"));
        $travellers = preg_replace(['/(^\s*(MRS|MR|MS) | (MRS|MR|MS)\s*$)/', '/^\s*([^\\/]+?)\s*\\/\s*([^\\/]+?)\s*$/'], ['', '$2 $1'], $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger Name'))}]/following::text()[normalize-space()][1]"));

        if (count($travellers) == 1 && !empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('Seat'))}]/following::text()[normalize-space()][1][normalize-space() = 'INF']"))) {
            $f->general()
                ->infants($travellers);
        } else {
            $f->general()
                ->travellers($travellers);
        }

        $account = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Frequent Flyer Number'))}]/following::text()[normalize-space()][1][not(starts-with(normalize-space(), '-'))]");

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        $s = $f->addSegment();

        $s->airline()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{2})\s*/"))
            ->number($this->http->FindSingleNode("//text()[{$this->eq($this->t('Flight'))}]/following::text()[normalize-space()][1]", null, true, "/^[A-Z\d]{2}\s*(\d{2,4})/"));

        $depDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure Date'))}]/following::text()[normalize-space()][1]");
        $depTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure Time'))}]/following::text()[normalize-space()][1]");
        $arrTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival Time'))}]/following::text()[normalize-space()][1]");

        $depTerminal = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Terminal'))}]/following::text()[normalize-space()][1][not(contains(normalize-space(), 'Check Monitors'))]");

        if (!empty($depTerminal)) {
            $s->departure()
                ->terminal($depTerminal);
        }

        $s->departure()
            ->date(strtotime($depDate . ', ' . $depTime))
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Origin'))}]/following::text()[normalize-space()][1]"))
            ->noCode();

        $s->arrival()
            ->date(strtotime($depDate . ', ' . $arrTime))
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Destination'))}]/following::text()[normalize-space()][1]"))
            ->noCode();

        $seat = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Seat'))}]/following::text()[normalize-space()][1]");

        if (!empty($seat) && $seat !== '-' && $seat !== 'INF' && $seat !== 'SBY') {
            $s->extra()
                ->seat($seat);
        }

        $cabin = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Class'))}]/following::text()[normalize-space()][1]", null, true, "/^(.+)\s*\|/");

        if (!empty($cabin)) {
            $s->extra()
                ->cabin($cabin);
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Mobile Boarding Pass is not available at the departing airport')]")->length == 0) {
            $bp = $email->add()->bpass();
            $bp->setFlightNumber($s->getAirlineName() . ' ' . $s->getFlightNumber())
                ->setTraveller($f->getTravellers()[0][0] ?? $f->getInfants()[0][0])
                ->setRecordLocator($f->getConfirmationNumbers()[0][0])
                ->setDepDate($s->getDepDate())
                ->setUrl($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Mobile Boarding Pass of your flight, please click')]/following::a[1]"));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

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
        $year = date('Y', $this->date);
        $in = [
            '/^(\w+)\s*(\d+)\.(\w+)\s*([\d\:]+)$/u', // Sunday 12.Dec 16:15
        ];
        $out = [
            '$1, $2 $3 ' . $year . ' $4',
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

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'AUD' => ['AU $'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[contains(normalize-space(), '{$word}')]")->length > 0) {
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
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
