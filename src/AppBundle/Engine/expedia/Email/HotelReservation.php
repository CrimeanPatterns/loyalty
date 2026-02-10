<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelReservation extends \TAccountChecker
{
    public $mailFiles = "expedia/it-91255276.eml";
    public $subjects = [
        '/Your Expedia.com Hotel Reservation/',
    ];

    public $lang = 'en';
    public $year;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@corporateperks.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Expedia.com')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Hotel Confirmation'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Hotel Itinerary Number'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]corporateperks\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hotel Itinerary Number')]", null, true, "/{$this->opt($this->t('Hotel Itinerary Number'))}\s*\:?\s*(\d+)/"))
            ->cancellation($this->http->FindSingleNode("//text()[normalize-space()='Cancellation Policy:']/following::text()[normalize-space()][1]"), true, true);

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Get Directions')]/preceding::text()[normalize-space()][2]"))
            ->address($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Get Directions')]/preceding::text()[normalize-space()][1]"));

        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Check In']/ancestor::tr[1]/following::tr[1]/descendant::td[1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Check In']/ancestor::tr[1]/following::tr[1]/descendant::td[normalize-space()][2]")))
            ->rooms($this->http->FindSingleNode("//text()[normalize-space()='Room Type']/ancestor::tr[1]/following::tr[1]/descendant::td[1]", null, true, "/(\d+)\s*Room/"));

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Room Type']/ancestor::tr[1]/following::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][1]");
        $roomDesc = $this->http->FindSingleNode("//text()[normalize-space()='Room Type']/ancestor::tr[1]/following::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][2]");
        $roomRate = $this->http->FindSingleNode("//text()[normalize-space()='Nightly Rate:']/ancestor::tr[1]/descendant::td[2]");

        if (!empty($roomType) || !empty($roomDesc) || !empty($roomRate)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($roomDesc)) {
                $room->setDescription($roomDesc);
            }

            if (!empty($roomRate)) {
                $room->setRate($roomRate);
            }
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total Charges:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D{1}\s*([\d\,\.]+)$/");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='Total Charges:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*(\D{1})\s*[\d\,\.]+$/");

        if (!empty($total) && !empty($currency)) {
            $h->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[normalize-space()='Subtotal:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D{1}\s*([\d\,\.]+)$/");

            if (!empty($cost)) {
                $h->price()
                    ->cost(PriceHelper::parse($cost, $currency));
            }

            $tax = $this->http->FindSingleNode("//text()[normalize-space()='Taxes & Fees']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*\D{1}\s*([\d\,\.]+)$/");

            if (!empty($cost)) {
                $h->price()
                    ->tax($tax);
            }
        }
        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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

    private function normalizeDate($str)
    {
        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            "#^\s*\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*\w+\s*at\s*([\d\:]+\s*[AP]M)(?: - [\d\:]+(?:\s*[AP]M)?)?\s*$#ui", // Fri, May 7, 2021 Starts at 3:00 PM
            "#^\s*\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*([\d\:]+\s*A?P?M)(?: - [\d\:]+(?:\s*[AP]M)?)?\s*$#ui", // Mon, May 10, 2021 1:00 PM
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/Free cancellation until\s*\w+\,\s*(\w+)\s*(\d+)\s*([\d\:]+\s*A?P?M)/u', $cancellationText, $m)) {
            $h->booked()->deadline(strtotime($m[2] . ' ' . $m[1] . ' ' . $this->year . ', ' . $m[3]));
        }
    }
}
