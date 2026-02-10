<?php

namespace AwardWallet\Engine\airchina\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "airchina/it-173392579.eml, airchina/it-721864990.eml";
    public $subjects = [
        '出票成功确认',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@airchina.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Air China'))}]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Booking Confirmation'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Passenger Information'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Itinerary'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airchina\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Order No.')]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Order Time')]/following::text()[normalize-space()][1]")))
        ;

        $trXpath = "//text()[contains(normalize-space(), 'Name(Last name/Surname First/Given name)')]/ancestor::tr[1]/following-sibling::tr";
        $pNodes = $this->http->XPath->query($trXpath);

        foreach ($pNodes as $trRoot) {
            $traveller = $this->http->FindSingleNode("*[1]", $trRoot);
            $f->general()
                ->traveller($traveller, true);

            $ticket = $this->http->FindSingleNode("*[4]", $trRoot, true, "/^(\d+)$/");

            if (!empty($ticket)) {
                $f->issued()
                    ->ticket($ticket, false, $traveller);
            }

            $account = $this->http->FindSingleNode("*[6]", $trRoot, true, "/^([\dA-Z]{5,})$/");

            if (!empty($account)) {
                $f->program()
                    ->account($account, false, $traveller);
            }
        }

        $xpath = "//text()[contains(normalize-space(), 'Departure Date')]/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^([A-Z\d]{2})\d{2,4}/"))
                ->number($this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^[A-Z\d]{2}(\d{2,4})/"));

            $date = $this->http->FindSingleNode("./descendant::td[1]", $root, true, "/(\d+\-\w+\-\d{4})$/");
            $depTime = $this->http->FindSingleNode("./descendant::td[3]", $root, true, "/(\d+\:\d+)/u");
            $arrTime = $this->http->FindSingleNode("./descendant::td[4]", $root, true, "/(\d+\:\d+)/u");

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::td[3]", $root, true, "/\(\s*([A-Z]{3})\)/u"))
                ->terminal($this->http->FindSingleNode("./descendant::td[3]", $root, true, "/\([A-Z]{3}\)\s*T?(\w+?)(?:航站楼)?\s+\d{1,2}:\d{2}/u"), true, true)
                ->date(strtotime($date . ', ' . $depTime));

            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::td[4]", $root, true, "/\(([A-Z]{3})\)/u"))
                ->terminal($this->http->FindSingleNode("./descendant::td[4]", $root, true, "/\([A-Z]{3}\)\s*T?(\w+?)(?:航站楼)?\s+\d{1,2}:\d{2}/u"), true, true)
                ->date(strtotime($date . ', ' . $arrTime));

            $cabin = $this->http->FindSingleNode("./descendant::td[5]", $root, true, "/^(.+)\(/");

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $bookingCode = $this->http->FindSingleNode("./descendant::td[5]", $root, true, "/\((.+)\)/");

            if (!empty($bookingCode)) {
                $s->extra()
                    ->bookingCode($bookingCode);
            }
        }

        $priceXpath = "//tr[*[5][{$this->contains($this->t('Passenger No.'))}]][*[6][{$this->contains($this->t('Total'))}]]";
        $currency = $this->http->FindSingleNode($priceXpath . "/preceding::text()[normalize-space()][1]", null, true,
            "/{$this->opt($this->t('Fare Details'))}.*[\(（]\s*(?:|.*[^A-Z])([A-Z]{3})(?:|[^A-Z].*)\s*[\)）]\s*$/");

        $pNodes = $this->http->XPath->query($priceXpath . "/following-sibling::*[normalize-space()]");

        $error = false;
        $fare = $tax = $total = 0.0;

        foreach ($pNodes as $pRoot) {
            $passengerNo = $this->http->FindSingleNode("*[5]", $pRoot, true, "/^\s*x\s*(\d+)\s*$/");

            if (empty($passengerNo)) {
                $error = true;

                break;
            }
            $fareValue = $this->http->FindSingleNode("*[2]", $pRoot, true, "/^\s*(\d+(?:\.\d{1,2})?)\s*$/");

            if (!empty($fareValue)) {
                $fare += $passengerNo * (float) $fareValue;
            } else {
                $error = true;

                break;
            }
            $taxValue = $this->http->FindSingleNode("*[3]", $pRoot, true, "/^\s*(\d+(?:\.\d{1,2})?)\s*$/");

            if (!empty($taxValue)) {
                $tax += $passengerNo * (float) $taxValue;
            } else {
                $error = true;

                break;
            }
            $totalValue = $this->http->FindSingleNode("*[6]", $pRoot, true, "/^\s*(\d+(?:\.\d{1,2})?)\s*$/");

            if (!empty($totalValue)) {
                $total += (float) $totalValue;
            } else {
                $error = true;

                break;
            }
        }

        if (!$error) {
            $f->price()
                ->total($total)
                ->cost($fare)
                ->tax($tax)
                ->currency($currency);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
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

    private function normalizeDate($str)
    {
        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#\w+\,(\d{4})\D(\d+)\D(\d+)\D#u", //星期六,2022年07月02日
        ];
        $out = [
            "$3.$2.$1",
        ];
        $str = preg_replace($in, $out, $str);

        $this->logger->debug('$str = ' . print_r($str, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            'CNY' => ['元'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'SGD' => ['SG$'],
            'ZAR' => ['R'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
