<?php

namespace AwardWallet\Engine\cellarpass\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmed extends \TAccountChecker
{
    public $mailFiles = "cellarpass/it-422441671.eml, cellarpass/it-425705769.eml, cellarpass/it-440198166.eml";
    public $subjects = [
        'Reservation Confirmation | No Replies Please',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Reservation Information' => ['Reservation Information', 'Reservation Details'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@reservations.cellarpass.com') !== false) {
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
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Party:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Confirm#'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation Information'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]reservations\.cellarpass\.com$/', $from) > 0;
    }

    public function ParseEvent(Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(Event::TYPE_EVENT);

        $bookingDate = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Booking Date:')]/following::text()[normalize-space()][1]", null, true, "/^(.+\s*A?P?M)\s*$/u");

        if (!empty($bookingDate)) {
            $e->general()
                ->date(strtotime($bookingDate));
        }

        $e->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Confirm#')]", null, true, "/{$this->opt($this->t('Confirm#'))}\s*([A-Z\d]{6,})/"));

        $travellers = array_filter(explode(",", $this->http->FindSingleNode("//text()[normalize-space()='Guest Name(s):']/following::text()[normalize-space()][1][not(contains(normalize-space(), 'Additional'))]")));

        if (count($travellers) > 0) {
            $e->general()
                ->travellers($travellers);
        }

        $eventName = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thanks for booking with')]/following::text()[normalize-space()][1]");

        if (empty($eventName)) {
            $eventName = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Party of')]/preceding::text()[normalize-space()][1]");
        }

        $e->setName($eventName);

        $eventInfo = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Party of')]/ancestor::tr[1]");

        if (preg_match("/Party of\s*(?<guests>\d+)\s*on\s*(?<date>.+\d{4})\s*at\s*(?<time>[\d\:]+\s*A?P?M)/", $eventInfo, $m)) {
            $e->booked()
                ->guests($m['guests']);

            $e->setStartDate(strtotime($m['date'] . ', ' . $m['time']));
            $e->setNoEndDate(true);
        }

        $placeInfo = implode("\n", $this->http->FindNodes("//text()[contains(normalize-space(), 'Confirm#')]/following::text()[contains(normalize-space(), 'www')][last()]/ancestor::td[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^.+\n(?<address>(?:.+\n){1,3})www\..+\n(?<phone>[\d\.]+)$/", $placeInfo, $m)) {
            $e->setAddress(str_replace("\n", " ", $m['address']));
            $e->setPhone($m['phone']);
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(), 'Check-In Venue')]")->length > 0) {
            $address = implode(" ", $this->http->FindNodes("//text()[contains(normalize-space(), 'Check-In Venue')]/ancestor::tr[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Check-In Venue'))]"));

            if (!empty($address)) {
                $e->setAddress($address);
            }
            $phone = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Check-In Venue')]/ancestor::tr[2]/descendant::text()[starts-with(normalize-space(), '+')]");

            if (!empty($phone)) {
                $e->setPhone($phone);
            }
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(), 'Get Directions')]")->length > 0) {
            $address = implode(" ", $this->http->FindNodes("//text()[normalize-space()='Get Directions']/preceding::text()[normalize-space()][2]/ancestor::*[1]"));

            if (!empty($address)) {
                $e->setAddress($address);
            }
            $phone = $this->http->FindSingleNode("//text()[normalize-space()='Get Directions']/preceding::text()[normalize-space()][1]", null, true, "/^([+][\d\(\)\-\s]+)$/");

            if (!empty($phone)) {
                $e->setPhone($phone);
            }
        }

        $price = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Amount Collected:')]", null, true, "/{$this->opt($this->t('Amount Collected:'))}\s*(.+)/");

        if (preg_match("/^(?<currency>\D{1,3})(?<total>[\.\d\,]+)$/", $price, $m)) {
            $e->price()
                ->currency($m['currency'])
                ->total(PriceHelper::parse($m['total'], $m['currency']));
        }

        $hours = $this->http->FindSingleNode("//text()[normalize-space()='Tasting Hours']/following::text()[normalize-space()='Open Daily']/ancestor::tr[1]", null, true, "/({$this->opt($this->t('Open Daily'))}\s*.+a?p?m)/i");

        if (!empty($hours)) {
            $e->setNotes($hours);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEvent($email);

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
        return 0;
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
