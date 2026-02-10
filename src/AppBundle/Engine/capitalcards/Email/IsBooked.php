<?php

namespace AwardWallet\Engine\capitalcards\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parser hopper/CheckReceiptOfStayDetails (in favor of hopper/CheckReceiptOfStayDetails)

class IsBooked extends \TAccountChecker
{
    public $mailFiles = "capitalcards/it-121790819.eml, capitalcards/it-150239789.eml, capitalcards/it-675932858.eml";
    public $subjects = [
        'Good news! Your hotel is booked', 'Your reservation details', 'Your reservation was canceled', 'Your reservation was cancelled',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'statusVariants'   => ['Confirmed', 'CONFIRMED', 'Cancelled', 'CANCELLED', 'Canceled', 'CANCELED', 'confirmed'],
            'cancelledPhrases' => ['Your reservation was cancelled.', 'Your reservation was canceled.'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@capitalonebooking.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Capital One Travel')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'Reservation Details')]")->length > 0
                && ($this->http->XPath->query("//text()[{$this->contains($this->t('Room Type'))}]")->length > 0
                    || $this->http->XPath->query("//text()[{$this->contains($this->t('Due Today'))}]")->length > 0);
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]capitalonebooking.com$/', $from) > 0;
    }

    public function ParseEmail(Email $email): void
    {
        $h = $email->add()->hotel();

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            // it-150239789.eml
            $h->general()->cancelled();
        }

        $status = $this->http->FindSingleNode("//text()[normalize-space()='Capital One Travel']/preceding::text()[normalize-space()][1]", null, true, "/^{$this->opt($this->t('statusVariants'))}$/i");

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//text()[contains(normalize-space(), ', your hotel is')]", null, true, "/{$this->opt($this->t(', your hotel is'))}\s*({$this->opt($this->t('statusVariants'))})/");
        }

        if ($status) {
            $h->general()->status($status);
        }

        $otaConfirmation = $this->http->FindSingleNode("//text()[normalize-space()='Capital One Travel']/following::text()[normalize-space()][1]", null, true, "/^[-A-z\d]{5,}$/");
        $email->ota()->confirmation($otaConfirmation);

        $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Hotel Confirmation']/following::text()[normalize-space()][1]", null, true, "/^[-A-z\d\|]{3,}$/");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Capital One Travel']/preceding::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{10,15})$/");
        }

        if ($confirmation) {
            if (stripos($confirmation, "|") !== false) {
                $confs = explode('|', $confirmation);

                foreach ($confs as $conf) {
                    $h->general()->confirmation($conf);
                }
            } else {
                $h->general()->confirmation($confirmation);
            }
        } elseif ($this->http->XPath->query("//text()[normalize-space()='Hotel Confirmation']")->length === 0) {
            $h->general()->noConfirmation();
        }

        $cancellation = $this->http->FindSingleNode("//tr[normalize-space()='Restrictions']/following::text()[normalize-space()][1]/ancestor::tr[1]/*[normalize-space()][last()]")
            ?? $this->http->FindSingleNode("//tr[normalize-space()='Refund Information']/following::text()[normalize-space()][1]/ancestor::tr[1]")
            ?? $this->http->FindSingleNode("//text()[normalize-space()='Cancellation Policy']/following::text()[normalize-space()][string-length()>2][1]")
        ;
        $h->general()->cancellation($cancellation);

        $hotelName = $this->http->FindSingleNode("//*[normalize-space()='Reservation Details']/preceding-sibling::*[normalize-space()][2]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//*[normalize-space()='Reservation Details']/preceding::a[1]/ancestor::table[1]/descendant::strong");
        }

        $address = $this->http->FindSingleNode("//*[normalize-space()='Reservation Details']/preceding-sibling::*[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()][1]");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//*[normalize-space()='Reservation Details']/preceding::a[1]/ancestor::table[1]/descendant::strong/following::tr[1]");
        }

        $xpathPhone = "//*[normalize-space()='Reservation Details']/preceding-sibling::*[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()][2]";
        $phone = $this->http->FindSingleNode($xpathPhone, null, true, "/^[+(\d][-+. \d)(]{5,}[\d)]$/");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//*[normalize-space()='Reservation Details']/preceding::a[1]/ancestor::table[1]/descendant::a");
        }

        $phone = str_replace('–', '-', $phone);

        $h->hotel()
            ->name($hotelName)
            ->address($address)
            ->phone($phone, false, $this->http->XPath->query($xpathPhone)->length === 0)
        ;

        $year = $this->http->FindSingleNode("//text()[normalize-space()='Check-in:']/ancestor::tr[1]/descendant::td[2]", null, true, "/(\d{4})\s*at/");
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Check-in:']/ancestor::tr[1]/descendant::td[2]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Checkout:']/ancestor::tr[1]/descendant::td[2]")))
            ->rooms($this->http->FindSingleNode("//text()[normalize-space()='Your Stay:']/following::text()[normalize-space()][1]", null, true, "/(\d+)\s*room/"));

        $guestName = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Guest Name:'] ]/*[normalize-space()][2]", null, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u");

        if (empty($guestName)) {
            $guestName = $this->http->FindSingleNode("//text()[contains(normalize-space(), ', your hotel is')]", null, true, "/^(.+)\s*{$this->opt($this->t(', your hotel is'))}/");
        }

        if ($guestName) {
            $h->general()->traveller($guestName, true);
        }

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Room Type']/following::text()[normalize-space()][1]");
        $roomDescription = $this->http->FindSingleNode("//text()[normalize-space()='Bed Selection']/following::text()[normalize-space()][1]");

        if (!empty($roomType) || !empty($roomDescription)) {
            $room = $h->addRoom();

            if (!empty($roomType) && strlen($roomType) < 251) {
                $room->setType($roomType);
            }

            if (!empty($roomDescription)) {
                $room->setDescription($roomDescription);
            } elseif (!empty($roomType) && strlen($roomType) > 250) {
                $room->setDescription($roomType);
            }
        }

        $spentAwards = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Rewards Applied from Capital One Venture')]/following::text()[normalize-space()][1]");

        if (!empty($spentAwards)) {
            $h->price()
                ->spentAwards($spentAwards);
        }

        $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Card Payment from Capital One Venture')]/following::text()[normalize-space()][1]");

        if (preg_match("/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/", $price, $matches)) {
            // $173.67
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $h->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        if (preg_match("/Non\-refundable/", $h->getCancellation())) {
            $h->setNonRefundable(true);
        }

        if (preg_match("/Free Cancellation until (\w+)\s*(\d+)/", $h->getCancellation(), $m)) {
            $h->booked()->deadline(strtotime($m[2] . ' ' . $m[1] . ' ' . $year));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEmail($email);

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

    private function normalizeDate($date)
    {
        $in = [
            '#^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*at\s*([\d\:]+\s*a?p?\.m\.)$#', //Thursday, November 11, 2021 at 3:00 p.m.
        ];
        $out = [
            '$2 $1 $3, $4',
        ];
        $str = preg_replace($in, $out, $date);

        return strtotime($str);
    }
}
