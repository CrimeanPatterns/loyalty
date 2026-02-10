<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OverviewCruise extends \TAccountChecker
{
    public $mailFiles = "expedia/it-460520982.eml, expedia/it-6153108.eml";

    public $reBody2 = [
        "en" => ["Cruise Line"],
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";
    private static $headers = [
        'expedia' => [
            'from' => ['expediamail.com'],
            'subj' => [
                "Expedia travel confirmation",
            ],
        ],
        'orbitz' => [
            'from' => ['orbitz.com'],
            'subj' => [
                'Orbitz travel confirmation',
            ],
        ],
        'ebookers' => [
            'from' => [],
            'subj' => [],
        ],
        'travelocity' => [
            'from' => [],
            'subj' => [],
        ],
        'rbcbank' => [
            'from' => [],
            'subj' => [],
        ],
    ];

    private $bodies = [
        'orbitz' => [
            '//img[contains(@alt,"Orbitz.com")]',
            'Collected by Orbitz',
        ],
        'ebookers' => [
            '//img[contains(@alt,"ebookers.com")]',
            'Collected by ebookers',
        ],
        'travelocity' => [
            '//img[contains(@src,"travelocity.com")]',
            'travelocity.com',
        ],
        'rbcbank' => [
            '//img[contains(@src,"rbcrewards.com")]',
            'rbcrewards.com',
        ],
    ];

    private $reBody = [
        'Expedia',
        'Orbitz',
        'ebookers',
    ];
    private $code = '';

    public function parseHtml(Email $email)
    {
        $c = $email->add()->cruise();

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Your cruise reservation has been canceled.')]")->length > 0) {
            $c->general()
                ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Expedia Itinerary number:')]", null, true, "/Expedia Itinerary number\:\s*(\d{5,})$/"))
                ->cancelled()
                ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Reserved for']/ancestor::tr[1]/descendant::td[last()]/descendant::text()[normalize-space()][1]"))
                ->status('canceled');

            $c->setClass($this->http->FindSingleNode("//text()[normalize-space()='Cabin type']/ancestor::tr[1]/descendant::td[last()]/descendant::text()[normalize-space()][1]"));
            $c->setShip($this->http->FindSingleNode("//text()[normalize-space()='Ship:']/following::text()[normalize-space()][1]"));
            $c->setDescription($this->http->FindSingleNode("//text()[normalize-space()='Ship:']/preceding::text()[normalize-space()][1]"));

            return $email;
        }

        $c->general()
            ->confirmation($this->nextText("Cruise Line Booking #"))
            ->travellers(array_filter($this->http->FindNodes("//text()[normalize-space(.)='Guests']/ancestor::tr[1]/following-sibling::tr/td[1][not(contains(normalize-space(), 'Citizen'))]")));

        $c->setShip($this->nextText("Ship Name"));
        $c->setRoom($this->re("#:\s+(\d+)$#", $this->nextText("Cabin", null, 2)));
        $c->setClass($this->nextText("Cabin"));

        $c->setDescription($this->http->FindSingleNode("//text()[normalize-space(.)='Travel Dates']/preceding::text()[normalize-space(.)][1]"));

        $total = $this->amount($this->http->FindSingleNode("//text()[" . $this->starts("Total:") . "]", null, true, "#Total:\s+\D+([\d\,\.]+)#"));

        if (empty($total)) {
            $total = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq("Total:") . "]/following::text()[normalize-space()][1]", null, true, "#^\s*\D+([\d\,\.]+)#"));
        }

        $currency = $this->http->FindSingleNode("//text()[" . $this->starts("All prices quoted in") . "]", null, true, "#All prices quoted in\s+([A-Z]{3})#");

        if (!empty($total) && !empty($currency)) {
            $c->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);

            $tax = $this->amount($this->http->FindSingleNode("//text()[" . $this->starts("Taxes & fees:") . "]", null, true, "#Taxes & fees:\s+\D+([\d\,\.]+)#"));

            if (empty($tax)) {
                $tax = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq("Taxes & fees:") . "]/following::text()[normalize-space()][1]", null, true, "#^\s*\D+([\d\,\.]+)#"));
            }

            if (!empty($tax)) {
                $c->price()
                    ->tax($tax);
            }

            $cost = $this->amount($this->http->FindSingleNode("//text()[" . $this->starts("Cruise fare:") . "]", null, true, "#Cruise fare:\s+\D+([\d\,\.]+)#"));

            if (empty($cost)) {
                $cost = $this->amount($this->http->FindSingleNode("//text()[" . $this->eq("Cruise fare:") . "]/following::text()[normalize-space()][1]", null, true, "#^\s*\D+([\d\,\.]+)#"));
            }

            if (!empty($cost)) {
                $c->price()
                    ->cost($cost);
            }
        }

        $xpath = "//text()[" . $this->eq("Travel schedule") . "]/ancestor::tr[1]/following-sibling::tr//tr[not(.//tr)]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $c->addSegment();

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]", $root)));

            $s->setName($this->http->FindSingleNode("./tr[1]", $root));

            if ($time = $this->http->FindSingleNode(".//text()[" . $this->starts("Depart:") . "]", $root, true, "#Depart:\s+(.+)#")) {
                $s->setAboard(strtotime($time, $date));
            }

            if ($time = $this->http->FindSingleNode(".//text()[" . $this->starts("Arrive:") . "]", $root, true, "#Arrive:\s+(.+)#")) {
                $s->setAshore(strtotime($time, $date));
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom || $bySubj) {
                $this->code = $code;
            }

            if ($bySubj) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $re) {
            if (stripos($body, $re) === false) {
                $first = true;
            }
        }

        if (empty($first)) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $lines) {
            foreach ($lines as $line) {
                if (stripos($body, $line) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        foreach ($this->reBody2 as $lang => $lines) {
            foreach ($lines as $line) {
                if (stripos($this->http->Response["body"], $line) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if ($code = $this->getProvider($parser)) {
            $email->setProviderCode($code);
        }

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

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    }

    private function getProvider(PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'expedia') {
                return null;
            } else {
                return $this->code;
            }
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (!(stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        && !(stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)) {
                        continue 2;
                    }
                }

                return $code;
            }
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4})$#", //October 8, 2017
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'  => 'EUR',
            'R$' => 'BRL',
            'C$' => 'CAD',
            'SG$'=> 'SGD',
            'HK$'=> 'HKD',
            'AU$'=> 'AUD',
            '$'  => 'USD',
            '£'  => 'GBP',
            'kr' => 'NOK',
            'RM' => 'MYR',
            '฿'  => 'THB',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
