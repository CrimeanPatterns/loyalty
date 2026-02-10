<?php

namespace AwardWallet\Engine\fcmtravel\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicketHtml extends \TAccountChecker
{
    public $mailFiles = "fcmtravel/it-21455575.eml, fcmtravel/it-22236728.eml, fcmtravel/it-22236772.eml, fcmtravel/it-22236859.eml";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";
    public $date;

    private $detectFrom = "fcm.travel";
    private $detectSubject = [
        "en" => "E-Ticket - (",
    ];
    private $detectCompany = 'FCM Travel Solutions';
    private $detectBody = [
        "en" => "Please print this e-ticket and ",
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $subject) {
            if (isset($headers['subject']) && stripos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response["body"];

        if (strpos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if (strpos($body, $dBody) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response["body"];

        foreach ($this->detectBody as $lang => $dBody) {
            if (strpos($body, $dBody) !== false) {
                $this->lang = $lang;
            }
        }

        $this->date = strtotime($parser->getHeader('date'));

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

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

    private function parseEmail(Email $email)
    {
        $email->ota();

        if ($this->parseFlight($email) == false) {
            return false;
        }

        if ($this->parseHotel($email) == false) {
            return false;
        }

        return true;
    }

    private function parseFlight(Email $email)
    {
        $xpath = "//text()[" . $this->starts($this->t('dep.')) . "]/ancestor::tr[1]";
        //		$this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $airline = $this->http->FindSingleNode("./td[2]", $root, true, "#^\s*([A-Z\d]{2})\s*#");

            if (empty($airline)) {
                return false;
            }
            $airs[$airline][] = $root;
        }

        foreach ($airs as $airline => $roots) {
            $f = $email->add()->flight();

            // General
            $f->general()
                ->noConfirmation()
                ->travellers(array_filter($this->http->FindNodes("///text()[" . $this->eq($this->t('Passengers')) . "]/ancestor::tr[1]/following-sibling::tr/td[1][contains(.,'/')]", null, "#^([A-Z\-\. ]+/[A-Z\-\. ]+)$#")))
            ;

            // Issued
            $tickets = array_filter(array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t('Ticket numbers')) . "]/ancestor::tr[1]/following-sibling::tr", null, "#^\s*" . $airline . "[\s:]+([\d\- ]{10,})\s*(?:\(|FOR)#")));

            if (!empty($tickets)) {
                $f->issued()
                    ->tickets($tickets, false);
            }
            $confs = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t('Airline record locators')) . "]/ancestor::tr[1]/following-sibling::tr", null, "#^\s*" . $airline . "\s+([A-Z\d]{5,})\s*$#"));

            foreach ($confs as $conf) {
                $f->issued()
                    ->confirmation($conf, false);
            }
            $f->issued()
                ->name($airline);

            foreach ($roots as $root) {
                $s = $f->addSegment();

                // Airline
                $node = $this->http->FindSingleNode("./td[2]", $root);

                if (preg_match("#^\s*([A-Z\d]{2})\s*(\d+)\s*$#", $node, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2])
                        ->operator($this->http->FindSingleNode("./following-sibling::tr[2][" . $this->contains("operated by") . "]", $root, true, "#operated by\s*(.+)#"), true, true);
                }

                $date = $this->normalizeDate($this->http->FindSingleNode("./td[1]", $root));

                if (empty($date)) {
                    return false;
                }

                // Departure
                $s->departure()
                    ->noCode()
                    ->name($this->http->FindSingleNode("./td[3]", $root))
                    ->date(($time = $this->http->FindSingleNode("./td[5]", $root, true, "#dep\.\s*(.+)#")) ? strtotime($time, $date) : null)
                ;

                $date2 = $this->http->FindSingleNode("./td[6]", $root, true, "#\((.+)\)#");

                if (!empty($date2)) {
                    $date = $this->normalizeDate($date2);
                }

                // Arrival
                $s->arrival()
                    ->noCode()
                    ->name($this->http->FindSingleNode("./td[4]", $root))
                    ->date(($time = $this->http->FindSingleNode("./td[6]", $root, true, "#arr\.\s*(.+?)(?:\(|$)#")) ? strtotime($time, $date) : null)
                ;

                $node = $this->http->FindSingleNode("./following-sibling::tr[1]", $root);

                if (preg_match("#Seats:(.+?)(?:\||$)#", $node, $m) && preg_match_all("#(?:^|\W)(\d{1,3}[A-Z])\b#", $m[1], $mat)) {
                    $s->extra()->seats($mat[1]);
                }

                if (preg_match("#Flight time:(.+?)(?:\||$)#", $node, $m)) {
                    $s->extra()->duration($m[1]);
                }

                if (preg_match("#Eq\.:(.+?)(?:\||$)#", $node, $m)) {
                    $s->extra()->aircraft($m[1]);
                }

                if (preg_match("#Class:(.+?)(?:\||$)#", $node, $m)) {
                    $s->extra()->cabin($m[1]);
                }

                if (preg_match("#Dep\. term\.:(.*?)(?:\||$)#", $node, $m)) {
                    $s->departure()->terminal(trim($m[1]), true);
                }

                if (preg_match("#Arr\. term\.:(.*?)(?:\||$)#", $node, $m)) {
                    $s->arrival()->terminal(trim($m[1]), true);
                }
            }
        }

        return true;
    }

    private function parseHotel(Email $email)
    {
        $xpath = "//table[" . $this->eq($this->t('Hotel')) . "]/following-sibling::table[" . $this->starts($this->t('Hotel name')) . "]";
        //		$this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // General
            $conf = $this->http->FindSingleNode(".//text()[" . $this->starts('Confirmation number') . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            if (!empty($conf) && preg_match("#^\s*([A-Z\d]{5,})\b#", $conf, $m)) {
                $h->general()
                    ->confirmation($this->http->FindSingleNode(".//text()[" . $this->starts('Confirmation number') . "]/ancestor::td[1]/following-sibling::td[1]", $root));
            } else {
                $h->general()->noConfirmation();
            }
            $h->general()
                ->travellers(array_filter($this->http->FindNodes("///text()[" . $this->eq($this->t('Passengers')) . "]/ancestor::tr[1]/following-sibling::tr/td[1][contains(.,'/')]", null, "#^([A-Z\-\. ]+/[A-Z\-\. ]+)$#")));

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode(".//text()[" . $this->starts('Hotel name') . "]/ancestor::td[1]/following-sibling::td[1]", $root))
                ->address($this->http->FindSingleNode(".//text()[" . $this->starts('Address') . "]/ancestor::td[1]/following-sibling::td[1]", $root))
                ->phone($this->http->FindSingleNode(".//text()[" . $this->starts('Telephone') . "]/ancestor::td[1]/following-sibling::td[1]", $root));

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->starts('Arrival') . "]/ancestor::td[1]/following-sibling::td[1]", $root)))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode(".//text()[" . $this->starts('Departure') . "]/ancestor::td[1]/following-sibling::td[1]", $root)))
            ;

            // Room
            $h->addRoom()
                ->setType($this->http->FindSingleNode(".//text()[" . $this->starts('Room type') . "]/ancestor::td[1]/following-sibling::td[1]", $root))
                ->setRate($this->http->FindSingleNode(".//text()[" . $this->starts('Roomrate') . "]/ancestor::td[1]/following-sibling::td[1]", $root), true, true);
        }

        return true;
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
        $year = date("Y", $this->date);

        $in = [
            "#^\s*([^\d\s]+)\s+(\d+)\s+([^\d\s]+)\s*$#u", //Mo 2 Oct
            "#^\s*(\d+)\s*([^\d\s]+)\s*$#u", //02OCT
        ];
        $out = [
            "$1, $2 $3 $year",
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^(\w+),\s+(\d+\s+\w+\s+\d{4})#u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m[1], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m[2], $weeknum);

            return $str;
        } else {
            $str = strtotime($str);

            if ($str < $this->date - 60 * 24 * 60) {
                $str = strtotime("+1year", $str);
            }

            return $str;
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
