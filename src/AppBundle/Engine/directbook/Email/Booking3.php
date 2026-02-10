<?php

namespace AwardWallet\Engine\directbook\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Booking3 extends \TAccountChecker
{
    public $mailFiles = "";

    public $lang;
    public static $dictionary = [
        'en' => [
            // 'Reference number:' => '',
            'Check-in'  => 'Check-in',
            'Check-out' => 'Check-out',
            // 'Your reservation' => '',
            // 'adult' => '',
            // 'child' => '',
            // 'Cancellation' => '',
            'Booked by' => 'Booked by',
            'Booked on' => 'Booked on',
            // 'CHARGES' => '',
            // 'night' => '',
            // 'Total' => '',
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
                !empty($dict['Check-in']) && !empty($dict['Check-out'])
                && !empty($dict['Booked by']) && !empty($dict['Booked on'])
                && $this->http->XPath->query("//*[count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->starts($dict['Check-in'])}] and *[normalize-space()][2][{$this->starts($dict['Check-out'])}]]"
                    . "/following::*[count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->starts($dict['Booked by'])}] and *[normalize-space()][2][{$this->starts($dict['Booked on'])}]]"
                )->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Booked by"]) && !empty($dict["Booked on"])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Booked by'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Booked on'])}]")->length > 0
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
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Reference number:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d]{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booked by'))}]/following::text()[normalize-space()][1]"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booked on'))}]/following::text()[normalize-space()][1]")))
        ;

        $cancellation = $this->http->FindSingleNode("//*[count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($this->t('Cancellation'))}]]/*[normalize-space()][2]");

        if (!empty($cancellation) && mb_strlen($cancellation) < 2000) {
            $h->general()
                ->cancellation($cancellation, true, true);
        }

        // Hotel
        $hotelText = implode("\n", $this->http->FindNodes("//*[count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->starts($this->t('Check-in'))}] and *[normalize-space()][2][{$this->starts($this->t('Check-out'))}]]/following::text()[normalize-space()][1]/ancestor::tr[count(*[normalize-space()]) = 1][*[not(normalize-space())][1]//img]/*[normalize-space()][1]//text()[normalize-space()]"));

        if (empty($hotelText)) {
            $hotelText = implode("\n", $this->http->FindNodes("//*[count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->starts($this->t('Check-in'))}] and *[normalize-space()][2][{$this->starts($this->t('Check-out'))}]]/following::text()[normalize-space()][1]/ancestor::tr[normalize-space()][1]//text()[normalize-space()]"));
        }

        if (preg_match("/^\s*(?<name>.+)\n(?<address>.+)\n(?:\D+[ :])?(?<phone>[\d \+\-\(\)\.]{5,})(?: *Call Center)? *\n\s*\S+@\S+\s*/", $hotelText, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address($m['address'])
                ->phone($m['phone'])
            ;
        } elseif (preg_match("/^\s*(?<name>.+)\n(?<address>.+)\n\s*\S+@\S+\s*/", $hotelText, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address($m['address'])
            ;
        }

        // Booked
        $checkXpath = "//*[count(*[normalize-space()]) = 2][*[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Check-in'))}] and *[normalize-space()][2]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Check-out'))}]]";
        $h->booked()
            ->checkIn($this->normalizeDate(implode(' ', $this->http->FindNodes($checkXpath . "/*[normalize-space()][1]/descendant::text()[normalize-space()][position() > 1]"))))
            ->checkOut($this->normalizeDate(implode(' ', $this->http->FindNodes($checkXpath . "/*[normalize-space()][2]/descendant::text()[normalize-space()][position() > 1]"))))
        ;

        // Rooms
        $reservationText = implode("\n", $this->http->FindNodes("//*[count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($this->t('Your reservation'))}]]/*[normalize-space()][2]//text()[normalize-space()]"));

        $reservationText = preg_replace("/^\s*\d+ [[:alpha:]]+.*\n/", '', $reservationText);
        $roomsText = $this->split("/\n(.+\s+\\/\s+.+\n.*{$this->opt($this->t('adult'))})/", "\n\n" . $reservationText . "\n\n");
        $adults = 0;
        $kids = 0;

        foreach ($roomsText as $rText) {
            $roomRe = "/^(?<desc>(?<type>.+?)(?: *\\/ *(?<rateType>.+))?)(?:\n\|\s*.+)?\n(?<guests>(?: *\d+ *)?{$this->opt($this->t('adult'))}[\s\S]+)\s*$/i";

            if (preg_match($roomRe, $rText, $m)) {
                $h->addRoom()
                    ->setType($m['type'])
                    ->setRateType($m['rateType'], true, true)
                ;
                $roomTypes[] = $m['desc'];

                if (preg_match("/^\s*(\d+)\s*{$this->opt($this->t('adult'))}/iu", $m['guests'], $mat)) {
                    $adults += $mat[1];
                }

                if (preg_match("/,\s*(\d+)\s*{$this->opt($this->t('child'))}/iu", $m['guests'], $mat)) {
                    $kids += $mat[1];
                }
            }
        }

        if (empty($h->getRooms())) {
            $h->addRoom()->setType(null);
        }

        $h->booked()
            ->guests($adults)
            ->kids($kids);

        // Price
        $total = $this->getTotal($this->http->FindSingleNode("//text()[{$this->eq($this->t('CHARGES'))}]/following::text()[{$this->eq($this->t('Total'))}]/ancestor::*[not({$this->eq($this->t('Total'))})][1]",
            null, true, "/^\s*{$this->opt($this->t('Total'))}\s*([^[:alpha:]]+[A-Z]{3}|[A-Z]{3}[^[:alpha:]]+)(?: .+|\s*$)/"));
        $h->price()
            ->total($total['amount'])
            ->currency($total['currency']);

        $priceXpath = "//table[not(.//table)][preceding::text()[{$this->eq($this->t('CHARGES'))}]][following::text()[{$this->eq($this->t('Total'))}]]";

        $feeNodes = $this->http->XPath->query($priceXpath . "[descendant::text()[normalize-space()][1][{$this->eq($this->t('Fees'))}]]//tr[normalize-space()][not({$this->eq($this->t('Fees'))})]");

        foreach ($feeNodes as $fRoot) {
            $name = $this->http->FindSingleNode("./*[normalize-space()][1]", $fRoot);
            $value = $this->getTotal($this->http->FindSingleNode("./*[normalize-space()][2]", $fRoot))['amount'];

            $h->price()
                ->fee($name, $value);
        }
        $feeNodes = $this->http->XPath->query($priceXpath . "[descendant::text()[normalize-space()][1][{$this->eq($this->t('Extras'))}]]//tr[normalize-space()][not({$this->eq($this->t('Extras'))})]");

        foreach ($feeNodes as $fRoot) {
            $name = $this->http->FindSingleNode("./*[normalize-space()][1]", $fRoot);
            $value = $this->getTotal($this->http->FindSingleNode("./*[normalize-space()][2]", $fRoot))['amount'];

            $h->price()
                ->fee($name, $value);
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
            'Rs.'=> 'INR',
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
