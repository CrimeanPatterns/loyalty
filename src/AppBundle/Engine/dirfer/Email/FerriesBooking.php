<?php

namespace AwardWallet\Engine\dirfer\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FerriesBooking extends \TAccountChecker
{
    public $mailFiles = "dirfer/it-150910304.eml, dirfer/it-151663104.eml";
    public $subjects = [
        'Direct Ferries - Payment Request',
        'Direct Ferries Booking Request',
    ];

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
            'Total:'            => ['Total:', 'Amount :'],
            'Booking Reference' => ['Booking Reference', 'Booking Reference :', 'booking reference number', 'quote the reference'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@directferries.com') !== false) {
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
        return ($this->http->XPath->query("//a[contains(@href, 'directferries')]")->length > 0
            || $this->http->XPath->query("//img[contains(@src, 'directferries')]")->length > 0)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Accommodation:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Operator:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]directferries.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $f = $email->add()->ferry();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total:'))}]/preceding::text()[{$this->contains($this->t('Booking Reference'))}]", null, true, "/{$this->opt($this->t('Booking Reference'))}\s*([\dA-Z]{6,})$/");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total:'))}]/following::text()[{$this->contains($this->t('Booking Reference'))}]", null, true, "/{$this->opt($this->t('Booking Reference'))}\s*([\dA-Z]{6,})\.?$/");
        }
        $f->general()
            ->confirmation($confirmation);

        $price = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total:'))}]", null, true, "/{$this->opt($this->t('Total:'))}\s*(\D[\d\,\.]+)/u");

        if (preg_match("/^(\D)([\d\.\,]+)$/u", $price, $m)) {
            $currency = $this->normalizeCurrency($m[1]);

            $f->price()
                ->total(PriceHelper::parse($m[2], $currency))
                ->currency($currency);
        }

        $xpath = "//text()[{$this->starts($this->t('Depart:'))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $voyageInfo = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<depName>.+)\nDepart\:\n(?<depDate>(?:\w+\s*\w+\s*\d+|\w+\s*\d+\s*\w+))[\@\s]+(?<depTime>[\d\:]+)\n(?<arrName>.+)\nArrive:\n(?<arrDate>(?:\w+\s*\w+\s*\s*\d+|\w+\s*\d+\s*\w+))[\@\s]+(?<arrTime>[\d\:]+)\n(?<duration>\d+.+)$/", $voyageInfo, $m)) {
                $s->departure()
                    ->name($m['depName'])
                    ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']));

                $s->arrival()
                    ->name($m['arrName'])
                    ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']));

                $s->extra()
                    ->duration($m['duration']);
            }

            $accomodation = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][1]", $root, true, "/{$this->opt($this->t('Accommodation:'))}\s*(.+)/s");

            if (!empty($accomodation)) {
                $s->booked()
                    ->accommodation($accomodation);
            }

            $operator = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][2]", $root, true, "/{$this->opt($this->t('Operator:'))}\s*(.+)/s");

            if (!empty($operator)) {
                $s->setCarrier($operator);
            }

            $guests = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Price based on ')]", null, true, "/\s*(\d+)\s*Adults?/");

            if (!empty($guests)) {
                $s->booked()
                    ->adults($guests);
            }
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
        $in = [
            //Sat May 07, 09:00
            "#^(\w+)\s+(\w+)\s*(\d+)\,\s*([\d\:]+)$#i",
            //Sat 07 May, 09:00
            "#^(\w+)\s+(\d+)\s*(\w+)\,\s*([\d\:]+)$#i",
        ];
        $out = [
            "$1, $3 $2 $year, $4",
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

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            '$'   => ['$'],
            'JPY' => ['¥'],
            'PLN' => ['zł'],
            'THB' => ['฿'],
            'CAD' => ['C$'],
            'COP' => ['COL$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
