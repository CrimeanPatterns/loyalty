<?php

namespace AwardWallet\Engine\aa\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class GroupBooking extends \TAccountChecker
{
    public $mailFiles = "aa/it-291900149.eml";

    public $detectFrom = "no-reply@notify.email.aa.com";

    public $detectBody = [
        'en'  => ['Group booking details'],
    ];
    public $detectBodyJunk = [
        'en'  => ['giving us the opportunity to provide this quote for your group'],
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'Cabin'   => ['Cabin', 'Stops (Equipment)'],
            'Created' => ['Created', 'Booking confirmation date'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }
        $isJunk = false;

        foreach ($this->detectBodyJunk as $lang => $dBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                $email->setIsJunk(true);
                $isJunk = true;

                break;
            }
        }

        if ($isJunk != true) {
            $this->parseEmail($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(@src,'@groups.aa.com')] | //a[contains(@href,'groups.aa.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                return true;
            }
        }

        foreach ($this->detectBodyJunk as $lang => $dBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["subject"], 'American Airlines Groups') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'noreply@groups.aa.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("//td[{$this->eq($this->t('Main PNR'))}]/following-sibling::td[1]", null, false, "/^\s*([A-Z\d]{5,7})\s*$/");
        $f->general()
            ->confirmation($conf, null, true)
        ;
        $confs = array_diff(array_filter($this->http->FindNodes("//tr[not(.//tr)][{$this->starts($this->t('PNR '))}]", null, "/^\s*{$this->opt($this->t('PNR '))}\s*([A-Z\d]{5,7})\s*$/")),
            [$conf]);

        if (!empty($confs)) {
            foreach ($confs as $c) {
                $f->general()
                    ->confirmation($c);
            }
        }

        $date = $this->normalizeDate($this->http->FindSingleNode("(//td[{$this->eq($this->t('Created'))}]/following-sibling::td[1])[2]"));

        if (!empty($date)) {
            $f->general()->date($date);
        }
        $status = $this->http->FindSingleNode("//td[{$this->eq($this->t('Status'))}]/following-sibling::td[1]");

        if ($status) {
            $f->general()->status($status);
        }

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Group Booking Cancelled")) . "])[1]"))) {
            $f->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }

        $tHeaderXpath = "//*[" . $this->eq($this->t('First name')) . "][following-sibling::*[1][" . $this->eq($this->t('Last name')) . "]]/ancestor::tr[1]";
        $nameStart = 1 + $this->http->XPath->query("(" . $tHeaderXpath . ")[1]/*[" . $this->eq($this->t('First name')) . "]/preceding-sibling::*")->length;
        $tXpath = $tHeaderXpath . "/following::tr[normalize-space()][1]/ancestor::*[1]/tr[normalize-space() and count(*) > 1][not(*[" . $this->eq($this->t('First name')) . "])]";
        $tNodes = $this->http->XPath->query($tXpath);

        foreach ($tNodes as $root) {
            $f->general()
                ->traveller($this->http->FindSingleNode("*[" . $nameStart . "]", $root) . ' ' . $this->http->FindSingleNode("*[" . ($nameStart + 1) . "]", $root), true);
        }

        // Price
        $pXpath = "//tr[*[1][" . $this->eq($this->t('Costs')) . "] and *[2][" . $this->eq($this->t('Amount')) . "] ]/following-sibling::tr[normalize-space()]";
        $pNodes = $this->http->XPath->query($pXpath);

        foreach ($pNodes as $i => $root) {
            $name = $this->http->FindSingleNode("*[1]", $root);

            if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $this->http->FindSingleNode("*[2]", $root), $matches)) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;

                $f->price()
                    ->currency($matches['currency']);
                $amount = PriceHelper::parse($matches['amount'], $currencyCode);

                if ($i == 0 and in_array($name, (array) $this->t("Netfare"))) {
                    $f->price()
                        ->cost($amount);

                    continue;
                } elseif ($i == 0) {
                    break;
                }

                if (in_array($name, (array) $this->t("Total"))) {
                    $f->price()
                        ->total($amount);

                    break;
                }
                $f->price()
                    ->fee($name, $amount);
            }
        }

        // Segments
        $headerXpath = "//tr[*[" . $this->eq($this->t('Flight')) . "] and *[" . $this->eq($this->t('Arrival')) . "] ]";
        $xpath = $headerXpath . "[not(preceding::text()[" . $this->eq($this->t("Original Itinerary")) . "])]/following::tr[normalize-space()][1]/ancestor::*[1]/tr[normalize-space() and count(*) > 2][not(*[" . $this->eq($this->t('Flight')) . "])]";
        $nodes = $this->http->XPath->query($xpath);
        $flightStart = 1 + $this->http->XPath->query("(" . $headerXpath . ")[1]/*[" . $this->eq($this->t('Flight')) . "]/preceding-sibling::*")->length;
        $cols = [
            'airline' => $this->http->XPath->query("(" . $headerXpath . ")[1]/*[" . $this->eq($this->t('Airline')) . "]/preceding-sibling::*")->length,
            'cabin'   => $this->http->XPath->query("(" . $headerXpath . ")[1]/*[" . $this->eq($this->t('Cabin')) . "]/preceding-sibling::*")->length,
        ];
        $cols = array_filter($cols);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            if (preg_match("/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\s*$/", $this->http->FindSingleNode("*[" . $flightStart . "]", $root), $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            } elseif (preg_match("/^\s*(\d{1,5})\s*$/", $this->http->FindSingleNode("*[" . $flightStart . "]", $root), $m)) {
                $s->airline()
                    ->noName()
                    ->number($m[1]);
            }

            if (!empty($cols['airline'])) {
                $airline = preg_replace("/^\s*" . $this->opt($this->t("OPERATED BY")) . "\s+/i", '', $this->http->FindSingleNode("*[" . ($cols['airline'] + 1) . "]", $root));

                if (!empty($airline)) {
                    $s->airline()
                        ->operator($airline);
                }
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("*[" . ($flightStart + 1) . "]", $root, false, "/\(\s*([A-Z]{3})\s*\)\s*$/"))
                ->name($this->http->FindSingleNode("*[" . ($flightStart + 1) . "]", $root, false, "/(.+?)\s*\(\s*[A-Z]{3}\s*\)\s*$/"))
                ->date($this->normalizeDate($this->http->FindSingleNode("*[" . ($flightStart + 2) . "]", $root) . ', ' . $this->http->FindSingleNode("*[" . ($flightStart + 3) . "]", $root)))
                ->terminal($this->http->FindSingleNode("*[" . ($flightStart + 4) . "]", $root), true, true)
            ;

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("*[" . ($flightStart + 5) . "]", $root, false, "/\(\s*([A-Z]{3})\s*\)\s*$/"))
                ->name($this->http->FindSingleNode("*[" . ($flightStart + 5) . "]", $root, false, "/(.+?)\s*\(\s*[A-Z]{3}\s*\)\s*$/"))
                ->date($this->normalizeDate($this->http->FindSingleNode("*[" . ($flightStart + 6) . "]", $root) . ', ' . $this->http->FindSingleNode("*[" . ($flightStart + 7) . "]", $root)))
                ->terminal($this->http->FindSingleNode("*[" . ($flightStart + 8) . "]", $root), true, true)
            ;

            // Extra
            if (!empty($cols['cabin'])) {
                $cabin = $this->http->FindSingleNode("*[" . ($cols['cabin'] + 1) . "]", $root);

                if (preg_match("/(.+?)\s*\(\s*([A-Z]{1,2})\s*\)\s*$/", $cabin, $m)) {
                    $s->extra()
                        ->cabin($m[1])
                        ->bookingCode($m[2]);
                } else {
                    $s->extra()
                        ->cabin($this->http->FindSingleNode("*[10]", $root));
                }
            }

            $aircraft = $this->http->FindSingleNode("following-sibling::tr[1][count(*) <3]/*[" . $this->contains($this->t("Equipment:")) . "]",
                $root, true, "/" . $this->opt($this->t("Equipment:")) . "\s*\(([^\)]+)\)/");

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            foreach ($f->getSegments() as $key => $seg) {
                if ($s->getId() !== $seg->getId() && serialize($s->toArray()) == serialize($seg->toArray())) {
                    $f->removeSegment($s);

                    break;
                }
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));

        $in = [
            // Tuesday 15-Feb-2022 15:20:06
            "/^\s*(?:\w+\s+)?(\d+)\s*-?\s*([[:alpha:]]+)\s*-?\s*(\d{4})\s+(\d{1,2}:\d{2})(?::\d{2})?\s*$/",
            // 18Jun22, 07:20
            "/^\s*(?:\w+\s+)?(\d+)\s*-?\s*([[:alpha:]]+)\s*-?\s*(\d{2})[\s,]+(\d{1,2}:\d{2})\s*$/",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 20$3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
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
}
