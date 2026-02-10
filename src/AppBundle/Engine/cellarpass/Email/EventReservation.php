<?php

namespace AwardWallet\Engine\cellarpass\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EventReservation extends \TAccountChecker
{
    public $mailFiles = "cellarpass/it-429376355.eml";
    public $subjects = [
        '| CellarPass Reservation Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Party Size:' => ['Party Size:', 'Number of Guests:'],
            'Booking ID:' => ['Booking ID:', 'Booking Code:'],
            'Event:'      => ['Event:', 'Experience:'],
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
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Our Address:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Important Information'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Event Start Time:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Booking Date:'))}]")->length > 0;
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
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking ID:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6,})/"));

        $travellers = array_filter(explode("&", $this->http->FindSingleNode("//text()[normalize-space()='Guest Name:']/following::text()[normalize-space()][1]")));

        if (count($travellers) > 0) {
            $e->general()
                ->travellers($travellers);
        }

        $eventName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Event:'))}]/following::text()[normalize-space()][1]");
        $e->setName($eventName);

        $location = trim($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Location')]/following::text()[normalize-space()][1]"), ':');

        $address = implode(" ", $this->http->FindNodes("//text()[normalize-space()='Our Address:']/following::text()[normalize-space()][1]/ancestor::*[1]/descendant::text()[not(contains(normalize-space(), 'Our Address:') or contains(normalize-space(), 'Reservation Line') or contains(normalize-space(), '+'))]"));

        if (!empty($address)) {
            $e->setAddress($location . ' ' . $address ?? $address);
        }

        $phone = $this->http->FindSingleNode("//text()[normalize-space()='Phone Number:']/following::text()[normalize-space()][1]", null, true, "/^\s*([+][\d\(\)\-\s]+)$/");

        if (!empty($phone)) {
            $e->setPhone($phone);
        }

        $startDate = $this->http->FindSingleNode("//text()[normalize-space()='Date:']/following::text()[normalize-space()][1]", null, true, "/^(.+\d{4})/");
        $startTime = $this->http->FindSingleNode("//text()[normalize-space()='Event Start Time:']/following::text()[normalize-space()][1]", null, true, "/^(.+A?P?M)/");

        if (!empty($startDate) && !empty($startTime)) {
            $e->setStartDate(strtotime($startDate . ', ' . $startTime))
            ->setNoEndDate(true);
        }

        $guests = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Party Size:'))}]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

        if (!empty($guests)) {
            $e->setGuestCount($guests);
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }
}
