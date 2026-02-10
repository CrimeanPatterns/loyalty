<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use AwardWallet\Schema\Parser\Common\Event;
use PlancakeEmailParser;

class EventReminde extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-18516671.eml, airbnb/it-110481719.eml";

    public $reSubject = [
        "en"=> "Your booking confirmation for",
    ];

    public $reBody2 = [
        "en"  => "Get ready for your upcoming experience",
        'en2' => 'You have successfully rescheduled your reservation',
        'en3' => 'Trip details',
        'en4' => 'experience with',
    ];

    public static $dictionary = [
        "en" => [
            'addressStart' => 'The address is',
            'addressEnd'   => '. Please note',
        ],
    ];

    public $lang = "en";

    public function parseHtml(Email $email): void
    {
        $ev = $email->add()->event();
        $ev->place()->type(Event::TYPE_EVENT);

        // General
        $confirmation = $this->nextText("Reservation Code");
        if ($confirmation) {
            // it-18516671.eml
            $ev->general()
                ->confirmation($confirmation);
        } elseif ($this->http->XPath->query("//tr[{$this->eq($this->t('Trip details'))}]")->length > 0) {
            // it-110481719.eml
            $ev->general()
                ->noConfirmation();
        }

        // Name
        $name = trim($this->http->FindSingleNode("//text()[" . $this->contains("hosted trip with") . "]/following::text()[normalize-space(.)][1]", null, true, "#[^,]+,(.+)#"));

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[" . $this->eq("Experience") . "]/preceding::text()[normalize-space(.)][1]");
        }

        if (empty($name)) {
            $name = implode(". ", array_map(function ($s) {
                return trim($s, " .");
            },
                $this->http->FindNodes("//img[contains(@src, 'slash')]/ancestor::table[1]/preceding::table[normalize-space(.)!=''][1]//text()[normalize-space(.)!='']")));
        }
        $ev->place()
            ->name($name);
        // Address
        $address = $this->http->FindSingleNode("//text()[{$this->contains($this->t('addressStart'))} and {$this->contains($this->t('addressEnd'))}]", null, true, "/{$this->opt($this->t('addressStart'))}\s*(.{3,}?)\s*{$this->opt($this->t('addressEnd'))}/");

        if (empty($address)) {
            $address = $this->nextText("Experience", null, 2);
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("(.//text()[normalize-space(.)='View itinerary']/following::text()[contains(normalize-space(.),'experience with ')])[1]/following::text()[normalize-space(.)][3]");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Meeting location'))}]/following::text()[normalize-space(.)!=''][1]");
        }

        if (empty($address) && !empty($name)
            && !empty($this->http->FindSingleNode("//img[contains(@src, 'slash')]/ancestor::tr[1]/following::text()[normalize-space()][1][".$this->starts(['Sent with ♥ from Airbnb', 'Airbnb, Inc., 888 Brannan St, San Francisco, CA 94103'])."]"))
        ) {
            $email->removeItinerary($ev);
            $email->setIsJunk(true);
            return;
        }
        $ev->place()
            ->address($address);

        // StartDate
        $startDate = strtotime($this->normalizeDate($this->re("#(.*?)\s+-\s+#", $this->nextText("Experience"))));

        // EndDate
        $endDate = strtotime($this->normalizeDate($this->re("#\s+-\s+(.+)#", $this->nextText("Experience"))), $startDate);

        if (empty($startDate) && empty($endDate)) {
            $startDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//img[contains(@src, 'slash')]/ancestor::*[local-name()='td' or local-name()='th'][1]/preceding-sibling::*[local-name()='td' or local-name()='th'][normalize-space()][1]")));
            $endDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//img[contains(@src, 'slash')]/ancestor::*[local-name()='td' or local-name()='th'][1]/following-sibling::*[local-name()='td' or local-name()='th'][normalize-space()][1]")));
            //img[contains(@src, 'slash')]/ancestor::*[local-name()='td' or local-name()='th'][1]/following-sibling::*[local-name()='td' or local-name()='th'][normalize-space()][1]
        }
        $ev->booked()
            ->start($startDate)
            ->end($endDate);


            // Phone
        // DinerName
        $travellers = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Travelers')]/ancestor::*[local-name()='td' or local-name()='th'][1]/following-sibling::*[local-name()='td' or local-name()='th'][normalize-space()][1]");

        if ($travellers) {
            // it-18516671.eml
            $ev->general()
                ->travellers(explode(',', $travellers));
        } else {
            // it-110481719.eml
            $ev->general()
                ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/^{$this->opt($this->t('Hi'))}\s+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[,:;!?]|$)/u"));
        }

        // Guests
        $guest = $this->re("#^(\d+)$#", $this->nextText('Guests'));
        if (!empty($guest)) {
            $ev->booked()
                ->guests($guest);
        }

        // TotalCharge
        // Currency
        $totalPayment = $this->nextText("Total");

        if ($totalPayment !== null) {
            $ev->price()
                ->total($this->amount($totalPayment))
                ->currency($this->currency($totalPayment));
        }

    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'admin@airbnb.com') !== false || stripos($from, 'express@airbnb.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".airbnb.") or contains(@href,"/abnb.me/")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Sent with ♥ from Airbnb")]')->length === 0
        ) {
            return false;
        }

        $body = $parser->getHTMLBody();

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

        $this->http->FilterHTML = true;

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
        return count(self::$dictionary);
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
        $str = preg_replace("#[^\d\w\s\,.:]#u", "", $str);
        $in = [
            "#^[^\s\d]+,\s*([^\s\d]+)\s*(\d+)\s+(\d+:\d+\s+[AP]M)$#", //Sun, Sep 3 10:30 AM
            "#^[^\s\d]+\s+([^\s\d]+)\s*(\d+),\s*(\d{4})\s+(\d+:\d+\s+[AP]M)$#", //Saturday ‌N‌o‌v‌ ‌2‌5‌,‌ ‌2‌0‌1‌7‌ ‌5‌:‌0‌0‌ ‌P‌M‌
        ];
        $out = [
            "$2 $1 $year, $3",
            "$2 $1 $3, $4",
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
            '£'=> 'GBP',
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

        return $s;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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
