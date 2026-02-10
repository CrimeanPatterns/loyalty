<?php

namespace AwardWallet\Engine\disneyresort\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelReservation extends \TAccountChecker
{
    public $mailFiles = "disneyresort/it-665185884.eml";
    public $subjects = [
        'Walt Disney World Confirmation (#',
    ];

    public $address = [
        "Disney's Art of Animation Resort"         => '1850 Animation Way Lake Buena Vista, Florida 32830-8400',
        "Disney's Pop Century Resort"              => '1050 Century Drive Lake Buena Vista, Florida 32830-8433',
        "Disney's Port Orleans Resort - Riverside" => '1251 Riverside Drive Lake Buena Vista, Florida 32830-8514',
        "Disney's All-Star Movies Resort"          => '1901 West Buena Vista Drive Lake Buena Vista, Florida 32830-8412',
        "Pixar Place Hotel"                        => '1717 S. Disneyland Drive Anaheim, California 92802',
        "Disneyland Hotel"                         => '1150 West Magic Way Anaheim, California 92802',
        "Disney's Grand Californian Hotel & Spa"   => '4401 Floridian Way Lake Buena Vista, Florida 32830-8451',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'View Itinerary in My Plans' => ['View Itinerary in My Plans', 'Go to My Hotel Reservations'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@wdw.disneyonline.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($subject, $headers['subject']) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Walt Disney')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Hotel Confirmation Number:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('View Itinerary in My Plans'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]wdw\.disneyonline\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->Hotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Hotel(Email $email)
    {
        $h = $email->add()->hotel();

        $travelers = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Guest (')]/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Guest'))]");

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hotel Confirmation Number:')]", null, true, "/{$this->opt($this->t('Hotel Confirmation Number:'))}\s*(\d+)$/"))
            ->date(strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Order Date:')]/ancestor::div[1]", null, true, "/{$this->opt($this->t('Order Date:'))}\s*(.+)/")))
            ->travellers(preg_replace("/^(?:Mr\.|Mrs\.|Miss)/", "", $travelers));

        $price = $this->http->FindSingleNode("//text()[normalize-space()='Total Order Price']/following::text()[normalize-space()][1]");

        if (preg_match("/^\D*(?<total>[\d\.\,']+)\s*(?<currency>[A-Z]{3})/", $price, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Room Price']/following::text()[normalize-space()][1]", null, true, "/^\D*([\d\.\,\']+)$/");

            if (!empty($cost)) {
                $h->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Tax']/following::text()[normalize-space()][1]", null, true, "/^\D*([\d\.\,\']+)$/");

            if (!empty($tax)) {
                $h->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }

            $rateArray = $this->http->FindNodes("//text()[normalize-space()='Rate Per Night']/ancestor::tr[2]/following-sibling::tr");
            $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Room Type:']/ancestor::tr[1]/descendant::td[2]");

            if (!empty($roomType) || count($rateArray) > 0) {
                $room = $h->addRoom();

                if (!empty($roomType)) {
                    $room->setType($roomType);
                }

                if (count($rateArray) > 0) {
                    $room->setRates($rateArray);
                }
            }
        }

        $hotelName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-In:')]/preceding::img[1]/following::text()[normalize-space()][1]");

        if (!empty($hotelName)) {
            $h->hotel()
                ->name($hotelName);

            foreach ($this->address as $key => $address) {
                if (stripos($key, $hotelName) !== false) {
                    $h->setAddress($address);
                }
            }
        }

        $checkIn = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check-In:')]/ancestor::tr[1]/descendant::td[2]");

        if (!empty($checkIn)) {
            $h->setCheckInDate(strtotime($checkIn));
        }

        $checkOut = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Check Out:')]/ancestor::tr[1]/descendant::td[2]");

        if (!empty($checkOut)) {
            $h->setCheckOutDate(strtotime($checkOut));
        }
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
}
