<?php

namespace AwardWallet\Engine\airasia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmed extends \TAccountChecker
{
    public $mailFiles = "airasia/it-95896566.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            //            '' => [''],
        ],
    ];

    private $detectSubject = [
        "en" => "Your AirAsia booking has been confirmed.",
    ];

    private $detectBody = [
        "en" => [
            "Your Booking is Confirmed",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.airasia.')] | //text()[contains(., 'Your AirAsia booking has been confirmed')]")->length == 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                if (self::detectEmailFromProvider($headers['from']) !== true || stripos($headers["subject"], 'AirAsia') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/@(?:.+\.)*airasia\./i', $from) > 0; // noreply@booking.airasia.co.in
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
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->travellers(array_unique(array_filter($this->http->FindNodes("//img[contains(@src, 'Guest.png')]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, "#^\s*(?:(?:MS|MR)\.? )?(.+)\s*$#i"))))
        ;

        // Status
        if (!empty($this->http->FindSingleNode("(//text()[{$this->contains($this->t('Your Booking is Confirmed'))}])[1]"))) {
            $f->general()->status('confirmed');
        }

        // Price
        $totalCharge = $this->http->FindSingleNode("//td[not(.//td) and " . $this->starts($this->t('View Payment Details')) . "]/ancestor::tr[1]/td[normalize-space()][1]");

        if (preg_match("/^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $totalCharge, $m)
            || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$/", $totalCharge, $m)) {
            $f->price()
                ->total(PriceHelper::cost(trim($m['amount']), ',', '.'))
                ->currency($this->currency(trim($m['curr'])));
        }

        // Segments
        $xpath = "//text()[{$this->starts($this->t('PNR:'))}]/ancestor::tr[1]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("descendant::td[not(.//td) and normalize-space()][1]", $root);

            if (preg_match("/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            }

            $date = $this->http->FindSingleNode("descendant::td[not(.//td) and normalize-space()][2]", $root);

            $conf = $this->http->FindSingleNode("descendant::text()[" . $this->starts($this->t("PNR:")) . "]", $root, true,
                "/" . $this->preg_implode($this->t("PNR:")) . "\s*([A-Z\d]{5,7})\s*$/");
            $s->airline()
                ->confirmation($conf);

            // Departure
            // Arrival
            $xpathTime = 'contains(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd")';
            $info = implode("\n", $this->http->FindNodes("following::text()[{$xpathTime}][1]/ancestor::*[count(.//text()[{$xpathTime}]) = 2][1]//text()[normalize-space()][1]", $root));

            if (preg_match("/^\s*(?<dtime>\d+:\d+.*)\s*\n(?<dcode>[A-Z]{3})\s*\n(?:[\s\S]*\n)?(?<atime>\d+:\d+.*)\s*\n\s*(?<acode>[A-Z]{3})\s*\n/", $info, $m)) {
                $s->departure()
                    ->code($m['dcode'])
                    ->date(!empty($date) ? $this->normalizeDate($date . $m['dtime']) : null);

                $s->arrival()
                    ->code($m['acode'])
                    ->date(!empty($date) ? $this->normalizeDate($date . $m['atime']) : null);
            }

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode("following::tr[not(.//tr)][normalize-space()][1]", $root, true,
                    "/^\s*((?: ?\d{1,2} ?(?:h|m))+)\s*$/"))
            ;
            $seats = array_filter($this->http->FindNodes("following::img[contains(@src, 'Guest.png')][1]/ancestor::*[not(" . $this->contains($this->t("PNR:")) . ")][last()]//td[not(.//td) and .//img[contains(@src, 'Seat.png')]]/following::td[1]", $root));

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }
        }

        return $email;
    }

    private function assignLang(): bool
    {
        foreach ($this->detectBody as $lang => $detects) {
            if ($this->http->XPath->query("//node()[{$this->contains($detects)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(normalize-space(' . $text . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$date = '.print_r( $str,true));
        $in = [
            //			"#^\s*(\d+:\d+(?:\s*[AP]M)?)\s+[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})\s*$#i",//14:25 Fri 05 Apr 2019
        ];
        $out = [
            //			"$2 $3 $4, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function currency($s)
    {
        if (preg_match("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $s;
        }
        $sym = [
            '₹' => 'INR',
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
