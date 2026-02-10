<?php

namespace AwardWallet\Engine\cellarpass\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationReminder extends \TAccountChecker
{
    public $mailFiles = "cellarpass/it-439487637.eml";
    public $subjects = [
        'Reservation Reminder | Do Not Reply',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@reminders.cellarpass.com') !== false) {
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
        return $this->http->XPath->query("//text()[{$this->contains($this->t('CellarPass'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('You\'ve got to be excited- upcoming reservation reminder notice'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Reservation Details'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]reminders\.cellarpass\.com$/', $from) > 0;
    }

    public function ParseEvent(Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(Event::TYPE_EVENT);

        $e->general()
            ->confirmation($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Confirmation #:')]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Confirmation #:'))}\s*([A-Z]{6,})\s*$/u"))
            ->traveller($this->http->FindSingleNode("//text()[contains(normalize-space(), 'Name of Party:')]/following::text()[normalize-space()][1]"));

        $guests = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Number of Guest(s):')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Number of Guest(s):'))}\s*(\d+)/");

        if (!empty($guests)) {
            $e->setGuestCount($guests);
        }

        $e->setName($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Event')][not(contains(normalize-space(), 'Time'))]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Event'))}\:\s*(.+)/"));

        $dateStart = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Date: ')]", null, true, "/^{$this->opt($this->t('Date: '))}\s*(.+A?P?M)/");

        if (!empty($dateStart)) {
            $e->setStartDate(strtotime(str_replace(' at ', ', ', $dateStart)))
                ->setNoEndDate(true);
        }

        $location = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Destination:')]/following::text()[normalize-space()][1]");
        $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Address:')]/following::span[normalize-space()][1]");
        $e->setAddress($location . ', ' . $address ?? $address);

        $phone = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Phone:')]/following::text()[normalize-space()][1]", null, true, "/^\s*([+][\d\(\)\-\s]+)/");

        if (!empty($phone)) {
            $e->setPhone($phone);
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
