<?php

namespace AwardWallet\Engine\frontierairlines\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "frontierairlines/it-1.eml, frontierairlines/it-2.eml";
    public $subjects = [
        '/Reservation Confirmation$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@flyfrontier.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return ($this->http->XPath->query("//text()[contains(normalize-space(), 'flyfrontier')]")->length > 0
                || $this->http->XPath->query("//text()[contains(normalize-space(), 'frontierairlines')]")->length > 0)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flights'))}]")->length > 0
            /*&& $this->http->XPath->query("//text()[{$this->contains($this->t('TOTAL:'))}]")->length > 0*/;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flyfrontier\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->http->SetEmailBody($parser->getHTMLBody());
        $emailType = $this->getEmailType();

        switch ($emailType) {
            case "HTML_Reservations":
                $this->getHtmlEmail($email);

                break;

            case "VirtuallyThereBrief":
                $this->parseVirtuallyThereBrief($email);

                break;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    protected function parseVirtuallyThereBrief(Email $email)
    {
        $http = $this->http;
        $xpath = $http->XPath;
        $f = $email->add()->flight();
        $confirmation = $http->FindSingleNode('//h4[contains(text(), "Confirmation Code:")]', null, true, '/Confirmation Code: (\w+)$/ims');

        if (empty($confirmation)) {
            $confirmation = $http->FindSingleNode('//*[contains(text(), "Frontier Reservation #")]', null, true, '/Frontier Reservation\s*#\s*(\w+)\s*-/ims');
        }
        $f->general()
            ->confirmation($confirmation)
            ->travellers([$http->FindSingleNode('//tr[td[contains(text(), "Passenger(s):")]]/following-sibling::node()[self::tr][1]/td[1]')]);

        $segmentNodes = $xpath->query('
            //tr[
                .//td[contains(text(), "Date")]/
                following-sibling::td[1][contains(text(), "From")]/
                following-sibling::td[contains(text(), "To")]
            ]/following-sibling::tr[1][contains(string(), "am") or contains(string(), "pm")]
            //tr[count(td) = 5]');

        foreach ($segmentNodes as $segmentNode) {
            $s = $f->addSegment();
            // multisegemnt emails stub
            $seats = explode(', ', $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), 'Seat(s):')]/ancestor::tr[1]/following::tr[1]/td[last()]", $segmentNode));

            if (count($seats) > 0) {
                $s->extra()->seats($seats);
            }

            $baseDate = $http->FindSingleNode('.//td[1]', $segmentNode);
            $s->departure()
                ->date(strtotime($baseDate . ' ' . $http->FindSingleNode('.//td[2]/descendant::text()[2]', $segmentNode)))
                ->name(implode(' ', array_filter([
                    $http->FindSingleNode('.//td[2]/descendant::text()[1]', $segmentNode),
                    $http->FindSingleNode('.//td[2]/descendant::text()[3]', $segmentNode),
                ], 'strlen')))
                ->noCode();

            $s->arrival()
                ->date(strtotime($baseDate . ' ' . $http->FindSingleNode('.//td[3]/descendant::text()[2]', $segmentNode)))
                ->name(implode(' ', array_filter([
                    $http->FindSingleNode('.//td[3]/descendant::text()[1]', $segmentNode),
                    $http->FindSingleNode('.//td[3]/descendant::text()[3]', $segmentNode),
                ], 'strlen')))
                ->noCode();

            $s->airline()
                ->name($http->FindSingleNode('.//td[4]', $segmentNode, true, "/^([A-Z\d]{2})\s*/"))
                ->number($http->FindSingleNode('.//td[4]', $segmentNode, true, "/^[A-Z\d]{2}\s*(\d{2,4})\s*$/"));

            $s->setStatus($http->FindSingleNode('.//td[5]', $segmentNode));
        }

        return true;
    }

    private function getHtmlEmail(Email $email)
    {
        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->http->FindSingleNode("//*[contains(text(), 'Reservation Code:')]/ancestor-or-self::tr[1]/td[2]"))
            ->traveller($this->http->FindSingleNode("//*[contains(text(), 'Passengers')]/ancestor-or-self::h2/following::table[1]//tr[1]/td[1]"));

        $paxCount = $this->http->FindSingleNode("//*[contains(text(), 'Fare Breakdown')]/ancestor-or-self::h2/following::table[1]//tr[2]/td[6]", null, true, "/(\d+)\s*$/");

        $cost = $paxCount * $this->http->FindSingleNode("//*[contains(text(), 'Fare Breakdown')]/ancestor-or-self::h2/following::table[1]//tr[2]/td[2]", null, true, "/([\d\.]+)/");
        $tax = $paxCount * $this->http->FindSingleNode("//*[contains(text(), 'Fare Breakdown')]/ancestor-or-self::h2/following::table[1]//tr[2]/td[4]", null, true, "/([\d\.]+)/");
        $fee = $paxCount * $this->http->FindSingleNode("//*[contains(text(), 'Fare Breakdown')]/ancestor-or-self::h2/following::table[1]//tr[2]/td[3]", null, true, "/([\d\.]+)/");
        $total = $this->http->FindSingleNode("//*[contains(text(), 'TOTAL:')]", null, true, "/([\d.]+)/u");

        $f->price()
            ->total($total)
            ->currency($this->http->FindSingleNode("//*[contains(text(), 'TOTAL:')]", null, true, "/[\d.]+\s*([A-Z]{3})/u"))
            ->cost($cost)
            ->fee('Fee', $fee)
            ->tax($tax);

        $segments = $this->http->XPath->query("//*[contains(text(), 'Flights')]/ancestor-or-self::h2[1]/following::table[contains(., 'Fare Type')]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();
            $info = $this->http->FindSingleNode("(.//td[3])[1]", $segment);

            if (preg_match("#^(?:[\w\d]+\s+)?(\d+)\s+(.*?)$#", $info, $m)) {
                $s->airline()
                    ->number($m[1])
                    ->name($m[2]);
            }

            $dep = $this->http->FindSingleNode("(.//td[1])[1]", $segment);
            $arr = $this->http->FindSingleNode("(.//td[2])[1]", $segment);

            if (preg_match("#^(.*?)\s+\((\w{3})\).*?(\d+\s+\w{3}\s+\d+,\s*\d+:\d+\s+\w{2})$#ms", $dep, $m)
                || preg_match("#^(.*?)\s+\((\w{3})\).*?(\d+\/\d+\/\d{4}\,\s*[\d\:]+\s*A?P?M)$#ms", $dep, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2])
                    ->date($this->normalizeDate($m[3]));
            }

            if (preg_match("#^(.*?)\s+\((\w{3})\).*?(\d+\s+\w{3}\s+\d+,\s*\d+:\d+\s+\w{2})$#ms", $arr, $m)
                || preg_match("#^(.*?)\s+\((\w{3})\).*?(\d+\/\d+\/\d{4}\,\s*[\d\:]+\s*A?P?M)$#ms", $arr, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2])
                    ->date($this->normalizeDate($m[3]));
            }

            $s->extra()
                ->aircraft(trim($this->http->FindSingleNode(".//tr[2]/td[1]", $segment), ",.\s"))
                ->cabin($this->http->FindSingleNode(".//td[contains(., 'Fare Type')]", $segment, true, "#:\s*([A-Z][a-z]+)#"));
        }

        return true;
    }

    private function getEmailType()
    {
        if ($this->http->FindSingleNode("//td[@class='confirmation']")) {
            return "HTML_Reservations";
        }

        if ($this->http->FindSingleNode("//*[contains(text(), 'Booking Confirmation')]")) {
            return "HTML_Reservations";
        }

        if ($this->http->FindPreg('/virtuallythere@flyfrontier\.com/ims')) {
            return "VirtuallyThereBrief";
        }

        return false;
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
        $in = [
            '/^(\d+)\s+(\w+)\s+(\d+),\s*(\d+\:\d+\s+\w{2})$/u', // 27 Dec 2013, 08:25 AM
            '/^(\d+)\/(\d+)\/(\d{4})\,\s*([\d\:]+\s*A?P?M)$/u', // 03/17/2009, 12:15 PM
        ];
        $out = [
            '$1 $2 $3, $4',
            '$2.$1.$3, $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match('/\d{1,2}\s+([^\d\s]+)\s+\d{4}/', $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
