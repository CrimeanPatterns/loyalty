<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It3951793 extends \TAccountChecker
{
    public $mailFiles = "avis/it-3951793.eml, avis/it-50653215.eml";

    public static $dictionary = [
        "en" => [
            'Confirmation:' => ['Confirmation:', 'YOUR CONFIRMATION NUMBER:'],
            'Pick-up'       => ['Pick-up', 'Pick Up:'],
            'Return'        => ['Return', 'Return:'],
        ],
    ];

    private $from = ["info@avis", "@e.avis.com"];
    private $subject = [
        "en" => ["your car awaits", "Reservation Reminder", "Rental confirmation"],
    ];

    private static $detectors = [
        'en' => [
            'for renting with us! Your car is reserved',
            'Your car is waiting.',
        ],
    ];
    private $body = 'avis';
    private $lang;

    public function parseHtml(Email $email)
    {
        $reData = '\s([A-z]{3})\s([A-z]{3})\s(\d{1,2}),\s(\d{4})\s\@\s(\d{1,2}:\d{1,2}\s[A-z]{2})';
        $reLoc = '\s[A-z]{3}\s[A-z]{3}\s\d{1,2},\s\d{4}\s\@\s\d{1,2}:\d{1,2}\s[A-z]{2}\s(.+)';
        $r = $email->add()->rental();

        // Number
        $confNo = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Confirmation:')) . "]", null,
            true, "#" . $this->opt($this->t('Confirmation:')) . "\s+(\w+)#");

        if (!empty($confNo)) {
            $r->general()->confirmation($confNo);
        }

        // PickupDatetime
        $pickupDatetime = strtotime($this->normalizeDate($this->getField("Pick-up", 2)));

        if (empty($pickupDatetime)) {
            $pickupDatetime = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Pick-up')) . "]/ancestor::td[1]");

            if (preg_match('/' . $this->opt($this->t('Pick-up')) . $reData . '/', $pickupDatetime, $m)) {
                $pickupDatetime = strtotime($m[2] . ' ' . $m[3] . ' ' . $m[4] . ' ' . $m[5]);
            }
        }

        if (!empty($pickupDatetime)) {
            $r->pickup()->date($pickupDatetime);
        }

        // PickupLocation
        $pickupLocation = $this->getField("Pick-up");

        if (empty($pickupLocation)) {
            $pickupLocation = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Pick-up')) . "]/ancestor::td[1]");

            if (preg_match('/' . $this->opt($this->t('Pick-up')) . $reLoc . '/', $pickupLocation, $m)) {
                $pickupLocation = $m[1];
            }
        }

        if (!empty($pickupLocation)) {
            $r->pickup()->location($pickupLocation);
        }

        // DropoffDatetime
        $dropoffDatetime = strtotime($this->normalizeDate($this->getField("Return", 2)));

        if (empty($dropoffDatetime)) {
            $dropoffDatetime = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Return')) . "]/ancestor::td[1]");

            if (preg_match('/' . $this->opt($this->t('Return')) . $reData . '/', $dropoffDatetime, $m)) {
                $dropoffDatetime = strtotime($m[2] . ' ' . $m[3] . ' ' . $m[4] . ' ' . $m[5]);
            }
        }

        if (!empty($dropoffDatetime)) {
            $r->dropoff()->date($dropoffDatetime);
        }

        // DropoffLocation
        $dropoffLocation = $this->getField("Return");

        if (empty($dropoffLocation)) {
            $dropoffLocation = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Return')) . "]/ancestor::td[1]");

            if (preg_match('/' . $this->opt($this->t('Return')) . $reLoc . '/', $dropoffLocation, $m)) {
                $dropoffLocation = $m[1];
            }
        }

        if (!empty($dropoffLocation)) {
            $r->dropoff()->location($dropoffLocation);
        }

        // CarModel
        $carModel = $this->getField("Your Car");

        if (empty($carModel)) {
            $carModel = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Pick-up')) . "]/preceding::p[1]");
        }

        if (!empty($carModel)) {
            $r->car()->model($carModel);
        }

        // RenterName
        $renterName = $this->http->FindSingleNode("//text()[contains(., \"you're all set to go\") or contains(., \"don't get stuck\")]",
            null, true, "#(\w+), (you're all set to go|don't get stuck)#");

        if (empty($renterName)) {
            $renterName = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t('Thank you')) . "])[1]",
                null, true,
                '/' . $this->opt($this->t('Thank you')) . '(.+)' . $this->opt($this->t(', for renting with us!')) . '/');
        }

        if (!empty($renterName)) {
            $r->general()->traveller($renterName, false);
        }
        // TotalCharge
        $totalCharge = $this->http->FindSingleNode("(//*[" . $this->contains($this->t('Estimated Total')) . "]/following-sibling::td[1])[1]",
            null, true, '/^(?:.)(\d+[\d.,]+$)/');

        if (empty($totalCharge)) {
            $totalCharge = $this->http->FindSingleNode("(//*[" . $this->contains($this->t('Estimated Total')) . "]/following-sibling::tr[1])[1]",
                null, true, '/^(?:.)(\d+[\d.,]+$)/');
        }

        if (!empty($totalCharge)) {
            $r->price()->total($totalCharge);
        }

        // Currency
        $currency = $this->http->FindSingleNode("(//*[" . $this->contains($this->t('Estimated Total')) . "]/following-sibling::td[1])[1]",
            null, true, '/^(.)(?:\d+[\d.,]+$)/');

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("(//*[" . $this->contains($this->t('Estimated Total')) . "]/following-sibling::tr[1])[1]",
                null, true, '/^(.)(?:\d+[\d.,]+$)/');
        }

        if (!empty($currency)) {
            $r->price()->currency($currency);
        }
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->from as $re) {
            if (stripos($from, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $reSubject) {
            foreach ($reSubject as $re) {
                if (stripos($headers["subject"], $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (stripos($text, $this->body) === false) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }
        $this->date = strtotime($parser->getHeader('date'));

        $email->setType('It3951793');
        $this->parseHtml($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
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

    private function getField($field, $n = 1)
    {
        return $this->http->FindSingleNode("//*[normalize-space(text())='{$field}']/ancestor::tr[1]/following-sibling::tr[{$n}]");
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
            "#^\w+\s+(\w+)\s+(\d+),\s+(\d{4})\s+at\s+(\d+:\d+\s+[AP]M)$#",
        ];
        $out = [
            "$2 $1 $3, $4",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function detectBody()
    {
        foreach (self::$detectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Confirmation:"], $words["Pick-up"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Confirmation:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Pick-up'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
