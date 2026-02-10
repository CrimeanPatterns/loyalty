<?php

namespace AwardWallet\Engine\disneyvacation\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Invoice extends \TAccountChecker
{
    public $mailFiles = "disneyvacation/it-113749047.eml, disneyvacation/it-161384447.eml, disneyvacation/it-170352513.eml";

    public $detectFrom = [
        'confirmations@reservation.disneydestinations.com',
    ];
    public $detectSubject = [
        // check detectEmailByHeaders if subject not contains 'Disney'
        'Star Wars: Galactic Starcruiser Invoice',
        'A payment has been made towards your Walt Disney World Vacation!',
        'Thank you for making a reservation for a Star Wars: Galactic Starcruiser vacation!',
    ];
    public $detectBody = [
        'en' => ['Payment History', 'ONBOARD ACCOMMODATIONS'],
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'totalPrice' => ['Package Total:', 'Grand Total:'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseHotel($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.disney.')] | //img[contains(@src, '//disneydestinations-')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers["subject"])) {
            return false;
        }

        if ($this->striposAll($headers["subject"], $this->detectSubject) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseHotel(Email $email): void
    {
        $patterns = [
            // 4:19PM    |    2:00 p. m.    |    3pm    |    12 noon
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?|[ ]*noon)?',
        ];

        $h = $email->add()->hotel();

        $generalInfo = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ count(*)=2 and *[1][descendant::img and normalize-space()=''] and *[2]/descendant::text()[{$this->starts($this->t('Confirmation Number'))}] ]"));

        // General
        if (preg_match("/^[ ]*({$this->opt($this->t('Confirmation Number'))})[:\s]+([-A-Z\d]{5,})[ ]*$/m", $generalInfo, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }

        $h->general()
            ->travellers($this->http->FindNodes("//text()[" . $this->eq($this->t("Guests")) . "]/ancestor::td[1][descendant::text()[normalize-space()][1][" . $this->eq($this->t("Guests")) . "]]/descendant::text()[normalize-space()][position() > 1]", null,
                "/^\s*([[:alpha:]]+(?:[ \-][[:alpha:]]+\.?){0,7})\s*(?:\(\d{1,2}\))?$/"))
        ;

        // Hotel
        $hotelName = $address = $roomType = null;

        $hotelText = $this->re("/^{$this->opt($this->t('Package(s)'))}[:\s]*(.{3,})$/", implode(' ', $this->http->FindNodes("//tr[ not(.//tr) and {$this->starts($this->t('Package(s)'))} ]/descendant::text()[normalize-space()]")));

        if (preg_match("/^(.{2,})\s+-\s+(.{2,})$/", $hotelText, $m) > 0
            && preg_match('/(?:Resort|Lodge|Villas|Vista Palace|\bInn\b)/i', $m[1]) > 0
            && preg_match('/(?:Hotel Package|Room Offer Package)/i', $m[2]) > 0
            || preg_match("/^(.*Star Wars.*)$/i", $hotelText, $m) > 0
        ) {
            // Disney's Port Orleans Resort - French Quarter - Disney Resort Hotel Package
            $hotelName = trim($m[1]);
        }

        // it-161384447.eml
        $hotelInfoVal = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ count(*)=2 and *[1][descendant::img and normalize-space()=''] and *[2]/descendant::a[contains(@href,'maps.google.com')] ]"));
        $hotelInfoParts = preg_split('/[ ]*\n+[ ]*/', $hotelInfoVal);

        if (count($hotelInfoParts) === 3) {
            $hotelName = $hotelInfoParts[0];
            $address = $hotelInfoParts[1];
            $roomType = $hotelInfoParts[2];
        }

        $h->hotel()->name($hotelName);

        if ($address) {
            $h->hotel()->address($address);
        } elseif (!$address && $hotelName) {
            $h->hotel()->noAddress();
        }

        if ($roomType) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        // Booked
        $xpathDates = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Arrive:'))}] and *[normalize-space()][2][{$this->starts($this->t('Depart:'))}] ]";

        $dateCheckInVal = $this->re("/^{$this->opt($this->t('Arrive:'))}[:\s]*(.*\d.*)$/", implode(' ', $this->http->FindNodes($xpathDates . "/*[normalize-space()][1]/descendant::text()[normalize-space()]")));
        $dateCheckIn = $this->normalizeDate($dateCheckInVal);
        $dateCheckOutVal = $this->re("/^{$this->opt($this->t('Depart:'))}[:\s]*(.*\d.*)$/", implode(' ', $this->http->FindNodes($xpathDates . "/*[normalize-space()][2]/descendant::text()[normalize-space()]")));
        $dateCheckOut = $this->normalizeDate($dateCheckOutVal);

        $timeCheckIn = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check-in is from'))}]", null, true, "/{$this->opt($this->t('Check-in is from'))}\s+({$patterns['time']})/");

        if ($timeCheckIn) {
            $dateCheckIn = strtotime($timeCheckIn, $dateCheckIn);
        }
        $timeCheckOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check out is at'))}]", null, true, "/{$this->opt($this->t('Check out is at'))}\s+({$patterns['time']})/");

        if ($timeCheckOut) {
            $dateCheckOut = strtotime($timeCheckOut, $dateCheckOut);
        }

        $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

        $totalPrice = '';

        foreach ((array) $this->t('totalPrice') as $phrase) {
            $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($phrase)}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if ($totalPrice !== null) {
                break;
            }
        }

        if (preg_match("/^\s*(?<currency>[^\d\s]{1,5}?)\s*(?<amount>\d[\d., ]*)\s*$/", $totalPrice, $matches)
            || preg_match("/^\s*(?<amount>\d[\d., ]*?)\s*(?<currency>[^\d\s]{1,5})\s*$/", $totalPrice, $matches)
        ) {
            // $7,091.00
            $currency = $this->currency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $h->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if (($this->striposAll($this->http->Response['body'], $dBody) !== false)) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        //$this->logger->error($str);
        $in = [
            // November 16, 2019; Thu, Dec 19, 2019
            "#^\s*(?:\w+[, ]+)?(\w+)\s+(\d+)[, ]+(\d{4})\s*$#u",
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

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

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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

    private function striposAll($text, $needle): bool
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
