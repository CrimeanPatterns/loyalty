<?php

namespace AwardWallet\Engine\clubready\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "clubready/it-690503683.eml, clubready/it-690507908.eml, clubready/it-690511204.eml";
    public $subjects = [
        'New Booking Confirmation',
        'Upcoming Booking Reminder',
        'Booking Cancellation Confirmation',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Hello' => ['Hello', 'Hi', 'Hi,'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@clubreadymail.com') !== false) {
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
        return $this->http->XPath->query("//text()[{$this->contains($this->t('Hello'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Start Time:'))}]")->length > 0
            && ($this->http->XPath->query("//text()[{$this->starts($this->t('End Time:'))}]/following::text()[normalize-space()][1][{$this->starts($this->t('with'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->starts($this->t('Start Time:'))}]/following::text()[normalize-space()][1][{$this->starts($this->t('with'))}]")->length > 0);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]clubreadymail\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseEmail(Email $email)
    {
        $e = $email->add()->event();

        $e->setEventType(EVENT_EVENT);

        $e->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]", null, true, "/{$this->opt($this->t('Hello'))}\s+(\D+)(?:\,|\!)/"));

        $e->setName($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Start Time:')]/preceding::text()[normalize-space()][2]"));

        $date = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Start Time:')]/preceding::text()[normalize-space()][1]");
        $startTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Start Time:')]", null, true, "/{$this->opt($this->t('Start Time:'))}\s*([\d\:]+\s*A?P?M)/");

        $e->setStartDate(strtotime($date . ', ' . $startTime));

        $endTime = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'End Time:')]", null, true, "/{$this->opt($this->t('End Time:'))}\s*([\d\:]+\s*A?P?M)/");

        if (!empty($endTime)) {
            $e->setEndDate(strtotime($date . ', ' . $endTime));
        } else {
            $e->setNoEndDate(true);
        }

        $addressText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Start Time:'))}]/following::text()[contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'ddd-')][last()]/ancestor::strong[1]");

        if (empty($addressText)) {
            $addressText = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('Start Time:'))}]/following::text()[contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'ddd-')][last()]/ancestor::table[1]/descendant::text()[normalize-space()='unsubscribe']/ancestor::tr[1]/following-sibling::tr"));
        }

        if (empty($addressText)) {
            $addressText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Start Time:'))}]/following::text()[contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'ddd-')][last()]/ancestor::p[1]");
        }

        if (preg_match("/\|\s*(?<phone>\d{3}\-\d{3}\-\d{4})\n*(?<address>.+)/", $addressText, $m)
        || preg_match("/^(?<address>.{10,100})\s+(?<phone>(?:\d{3}\-\d{3}\-\d{4}|\(\d{3}\)\s*[\d\-]+))(?:$|\s)/", $addressText, $m)) {
            $e->setPhone($m['phone']);
            $e->setAddress($m['address']);
        }

        if ($this->http->XPath->query("//text()[normalize-space()='BOOKING CANCELLATION']")->length > 0) {
            $e->setCancelled(true);
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
