<?php

namespace AwardWallet\Engine\austrian\Email;

use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class BoardingPassGate extends \TAccountChecker
{
    public $mailFiles = "austrian/it-100732147.eml, austrian/it-38621139.eml, austrian/it-38877418.eml";

    public $reFrom = ["austrianinternet@austrian.com"];
    public $reBody = [
        'en' => ['Please visit www.austrian.com and print your passenger receipt'],
    ];
    public $reSubject = [
        '#(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\d+\/\d{1,2} [A-Z]{3} \d{1,2}\/[A-Z]{3} \- [A-Z]{3}\/\d+:\d+\/Gate#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'DEPARTURE'       => 'DEPARTURE',
            'PLANNED ARRIVAL' => 'PLANNED ARRIVAL',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $html = str_ireplace(['&zwnj;', '&8203;', '&#8203;', '​', '​'], '',
            $this->http->Response['body']); // Zero-width
        $this->http->SetEmailBody($html);

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
        $html = str_ireplace(['&zwnj;', '&8203;', '&#8203;', '​', '​'], '',
            $this->http->Response['body']); // Zero-width
        $this->http->SetEmailBody($html);

        if ($this->http->XPath->query("//a[contains(@href,'.austrian.com')] | //img[contains(@src,'austrian')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
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

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"]) > 0) {
                    return true;
                }
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
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('GATE'))}]/ancestor::tr[.//img][1][not({$this->contains($this->t('DEPARTURE'))})]");

        if ($nodes->length !== 1) {
            $this->logger->debug("other format");

            return false;
        }
        $root = $nodes->item(0);

        $r = $email->add()->flight();

        $type = null;

        // General
        $r->general()
            ->confirmation($this->getField($this->t('TICKET NR.'), $type, 2, '*'))
            ->traveller($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root), true);

        // Issued
        $r->issued()
            ->ticket($this->getField($this->t('TICKET NR.'), $type, 1, '*'), false);

        // Program
        $account = $this->getField($this->t('FQTV NR.'), $type, 1, '*');
        if (!empty($account)) {
            $r->program()->account($account, false);
        }

        // Segment
        $s = $r->addSegment();

        if ($this->http->XPath->query("./descendant::img[1]/ancestor::td[1]/preceding-sibling::td[1]", $root)->length > 0) {
            $this->parseSegment_1($s, $root);
        } else {
            $this->parseSegment_2($s, $root);
        }

        // Departure
        $date = $this->getField($this->t('DEPARTURE'), $type, 2);
        $time = $this->getField($this->t('DEPARTURE'), $type, 1);
        if (!empty($date) && !empty($time)) {
            $s->departure()->date(strtotime($date . ', ' . $time));
        }

        $terminal = str_replace('-', '', $this->getField($this->t('TERMINAL'), $type, 1));
        if (!empty($terminal)) {
            $s->departure()->terminal($terminal);
        }

        // Arrival
        $date = $this->getField($this->t('PLANNED ARRIVAL'), $type, 2);
        $time = $this->getField($this->t('PLANNED ARRIVAL'), $type, 1);
        if (!empty($date) && !empty($time)) {
            $s->arrival()->date(strtotime($date . ', ' . $time));
        }


        // Extra
        $s->extra()->cabin($this->getField($this->t('TERMINAL'), $type, 2));

        return true;
    }

    private function getField($label, &$type, $column, $xpath = 'text()')
    {
        $result = null;

        if ($type == 1 || empty($type)) {
            // <tr> <td>07:20</td>          <td>06Jul21</td>    </tr>
            // <tr> <td>DEPARTURE</td>      <td>DATE</td>       </tr>
            $result = $this->http->FindSingleNode("//{$xpath}[{$this->eq($label)}]/ancestor::tr[1][not({$this->eq($label)})]/preceding-sibling::tr[1]/td[{$column}]");
            if (empty($type) && !empty($result)) {
                $type = 1;
            }
        }
        if ($type == 2 || empty($type)) {
            // <tr>
            //  <td> ... <tr><td>07:20</td></tr>        <tr><td>DEPARTURE</td></tr> ... <td>
            //  <td> ... <tr><td>06Jul21</td></tr>      <tr><td>DATE</td></tr>      ... <td>
            // </tr>
            if ($column == 1) {
                $result = $this->http->FindSingleNode("//{$xpath}[{$this->eq($label)}]/ancestor::tr[1][{$this->eq($label)}]/preceding-sibling::tr[1]");
            } else {
                $result = $this->http->FindSingleNode("//{$xpath}[{$this->eq($label)}]/ancestor::tr[1][{$this->eq($label)}]/ancestor::td[1]/following-sibling::td[1]/descendant::td[1]");
            }
            if (empty($type) && !empty($result)) {
                $type = 2;
            }
        }
        return $result;
    }

    private function parseSegment_1(FlightSegment $s, \DOMElement $root)
    {
        $s->departure()
            ->code($this->http->FindSingleNode("./descendant::img[1]/ancestor::td[1]/preceding-sibling::td[1]", $root))
            ->name($this->http->FindSingleNode("./descendant::img[1]/ancestor::tr[1]/following-sibling::tr[1]/td[1]",
                $root));

        $s->arrival()
            ->code($this->http->FindSingleNode("./descendant::img[1]/ancestor::td[1]/following-sibling::td[last()]",
                $root))
            ->name($this->http->FindSingleNode("./descendant::img[1]/ancestor::tr[1]/following-sibling::tr[1]/td[last()]",
                $root));

        // seat
        $seat = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('SEAT'))}]/ancestor::tr[1]/preceding-sibling::tr[1]/td[last()]",
            $root, false, "#^\d+[A-z]$#");

        if (!empty($seat)) {
            $s->extra()->seat($seat);
        }

        // airline - operator
        $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('SEAT'))}]/following::text()[normalize-space()!=''][1]",
            $root);

        if (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$#", $node, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
        } elseif (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)\s*\(\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)\s*\)$#",
            $node, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
            // it's codeshare, not carrier
            //    ->carrierName($m[3])
            //    ->carrierNumber($m[4]);
        }
        $operator = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Operated by'))}]", $root,
            false,
            "#{$this->opt($this->t('Operated by'))}\s*(.+)#");

        if (!empty($operator)) {
            $s->airline()->operator($operator);
        }
    }

    private function parseSegment_2(FlightSegment $s, \DOMElement $root)
    {
        $dep = $this->http->FindNodes("./descendant::img[1]/ancestor::td[count(preceding-sibling::td)=1 and count(following-sibling::td)=1][1]/preceding-sibling::td[1]/descendant::text()[normalize-space()!='']",
            $root);

        if (count($dep) == 2) {
            $s->departure()
                ->code($dep[0])
                ->name($dep[1]);
        }

        $arr = $this->http->FindNodes("./descendant::img[1]/ancestor::td[count(preceding-sibling::td)=1 and count(following-sibling::td)=1][1]/following-sibling::td[1]/descendant::text()[normalize-space()!='']",
            $root);

        if (count($arr) == 2) {
            $s->arrival()
                ->code($arr[0])
                ->name($arr[1]);
        }

        // seat
        $seat = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('SEAT'))}]/ancestor::tr[1]/preceding-sibling::tr[1]/td[last()]",
            $root, false, "#^\d+[A-z]$#");

        if (!empty($seat)) {
            $s->extra()->seat($seat);
        }

        // airline - operator
        $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('SEAT'))}]/following::text()[normalize-space()!=''][1]",
            $root);

        if (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$#", $node, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
        } elseif (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)\s*\(\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)\s*\)$#",
            $node, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
        }
        $operator = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Operated by'))}]", $root,
            false,
            "#{$this->opt($this->t('Operated by'))}\s*(.+)#");

        if (!empty($operator)) {
            $s->airline()->operator($operator);
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
        foreach (self::$dict as $lang => $words) {
            if (isset($words['DEPARTURE'], $words['PLANNED ARRIVAL'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['DEPARTURE'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['PLANNED ARRIVAL'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
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
