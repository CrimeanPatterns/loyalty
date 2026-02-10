<?php

namespace AwardWallet\Engine\fcmtravel\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicketConfirmation extends \TAccountChecker
{
    public $mailFiles = "fcmtravel/it-136777687-tag.eml, fcmtravel/it-7669280.eml, fcmtravel/it-9868886.eml, fcmtravel/it-655534241-tag.eml";
    public $reFrom = "@uk.fcm.travel";
    public $reSubject = [
        "en" => "E-TICKET CONFIRMATION",
        "Travel Itinerary for",
    ];
    public $reBody = 'FCm Travel Solutions';
    public $reBody2 = [
        "en" => [
            'Thank you for booking with FCm Travel Solutions',
            'Thank you for booking with Flight Centre Travel Group',
            "Any enquiry processed by TAG's Emergency Team may be",
            'information on how TAG can',
        ],
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    private $detectProvs = [
        'flightcentre' => [
            'text'  => ['Thank you for booking with Flight Centre Travel Group'],
            'xpath' => ["//img[contains(@src, 'FlightCentreItinLogo')]"],
        ],
        'tag' => [
            'text'  => ['Any enquiry processed by TAG\'s Emergency Team may be subject', 'TAG Global Travel and Event Management Company'],
            'xpath' => ["//a[contains(@href, 'tag-group.com')] | //*[contains(., 'TAG Global Travel and Event Management Company')]"],
        ],
    ];

    public static function getEmailProviders()
    {
        return ['fcmtravel', 'flightcentre', 'tag'];
    }

    public function parseHtml(Email $email): void
    {
        // Travel Agency
        $otaConfNoText = $this->http->FindSingleNode("//text()[{$this->starts(["FCm Reference:", 'TAG Booking Reference:', 'Galileo booking file reference:'])}]");

        if (preg_match("/^(.+?)\s*[:]+\s*(.+?)(?:\s*[\/]+\s*Consultant|$)/i", $otaConfNoText, $m)) {
            $otaConfirmation = $m[2];
            $otaConfirmationTitle = $m[1];
        } else {
            $otaConfirmation = $otaConfirmationTitle = null;
        }
        $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        $travellers = $this->http->FindNodes("//text()[" . $this->starts("To :") . "]", null, "#:\s+(.+)#");

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//td[" . $this->eq("Traveller(s) :") . "]/following-sibling::td[1]//text()[normalize-space()]",
                null, "/^\s*(.+?)( - .+|$)/");
        }
        $f->general()
            ->travellers($travellers, true);

        // Issued
        $tickets = array_filter(array_merge(
            $this->http->FindNodes("//tr/*[{$this->eq(["E Ticket Number(s):", "E-Ticket Number(s):"])}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]", null, "/^\s*(\d{10,})(?:\s+[-–]+\s+\D|$)/"),
            $this->http->FindNodes("//text()[{$this->eq(["E Ticket Number(s):", "E-Ticket Number(s):"])}]/following::text()[normalize-space()][1]", null, "/^\s*(\d{10,})$/")
        ));

        if (count($tickets) > 0) {
            $f->issued()->tickets(array_unique($tickets), false);
        }

        // Accounts
        $accounts = [];
        $accountsRows = array_filter($this->http->FindNodes("//td[{$this->eq("Traveller(s) :")}]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()]", null, "/Membership\s*:\s*(.+)/i"));

        foreach ($accountsRows as $aRow) {
            $accounts = array_merge($accounts, preg_split('/(\s*\/s*)+/', $aRow));
        }

        if (count($accounts) > 0) {
            $f->program()->accounts(array_unique(array_map('trim', $accounts)), false);
        }

        // Segments
        $xpath = "//text()[" . $this->contains(["Airline Reference:", "Airline Booking Reference :"]) . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->number($this->http->FindSingleNode("./tr[1]/td[3]", $root, true, "/\s+-\s+(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])(\d{1,5})\b/"))
                ->name($this->http->FindSingleNode("./tr[1]/td[3]", $root, true, "/\s+-\s+([A-Z\d][A-Z]|[A-Z][A-Z\d])\d{1,5}\b/"))
            ;
            $conf = $this->http->FindSingleNode(".//text()[" . $this->starts(["Airline Reference:", 'Airline Booking Reference :']) . ']',
                $root, true, "/:\s*([A-Z\d]{5,7})\s*$/");

            if (empty($conf)) {
                $conf = $this->nextText(["Airline Reference:", 'Airline Booking Reference :'], $root);
            }
            $s->airline()
                ->confirmation($conf);

            // Departure
            $nameDepVal = implode("\n", $this->http->FindNodes("tr[2]/td[2]/descendant::text()[normalize-space()]", $root));

            if (preg_match($patternName = '/^\s*(?<code>[A-Z]{3})[ ]+-[ ]+(?<name>.{2,})/s', $nameDepVal, $m)) {
                $s->departure()->code($m['code']);
                $nameDep = $m['name'];
            } else {
                $s->departure()->noCode();
                $nameDep = $nameDepVal;
            }

            if (preg_match($patternTerm = "/^(?<name>.{2,})\n+(?<terminal>.+)$/", $nameDep, $m)) {
                // it-655534241.eml
                $nameDep = $m['name'];
                $terminalDep = $m['terminal'];
            } else {
                $terminalDep = $this->http->FindSingleNode("tr[3][ *[1][normalize-space()=''] ]/*[2]", $root, false);
            }
            $s->departure()
                ->date($this->normalizeDate($this->http->FindSingleNode("./tr[1]/td[2]", $root) . ', ' . $this->http->FindSingleNode("./tr[2]/td[1]", $root)))
                ->name($nameDep)
                ->terminal($terminalDep ? preg_replace("/\s*\bTerminal\b\s*/i", '', $terminalDep) : null, false, true);

            // Arrival
            $nameArrVal = implode("\n", $this->http->FindNodes("tr[5]/td[2]/descendant::text()[normalize-space()]", $root));

            if (preg_match($patternName, $nameArrVal, $m)) {
                $s->arrival()->code($m['code']);
                $nameArr = $m['name'];
            } else {
                $s->arrival()->noCode();
                $nameArr = $nameArrVal;
            }

            if (preg_match($patternTerm, $nameArr, $m)) {
                $nameArr = $m['name'];
                $terminalArr = $m['terminal'];
            } else {
                $terminalArr = $this->http->FindSingleNode("tr[6][ *[1][normalize-space()=''] ]/*[2]", $root);
            }
            $s->arrival()
                ->date($this->normalizeDate($this->http->FindSingleNode("./tr[4]/td[1]", $root) . ', ' . $this->http->FindSingleNode("./tr[5]/td[1]", $root)))
                ->name($nameArr)
                ->terminal($terminalArr ? preg_replace("/\s*\bTerminal\b\s*/i", '', $terminalArr) : null, false, true);

            // Extra
            $s->extra()
                ->aircraft($this->http->FindSingleNode("tr[1]/td[3]", $root, true, "/\s+on a\s+(.{2,}?)(?:\s*Operated By|$)/i"))
                ->stops(preg_replace("/^\s*non\W?stop\s*$/i", '0', $this->http->FindSingleNode("./tr/td[3][" . $this->contains(['stop', 'Stop']) . "]", $root)))
                ->duration($this->http->FindSingleNode(".//text()[" . $this->starts("Journey Time:") . "]", $root, true, "#:\s+(.+)#"))
                ->miles($this->http->FindSingleNode("tr/td[3]/descendant::text()[{$this->starts("Distance (in Miles)")}]", $root, true, "/:\s*(\d{1,5})$/"), false, true)
                ->cabin($this->http->FindSingleNode("./tr[td[3][" . $this->contains(['stop', 'Stop']) . "]]/preceding-sibling::tr[1]/td[position()>=3][not(contains(., 'on a '))]", $root))
            ;

            $seats = $this->http->FindNodes(".//td[not(.//td)][" . $this->starts(["Seats:"]) . "]//text()[normalize-space()]", $root);

            if (!empty($seats)) {
                $seats = preg_replace("/^\s*Seats\s*:\s*/", '', implode("\n", $seats));

                if (preg_match_all("/^\s*(\d{1,3}[A-Z]) *(?:\(|$)/m", $seats, $m)) {
                    $s->extra()
                        ->seats($m[1]);
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false
        && strpos($headers["from"], '@tag-group.com') === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $re) {
            foreach ($re as $r) {
                if (strpos($body, $r) !== false || $this->http->XPath->query("//img[contains(@alt, 'FCM Travel Solutions')]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->reBody2 as $lang => $re) {
            foreach ($re as $r) {
                if (strpos($this->http->Response["body"], $r) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $providerCode = $this->getProviderCode($parser->getHTMLBody());

        if ('' !== $providerCode) {
            $email->setProviderCode($providerCode);
        }

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

    private function getProviderCode(string $body): string
    {
        $res = '';

        foreach ($this->detectProvs as $detectProv => $detects) {
            foreach ($detects['text'] as $detect) {
                if (stripos($body, $detect) !== false) {
                    $res = $detectProv;
                }
            }
            $xpath = implode('|', $detects['xpath']);

            if ($this->http->XPath->query($xpath)->length === 0) {
                $res = '';
            }
        }

        return $res;
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // Monday, January 31 2022, 4:00 Depart
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+)\s+(\d{4}),\s+(\d+:\d+(?:\s*[ap]m)?)\s+(?:Depart|Arrive)\s*$#i",
            // Monday, 31 January 2022, 4:00 PM Depart
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+(\d{4}),\s+(\d+:\d+(?:\s*[ap]m)?)\s+(?:Depart|Arrive)\s*$#i",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function nextText($field, $root = null): ?string
    {
        $rule = $this->starts($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }
}
