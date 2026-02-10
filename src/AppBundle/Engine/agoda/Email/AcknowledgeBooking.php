<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Schema\Parser\Email\Email;

class AcknowledgeBooking extends \TAccountChecker
{
    public $mailFiles = "agoda/it-47044042.eml";

    public $reFrom = "@agoda.com";
    public $reSubject = [
        'en' => 'Please ensure Agoda guests have a room ready at',
    ];
    public $reBody = 'Agoda';
    public $reBody2 = [
        'en' => ['Below is your Agoda confirmed bookings list that you need to acknowledge:'],
    ];

    public static $dictionary = [
        'en' => [
            'confirmation' => 'Booking ID',
        ],
    ];

    public $lang = 'en';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        //Please ensure Agoda guests have a room ready at 138 Bowery, New York (NY), United States
        $address = $this->http->FindPreg('/a room ready at\s+(.+)/', false, $parser->getSubject());
        $nodes = $this->http->XPath->query("//tr[td[contains(text(),'Booking ID')] and td[contains(text(),'Room Type')]]/following-sibling::tr");
        $hotels = [];

        $email->setSentToVendor(true);

        foreach ($nodes as $root) {
            $cnf = $this->http->FindSingleNode("./td[1]", $root, false, '/^\d+$/');

            if (isset($cnf) && !array_key_exists($cnf, $hotels)) {
                $this->logger->debug($root->nodeValue);
                $hotels[$cnf] = null;
                $this->parseHotel($email, $root, $address);
            }
        }
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers["from"], $headers["subject"]) || stripos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//*[{$this->contains($this->reBody)}]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHotel(Email $email, $root, $address)
    {
        $r = $email->add()->hotel();
        $r->general()
            ->confirmation($this->http->FindSingleNode("./td[1]", $root), $this->t('confirmation'))
            ->traveller($this->http->FindSingleNode("./td[4]", $root));

        if (isset($address)) {
            $r->hotel()->address($address);
        }
        // Agoda Hotel Hot Line (United States) (1) 929 270 4046 | General Questions: biz@agoda.com |
        $string = $this->http->FindSingleNode("//text()[contains(., 'General Questions:')]");

        if (preg_match('/^(.+?)\s+([+\-\d\s)(]{10,})\s+/', $string, $m)) {
            $r->hotel()
                ->name($m[1])
                ->phone($m[2]);
        }

        $r->booked()
            ->checkIn2($this->http->FindSingleNode("./td[2]", $root))
            ->checkOut2($this->http->FindSingleNode("./td[3]", $root));

        $room = $r->addRoom();
        $room->setType($this->http->FindSingleNode("./td[5]", $root));
    }

    private function assignLang()
    {
        foreach ($this->reBody2 as $lang => $re) {
            if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return null;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'normalize-space(' . $node . ')="' . $s . '"';
                }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'contains(normalize-space(' . $node . '),"' . $s . '")';
                }, $field))
            . ')';
    }
}
