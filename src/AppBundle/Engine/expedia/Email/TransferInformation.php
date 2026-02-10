<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TransferInformation extends \TAccountChecker
{
    public $mailFiles = "expedia/it-708805085.eml";
    public $subjects = [
        '',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Trip details' => ['Trip details', 'Return trip details', 'Trip Details'],
            'Vehicle type' => ['Vehicle type', 'Vehicle Type'],
            'Driver notes' => ['Driver notes', "Driver's Notes"],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@taxi2airport.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'taxi2airport.com')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Trip details'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Vehicle type'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]taxi2airport\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseTransfer($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseTransfer(Email $email)
    {
        $t = $email->add()->transfer();

        $travellers = $this->http->FindNodes("//text()[normalize-space()='Passenger information']/following::table[1]/descendant::text()[normalize-space()='Name']/ancestor::tr[1]/descendant::td[2]");
        $t->general()
            ->travellers(preg_replace("/^(?:MR AND MRS|MRS|MR)/", "", $travellers));

        $confArray = [];

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Trip details'))}]/following::table[1]");

        foreach ($nodes as $root) {
            $conf = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Booking code']/ancestor::tr[1]/descendant::td[normalize-space()][2]", $root);

            if (in_array($conf, $confArray) === false) {
                $confArray[] = $conf;
            }

            $s = $t->addSegment();

            $s->departure()
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()='From']/ancestor::tr[1]/descendant::td[normalize-space()][2]", $root))
                ->date(strtotime($this->http->FindSingleNode("./descendant::text()[normalize-space()='Date & time']/ancestor::tr[1]/descendant::td[normalize-space()][2]", $root, true, "/^(\d+.+\d+)\s+\(/")));

            $s->arrival()
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()='To']/ancestor::tr[1]/descendant::td[normalize-space()][2]", $root));

            $endTime = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Driver notes'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]", $root, true, "/{$this->opt($this->t('Flight departure:'))}\s*(\d+\:\d+)$/");

            if (empty($endTime)) {
                $endTime = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Driver notes'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]", $root, true, "/Flight leaves.*at\s*(\d+\:\d+a?p?m)/");
            }

            if (empty($endTime)) {
                $s->arrival()
                    ->noDate();
            } else {
                $s->arrival()
                    ->date(strtotime($endTime, $s->getDepDate()));
            }

            $s->setCarType($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Vehicle type'))}]/ancestor::tr[1]/descendant::td[normalize-space()][2]", $root));
            $s->extra()
                ->adults($this->http->FindSingleNode("//text()[normalize-space()='Passengers']/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\d+)$/"));
        }

        foreach ($confArray as $conf) {
            $t->general()
                ->confirmation($conf);
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

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
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
