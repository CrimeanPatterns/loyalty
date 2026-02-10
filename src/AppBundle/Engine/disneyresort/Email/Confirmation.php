<?php

namespace AwardWallet\Engine\disneyresort\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "disneyresort/it-1603473.eml, disneyresort/it-1637770.eml, disneyresort/it-1930910.eml, disneyresort/it-2490784.eml, disneyresort/it-2772404.eml, disneyresort/it-2796585.eml";
    public $subjects = [
        '/Your Reservation Confirmation \([#]\d+\)/u',
        '/Disneyland Resort Confirmation[#\s]+\d{5,}/',
        '/Disneyland Confirmation \([#][A-Z\d]+\)/u',
        '/Walt Disney World Resort Confirmation \d{5,}/u',
        '/Walt Disney World Vacation Confirmation\!/',
        '/Vero Beach Vacation Confirmation\!/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Disney Resort'                => ['Disney Resort', 'Walt Disney World', 'Walt Disney Parks and Resorts', '© Disney and its related entities'],
            'Hotel Confirmation Number:'   => ['Hotel Confirmation Number:', 'Resort Confirmation Number:', 'Package Confirmation Number:'],
            'Hotel Reservation'            => ['Hotel Reservation', 'Vacation Package Reservation', 'Resort Reservation', 'Special Offer', 'Hotel Package'],
            'Room Price'                   => ['Room Price', 'Reservation Price'],
            'Total Order Price'            => ['Total Order Price', 'Total Room Price', 'Total Payment Due'],
            'Adults'                       => ['Adults', 'Adult'],
            'Children'                     => ['Children', 'Child'],
            'CANCELLATION/CHANGE POLICY'   => ['CANCELLATION/CHANGE POLICY', 'CANCELLATION POLICY:', 'Cancellation Policy', 'Cancellation Prior to Guest Arrival'],
            'Package Cancellation Policy:' => ['Package Cancellation Policy:', 'Cancellation and Refunds'],
            'Price and Payment Summary'    => ['Price and Payment Summary', 'Refund Information'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'Disneyland Resort') === false
        ) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (preg_match($subject, $headers['subject'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Disney Resort'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Hotel Reservation'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Price and Payment Summary'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]disney/', $from) > 0;
    }

    public function ParseHotel(Email $email): void
    {
        $h = $email->add()->hotel();

        $travellers = [];
        $travellerCells = $this->http->XPath->query("//tr[{$this->eq($this->t('Your Travel Party'))}]/following-sibling::tr[normalize-space()][1]/descendant::*[not(.//tr[normalize-space()]) and ../self::tr and normalize-space()]");

        foreach ($travellerCells as $tCell) {
            $travellerVal = implode(' ', $this->http->FindNodes('descendant::text()[normalize-space()]', $tCell));

            if (preg_match("/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]](?: Jr\.)?$/ui", $travellerVal)) {
                $travellers[] = $travellerVal;
            } else {
                $travellers = [];

                break;
            }
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CANCELLATION/CHANGE POLICY'))}]/following::text()[normalize-space()][1]");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[normalize-space(.)='Aulani, A Disney Resort & Spa Vacation Package Cancellation Policy:']/following::text()[normalize-space()][1]/ancestor::ul[1]");
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservations may only be')]");
        }

        if (empty($cancellation)) {
            $cancellation = trim($this->http->FindSingleNode("//text()[{$this->contains($this->t('Package Cancellation Policy:'))}]/following::text()[string-length()>10][1]/ancestor::p[1]"), '·');
        }

        if (empty($cancellation)) {
            $cancellation = trim($this->http->FindSingleNode("//text()[{$this->contains($this->t('vacation packages Cancellation and Refunds'))}]/following::text()[string-length()>10][1]/ancestor::p[1]"), '·');
        }

        $h->general()
            ->travellers(preg_replace("/^(?:Mrs|Mr|Miss|Mstr|Ms)[.\s]+(.{2,})$/i", '$1', $travellers))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Confirmation Number:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Hotel Confirmation Number:'))}\s*([A-Z\d]+)/u"))
            ->cancellation($cancellation, true, true);

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank You. Your Order is '))}]", null, true, "/{$this->opt($this->t('Thank You. Your Order is '))}\s*(\w+)/");

        if (!empty($status)) {
            $h->general()
                ->status($status);
        }

        $xpathHotel1 = "following::img[1]/following::td[1]/descendant::text()[normalize-space()]";

        $hotelInfo = $this->http->FindNodes("//text()[{$this->starts($this->t('Hotel Reservation'))}]/" . $xpathHotel1);

        if (count($hotelInfo) < 4) {
            $hotelInfo = $this->http->FindNodes("//tr[ *[normalize-space()][2][{$this->starts($this->t('Hotel Confirmation Number:'))}] ]/" . $xpathHotel1);
        }

        $xpathHotel2 = "following::img[1]/following::td[1]/ancestor::table[1]/descendant::text()[normalize-space()]";

        if (count($hotelInfo) < 4) {
            $hotelInfo = $this->http->FindNodes("//text()[{$this->starts($this->t('Hotel Reservation'))}]/" . $xpathHotel2);
        }

        if (count($hotelInfo) < 4) {
            $hotelInfo = $this->http->FindNodes("//tr[ *[normalize-space()][2][{$this->starts($this->t('Hotel Confirmation Number:'))}] ]/" . $xpathHotel2);
        }

        if (count($hotelInfo) >= 4) {
            $h->hotel()
                ->name($hotelInfo[0])
                ->noAddress();

            if (preg_match("/^(.+)\s\-\s(.+)$/", $hotelInfo[1], $m)) {
                $h->booked()
                    ->checkIn($this->normalizeDate($m[1]))
                    ->checkOut($this->normalizeDate($m[2]));
            }

            if (preg_match("/^(\d+)\s*{$this->opt($this->t('Adults'))}\s*\,\s*(\d+)\s*{$this->opt($this->t('Children'))}/u", $hotelInfo[3], $m)
                || preg_match("/^(\d+)\s*{$this->opt($this->t('Adults'))}\s*$/u", $hotelInfo[3], $m)
            ) {
                $h->booked()
                    ->guests($m[1]);

                if (isset($m[2])) {
                    $h->booked()
                        ->kids($m[2]);
                }

                $room = $h->addRoom();

                $room->setType($hotelInfo[2]);

                $rates = $this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$this->eq($this->t('Rate per Night'))}] ]/following-sibling::tr[count(*[normalize-space()])=2]/*[normalize-space()][2]", null, '/^[^\-\d)(]+[ ]*\d[,.\'\d ]*$/');

                if (count($rates) && !in_array(null, $rates, true)) {
                    $room->setRates($rates);
                }
            }
        }

        if (preg_match("/In (?i)order to receive a refund of your deposit, notification of cancell?ation must be received at least (?<prior>\d{1,3} days?) prior to your arrival date\./", $cancellation, $m)) {
            $h->booked()->deadlineRelative($m['prior'], '00:00');
        }

        $totalPrice = $this->http->FindSingleNode("//td[{$this->eq($this->t('Total Order Price'))}]/following-sibling::td[normalize-space()][last()]");

        if (preg_match('/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d ]*?)[ ]*(?<currencyCode>[A-Z]{3})?$/', $totalPrice, $matches)) {
            // $3,404.83    |    $2,470.51 USD
            $currencyCode = empty($matches['currencyCode']) ? null : $matches['currencyCode'];

            $h->price()
                ->total(PriceHelper::parse($matches['amount'], $currencyCode))
                ->currency($currencyCode ?? $matches['currency'])
            ;

            $baseFare = $this->http->FindSingleNode("//td[{$this->eq($this->t('Room Price'))}]/following-sibling::td[last()]");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)/', $baseFare, $m)) {
                $h->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $tax = $this->http->FindSingleNode("//td[{$this->eq($this->t('Tax'))}]/following-sibling::td[last()]");

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)/', $tax, $m)) {
                $h->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^(\w+)\s*(\d+)\D+\,\s*(\d{4})$#', //April 05 (Mon), 2021
        ];
        $out = [
            '$2 $1 $3',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }
}
