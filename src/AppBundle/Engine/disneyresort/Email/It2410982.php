<?php

namespace AwardWallet\Engine\disneyresort\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class It2410982 extends \TAccountChecker
{
    public $mailFiles = "disneyresort/it-2410982.eml, disneyresort/it-2813253.eml, disneyresort/it-2813255.eml, disneyresort/it-2813256.eml, disneyresort/it-2813258.eml, disneyresort/it-58931810.eml, disneyresort/it-78136190.eml";

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Walt Disney World Dining') !== false
            || strpos($from, 'Disneyland Dining') !== false
            || preg_match('/[.@](?:disneyonline|disneyland)\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Your Dining Reservation has been') !== false || stripos($headers['subject'], 'Your Reservation has been') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"disney.go.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"your Disneyland Resort") or contains(normalize-space(),"Disney. All Rights Reserved") or contains(.,"@disneyonline.com") or contains(.,"@disneyland.com") or contains(.,"@disney.com")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query('//node()[' . $this->contains(["Reservation Detail", "Dining Party:", "reservation has been canceled"]) . ']')->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        $this->parseDining($email, $parser->getSubject());

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function parseDining(Email $email, string $subjectText): void
    {
        $ev = $email->add()->event();

        // General
        $conf = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Cancellation Confirmation Number')]/following::text()[normalize-space()][1]");

        if (!empty($conf)) {
            $ev->general()
                ->cancellation($conf)
                ->cancelled()
                ->status('Canceled')
            ;
        } else {
            $conf = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Confirmation Number')]/following::text()[normalize-space()][1]");
            $ev->general()
                ->confirmation($conf, 'Dining Confirmation number')
                ->status($this->http->FindSingleNode("//text()[{$this->contains([', your dining reservation is', ', your dining reservation has been'])}]", null, true, "/{$this->opt([', your dining reservation is', ', your dining reservation has been'])}\s+({$this->opt(['confirmed', 'changed'])})(?:\s*[,.;!?]|$)/i") ?? $this->re("/{$this->opt(['reservation is', 'reservation has been'])}\s+({$this->opt(['confirmed', 'changed'])})(?:\s*[,.;!?]|$)/i", $subjectText))
            ;
        }
        $ev->general()
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq(['Reservation Primary Contact', 'Reservation Contact', 'Primary Guest']) . "]/following::text()[normalize-space()][1]"))
        ;

        // Place
        $ev->place()
            ->type(Event::TYPE_RESTAURANT)
        ;

        $xpathFilter = "not(preceding::text()[contains(normalize-space(),'This previous dining reservation is cancelled')])";

        if ($this->http->XPath->query("//text()[contains(normalize-space(),'Reservation for') and {$xpathFilter}]")->length > 0) {
            $ev->place()
                ->name($this->http->FindSingleNode("//text()[contains(normalize-space(),'Reservation for') and {$xpathFilter}]/following::text()[normalize-space()][1]"))
                ->address(implode(', ', array_filter([trim($this->http->FindSingleNode("//text()[contains(normalize-space(),'Reservation for') and {$xpathFilter}]/following::text()[normalize-space()][3]
                [not(contains(normalize-space(), 'Reservation'))]")), 'Walt Disney World Resort'])))
            ;

            $date = $this->normalizeDate($this->http->FindSingleNode("//text()[contains(normalize-space(),'Reservation for') and {$xpathFilter}]", null, true, "# on (.+)#"));
            $time = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Reservation for') and {$xpathFilter}]/following::text()[normalize-space()][2]", null, true, "#(.+) - #");
        } elseif (!empty($this->http->FindSingleNode("(//text()[contains(normalize-space(), 'Location:')])[1]"))) {
            $ev->place()
                ->name($this->http->FindSingleNode("//tr[td[1][.//img and not(normalize-space())] and td[3][" . $this->starts(['Dining Confirmation Number', 'Confirmation Number:']) . "]]/td[2][count(.//td[not(.//td) and normalize-space()]) = 2]/descendant::text()[normalize-space()][1]/ancestor::td[1]"))
                ->address(implode(', ', ([trim($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Location:')]", null, true, "/^\s*Location:\s*(.+)/")), 'Walt Disney World Resort'])))
            ;
            $date = $this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Date:')]", null, true,
                    "#:\s*(.+)#"));
            $time = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Time:')]", null, true,
                    "#:\s*(.+?),#");
        }

        if (stripos($ev->getName(), 'Restaurant Name N/A') !== false) {
            $ev->place()->name(null);
        }
        $phone = $this->http->FindSingleNode("(//text()[" . $this->contains(['For assistance', 'To learn more', 'Walt Disney World dining reservation']) . "])[1]", null, true,
            "#(?:For assistance|To learn more|Walt Disney World dining reservation), call ([\d+\(\) \-+]+)#i");

        if (!empty($phone)) {
            $ev->place()
                ->phone($phone);
        }

        if (!empty($date) && !empty($time)) {
            $ev->booked()
                ->start(strtotime($date . ', ' . $time))
                ->noEnd()
            ;
        }

        // Booked
        $ev->booked()->guests($this->http->FindSingleNode("//text()[{$this->contains(['Reservation for', 'Dining Party:'])} and {$xpathFilter}]", null, true, "/(?:for|:) (\d+) (?:Guests?|on )/"));
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str): string
    {
        //$this->logger->error($str);
        $in = [
            // November 16, 2019; Thu, Dec 19, 2019
            "#^\s*(?:\w+[, ]+)?(\w+)\s+(\d+)[, ]+(\d{4})\s*$#u",
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], 'en')) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
