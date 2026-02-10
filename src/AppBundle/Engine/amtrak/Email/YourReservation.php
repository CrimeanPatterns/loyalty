<?php

namespace AwardWallet\Engine\amtrak\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "amtrak/it-115756842.eml";
    public $subjects = [
        '/Your reservation[\s\-]+.+\,\s*\d{4}\shas been confirmed[!]/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@amtrak.com') !== false) {
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
        if ($this->http->XPath->query("//a[contains(@href, 'amtrak')]")->length > 0) {
            return $this->http->XPath->query("//text()[contains(normalize-space(), 'With this reservation you will earn')]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('A receipt has been attached to this email for your records'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]amtrak\.com$/', $from) > 0;
    }

    public function ParseRails(Email $email): void
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Reservation ID']/ancestor::tr[1]/descendant::td[2]"))
            ->cancellation($this->http->FindSingleNode("//text()[normalize-space()='Cancellation policy']/following::text()[normalize-space()][1]"));

        $account = $this->http->FindSingleNode("//text()[normalize-space()='Membership ID']/ancestor::tr[1]/descendant::td[2]");

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }

        $earnPoints = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'reservation you will earn')]", null, true, "/{$this->opt($this->t('reservation you will earn'))}\s*(\d+)\s*{$this->opt($this->t('Points'))}/");

        if (!empty($earnPoints)) {
            $h->setEarnedAwards($earnPoints);
        }

        $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Guest Name']/ancestor::tr[1]/descendant::td[2]");

        if (!empty($traveller)) {
            $h->general()
                ->traveller($traveller);
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[normalize-space()='Print Receipt']/following::text()[contains(normalize-space(), 'Phone:')][1]/ancestor::table[1]/descendant::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode("//text()[normalize-space()='Print Receipt']/following::text()[contains(normalize-space(), 'Phone:')][1]/ancestor::table[1]/descendant::text()[normalize-space()][2]"))
            ->phone($this->http->FindSingleNode("//text()[normalize-space()='Print Receipt']/following::text()[contains(normalize-space(), 'Phone:')][1]/ancestor::table[1]/descendant::text()[normalize-space()][3]", null, true, "/{$this->opt($this->t('Phone:'))}\s*(.+)/"));

        $fax = $this->http->FindSingleNode("//text()[normalize-space()='Print Receipt']/following::text()[contains(normalize-space(), 'Phone:')][1]/ancestor::table[1]/descendant::text()[normalize-space()][4]", null, true, "/{$this->opt($this->t('Fax:'))}\s*(.+)/");

        if (!empty($fax)) {
            $h->hotel()
                ->fax($fax);
        }

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Check in']/ancestor::tr[1]/descendant::td[2]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[normalize-space()='Check out']/ancestor::tr[1]/descendant::td[2]")))
            ->guests($this->http->FindSingleNode("//text()[normalize-space()='Adults']/ancestor::tr[1]/descendant::td[2]"));

        $roomType = $this->http->FindSingleNode("//text()[normalize-space()='Room type']/ancestor::tr[1]/descendant::td[2]");
        $description = $this->http->FindSingleNode("//text()[normalize-space()='Bed Type']/ancestor::tr[1]/descendant::td[2]");
        $rate = $this->http->FindSingleNode("//text()[normalize-space()='Avg. rate per night']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D\s*([\d\.]+)/u");

        if (!empty($roomType) || !empty($description || !empty($rate))) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($description)) {
                $room->setDescription($description);
            }

            if (!empty($rate)) {
                $room->setRate($rate);
            }
        }

        $this->detectDeadLine($h);

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total cost']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D\s*([\d\.]+)/u");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='Total cost']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\D)\s*[\d\.]+/");

        $h->price()
            ->total($total)
            ->currency($currency);

        $cost = $this->http->FindSingleNode("//text()[normalize-space()='Subtotal']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\D\s*([\d\.]+)/");

        if (!empty($cost)) {
            $h->price()
                ->cost($cost);
        }

        $feeNodes = $this->http->XPath->query("//text()[normalize-space()='Total cost']/ancestor::table[1]/descendant::tr[count(*[normalize-space()])=2]/td[1][not(contains(normalize-space(),'Total') or contains(normalize-space(),'Avg. rate per night') or contains(normalize-space(),'Subtotal'))]/ancestor::tr[normalize-space()][1]");

        foreach ($feeNodes as $root) {
            $feeName = $this->http->FindSingleNode("*[normalize-space()][1]", $root);
            $feeSum = $this->http->FindSingleNode("*[normalize-space()][2]", $root, true, "/^\D\s*(\d[,.\'\d ]*?)[*\s]*$/");

            $h->price()
                ->fee($feeName, $feeSum);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseRails($email);

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match('/Free cancellation available before\s*(?<time>[\d\:]+a?p?m)\s*on\s*(?<day>\d+\s*\w+\s*\d{4})/', $cancellationText, $m)) {
            $h->booked()->deadline(strtotime($m['day'] . ', ' . $m['time']));
        }
    }
}
