<?php

namespace AwardWallet\Engine\disneyresort\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EventReservation extends \TAccountChecker
{
    public $mailFiles = "disneyresort/it-657048924.eml";
    public $subjects = [
        'Your Reservation has been',
    ];

    public $lang = 'en';

    public $address = [
        'Morimoto Asia' => '1486 East Buena Vista Drive, Orlando, FL 32830-8519',
    ];

    public static $dictionary = [
        "en" => [
            'Dinner at' => ['Dinner at', 'Lunch at', 'Breakfast at'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@wdw.disneyonline.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Walt Disney World')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Reservation Has Been'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Restaurant Reservation'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]wdw\.disneyonline\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEvent($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseEvent(Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(Event::TYPE_RESTAURANT);

        $e->general()
            ->traveller($this->http->FindSingleNode("//text()[normalize-space()='Primary Guest']/following::text()[normalize-space()][1]"));

        $restInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Restaurant Reservation']/following::p[normalize-space()][1]/descendant::text()[normalize-space()]"));

        if (preg_match("/^(?<restName>.+)\n(?<startDate>.+\s\d{4})\n{$this->opt($this->t('Dinner at'))}\s*(?<startTime>\d+\:\d+\s*A?P?M)$/", $restInfo, $m)) {
            $e->setName($m['restName']);

            $e->setStartDate(strtotime($m['startDate'] . ', ' . $m['startTime']))
                ->setNoEndDate(true);
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Your Reservation Has Been Canceled')]")->length > 0) {
            $e->general()
                ->cancelled()
                ->cancellationNumber($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Cancellation Confirmation Number:')]", null, true, "/^{$this->opt($this->t('Cancellation Confirmation Number:'))}\s*(\d+)/"));
        }

        foreach ($this->address as $key => $address) {
            if (stripos($key, $m['restName']) !== false) {
                $e->setAddress($address);
            }
        }

        $guests = $this->http->FindSingleNode("//text()[normalize-space()='Primary Guest']/following::text()[normalize-space()][2]/ancestor::p[1]", null, true, "/^(\d+)[x]/");

        if (!empty($guests)) {
            $e->setGuestCount($guests);
        }
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
