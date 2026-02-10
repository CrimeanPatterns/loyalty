<?php

namespace AwardWallet\Engine\disneyresort\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class Reminder extends \TAccountChecker
{
    public $mailFiles = "disneyresort/it-1637506.eml, disneyresort/it-278502589.eml, disneyresort/it-278502630.eml, disneyresort/it-2948446.eml, disneyresort/it-75917978.eml, disneyresort/it-360138498-junk.eml";

    public $detectFrom = [
        'disneydestinations.com',
    ];
    public $detectSubject = [
        'Walt Disney World Dining Reservation Reminder',
        'Disneyland Dining Reservation Reminder',
        'WALT DISNEY WORLD® Dining Reservation Reminder',
        'Your Theme Park reservation reminder', // junk
    ];
    public $detectBody = [
        'Dining Reservation Reminder',
        'reminder of your upcoming dining reservation',
        'reminder that you have a Walt Disney World Theme Park reservation coming up', // junk
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = '';

        if ($this->http->XPath->query("//*[ preceding::tr[not(.//tr) and normalize-space()][1][{$this->eq(['Reservation Details', 'Reservation details'])}] and count(tr[count(*[normalize-space()])=2])=2 and tr[normalize-space()][1]/*[normalize-space()][1][{$this->eq(['Park', 'Park:', 'Park :'])}] and tr[normalize-space()][2]/*[normalize-space()][1][{$this->eq(['Date', 'Date:', 'Date :'])}] ]")->length === 1) {
            $type = 'junk';
            $email->setIsJunk(true);
        } else {
            $this->parseDining($email);
        }

        $email->setType('Reminder' . ucfirst($type));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.disney.') or contains(@href, '.disneydestinations.')]")->length > 0) {
            foreach ($this->detectBody as $dBody) {
                if ($this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers["subject"])) {
            return false;
        }

        if ($this->striposAll($headers["subject"], $this->detectSubject) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    private function parseDining(Email $email): void
    {
        $ev = $email->add()->event();

        // General
        $conf = $this->http->FindSingleNode("//text()[" . $this->eq(['Dining Confirmation number:', 'Dining Confirmation Number:']) . "]/following::text()[normalize-space()][1]", null, true, "/^\s*(\w{5,})\s*$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->starts(['Dining Confirmation number:', 'Dining Confirmation Number:']) . "]", null, true, "/:\s*(\w{5,})\s*$/");
        }
        $ev->general()
            ->confirmation($conf,
                'Dining Confirmation number')
        ;

        $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Guest information:']/following::text()[normalize-space()][1]")
            ?? $this->http->FindSingleNode("//text()[{$this->starts(["Hello,"])}]", null, true, "/Hello,\s*([[:alpha:]\- ]+?)\s*[,!]*$/");

        if (empty($traveller) && !empty($this->http->FindSingleNode("//text()[{$this->eq(['Hello', 'Hello,', 'Hello ,', 'Hello.', 'Hello .', 'Hello!', 'Hello !'])}]/following::text()[normalize-space()][1][not({$this->starts(',')})]"))) {
        } else {
            $ev->general()
                ->traveller($traveller);
        }

        $ev->place()
            ->type(Event::TYPE_RESTAURANT);

        $info = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Party Size:')]/ancestor::*[contains(., 'Time:')][1]//text()[normalize-space()]"));

        if (preg_match("#Restaurant Name:\s*(?<Name>.*)\n\s*Dining Location:\s*(?<Address>.*)\n\s*Date:\s+\w+,\s+(?P<Date>.*)\n\s*Time:\s+(?<Time>\d+:\d+\s*(?:am|pm))#i", $info, $m)) {
            $ev->place()
                ->name($m['Name'])
                ->address($m['Address'] . (($this->http->XPath->query("//*[" . $this->contains(["your dining reservation at select Walt Disney World Resort"]) . "]")->length > 0) ? ', Walt Disney World Resort' : ''))
            ;

            $ev->booked()
                ->start(strtotime($m['Date'] . ' ' . $m['Time']))
                ->noEnd();
        } elseif (preg_match("#(^|Reservation Details:)?\s*(?<Name>.*)\n(?<Address>.*)\n\s*Date:\s+\w+,\s+(?P<Date>.*)\n\s*Time:\s+(?<Time>\d+:\d+\s+(?:am|pm))#i", $info, $m)) {
            $ev->place()
                ->name($m['Name'])
                ->address($m['Address'])
                ->phone($this->http->FindSingleNode("//text()[contains(., 'or calling ')]", null, true, "#or calling\s*.*?(\(\d+\)\s+\d+-\d+)#"))
            ;

            $ev->booked()
                ->start(strtotime($m['Date'] . ' ' . $m['Time']))
                ->noEnd();
        }
        $ev->booked()
            ->guests($this->http->FindSingleNode("//text()[contains(., 'Party Size')]", null, true, "#Party Size:\s*(\d+)#")
                ?? $this->http->FindSingleNode("//text()[normalize-space() = 'Party Size:']/following::text()[normalize-space()][1]", null, true, "#^\s*(\d+)\s*$#"))
        ;
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
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

    private function contains($field, $node = '')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field));
    }
}
