<?php

namespace AwardWallet\Engine\amtrak\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Cancellation extends \TAccountChecker
{
    public $mailFiles = "amtrak/it-78847882.eml";
    public $subjects = [
        '/Amtrak\: Reservation Cancellation Confirmation/',
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Amtrak')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('SALES RECEIPT - NOT VALID FOR TRAVEL'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Reservation Number'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]amtrak\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $t = $email->add()->train();

        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation Number')]", null, true, "/{$this->opt($this->t('Reservation Number'))}[\-\s]+([A-Z\d]+)/"));

        $status = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Billing Information')]/following::text()[normalize-space()][1]/ancestor::tr[1]/descendant::td[2]", null, true, "/{$this->opt($this->t('Reservation'))}\s*(\w+)$/");

        if (!empty($status)) {
            $t->general()
                ->status($status);

            if ($status == 'Cancelled') {
                $t->general()
                    ->cancelled();
            }
        }

        $s = $t->addSegment();

        $info = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation Number')]/following::text()[normalize-space()][1]");

        if (preg_match("/^\D+\s+\-\s+(?<depName>\D+)\,\s\((?<depCode>[A-Z]{3})\)\s+to\s+\D+\s+\-\s+(?<arrName>\D+)\,\s*\((?<arrCode>[A-Z]{3})\)/", $info, $m)) {
            $s->departure()
                ->name($m['depName'])
                ->code($m['depCode']);

            $s->arrival()
                ->name($m['arrName'])
                ->code($m['arrCode'])
                ->noDate();
        }

        $depDay = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Reservation Number')]/following::text()[normalize-space()][2]");
        $s->departure()
            ->date(strtotime($depDay));

        $t->price()
            ->total($this->http->FindSingleNode("//text()[normalize-space()='Original Amount Paid']/ancestor::tr[1]/descendant::td[2]", null, true, "/^\S{1}([\d\.]+)/"))
            ->currency($this->http->FindSingleNode("//text()[normalize-space()='Original Amount Paid']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\S{1})\d+/"));

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
}
