<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationReminder extends \TAccountChecker
{
    public $mailFiles = "avis/it-26691599.eml, avis/it-27134384.eml";

    public static $dictionary = [
        "en" => [
            //			"CONFIRMATION NUMBER" => "",
            //			"you're all set to go" => "",
            //			"PICK UP" => "",
            //			"DROP OFF" => "",
            //			"MODIFY RESERVATION" => "",
            "YOUR CAR" => ["YOUR CAR", "YOUR VEHICLE"],
            //			"similar" => "",
        ],
    ];

    private $detectFrom = "avis.com";
    private $detectSubject = [
        "en" => ["Reservation Reminder | ", "Reminder: Avis reservation #"],
    ];
    private $detectCompany = [
        "Thank you for choosing Avis",
    ];
    private $detectBody = [
        "en" => ["you're all set to go", "you&#39;re all set to go"],
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //		$body = $this->http->Response['body'];
        //		foreach ($this->detectBody as $lang => $detectBody) {
        //			foreach ($detectBody as $dBody) {
        //				if (strpos($body, $dBody) !== false) {
        //					$this->lang = $lang;
        //				}
        //			}
        //		}

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseHtml($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            foreach ($detectSubject as $dSubject) {
                if (stripos($headers["subject"], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $detectCompanyFlag = false;

        foreach ($this->detectCompany as $dCompany) {
            if (stripos($body, $dCompany) !== false) {
                $detectCompanyFlag = true;
            }
        }

        if ($detectCompanyFlag == false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email): void
    {
        $patterns = [
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)](?: ?\/ ?\d+)?', // +377 (93) 15 48 52/8    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("CONFIRMATION NUMBER")) . "]/following::text()[string-length(normalize-space(.))>1][1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#"))
            ->traveller($this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("you're all set to go"))}][1]", null, true, "/^\s*({$patterns['travellerName']})[\s,]+{$this->opt($this->t("you're all set to go"))}/u"))
        ;
        $dateLocationRegexp = "[ ]*\n(?<date>[\s\S]+?\n[ ]*(?:\d{1,2}:\d{2}|\d{4}).*)\n(?<location>[\s\S]+?)(?:\n(?<phone>{$patterns['phone']}))?\n{$this->opt($this->t("MODIFY RESERVATION"))}";

        // Pick Up
        $pickup = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("PICK UP")) . "]/ancestor::*[" . $this->contains($this->t("MODIFY RESERVATION")) . "][1]//text()[normalize-space()]"));

        if (preg_match("/^\s*" . $this->opt($this->t("PICK UP")) . $dateLocationRegexp . "/", $pickup, $m)) {
            $r->pickup()
                ->date($this->normalizeDate($m['date']))
                ->location(trim(preg_replace("#\s*\n\s*#", ", ", $m['location'])))
                ->phone(empty($m['phone']) ? null : $m['phone'], false, true)
            ;
        }

        // Drop Off
        $dropoff = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("DROP OFF")) . "]/ancestor::*[" . $this->contains($this->t("MODIFY RESERVATION")) . "][1]//text()[normalize-space()]"));

        if (preg_match("/^\s*" . $this->opt($this->t("DROP OFF")) . $dateLocationRegexp . "/", $dropoff, $m)) {
            $r->dropoff()
                ->date($this->normalizeDate($m['date']))
                ->location(trim(preg_replace("#\s*\n\s*#", ", ", $m['location'])))
                ->phone(empty($m['phone']) ? null : $m['phone'], false, true)
            ;
        }

        // Car
        $xpathFragment1 = "//tr[" . $this->eq($this->t("YOUR CAR")) . " and ./following-sibling::tr[string-length(normalize-space(.))>1] ][1]";

        $r->car()
            ->image($this->http->FindSingleNode($xpathFragment1 . "/following-sibling::tr[./descendant::img][1]/descendant::img[1]/@src[contains(.,'://')]"), true, true)
            ->model($this->http->FindSingleNode($xpathFragment1 . "/following-sibling::tr[" . $this->contains($this->t("similar")) . "][1]"), true, true)
        ;

        // Price
        $total = $this->http->FindSingleNode("//tr[" . $this->eq($this->t("ESTIMATED TOTAL")) . " and ./following-sibling::tr[string-length(normalize-space(.))>1] ][1]/following-sibling::tr[string-length(normalize-space(.))>1 and not(contains(.,':'))][1]");

        if (preg_match('#^\s*(?<currency>\D[^\d)(]{0,5})\s*(?<amount>\d[,.\d ]*)\s*$#', $total, $m) || preg_match('#^\s*(?<amount>\d[,.\d ]*)\s*(?<currency>\D[^\d)(]{0,5})\s*$#', $total, $m)) {
            $r->price()
                ->total($this->normalizePrice($m['amount']))
                ->currency(trim($this->currency($m['currency'])))
            ;
        }
    }

    private function t($phrase)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*[^\s\d]+\s+([^\s\d]+)\s+(\d+)[\s,]+(\d{4})\s+(\d+:\d+(\s*[ap]m)?)\s*$#i", //Friday September 28, 2018 9:00 PM
            "#^\s*[^\s\d]+\s+([^\s\d]+)\s+(\d+)[\s,]+(\d{4})\s*(\d{2})(\d{2})\s*hours\s*$#i", //Thursday September 27, 2018 0900 hours
        ];
        $out = [
            "$2 $1 $3 $4",
            "$2 $1 $3 $4:$5",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizePrice($price)
    {
        if (preg_match("#^\s*(\d{1,3}(?:[ ,]\d{3})*\.\d{2})\s*$#", $price)) {
            $price = preg_replace("#[^\d\.]+#", '', $price);

            if (is_numeric($price)) {
                return (float) $price;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
            'C$'=> 'CAD',
        ];

        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
