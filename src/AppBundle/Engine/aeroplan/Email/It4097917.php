<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It4097917 extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-31054926.eml, aeroplan/it-4097917.eml, aeroplan/it-4104419.eml, aeroplan/it-4104420.eml, aeroplan/it-488750094.eml, aeroplan/it-6941605.eml, aeroplan/it-6941625.eml";

    public static $dictionary = [
        "en" => [
            "Your booking reference is:" => ["Your booking reference is:", "Booking Reference:"],
        ],
        "fr" => [
            "Your booking reference is:" => ["Votre numéro de dossier", "Numéro de Réservation:"],
            "Passengers:"                => "Passagers",
            "From"                       => "De",
            "Class"                      => "Classe",
        ],
    ];

    private $reFrom = "ets@aircanada.";
    private $reSubject = "Air Canada";
    private $reSubject2 = [
        "en" => "Booking Update",
        "Cancelled booking confirmation:",
        "fr" => "servation", //réservation
    ];
    private $date;
    private $reBody = 'Air Canada';
    private $reBody2 = [
        "en"  => "Planned Flights:",
        "en2" => "Cancelled Flights:",
        "en3" => "Other Airlines Ticketing - Email Confirmation",
        "fr"  => "Renseignements sur la r", //éservation
        "fr2" => "Vols programmés:", //éservation
    ];

    private $lang = "en";

    public function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->nextText($this->t("Your booking reference is:")))
            ->travellers($this->http->FindNodes("(//text()[starts-with(normalize-space(.), '{$this->t("Passengers:")}')])[1]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space(.)!='']"));

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your Booking has been cancelled'))}]/ancestor::tr[1]/following-sibling::tr[./td[2]]")->length > 0) {
            $f->general()
                ->status('Cancelled')
                ->cancelled();
        }

        $xpath = "//text()[normalize-space(.)='" . $this->t("From") . "']/ancestor::tr[1]/following-sibling::tr[./td[2]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        if (!empty($this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Class") . "']/ancestor::tr[1][" . $this->contains($this->t("Class")) . "]/td[6][normalize-space(.)='" . $this->t("Class") . "']"))) {
            $column = [
                "flight"  => 1,
                "depdate" => 2,
                "from"    => 3,
                "to"      => 4,
                "class"   => 6,
                "deptime" => 7,
                "arrtime" => 8,
                "arrdate" => 9,
            ];
        } else {
            $column = [
                "flight"  => 2,
                "from"    => 3,
                "to"      => 4,
                "depdate" => 5,
                "deptime" => 6,
                "arrtime" => 7,
                "class"   => 9,
            ];
        }

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            if (isset($column['depdate'])) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./td[" . $column['depdate'] . "]", $root));
            }

            if (isset($column['flight'])) {
                $s->airline()
                    ->name($this->http->FindSingleNode("./td[" . $column['flight'] . "]", $root, true, "#^(\w{2})\d+$#"))
                    ->number($this->http->FindSingleNode("./td[" . $column['flight'] . "]", $root, true, "#^\w{2}(\d+)$#"));
            }

            if (isset($column['from'])) {
                $s->departure()
                    ->code($this->http->FindSingleNode("./td[" . $column['from'] . "]", $root));
            }

            if (isset($column['deptime']) && !empty($date)) {
                $s->departure()
                    ->date(strtotime($this->http->FindSingleNode("./td[" . $column['deptime'] . "]", $root), $date));
            }

            if (isset($column['to'])) {
                $s->arrival()
                    ->code($this->http->FindSingleNode("./td[" . $column['to'] . "]", $root));
            }

            if (!empty($column['arrdate']) && !empty($time = $this->http->FindSingleNode("./td[" . $column['arrdate'] . "]", $root))) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./td[" . $column['arrtime'] . "]", $root));

                if (!empty($date)) {
                    $s->arrival()
                        ->date(strtotime($time, $date));
                }
            } else {
                $time = $this->http->FindSingleNode("./td[" . $column['arrtime'] . "]", $root, true, "/^(\d+\:\d+)$/");

                if (empty($time)) {
                    $column['arrtime'] = $column['arrtime'] + 1;
                    $time = $this->http->FindSingleNode("./td[" . $column['arrtime'] . "]", $root, true, "/^(\d+\:\d+)$/");
                }

                if (!empty($date) && !empty($time)) {
                    $s->arrival()
                       ->date(strtotime($time, $date));
                }
            }

            if (isset($column['class'])) {
                $bookingCode = $this->http->FindSingleNode("./td[" . $column['class'] . "]", $root, true, "/^([A-Z])$/");

                if (empty($bookingCode)) {
                    $column['class'] = $column['class'] + 1;
                    $bookingCode = $this->http->FindSingleNode("./td[" . $column['class'] . "]", $root, true, "/^([A-Z])$/");
                }
                $s->extra()
                    ->bookingCode($bookingCode);
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false && strpos($headers["subject"], $this->reSubject) === false) {
            return false;
        }

        foreach ($this->reSubject2 as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false
            && $this->http->XPath->query("//a[contains(@href, 'aircanada.com')]")->length === 0) {
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
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

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
        return count(self::$dictionary) * 2; // different column position
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[{$this->starts($field)}])[{$n}]/following::text()[normalize-space(.)!=''][1]", $root);
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
//        $this->http->log('$str = '.print_r( $str,true));
        $year = date("Y", $this->date);
        $in = [
            "#^\s*(\d+)\s+(\w+)\s*$#u",
            "#^\s*([^\d\s]+)\s+(\d+)\s+([^\d\s]+)$#u",
        ];
        $out = [
            "$1 $2 $year",
            "$1, $2 $3 $year",
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], 'en')) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^\s*(?<week>[^\s\d\.\,]+), (?<date>.*\d{4}.*)\s*$#", $str, $m)) {
            $weekDayNumber = WeekTranslate::number1($m['week'], $this->lang);

            if (empty($weekDayNumber)) {
                $weekDayNumber = WeekTranslate::number1($m['week'], 'en');
            }

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weekDayNumber);
        }

        if (!preg_match("#\b\d{4}\b#", $str)) {
            return null;
        }

        return strtotime($str);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
