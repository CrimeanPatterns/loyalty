<?php

namespace AwardWallet\Engine\aceh\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "aceh/it-207828901.eml, aceh/it-209287759.eml, aceh/it-213368952.eml";
    public $subjects = [
        'Your reservation at Ace Hotel',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Manage Your Reservation' => ['Manage Your Reservation', 'MANAGE YOUR RESERVATION'],

            'Check-in Date'              => ['Check-in Date', 'Check-in date'],
            'Check-out Date'             => ['Check-out Date', 'Check-out date'],
            'Total Cost including taxes' => ['Total Cost including taxes', 'Total cost'],

            'Book another stay' => ['Book another stay', 'BOOK ANOTHER STAY'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@acehotel.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Ace Hotel')]")->length > 0) {
            return ($this->http->XPath->query("//text()[{$this->contains($this->t('Stay:'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Manage Your Reservation'))}]")->length > 0)
            || ($this->http->XPath->query("//text()[{$this->contains($this->t('Book another stay'))}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($this->t('Cancellation Number'))}]")->length > 0);
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]acehotel\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $travellers = array_filter(explode(' and ', $this->http->FindSingleNode("//text()[normalize-space()='Your name']/ancestor::tr[1]/descendant::td[2]")));

        if (count($travellers) == 0) {
            $travellers = array_filter(explode(' and ', $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'name')]/following::text()[normalize-space()][1]")));
        }

        if (count($travellers) == 0) {
            $travellers[] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hi')]", null, true, "/{$this->opt($this->t('Hi'))}\s*(\w+)\,?$/");
        }

        $confirmation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation Number')]/ancestor::tr[2]", null, true, "/{$this->opt($this->t('Confirmation Number'))}\s*{$this->opt($this->t('Cancellation Number'))}\s*([A-Z\d]+)/su");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Confirmation Number']/ancestor::tr[1]/descendant::td[2]", null, true, "/^([A-Z\d]+)$/");
        }

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Confirmation Number']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{5,})$/");
        }

        $h->general()
            ->confirmation($confirmation)
            ->travellers($travellers);

        $cancellationNumber = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirmation Number')]/ancestor::tr[2]", null, true, "/{$this->opt($this->t('Confirmation Number'))}\s*{$this->opt($this->t('Cancellation Number'))}\s*[A-Z\d]+\s*([A-Z\d]+)/su");

        if (empty($cancellationNumber)) {
            $cancellationNumber = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation Number']/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]+)$/");
        }

        if (!empty($cancellationNumber)) {
            $h->general()
                ->cancelled()
                ->cancellationNumber($cancellationNumber);
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation Policy'))}]/following::text()[normalize-space()][1]");

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $checkIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in Date'))}]/ancestor::tr[1]/descendant::td[2]");

        if (empty($checkIn)) {
            $checkIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in date'))}]/ancestor::tr[1]/descendant::td[2]");
        }

        $checkOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out Date'))}]/ancestor::tr[1]/descendant::td[2]");

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-out date'))}]/ancestor::tr[1]/descendant::td[2]");
        }

        if (!empty($checkIn) && !empty($checkOut)) {
            $h->booked()
                ->checkIn(strtotime($checkIn))
                ->checkOut(strtotime($checkOut));
        }

        $hotelInfo = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Ace Hotel')]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<hotelName>.+)\n(?<address>.+)\n(?<phone>[+]?[\d\.\-\n\s]+)\n/", $hotelInfo, $m)) {
            $h->hotel()
                ->name($m['hotelName'])
                ->address($m['address'])
                ->phone(str_replace("\n", "", $m['phone']));
        }

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Room Type']/ancestor::tr[1]/descendant::td[2]");
        $rateType = $this->http->FindSingleNode("//text()[normalize-space()='Rate Type']/ancestor::tr[1]/descendant::td[2]");

        if (!empty($roomType) || !empty($rateType)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($rateType)) {
                $room->setRateType($rateType);
            }
        }

        $this->detectDeadLine($h);

        $priceText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Total Cost including taxes'))}]/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)/", $priceText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $h->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

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

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/We\'re unable to refund your stay if your plans change/", $cancellationText)) {
            $h->setNonRefundable(true);
        }

        if (preg_match("/Cancel by\s*(?<hours>[\d\:]+a?p?m)\D+\,\s*(?<prior>\d+\s*days?)\s*prior to arrival, to avoid being charged/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['prior'], $m['hours']);
        }
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
            'USD' => ['US$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
