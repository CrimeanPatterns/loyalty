<?php

namespace AwardWallet\Engine\directbook\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Booking4 extends \TAccountChecker
{
    public $mailFiles = "";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Your reservation details' => 'Your reservation details',
            // 'Reference Number' => '',
            'Check in Date'  => 'Check in Date',
            'Check out Date' => 'Check out Date',
            // 'Guest ETA' => '',
            // 'Guest' => '',
            // 'Occupancy' => '',
            // 'Room cost' => '',
            // 'Cost breakdown' => '',
            'Show on map' => 'Show on map',
            // 'Cancellation Policy' => '',
        ],
    ];

    private $detectFrom = ["donotreply@book-directonline.com", 'donotreply@app.thebookingbutton.com',
        'donotreply@reservation.easybooking-asia.com', 'donotreply@bookings.skytouchhos.com', ];

    private $detectSubject = [
        // en
        'Online Booking For',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.](?:book-directonline|thebookingbutton|easybooking-asia|direct-book|skytouchhos)\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->containsText($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach (self::$dictionary as $dict) {
            if (
                !empty($dict['Your reservation details']) && !empty($dict['Check in Date'])
                && !empty($dict['Check out Date']) && !empty($dict['Show on map'])
                && $this->http->XPath->query("//node()[{$this->eq($dict['Your reservation details'])}]/following::node()[{$this->eq($dict['Check in Date'])}]"
                    . "/following::node()[{$this->eq($dict['Check out Date'])}]/following::node()[{$this->eq($dict['Show on map'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Your reservation details"]) && !empty($dict["Check in Date"])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Your reservation details'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Check in Date'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reference Number'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest'))}]/following::text()[normalize-space()][1]"))
        ;

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy'))}]/following::text()[normalize-space()][1]/ancestor::*[descendant::text()[normalize-space()][1][{$this->eq($this->t('Cancellation Policy'))}]][1]",
            null, true, "/^\s*{$this->opt($this->t('Cancellation Policy'))}\s*(.+)/");

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation, true, true);
        }

        // Hotel
        $hotelXpath = "//tr[count(*[normalize-space()]) = 2][*[normalize-space()][1][descendant::text()[normalize-space()][last()][{$this->eq($this->t('Show on map'))}]]]";
        $h->hotel()
            ->name($this->http->FindSingleNode($hotelXpath . "/preceding::text()[normalize-space()][1]/ancestor::h2[1]"))
            ->address($this->http->FindSingleNode($hotelXpath . "/*[normalize-space()][1]"))
            ->phone($this->http->FindSingleNode($hotelXpath . "/*[normalize-space()][2]"))
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check in Date'))}]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Check out Date'))}]/following::text()[normalize-space()][1]")))
        ;
        $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest ETA'))}]/following::text()[normalize-space()][1]");

        if (!empty($time) && !empty($h->getCheckInDate())) {
            $h->booked()
                ->checkIn(strtotime($time, $h->getCheckInDate()));
        }

        $h->booked()
            ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Occupancy'))}]/following::text()[normalize-space()][1]",
                null, true, "/\b(\d+)\s*{$this->opt($this->t('adult'))}/"))
            ->kids($this->http->FindSingleNode("//text()[{$this->eq($this->t('Occupancy'))}]/following::text()[normalize-space()][1]",
                null, true, "/\b(\d+)\s*{$this->opt($this->t('child'))}/"));

        // Rooms
        $r = $h->addRoom();
        $r
            ->setType($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest'))}]/preceding::text()[normalize-space()][1]/ancestor::h2[1]",
                null, true, "/(.+) *\/ *.+/"))
            ->setRateType($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest'))}]/preceding::text()[normalize-space()][1]/ancestor::h2[1]",
                null, true, "/.+ *\/ *(.+)/"))
        ;

        $rates = $this->http->FindNodes("//tr[count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($this->t('Cost breakdown'))}]]/*[normalize-space()][2][.//text()[{$this->eq($this->t('Rate'))}]]//tr[count(*[normalize-space()]) = 2]/*[2][not({$this->eq($this->t('Rate'))})]");
        $nights = $this->http->FindSingleNode("//tr[count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($this->t('Check out Date'))}]]/*[normalize-space()][2]",
            null, true, "/\(\s*(\d+)\s*\w+\)/");

        if ($nights === count($rates)) {
            $r->setRates($rates);
        }

        // Price
        $priceText = implode("\n", $this->http->FindNodes("//tr[count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($this->t('Room cost'))}]]/*[normalize-space()][2]//text()[normalize-space()]"));
        $this->logger->debug('$priceText = ' . print_r($priceText, true));

        if (preg_match("/^\s*(?<total>\S.+?)\s*\(including Tax\)\s*(?<tax>.+?)\s*\(Tax included\)/", $priceText, $mat)) {
            $total = $this->getTotal($mat['total']);
            $h->price()
                ->total($total['amount'])
                ->currency($total['currency'])
            ;
            $tax = $this->getTotal($mat['tax']);
            $h->price()
                ->tax($tax['amount'])
            ;
        }

        return true;
    }

    private function nextTd($field, $regexp = null)
    {
        return $this->http->FindSingleNode("//text()[{$this->eq($field)}]/ancestor::*[{$this->eq($field)}]/following-sibling::*[normalize-space()][1]",
            null, true, $regexp);
    }

    private function nextTds($field, $regexp = null)
    {
        return $this->http->FindNodes("//text()[{$this->eq($field)}]/ancestor::*[{$this->eq($field)}]/following-sibling::*[normalize-space()][1]",
            null, $regexp);
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
        ];
        $out = [
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }
//        if (
//            preg_match("/Free cancellation before (?<day>\d+)\-(?<month>\D+)\-(?<year>\d{4}) (?<time>\d+:\d+)\./i", $cancellationText, $m)
//        ) {
//            $h->booked()
//                ->deadline($this->normalizeDate($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time']));
//        }
//
//        if (
//            preg_match("/Free cancellation before  (?<date>.+{6,40}), (?<time>\d+:\d+)\./i", $cancellationText, $m)
//        ) {
//            $h->booked()
//                ->deadline2($this->normalizeDate($m['date'] . ', ' . $m['time']));
//        }
//
//        if (
//            preg_match("/This reservation is non-refundable/i", $cancellationText, $m)
//        ) {
//            $h->booked()
//                ->nonRefundable();
//        }
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount'], $m['currency']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function currency($s)
    {
        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        $sym = [
            '$'=> 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
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

    private function amount($value, $currency)
    {
        $value = PriceHelper::parse($value, $currency);

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
