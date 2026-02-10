<?php

namespace AwardWallet\Engine\bringfido\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "bringfido/it-89966143.eml, bringfido/it-89998027.eml";
    public $subjects = [
        '/BringFido Booking Confirmation/u',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Guest Name'                    => ['Guest Name', 'Guest name'],
            'Hotel Confirmation'            => ['Hotel Confirmation', 'Confirmation number'],
            'Trip Number'                   => ['Trip Number', 'Trip number'],
            'Room Type'                     => ['Room Type', 'Room type'],
            'Bed Type'                      => ['Bed Type', 'Beds'],
            'Average Nightly Rate per Room' => ['Average Nightly Rate per Room', 'Average nightly rate per room'],
            'Hotel Taxes & Fees'            => ['Hotel Taxes & Fees', 'Hotel taxes & fees'],
            'Room Subtotal'                 => ['Room Subtotal', 'Room subtotal'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@bringfido.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'BringFido')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Summary of Charges'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Cancellation Policy'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]bringfido\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest Name'))}]/ancestor::tr[1]/descendant::td[2]"))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Confirmation'))}]/ancestor::tr[1]/descendant::td[2]"))
            ->cancellation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy'))}]/following::text()[normalize-space()][1]"));

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type'))}]/ancestor::tr[1]/preceding::tr[1]/preceding::text()[normalize-space()][2]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type'))}]/ancestor::tr[1]/preceding::tr[1]/preceding::text()[normalize-space()][1]"));

        $inTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-in'))}]/ancestor::tr[1]/descendant::td[2]");
        $outTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Check-out'))}]/ancestor::tr[1]/descendant::td[2]");

        $h->booked()
            ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Occupancy'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\s*(\d+)\s*(?:People|Person)/"));

        if (!empty($inTime) && !empty($outTime)) {
            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival'))}]/ancestor::tr[1]/descendant::td[2]") . $inTime))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure'))}]/ancestor::tr[1]/descendant::td[2]") . $outTime));
        } else {
            $h->booked()
                ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival'))}]/ancestor::tr[1]/descendant::td[2]")))
                ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure'))}]/ancestor::tr[1]/descendant::td[2]")));
        }

        $room = $h->addRoom();

        $room->setType($this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Type'))}]/ancestor::tr[1]/descendant::td[2]"));

        $description = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Bed Type'))}]/ancestor::tr[1]/descendant::td[2]");

        if (!empty($description)) {
            $room->setDescription($description);
        }

        $room->setRate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Average Nightly Rate per Room'))}]/ancestor::tr[1]/descendant::td[2]"));

        $h->price()
            ->total($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D{1}\s*([\d\.]+)/u"))
            ->currency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\D){1}/"))
            ->tax($this->http->FindSingleNode("//text()[{$this->eq($this->t('Hotel Taxes & Fees'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D{1}\s*([\d\.]+)/u"))
            ->cost($this->http->FindSingleNode("//text()[{$this->eq($this->t('Room Subtotal'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D{1}\s*([\d\.]+)/u"));

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Trip Number'))}]/ancestor::tr[1]/descendant::td[2]"));

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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            // Thu, 29 Apr, 2021
            '/^\w+\,\s*(\d+)\s*(\w+)\,\s*(\d{4})$/su',
            // Thu, 29 Apr, 2021
            '/^\w+\,\s*(\d+)\s*(\w+)\,\s*(\d{4})\s*(?:After|Before)?\s*([\d\:]+\s*A?P?M)$/sui',
            // Fri, Apr 28, 2023Before 11am
            '/^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*(?:After|Before)?\s*([\d\:]+\s*A?P?M)(?:[\–\s]*midnight|[\–\s]*\d+a?p?m?)?$/sui',
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3, $4",
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

        if (preg_match('/This rate is non-refundable/', $cancellationText, $m)
        || preg_match('/Please note that this rate is 100% non-refundable/', $cancellationText, $m)) {
            $h->booked()->nonRefundable();
        }

        if (preg_match('/Changes or cancellations made after (\d+a?p?m)\s*on\s*(\w+)\s*(\d+)\,\s*(\d{4}) are subject/', $cancellationText, $m)) {
            $h->booked()->deadline(strtotime($m[3] . '' . $m[2] . ' ' . $m[4] . ', ' . $m[1]));
        }

        $year = date("Y", $h->getCheckInDate());

        if (preg_match('/Cancel before\s*(?<time>\d+a?p?m)\s*(?:\(property local time\))?\s*on (?<month>\w+)\s*(?<day>\d+)\s*for a full refund/', $cancellationText, $m)) {
            $h->booked()->deadline(strtotime($m['day'] . '' . $m['month'] . ' ' . $year . ', ' . $m['time']));
        }

        if (preg_match('/Cancel before\s*(?<time>\d+a?p?m)\s*\(property local time\)\s*on (?<month>\w+)\s*(?<day>\d+)\s*for a full refund/', $cancellationText, $m)) {
            $h->booked()->deadline(strtotime($m['day'] . '' . $m['month'] . ' ' . $year . ', ' . $m['time']));
        }
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }
}
