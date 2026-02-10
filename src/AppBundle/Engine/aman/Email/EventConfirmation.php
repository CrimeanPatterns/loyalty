<?php

namespace AwardWallet\Engine\aman\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EventConfirmation extends \TAccountChecker
{
    public $mailFiles = "aman/it-189990835.eml";
    public $subjects = [
        '/Aman\D+Reservation Confirmation/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'beforeName'    => "Thank you for choosing the",
            'afterName'     => ". We are delighted to confirm",
            'beforeAddress' => "We are located on the",
            'afterAddress'  => "and are available via",
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aman.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Aman ')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Thank you for choosing the Aman'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('We are located on'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Date of Treatment:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aman\.com$/', $from) > 0;
    }

    public function ParseEvent(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[contains(normalize-space(), 'Name of guest:')]");

        foreach ($nodes as $root) {
            $e = $email->add()->event();

            $e->general()
                ->noConfirmation()
                ->traveller($this->http->FindSingleNode("./ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Name of guest:'))}\s*(.+)/"), true);

            $e->setName($this->http->FindSingleNode("//text()[{$this->starts($this->t('beforeName'))}]", null, true, "/{$this->opt($this->t('beforeName'))}(.+){$this->opt($this->t('afterName'))}/"));
            $e->setAddress($this->http->FindSingleNode("//text()[{$this->starts($this->t('beforeAddress'))}]", null, true, "/{$this->opt($this->t('beforeAddress'))}(.+){$this->opt($this->t('afterAddress'))}/"));

            $e->setEventType(4);

            $date = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'Date of Treatment:')][1]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Date of Treatment:'))}\s*(.+)/");
            $timeStart = $this->http->FindSingleNode("./following::text()[contains(normalize-space(), 'Time of Treatment:')][1]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Time of Treatment:'))}\s*(.+)/");

            $e->booked()
                ->start(strtotime($date . ', ' . $timeStart))
                ->noEnd();
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
