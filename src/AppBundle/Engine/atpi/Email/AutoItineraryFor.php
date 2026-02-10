<?php

namespace AwardWallet\Engine\atpi\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AutoItineraryFor extends \TAccountChecker
{
    public $mailFiles = "atpi/it-13911360.eml";

    private $reFrom = "@atpi.com";
    private $reSubject = [
        "en" => "AutoItinerary for",
    ];
    private $reBody = 'ATPI';
    private $reBody2 = [
        "en" => "CONFIRMATION FOR RESERVATION",
    ];

    private static $dictionary = [
        "en" => [],
    ];

    private $lang = "en";
    private $emailSubject;

    public function flight(Email $email)
    {
        // airline Params
        $xpath = "//text()[starts-with(normalize-space(), 'Electronic Ticket - ')]/ancestor::tr[1]";
        $airlines = $this->http->XPath->query($xpath);

        foreach ($airlines as $roots) {
            $airline = trim($this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Electronic Ticket - ')]", $roots, true, "# - (.+)#"));
            $company[$airline]['Currency'] = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Charges ')]", $roots, true, "#\(([A-Z]{3})\)#");
            $xpath2 = ".//tr[count(td[normalize-space()]) = 2]";
            $nodes = $this->http->XPath->query($xpath2, $roots);

            foreach ($nodes as $root) {
                $name = trim($this->http->FindSingleNode("./td[normalize-space()][1]", $root), ' :');
                $value = trim($this->http->FindSingleNode("./td[normalize-space()][2]", $root), ' :');
                $company[$airline][$name] = $value;
            }
            $airline = trim($this->http->FindSingleNode("./ancestor::tr[1]//text()[starts-with(normalize-space(), 'Electronic Ticket - ')]", $root, true, "# - (.+)#"));
        }

        // Segments
        $xpath = "//text()[normalize-space() = 'Departure']/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $airline = trim($this->http->FindSingleNode("./*[local-name()='th' or local-name()='td'][1]", $root));
            $airs[$airline][] = $root;
        }

        foreach ($airs as $airline => $roots) {
            $f = $email->add()->flight();

            $f->general()->noConfirmation();
            $f->general()->traveller($this->http->FindSingleNode("(//text()[normalize-space() = 'Traveler:'])[1]/following::text()[normalize-space()][1]"));

            // Issued
            if (!isset($company[$airline])) {
                foreach ($company as $key => $value) {
                    if (preg_match("#^$airline\s+#", $key)) {
                        $company[$airline] = $company[$key];

                        break;
                    }
                }
            }

            if (!empty($airline)) {
                $f->issued()->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Airline locators:')]/ancestor::table[1]//td[not(.//td) and contains(normalize-space(), '$airline')]", null, true, "#\s*-\s*([A-Z\d]{5,7})\s*$#"));

                if (isset($company[$airline]) && !empty($company[$airline]['E-ticket Number'])) {
                    $f->issued()->ticket($company[$airline]['E-ticket Number'], false);
                }

                // Price
                if (isset($company[$airline]) && !empty($company[$airline]['Base Fare'])) {
                    $f->price()
                        ->cost($this->amount($company[$airline]['Base Fare']));
                }

                if (isset($company[$airline]) && !empty($company[$airline]['Tax'])) {
                    $f->price()
                        ->fee('Tax', $company[$airline]['Tax']);
                }

                if (isset($company[$airline]) && !empty($company[$airline]['Fee'])) {
                    $f->price()
                        ->fee('Fee', $company[$airline]['Fee']);
                }

                if (isset($company[$airline]) && !empty($company[$airline]['Total'])) {
                    $f->price()
                        ->total($this->amount($company[$airline]['Total']));
                }

                if (isset($company[$airline]) && !empty($company[$airline]['Currency'])) {
                    $f->price()
                        ->currency($company[$airline]['Currency']);
                }
            }

            foreach ($roots as $root) {
                $s = $f->addSegment();
                $addXpath = "./following-sibling::tr[1]";
                // Airline
                $s->airline()
                    ->name($this->http->FindSingleNode("{$addXpath}/td[1]//text()[starts-with(normalize-space(), 'Flight:')]", $root, true, "#:\s*([A-Z\d]{2})-\d+#"))
                    ->number($this->http->FindSingleNode("{$addXpath}/td[1]//text()[starts-with(normalize-space(), 'Flight:')]", $root, true, "#:\s*[A-Z\d]{2}-(\d{1,5})\b#"));

                // Departure
                $node = implode("\n", $this->http->FindNodes("{$addXpath}/td[2]//text()", $root));

                if (preg_match("#(?<airport>.+?)\s*\((?<code>[A-Z]{3})\)\s+(?<city>.+)\s+(?<date>.+\d{4}.*)\s+.+\s(?<time>\d+:\d+.*)(?<terminal>\s+.*TERMINAL.*)?#", $node, $m)) {
                    $s->departure()
                        ->code($m['code'])
                        ->name($m['airport'] . ', ' . $m['city'])
                        ->date($this->normalizeDate($m['date'] . ' ' . $m['time']));

                    if (!empty($m['terminal'])) {
                        $s->departure()
                            ->terminal(trim(str_ireplace('Terminal', '', $m['terminal'])));
                    }
                }

                // Arrival
                $node = implode("\n", $this->http->FindNodes("{$addXpath}/td[3]//text()", $root));

                if (preg_match("#(?<airport>.+?)\s*\((?<code>[A-Z]{3})\)\s+(?<city>.+)\s+(?<date>.+\d{4}.*)\s+.+\s(?<time>\d+:\d+.*)(?<terminal>\s+.*TERMINAL.*)?#", $node, $m)) {
                    $s->arrival()
                        ->code($m['code'])
                        ->name($m['airport'] . ', ' . $m['city'])
                        ->date($this->normalizeDate($m['date'] . ' ' . $m['time']));

                    if (!empty($m['terminal'])) {
                        $s->arrival()
                            ->terminal(trim(str_ireplace('Terminal', '', $m['terminal'])));
                    }
                }

                // Extra
                $s->extra()
                    ->bookingCode($this->http->FindSingleNode("{$addXpath}/td[1]//text()[starts-with(normalize-space(), 'Flight:')]", $root, true, "#\(\s*([A-Z]{1,2})\s+class\s*\)#"))
                    ->cabin($this->http->FindSingleNode("{$addXpath}/td[1]//text()[starts-with(normalize-space(), 'Class:')]", $root, true, "#:\s*(.+)#"))
                    ->aircraft($this->http->FindSingleNode("{$addXpath}/td[1]//text()[starts-with(normalize-space(), 'Equipment:')]", $root, true, "#:\s*(.+)#"), true, true)
                    ->seat($this->http->FindSingleNode("{$addXpath}/td[1]//text()[starts-with(normalize-space(), 'Seat(s):')]", $root, true, "#:\s*(\d{1,3}[A-Z])\b#"), true, true);

                $node = $this->http->FindSingleNode("./following-sibling::tr[2]", $root, true, "#^(.+?)(Connection via|$)#");

                if (preg_match("#Flight duration:(.+?)(?:,|$)#", $node, $m)) {
                    $s->extra()->duration($m[1]);
                }

                if (preg_match("#distance:(.+?)(?:,|$)#", $node, $m)) {
                    $s->extra()->miles($m[1]);
                }

                if (preg_match("#meal:(.+?)(?:,|$)#", $node, $m)) {
                    $s->extra()->meal($m[1]);
                }

                if (!empty($stops = $this->http->FindSingleNode("./following-sibling::tr[2]", $root, true, "#Connection via:(.+)#"))) {
                    $s->extra()->stops(count(explode(',', $m[1])));
                }
            }
        }

        return $email;
    }

    public function hotel(Email $email)
    {
        $xpath = "//text()[normalize-space() = 'Hotel Information']/following::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $confirmation = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Confirmation:')][1]", $root, true, "#:\s*([A-Z\d]{5,})$#");

            if (!empty($confirmation)) {
                $h->general()->confirmation($confirmation, 'Confirmation');
            } elseif (empty($this->http->FindSingleNode(".//text()[contains(normalize-space(), 'Confirmation')][1]", $root))) {
                $h->general()->noConfirmation();
            }

            $h->general()->traveller($this->http->FindSingleNode("(//text()[normalize-space() = 'Traveler:'])[1]/following::text()[normalize-space()][1]"));

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode("(.//descendant::text()[normalize-space()][1])[1]", $root));

            $node = implode("\n", $this->http->FindNodes(".//table[1]//td[1]//text()", $root));

            if (preg_match("#^([\s\S]+?)\nMap\n#", $node, $m)) {
                $h->hotel()->address(trim(str_replace("\n", ', ', $m[1]), ', '));

                if (preg_match("#Phone:\s*([\d\+\-\(\) \.]+)\n#", $node, $mat)) {
                    $h->hotel()->phone($mat[1]);
                }

                if (preg_match("#Fax:\s*([\d\+\-\(\) \.]+)\n#", $node, $mat)) {
                    $h->hotel()->fax($mat[1]);
                }
            } elseif (preg_match("#^([\s\S]+?)\n([\d \(\)\-]{5,})\nRATE#", $node, $m)) {
                $h->hotel()
                    ->address(trim(str_replace("\n", ', ', $m[1]), ', '))
                    ->phone($m[2]);
            }

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Check in:')][1]", $root, true, "#:\s*(.+)#")))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Check out:')][1]", $root, true, "#:\s*(.+)#")));

            // Price
            $total = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Approx Total:')][1]", $root, true, "#:\s*(.+)#");

            if (!empty($total)) {
                $h->price()
                    ->total($this->amount($total))
                    ->currency($this->currency($total));
            }
            $rate = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'Daily rate:')][1]", $root, true, "#:\s*(.+)#");

            if (!empty($rate)) {
                $r = $h->addRoom();
                $r->setRate($rate);
            }
        }

        return $email;
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

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $this->emailSubject = $parser->getSubject();
        $this->flight($email);
        $this->hotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $email->ota()->code('atpi');
        // General
        $confirmation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation number:')][1]", null, true, "#Reservation number:\s*([A-Z\d]{5,7})\b#");

        if (empty($confirmation) && !empty($this->emailSubject) && preg_match("#AutoItinerary for\s+([A-Z\d]{5,7})\s+-#", $this->emailSubject, $m)) {
            $confirmation = $m[1];
        }

        if (!empty($confirmation)) {
            $email->ota()->confirmation($confirmation);
        }
        $account = $this->http->FindSingleNode("//text()[normalize-space() = 'Traveler Information']/following::table[1]//text()[starts-with(normalize-space(), 'Account number:')]", null, true, "#:\s*([A-Z\d]+)#");

        if (!empty($account)) {
            $email->ota()->account($account, false);
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
        $str = preg_replace("#\s+0{1,2}:(\d+)#", ' 12:$1', $str); // 15 Mar 2017 0:50AM
        $in = [
            "#^\s*(\d{1,2})\s+([^\d\s]+)\s+(\d{4})\s+(\d{1,2}:\d{1,2}(?:\s*[AP]M)?)\s*$#i", // 01 Dec 2017 10:48PM
            "#^\s*(\d{1,2})\s+([^\d\s]+)\s+(\d{4})\s*$#i", // 20 Oct 2016
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        //		if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
        //			if ($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
