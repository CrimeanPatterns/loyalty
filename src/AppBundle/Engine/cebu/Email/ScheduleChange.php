<?php

namespace AwardWallet\Engine\cebu\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ScheduleChange extends \TAccountChecker
{
    public $mailFiles = "cebu/it-504410636.eml";
    public $subjects = [
        'Minor Schedule Change for Flight',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@email.mycebupacific.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Cebu Pacific')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Original flight schedule'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('New flight schedule'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.mycebupacific\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Booking Reference No.']/ancestor::tr[normalize-space()][1]/following::tr[1]/descendant::td[1]"))
            ->travellers($this->http->FindNodes("//text()[normalize-space()='Name of Guest/s']/ancestor::tr[normalize-space()][1]/following::tr[1]/descendant::td[2]/descendant::text()[normalize-space()]"));

        $nodes = $this->http->XPath->query("//text()[normalize-space()='New flight schedule']/ancestor::tr[1]/following::table[normalize-space()][contains(normalize-space(), 'Flight No.:')]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $flightInfo = $this->http->FindSingleNode("./descendant::td[contains(normalize-space(), 'Flight No.:')]", $root);

            if (preg_match("/{$this->opt($this->t('Flight No.:'))}\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d{1,4})$/", $flightInfo, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $depInfo = $this->http->FindSingleNode("./descendant::td[contains(normalize-space(), 'Departure:')]", $root);

            if (preg_match("/Departure:\s*(?<depDate>.*)\s+\/\s*[\d\:]+\s*\((?<depTime>[\d\:]+\s*A?P?M)\)\s*\D+\((?<depCode>[A-Z]{3})\)/", $depInfo, $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->date(strtotime($m['depDate'] . ', ' . $m['depTime']));
            }

            $arrInfo = $this->http->FindSingleNode("./descendant::td[contains(normalize-space(), 'Arrival:')]", $root);

            if (preg_match("/Arrival:\s*(?<arrDate>.*)\s+\/\s*[\d\:]+\s*\((?<arrTime>[\d\:]+\s*A?P?M)\)\s*\D+\((?<arrCode>[A-Z]{3})\)/", $arrInfo, $m)) {
                $s->arrival()
                    ->code($m['arrCode'])
                    ->date(strtotime($m['arrDate'] . ', ' . $m['arrTime']));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseFlight($email);

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
}
