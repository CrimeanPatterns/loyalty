<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BlueTables3 extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = ['BCD Travel', '@BCDTRAVEL.'];
    public $reBody = [
        'en' => ['Agency Contact', 'Trip Summary'],
    ];
    public $reSubject = [
        '#Itinerary for .+?, Trip to:#',
        '#Itinerary and e-ticket receipt for .+?, Trip to:#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];
    private $mainXpath = "//text()[normalize-space()='Status']/ancestor::tr[count(./td[normalize-space()])>1][1][starts-with(normalize-space(.),'Status')]";
    private $travellers;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(),'BCD Travel')]")->length > 0
            && $this->http->XPath->query($this->mainXpath)->length > 0
        ) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (preg_match($reSubject, $headers["subject"])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 2; // flights | hotels
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function parseEmail(Email $email)
    {
        $ruleYear = "contains(translate(.,'1234567890','dddddddddd'),' dddd ')";
        $ruleTime = "contains(translate(.,'1234567890','dddddddddd'),'d:dd')";

        $this->travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/ancestor::table[count(./descendant::text()[normalize-space()!=''])>1][1]/descendant::tr[normalize-space()!=''][1][normalize-space()='Passengers']/following-sibling::tr[normalize-space()!='']");
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference'))}]", null,
                false, "#{$this->opt($this->t('Booking Reference'))}[\s:]+([A-Z\d]{5,})#"),
                $this->t('Booking Reference'));
        $airs = [];
        $xpath = $this->mainXpath . "/preceding-sibling::tr[normalize-space()][count(./td[normalize-space()])>=2][position()<=4][{$ruleYear} and not($ruleTime)][1]";
        $this->logger->debug("[XPATH]: " . $xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $type = $this->http->FindSingleNode("./td[normalize-space(.)!=''][1]", $root);

            if (in_array($type, (array) $this->t('Flight'))) {
                $confNo = $this->http->FindSingleNode("./td[normalize-space(.)!=''][3]", $root);
                $airs[$confNo][] = $root;
            } elseif (in_array($type, (array) $this->t('Hotel'))) {
                if (!$this->parseHotel($root, $email)) {
                    return false;
                }
            } else {
                $this->logger->debug('other format or new type');

                return false;
            }
        }

        if (count($airs) > 0) {
            if (!$this->parseFlight($airs, $email)) {
                return false;
            }
        }

        return true;
    }

    private function parseFlight(array $airs, Email $email)
    {
        $ruleYear = "contains(translate(.,'1234567890','dddddddddd'),'dddd')";

        foreach ($airs as $confNo => $roots) {
            $r = $email->add()->flight();
            $r->general()
                ->confirmation($confNo)
                ->travellers($this->travellers);
            $flights = [];

            foreach ($roots as $root) {
//                $date = $this->http->FindSingleNode("./td[normalize-space(.)!=''][2]", $root);
                $s = $r->addSegment();
                $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]", $root);

                if (preg_match("#{$this->opt($this->t('Flight:'))} *([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)#", $node, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);

                    $depCode = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'{$m[1]}') and contains(.,'{$m[2]}')]/ancestor::tr[count(./td)>3][1][./td[normalize-space()!=''][1][{$ruleYear}]]/td[normalize-space()!=''][2]",
                        null, false, "#^\s*([A-Z]{3})\s*$#");
                    $arrCode = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'{$m[1]}') and contains(.,'{$m[2]}')]/ancestor::tr[count(./td)>3][1][./td[normalize-space()!=''][1][{$ruleYear}]]/td[normalize-space()!=''][3]",
                        null, false, "#^\s*([A-Z]{3})\s*$#");
                    $bookingCode = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'{$m[1]}') and contains(.,'{$m[2]}')]/ancestor::tr[count(./td)>3][1][./td[normalize-space()!=''][1][{$ruleYear}]]/td[normalize-space()!=''][7]",
                        null, false, "#^\s*([A-Z]{1,2})\s*$#");
                    $s->departure()->code($depCode);
                    $s->arrival()->code($arrCode);
                    $s->extra()->bookingCode($bookingCode);

                    $flights[] = $m[1] . $m[2];
                }

                $depName = implode(', ',
                    $this->http->FindNodes("./following::tr[normalize-space()!=''][count(./td[normalize-space()!=''])=2][1][{$this->starts($this->t('Depart'))}]/td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][position()<3]",
                        $root));
                $depDate = $this->http->FindSingleNode("./following::tr[normalize-space()!=''][count(./td[normalize-space()!=''])=2][1][{$this->starts($this->t('Depart'))}]/td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][position()=3]",
                    $root);
                $arrName = implode(', ',
                    $this->http->FindNodes("./following::tr[normalize-space()!=''][count(./td[normalize-space()!=''])=2][2][{$this->starts($this->t('Arrive'))}]/td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][position()<3]",
                        $root));
                $arrDate = $this->http->FindSingleNode("./following::tr[normalize-space()!=''][count(./td[normalize-space()!=''])=2][2][{$this->starts($this->t('Arrive'))}]/td[normalize-space()!=''][2]/descendant::text()[normalize-space()!=''][position()=3]",
                    $root);

                $s->departure()
                    ->name($depName)
                    ->date(strtotime($depDate));
                $s->arrival()
                    ->name($arrName)
                    ->date(strtotime($arrDate));

                $s->extra()
                    ->status($this->http->FindSingleNode("./following::tr[normalize-space()!=''][count(./td[normalize-space()!=''])=2][3][{$this->starts($this->t('Status'))}]/td[normalize-space()!=''][2]",
                        $root))
                    ->cabin($this->http->FindSingleNode("./following::tr[normalize-space()!=''][count(./td[normalize-space()!=''])=2][4][{$this->starts($this->t('Class'))}]/td[normalize-space()!=''][2]",
                        $root))
                    ->duration($this->http->FindSingleNode("./following::tr[normalize-space()!=''][count(./td[normalize-space()!=''])=2][5][{$this->starts($this->t('Duration'))}]/td[normalize-space()!=''][2]",
                        $root))
                    ->aircraft($this->http->FindSingleNode("./following::tr[normalize-space()!=''][count(./td[normalize-space()!=''])=2][position()=6 or position()=7][{$this->starts($this->t('Equipment'))}]/td[normalize-space()!=''][2]",
                        $root));

                $node = $this->http->FindSingleNode("./following::tr[normalize-space()!=''][count(./td[normalize-space()!=''])=2][6][{$this->starts($this->t('Terminal Information'))}]/td[normalize-space()!=''][2]",
                    $root);

                if (preg_match("#{$this->opt($this->t('Departs from terminal'))} (\w+)#", $node, $m)) {
                    $s->departure()->terminal($m[1]);
                }

                if (preg_match("#{$this->opt($this->t('Arrives at terminal'))} (\w+)#", $node, $m)) {
                    $s->arrival()->terminal($m[1]);
                }
            }
            $ticketRoots = $this->http->XPath->query("//text()[{$this->eq($this->t('Flight(s)'))}]/ancestor::tr[count(./td[normalize-space()!=''])=2][1][{$this->contains($flights, true)}]");
            $tickets = [];
            $sums = ['cost' => 0.0, 'tax' => 0.0, 'total' => 0.0];
            $currency = '';

            if ($ticketRoots->length > 0) {
                foreach ($ticketRoots as $ticketRoot) {
                    $node = $this->http->FindSingleNode("./following-sibling::tr[2][contains(.,'Ticket Number')]/td[normalize-space()!=''][2]",
                        $ticketRoot);

                    if (!empty($node)) {
                        $tickets[] = $node;
                    }
                    $node = $this->http->FindSingleNode("./following-sibling::tr[1][contains(.,'Fare')]/td[normalize-space()!=''][2]",
                        $ticketRoot);

                    if (isset($sums) && preg_match("#(?<cost>\d[\d\.\,]+) (?<currency>[A-Z]{3}) *\+ *(?<tax>\d[\d\.\,]+) [A-Z]{3} {$this->opt($this->t('taxes'))} : (?<total>\d[\d\.\,]+) [A-Z]{3}#",
                            $node, $m)
                    ) {
                        $sums['cost'] += PriceHelper::cost($m['cost']);
                        $sums['tax'] += PriceHelper::cost($m['tax']);
                        $sums['total'] += PriceHelper::cost($m['total']);
                        $currency = $m['currency'];
                    } else {
                        $sums = null;
                    }
                }

                if (isset($sums) && ($sums['cost'] + $sums['tax'] === $sums['total'])) {
                    $r->price()
                        ->total($sums['total'])
                        ->tax($sums['tax'])
                        ->cost($sums['cost'])
                        ->currency($currency);
                }

                if (count($tickets) > 0) {
                    $r->issued()->tickets($tickets, false);
                }
            }
        }

        return true;
    }

    private function parseHotel(\DOMNode $root, Email $email)
    {
        $date = $this->http->FindSingleNode("./td[normalize-space(.)!=''][2]", $root);
        $confNo = $this->http->FindSingleNode("./td[normalize-space(.)!=''][3]", $root);

        $r = $email->add()->hotel();

        $r->general()
            ->confirmation($confNo)
            ->status($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][4][{$this->starts($this->t('Status'))}]/td[normalize-space()!=''][2]",
                $root))
            ->travellers($this->travellers);

        $r->hotel()
            ->name($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][1]", $root))
            ->address($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][2][{$this->starts($this->t('Address'))}]/td[normalize-space()!=''][2]",
                $root))
            ->phone($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][3][{$this->starts($this->t('Tel'))}]/td[normalize-space()!=''][2]",
                $root));

        $node = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][5][{$this->starts($this->t('Check-In / Check-Out'))}]/td[normalize-space()!=''][2]",
            $root);

        if (preg_match("#^ *(.+\d{4}) *- *(.+\d{4}) *$#", $node, $m)) {
            $r->booked()
                ->checkIn(strtotime($m[1]))
                ->checkOut(strtotime($m[2]));
        }
        $r->general()
            ->cancellation($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][6][{$this->starts($this->t('Cancellation Policy'))}]/td[normalize-space()!=''][2]",
                $root));

        $room = $r->addRoom();
        $room->setRate($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][7][{$this->starts($this->t('Rate Per Night'))}]/td[normalize-space()!=''][2]",
            $root));
        $sum = $this->getTotalCurrency($this->http->FindSingleNode("./following-sibling::tr[normalize-space()!=''][8][{$this->starts($this->t('Estimated Total'))}]/td[normalize-space()!=''][2]",
            $root));
        $r->price()
            ->total($sum['Total'])
            ->currency($sum['Currency']);

        if (!empty($node = $r->getCancellation())) {
            $this->detectDeadLine($r, $node);
        }

        return true;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        //Here we describe various variations in the definition of dates deadLine
        if (preg_match("#Cancel On (\d+) *(\w+?) *(\d{2}) By (\d+:\d+(?:\s*[ap]m)?) Local Hotel Time#i",
            $cancellationText, $m)) {
            $h->booked()->deadline(strtotime($m[1] . ' ' . $m[2] . ' 20' . $m[3] . ', ' . $m[4]));
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field, $noSpaces = false)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        if ($noSpaces) {
            return implode(' or ', array_map(function ($s) {
                return 'contains(translate(.," ",""),"' . $s . '")';
            }, $field));
        } else {
            return implode(' or ', array_map(function ($s) {
                return 'contains(normalize-space(.),"' . $s . '")';
            }, $field));
        }
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
