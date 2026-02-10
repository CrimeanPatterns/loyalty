<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightCancellation extends \TAccountChecker
{
    public $mailFiles = "expedia/it-11460009.eml, expedia/it-11527950.eml";

    public $reFrom = "expediamail.com";
    public $reBody = [
        'en' => ['Your flight has been cancelled', 'One Way to'],
        // 'ja' => [''],
    ];
    public $reSubject = [
        'Expedia Flight Cancellation Confirmation',
    ];
    public $lang = '';
    public $date;
    public static $dict = [
        'en' => [
            // Flight
            'One Way to'               => ['Round Trip to', 'One Way to'],
            'flightDetect'             => ['Your flight has been cancelled', 'Thank you for choosing Expedia', 'Departure'],
            'Expedia Itinerary Number' => ['Expedia Itinerary Number', 'Chase Travel Itinerary Number'],
        ],
        'ja' => [
            // Flight
            'One Way to'      => ['Round Trip to', 'One Way to'],
            'airline credits' => 'ご予約者名',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        if ($this->http->FindSingleNode("(//text()[{$this->contains($this->t('flightDetect'))}])[1]")) {
            $this->parseFlight($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'expedia.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
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

    private function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        $f->ota()->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Expedia Itinerary Number'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "#([A-Z\-\d]{5,})#"));

        if ($travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('airline credits'))}]/ancestor::td[1]/preceding-sibling::td[2]")) {
            $f->general()->travellers($travellers);
        }
        //		$tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('You will be refunded'))}]/following::text()[normalize-space(.)!=''][1]"));
        //		if (!empty($tot['Total'])) {
//            $f->price()->total($tot['Total']);
//            $f->price()->currency($tot['Currency']);
        //		}

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('flightDetect'))}]")->length > 0) {
            $f->general()->status($this->t('cancelled'));
            $f->general()->cancelled();
        }
        $s = $f->addSegment();
        $s->departure()->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('One Way to'))}]", null, true, "#{$this->opt($this->t('One Way to'))}\s+(.+?)(?:\s*\-|$)#"));
        $s->departure()->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('One Way to'))}]/following::text()[normalize-space(.)!=''][1]")));
        $s->arrival()->noCode();
        $s->arrival()->noDate();

        if (!empty($node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('will issue a total of'))}]", null, true, "#(.+)\s+{$this->opt($this->t('will issue a total of'))}#"))) {
            $s->airline()->name($node);
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $items) {
            foreach ($items as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Sat, Feb 24
            '#^(\w+),\s+(\D+)\s+(\d+)$#u',
            //2/21/2018
            '#^(\d+)\/(\d+)\/(\d{4})$#u',
            // 2020 年 3 月 15 日
            '/^(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日/u',
        ];
        $out = [
            '$3 $2 ' . $year,
            '$3-$1-$2',
            '$2/$3/$1',
        ];
        $outWeek = [
            '$1',
            '',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weekNum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weekNum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
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
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }

    /*
    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[.\d,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[.\d,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>-*?)(?<t>\d[.\d,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
    */
}
