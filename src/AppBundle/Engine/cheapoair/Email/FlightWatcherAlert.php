<?php

namespace AwardWallet\Engine\cheapoair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightWatcherAlert extends \TAccountChecker
{
    public $mailFiles = "cheapoair/it-10105995.eml, cheapoair/it-10115858.eml, cheapoair/it-214182062.eml";
    public $reFrom = "noreply@cheapoair.com";
    public $reSubject = [
        "en"=> "Flight Watcher Alert from CheapOair.com",
    ];
    public $reBody = ['CheapOair', 'World Travel Inc.'];
    public $reBody2 = [
        "en" => "Flight Monitoring",
        "en2"=> "Flight Alert system",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    private static $providers = [
        'cheapoair' => [
            'from' => '@cheapoair.com',
            'body' => 'CheapOair',
        ],
        'travelinc' => [
            'from' => '@worldtravelinc.com',
            'body' => 'World Travel, Inc.',
        ],
    ];

    public function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->nextText("Booking Confirmation Code:"))
            ->travellers([$this->nextText("Passengers:")], true);

        $xpath = "//text()[" . $this->eq("Depart:") . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::img[contains(@src, '/plane.png')]/ancestor::tr[1]/td[3]", $root)));

            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./preceding::text()[normalize-space(.)][2]", $root, true, "#\((\w{2})\)#"))
                ->number($this->http->FindSingleNode("./preceding::text()[normalize-space(.)][2]", $root, true, "#\s+(\d+)$#"));

            $s->departure()
                ->date(strtotime($this->nextText("Depart:", $root), $date))
                ->code($this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]", $root, true, "#^([A-Z]{3}) to [A-Z]{3}$#"));

            $depTerminal = $this->http->FindSingleNode("//text()[" . $this->contains("Departure:") . " and " . $this->contains($s->getDepCode()) . "]/ancestor::tr[1]/..//text()[" . $this->eq("Terminal:") . "]/ancestor::tr[1]/td[2]");

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $s->arrival()
               ->code($this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]", $root, true, "#^[A-Z]{3} to ([A-Z]{3})$#"))
               ->date(strtotime($this->nextText("Arrive:", $root), $date));

            $arrTerminal = $this->http->FindSingleNode("//text()[" . $this->contains("Arrival:") . " and " . $this->contains($s->getArrCode()) . "]/ancestor::tr[1]/..//text()[" . $this->eq("Terminal:") . "]/ancestor::tr[1]/td[2]");

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $prov) {
            if (strpos($body, $prov) !== false) {
                foreach ($this->reBody2 as $re) {
                    if (strpos($body, $re) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->parseHtml($email);

        $email->setProviderCode($this->getProvider());

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

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function getProvider()
    {
        foreach (self::$providers as $prov => $bodyArray) {
            if ($this->http->XPath->query("//text()[{$this->contains($bodyArray['body'])}]")->length > 0) {
                return $prov;
            }
        }
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+:\d+)\s*\|\s*[^\d\s]+,(\d+)-([^\d\s]+)-(\d{2})$#", //09:25| Tue,30-Dec-14
        ];
        $out = [
            "$2 $3 $4, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
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
