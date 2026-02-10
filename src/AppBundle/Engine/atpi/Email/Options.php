<?php

namespace AwardWallet\Engine\atpi\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Options extends \TAccountChecker
{
    public $mailFiles = "atpi/it-21127099.eml, atpi/it-22232858.eml, atpi/it-23748156.eml, atpi/it-22232928.eml";
    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = '@atpi.com';

    private $detectCompany = [
        'ATPI',
    ];

    private $detectBody = [
        'en' => [
            'reservation and fare quote is not guaranteed',
        ],
    ];

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //		$body = $this->http->Response['body'];
        //		foreach ($this->detectBody as $lang => $detectBody) {
        //			foreach ($detectBody as $detect) {
        //				if (stripos($body, $detect) !== false) {
        //					$this->lang = $lang;
        //					break;
        //				}
        //			}
        //		}

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        $findedCompany = false;

        foreach ($this->detectCompany as $detectBody) {
            if (stripos($body, $detectBody) !== false) {
                $findedCompany = true;

                break;
            }
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $detect) {
                if (stripos($body, $detect) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        //		if (stripos($headers['from'], $this->detectFrom) === false) {
        //			return false;
        //		}
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

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function flight(Email $email)
    {
        $sectionCount = 1;
        $optionsCount = count(array_unique($this->http->FindNodes("(//text()[" . $this->contains("reservation and fare quote is not guaranteed") . "])[1]/preceding::table[" . $this->starts("Option") . "]", null, "#Option\s*(\d+)#")));

        if ($optionsCount > 1) {
            $this->logger->info("more than 1 option");

            return $email;
        } elseif ($optionsCount == 0) {
            $optionsCount = count(array_unique($this->http->FindNodes("(//text()[" . $this->contains("reservation and fare quote is not guaranteed") . "])[2]/preceding::table[" . $this->starts("Option") . "]", null, "#Option\s*(\d+)#")));

            if ($optionsCount > 1) {
                $this->logger->info("more than 1 option");

                return $email;
            }
            $sectionCount = 2;
        }

        $xpathMain = "(//text()[" . $this->contains("reservation and fare quote is not guaranteed") . "])[" . $sectionCount . "]";

        $conf = $this->http->FindSingleNode("(//text()[" . $this->contains("Agency Booking Reference") . "])[1]", null, true, "#:\s*([A-Z\d]{5,})\s*$#");

        if (!empty($conf)) {
            $email->ota()->confirmation($conf, "Agency Booking Reference");
        } else {
            $email->ota();
        }

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers(array_filter($this->http->FindNodes("(//text()[" . $this->starts("Passenger Name") . "])[1]/ancestor::tr[1]/following-sibling::tr", null, "#^\s*\d+\s*-\s*(.+)#")));

        // Price
        $total = $this->http->FindSingleNode($xpathMain . "/preceding::text()[" . $this->contains("Total Fare:") . "][1]", null, true, "#Total Fare:\s*(.+)#");

        if (!empty($total)) {
            if ((preg_match("#^\s*(?<curr>[A-Z]{3})\s*(?<total>[\d\.]+)\s*$#", $total, $m) || preg_match("#^\s*(?<total>[\d\.]+)\s*(?<curr>[A-Z]{3})\s*$#", $total, $m)) && is_numeric($m['total'])) {
                $f->price()
                    ->total($m['total'])
                    ->currency($m['curr']);
            }
        }

        $xpath = $xpathMain . "/preceding::text()[" . $this->eq("From") . "]/ancestor::tr[1][" . $this->contains("Depart") . "]/following-sibling::tr[count(./td)>3]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("./td[3]", $root, true, "#^\s*([A-Z\d]{2})\s*\d{1,5}(?:\D|$)#"))
                ->number($this->http->FindSingleNode("./td[3]", $root, true, "#^\s*[A-Z\d]{2}\s*(\d{1,5})(?:\D|$)#"))
                ->operator($this->http->FindSingleNode("./td[10]", $root, true, "#Operated By\s*(.+)#"), true, true)
            ;

            $date = $this->http->FindSingleNode("./td[1]", $root);

            // Departure
            $s->departure()
                ->noCode()
                ->name($this->http->FindSingleNode("./td[4]", $root))
            ;

            $time = $this->http->FindSingleNode("./td[7]", $root);

            if (!empty($date) && !empty($time)) {
                $s->departure()
                    ->date($this->normalizeDate($time . ' ' . $date));
            }

            // Arrival
            $s->arrival()
                ->noCode()
                ->name($this->http->FindSingleNode("./td[5]", $root))
            ;

            $time = $this->http->FindSingleNode("./td[8]", $root);

            if (!empty($date) && !empty($time)) {
                if (preg_match("#\d+:\d+(\D.*\d+)?$#", $time)) {
                    $s->arrival()
                        ->date($this->normalizeDate($time));
                } else {
                    $s->arrival()
                        ->date($this->normalizeDate($time . ' ' . $date));
                }
            }

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode("./td[9]", $root, true, "#^(.+?)\s*-\s*[A-Z]{1,2}\s*$#"), true, true)
                ->bookingCode($this->http->FindSingleNode("./td[9]", $root, true, "#^.+?\s*-\s*([A-Z]{1,2})\s*$#"), true, true)
                ->aircraft($this->http->FindSingleNode("./td[11]", $root), true)
                ->stops($this->http->FindSingleNode("./td[6]", $root), true)
            ;
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d+:\d+(?:\s*[ap]m)?)\s+(\d{1,2})\s*([^\s\d\,\.]+)\s*(\d{2})\s*$#i", // 9:00 AM 11May17
        ];
        $out = [
            "$2 $3 $4 $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
