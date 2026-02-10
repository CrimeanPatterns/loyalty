<?php

namespace AwardWallet\Engine\aplus\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Reconfirming extends \TAccountChecker
{
    public $mailFiles = "aplus/it-38356272.eml";

    public $reFrom = ["@accor.com"];
    public $reBody = [
        'en' => ['We look forward to welcoming you to the'],
    ];
    public $reSubject = [
        'Reconfirming your arrival for:',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Arrival Date'   => 'Arrival Date',
            'Departure Date' => 'Departure Date',
            'Adults'         => ['Adults', 'Adult'],
            'Children'       => ['Children', 'Child'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'accor.com')]")->length > 0
            && $this->detectBody()
        ) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->stripos($from, $this->reFrom);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        if (isset($headers["subject"])) {
            return $this->stripos($headers["subject"], $this->reSubject);
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $r = $email->add()->hotel();

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Key Contacts'))}]/ancestor::td[1]");

        if ($nodes->length !== 1) {
            $this->logger->debug('other format hotelInfo');

            return false;
        }
        $root = $nodes->item(0);
        $r->hotel()
            ->name($this->http->FindSingleNode("./preceding-sibling::td[1]/descendant::text()[normalize-space()!=''][1]",
                $root))
            ->address(implode(" ",
                $this->http->FindNodes("./preceding-sibling::td[1]/descendant::text()[normalize-space()!=''][position()>1]",
                    $root)))
            ->phone($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Tel:'))}]", $root, false,
                "#{$this->opt($this->t('Tel:'))}\s*(.+)#"))
            ->fax($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Fax:'))}]", null, false,
                "#{$this->opt($this->t('Fax:'))}\s*(.+)#"));

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Reservation Details:'))}]/following::table[normalize-space()!=''][1][{$this->contains($this->t('Guest Name'))}]");

        if ($nodes->length !== 1) {
            $this->logger->debug('other format bookingInfo');

            return false;
        }
        $root = $nodes->item(0);
        $r->general()
            ->traveller($this->nextTd($this->t('Guest Name'), $root), true)
            ->confirmation($this->nextTd($this->t('Confirmation Number'), $root));

        $node = $this->nextTd($this->t('Number of Guests'), $root);
        $r->booked()
            ->checkIn($this->normalizeDate($this->nextTd($this->t('Arrival Date'), $root)))
            ->checkOut($this->normalizeDate($this->nextTd($this->t('Departure Date'), $root)))
            ->guests($this->re("#(\d+)\s+{$this->opt($this->t('Adults'))}#i", $node), false, true)
            ->kids($this->re("#(\d+)\s+{$this->opt($this->t('Children'))}#i", $node), false, true);

        $room = $r->addRoom();
        $room->setType($this->nextTd($this->t('Room Type'), $root));
        $request = $this->nextTd($this->t('Requests and Preferences'), $root);

        if (!empty($request)) {
            $room->setDescription($request);
        }

        $timeIn = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check in time'))}]/following::text()[normalize-space()!=''][1]");

        if (preg_match("#^\d+(?::\d+)?[ ]*(?:[ap]m)?$#i", $timeIn)) {
            $r->booked()->checkIn(strtotime($timeIn, $r->getCheckInDate()));
        }
        $timeOut = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check out time'))}]/following::text()[normalize-space()!=''][1]");

        if (preg_match("#^\d+(?::\d+)?[ ]*(?:[ap]m)?$#i", $timeOut)) {
            $r->booked()->checkOut(strtotime($timeOut, $r->getCheckOutDate()));
        }
        $cancel = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Cancellation Policy'))}]/following-sibling::tr[1]");

        if (!empty($cancel)) {
            $r->general()->cancellation($cancel);
        }

        return true;
    }

    private function nextTd($field, $root = null)
    {
        return $this->http->FindSingleNode("./descendant::td[{$this->eq($field)}]/following-sibling::td[1]", $root);
    }

    private function normalizeDate($str)
    {
        $in = [
            //Tuesday, 15 January, 2019
            '#^(\w+),\s+(\d+)\s+(\w+),?\s+(\d{4})$#u',
        ];
        $out = [
            '$2 $3 $4',
        ];
        $str = strtotime(preg_replace($in, $out, $str));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Arrival Date'], $words['Departure Date'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Arrival Date'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Departure Date'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
