<?php

namespace AwardWallet\Engine\dresorts\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmed extends \TAccountChecker
{
    public $mailFiles = "dresorts/it-91620589.eml";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            //            '' => ['', ''],
        ],
    ];

    private $detectFrom = '@Diamondresorts.com';

    private $detectSubject = [
        'Your Reservation has been confirmed', // Thank You for Your Reservation at Santa Barbara Golf And Ocean Club By Diamond Resorts!  Your Reservation has been confirmed  534878416
    ];

    private $detectCompany = ['Diamond Resorts Team', 'Diamond Resorts Holdings'];

    private $detectBody = [
        'Please review your reservation information for your upcoming stay',
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[" . $this->contains($this->detectCompany) . "]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//text()[" . $this->contains($this->detectBody) . "]")->length > 0) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->nextText($this->t('Confirmation Number:')), 'Confirmation Number')
            ->traveller($this->nextText($this->t('Guest Information')), true)
        ;

        // Hotel
        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[" . $this->eq($this->t("Hotel Information")) . "]/ancestor::td[1]//text()[normalize-space()]"));

        if (preg_match("/Hotel Information\s*\n\s*(?<name>.+)\s*\n\s*(?<address>[\s\S]+?)((\s+Phone:|\n\s*)(?<phone>[\d\(\)\-\+\. ]{5,}))\s*(?:\n\s*Website|$)/", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['name'])
                ->address(preg_replace("/\s*\n\s*/", ", ", $m['address']))
                ->phone($m['phone'], true, true)
            ;
        }

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->startText('Check In:')))
            ->checkOut(strtotime($this->startText('Check Out:')))
            ->rooms($this->startText('Number of Rooms:', '/:\s*(\d{1,2})\s*$/'), true, true)
            ->guests($this->startText('Adults:', '/:\s*(\d{1,2})\s*$/'), true, true)
            ->kids($this->startText('Children:', '/:\s*(\d{1,2})\s*$/'), true, true)
        ;

        // Rooms
        $type = $this->startText('Room Type:');

        if (!empty($type)) {
            $h->addRoom()
                ->setType($type);
        }

        // Price
        $taxes = $this->startText('Taxes:');

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $taxes, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $taxes, $m)) {
            $h->price()
                ->tax(PriceHelper::cost($m['amount']))
            ;
        }
        $total = $this->startText('Total:');

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
                ->total(PriceHelper::cost($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        // Cancellation
        // Deadline
        $cancellationText = $this->http->FindSingleNode("//p[{$this->starts($this->t('Cancellation Policy:'))}]", null, true, "/{$this->preg_implode($this->t('Cancellation Policy:'))}[:\s]*(.+)$/");

        if (!empty($cancellationText) && strlen($cancellationText) < 2000) {
            $h->general()->cancellation($cancellationText);
            $this->detectDeadLine($h, $cancellationText);
        }

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
//        if (preg_match("#\s+(?<prior>\d+)-\d+ day prior to arrival date [.]+0%\s+#i", $cancellationText, $m) // en
//        ) {
//            $h->booked()->deadlineRelative($m['prior'] . ' days', '00:00');
//        } else
        if (
            preg_match("/If you cancel your booking or do not check in, 100% of the payment will be retained\./", $cancellationText) // en
        ) {
            $h->booked()->nonRefundable();
        }
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
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

    private function nextText($field, $regexp = null, $root = null)
    {
        $result = $this->http->FindSingleNode('descendant::text()[' . $this->eq($this->t($field)) . '][1]/following::text()[normalize-space()][1]', $root, true, $regexp);

        if ($result === null) {
            $result = $this->http->FindSingleNode("//text()[{$this->eq(preg_replace('/\s*:\s*$/', '', $this->t($field)))}]/following::text()[normalize-space() and not(normalize-space() = ':')][1]", $root, true, $regexp);
        }

        return $result;
    }

    private function startText($field, $regexp = "/:\s*(.+)/", $root = null)
    {
        $result = $this->http->FindSingleNode('descendant::text()[' . $this->starts($this->t($field)) . '][1]', $root, true, $regexp);

        return $result;
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
}
