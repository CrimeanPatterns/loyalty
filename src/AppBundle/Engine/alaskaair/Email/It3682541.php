<?php

namespace AwardWallet\Engine\alaskaair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3682541 extends \TAccountChecker
{
    public $mailFiles = "alaskaair/it-10115425.eml, alaskaair/it-30581549.eml, alaskaair/it-3682541.eml, alaskaair/it-4161880.eml, alaskaair/it-6200013.eml, alaskaair/it-6222184.eml, alaskaair/it-6229539.eml, alaskaair/it-625159738.eml, alaskaair/it-6292141.eml, alaskaair/it-6325044.eml";

    public $reFrom = "service@ifly.alaskaair.com";
    public $reSubject = [
        "en"  => "Ready for your trip to",
        "en2" => "you've been upgraded",
        "en3" => "Check in now for your flight to",
        "en4" => "Make the Most of Your Trip to",
        "en5" => "Your flight is delayed",
    ];
    public $reBody = 'Alaska Airlines';
    public $reBody2 = [
        "en" => "Depart",
    ];
    public $reBodyDetect = [
        'str' => [
            "Connecting flight details",
            "Your trip details:",
            "Your Trip Details",
            "Check In & start your journey",
            "Plan for your trip",
            "Check in",
            'You may still receive transactional messages from Alaska Airlines',
            'You\'ve been upgraded',
            'Gate change',
        ],
        'reg' => [
            "#Flight\s+\d+\s+on\s+time#",
            "#You've been upgraded#",
        ],
    ];
    public $subj;
    public $date;

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(Email $email)
    {
        $needCorrectDate = false; //for emails with subject "Flight\s+\d+\s+on\s+time"
        $flightNum = null; //flight number after which the year should coincide with the year of email

        $f = $email->add()->flight();

        // TripNumber
        // Passengers
        $travellers = array_map(function ($s) {
            return trim($s, ', ');
        }, array_unique($this->http->FindNodes("//text()[normalize-space(.)='Seat assignment(s):']/following::text()[normalize-space(.)][1]")));

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//text()[contains(normalize-space(.),'Travelers:')]/ancestor::tr[1]/following-sibling::tr[position()<last()]");
        }

        if (count($travellers) > 0) {
            $f->general()
                ->travellers($travellers);
        }

        $f->general()
            ->confirmation($this->http->FindSingleNode("(//text()[starts-with(normalize-space(.), 'Confirmation code:') or starts-with(normalize-space(.), 'Confirmation Code:')])[1]", null, true, "#:\s+(\w+)#"));

        if (preg_match("#Flight\s+(\d+)\s+on\s+time#", $this->subj, $m)) {
            $needCorrectDate = true;
            $flightNum = $m[1];
            $this->date = strtotime($this->http->FindSingleNode("//text()[contains(.,'Please read these important flight details')]/following::text()[normalize-space(.)][1]", null, true, "#As of\s+(.+)\s+PT#"));
        }

        $xpath = "//text()[starts-with(normalize-space(.), 'Depart')]/ancestor::tr[.//img[contains(@src, '/AirlineLogos/')]][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::text()[contains(.,',') and translate(substring(normalize-space(.),string-length(normalize-space(.))-1),'0123456789','dddddddddd')][1]", $root));

            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode(".//img[contains(@src, '/AirlineLogos/')]/@src", $root, true, "#/AirlineLogos/(\w{2})\.#"))
                ->number($this->http->FindSingleNode("./descendant::text()[contains(translate(., '1234567890', 'dddddddddd'), 'd')][1]", $root, true, "#^\d+$#"));

            if (!($time = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), 'Depart')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", $root, true, "#\d+:\d+.*#"))) {
                $time = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), 'Depart')]/following::text()[normalize-space(.)][1]", $root, true, "#\d+:\d+.*#");
            }

            if (!empty($date) && !empty($time)) {
                $depDate = strtotime($time, $date);
            }

            $s->departure()
                ->code($this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), 'Depart')]", $root, true, "#\(([A-Z]{3})\)#"))
                ->date($depDate);

            $depTerminal = $this->http->FindSingleNode(".//text()[normalize-space(.)='Term/Gate']/ancestor::tr[1]/following-sibling::tr[1]", $root, null, "#(.*?)\/#");
            $s->departure()
                ->terminal($depTerminal, true, true);

            if (!($time = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), 'Arrive')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]", $root, true, "#\d+:\d+.*#"))) {
                $time = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), 'Arrive')]/following::text()[normalize-space(.)][1]", $root, true, "#\d+:\d+.*#");
            }

            if (!empty($date) && !empty($time)) {
                $arrDate = strtotime($time, $date);
            }

            $s->arrival()
                ->date($arrDate)
                ->code($this->http->FindSingleNode(".//text()[starts-with(normalize-space(.), 'Arrive')]", $root, true, "#\(([A-Z]{3})\)#"));

            $cabin = $this->http->FindSingleNode("./following-sibling::tr[2]", $root, null, "#,\s+([^,]+)$#");

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $seats = array_filter([$this->http->FindSingleNode(".//text()[normalize-space(.)='Seat']/ancestor::tr[1]/following-sibling::tr[1]/td[2]", $root, true, "/^\s*(\d{1,3}[A-Z])\s*$/")]);

            if (empty($seats)) {
                for ($i = 2; $i < 15; $i++) {
                    if ($this->http->FindSingleNode("./following-sibling::tr[{$i}][.//a and not(.//img)]", $root)) {
                        $seats[] = $this->http->FindSingleNode("./following-sibling::tr[{$i}]//a", $root,
                            true, "/^\s*(\d{1,3}[A-Z])\s*$/");
                    } else {
                        break;
                    }
                }
                $s->extra()
                    ->seats(array_filter($seats));
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->reFrom) === false) {
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

        $pos = max(strpos($body, "Please see details below"), strpos($body, "Confirmation code"));

        if (!$pos) {
            $text = "";
        } else {
            $text = substr($body, 0, $pos);
        }

        foreach ($this->reBodyDetect as $type => $re) {
            if ($type === 'str') {
                foreach ($re as $r) {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),\"" . $r . "\")]")->length > 0) {
                        return true;
                    }
                }
            }

            if ($type === 'reg') {
                foreach ($re as $r) {
                    if (preg_match($r, $text)) {
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

        $this->subj = $parser->getSubject();

        $this->http->FilterHTML = false;
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
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
            "#^\s*([^\d\s]+)\s*,\s+([^\d\s]+)\s+(\d+)$#",
        ];
        $out = [
            "$1, $3 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        $str = $this->dateStringToEnglish($str);

        if (preg_match("#^(?<week>[\w\-]+), (?<date>\d+ \w+ .+)#u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b20\d{2}\b#", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }
}
