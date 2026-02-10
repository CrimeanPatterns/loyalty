<?php

namespace AwardWallet\Engine\aa\Email;

use AwardWallet\Schema\Parser\Email\Email;

// parsers with similar formats: aa/API

class YourFlightToChangeOnTime extends \TAccountCheckerAa
{
    public $mailFiles = "aa/it-420282840.eml, aa/it-44039865.eml, aa/it-44041045.eml, aa/it-657565572.eml, aa/it-67571722.eml";

    public $reFrom = ['no-reply@notify.email.aa.com', 'no-reply@info.email.aa.com'];
    public $reBody = [
        'en' => ['ON TIME', 'UPDATED', 'DELAYED', 'CANCELED', 'CANCELLED', 'Your connecting flight', 'is arriving soon', 'Great news! Your upgrade is confirmed'],
    ];
    public $reSubject = [
        '/Your flight to .+ - (?i)(?:On Time|Updated|Delayed|Canceled|Cancelled|Departure Gate Change)$/',
        '/Your connecting flight to .+/',
        '/Your trip confirmation-/',
    ];
    public $lang = '';
    public $travellers = [];
    public static $dict = [
        'en' => [
            'header'              => ['Your connecting flight', 'Reminder: Flight'],
            'Check flight status' => ['Check flight status', 'View your trip'],
            'confNo'              => ['Record locator', 'Record Locator', 'RECORD LOCATOR', 'record locator'],
            'Flight'              => ['Flight', 'flight'],
            'status'              => ['ON TIME', 'UPDATED', 'DELAYED', 'CANCELED', 'CANCELLED'],
            'to'                  => ['to', 'To', 'TO'],
        ],
    ];
    private $keywordProv = 'American Airlines';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.aa.com')] | //a[contains(@href,'.aa.com')]")->length > 0
            && $this->http->XPath->query("//*[{$this->contains($this->keywordProv)}]")->length > 0
        ) {
            foreach ($this->reBody as $reBody) {
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
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && !preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers['subject'])
        ) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers['subject']) > 0) {
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

    private function parseEmail(Email $email): void
    {
        if ($this->http->XPath->query("//tr[not(.//tr)][ancestor-or-self::*[{$this->eq(['#0077d1', '#0077D1'], '@bgcolor')} and {$this->contains(['Flight', 'flight'])}] or {$this->contains($this->t('header'))}]/following::tr[not(.//tr) and {$this->eq($this->t('Check flight status'))}]")->length !== 1
            && $this->http->XPath->query("//node()[{$this->starts('© American Airlines, Inc. All Rights Reserved')}]/preceding::tr[not(.//tr) and {$this->eq($this->t('Check flight status'))}]")->length !== 1
        ) {
            $this->logger->debug('other format');

            return;
        }

        $xpathAirportCode = 'translate(normalize-space(),"ABCDEFGHIJKLMNOPQRSTUVWXYZ","∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆∆")="∆∆∆"';

        $r = $email->add()->flight();

        $confirmationNumbers = array_filter($this->http->FindNodes("//tr[not(.//tr) and {$this->starts($this->t('confNo'))}]", null, "/^{$this->opt($this->t('confNo'))}[:\s]+[A-Z\d]{5,}$/i"));

        if (count($confirmationNumbers) === 1) {
            $confirmationNumber = array_shift($confirmationNumbers);

            if (preg_match("/^({$this->opt($this->t('confNo'))})[:\s]+([A-Z\d]{5,})$/i", $confirmationNumber, $m)) {
                $r->general()->confirmation($m[2], $m[1]);
            }
        } elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('confNo'))}]")->length === 0) {
            $r->general()->noConfirmation();
        }

        $status = $this->http->FindSingleNode("//tr[{$this->eq($this->t('status'))} and following::tr[normalize-space()][1][{$this->contains($this->t('to'))}] ]");

        if ($status) {
            $r->general()->status($status);
        }

        $nodes = $this->http->XPath->query($xpath = "//img[{$this->contains('Icon-Arrow.png', '@src')}]/ancestor::tr[2]");

        if ($nodes->length === 0) {
            // it-420282840.eml
            $nodes = $this->http->XPath->query($xpath = "//tr[ descendant::text()[normalize-space()][1][{$xpathAirportCode}] and descendant::text()[normalize-space()][position()>1 and last()][{$xpathAirportCode}] ]/following-sibling::tr[normalize-space()][1]/following-sibling::tr[1]");
        }

        $this->logger->debug($xpath);

        if ($nodes->length !== 1) {
            $this->logger->debug('check format');

            //return;
        }

        foreach ($nodes as $root) {
            $s = $r->addSegment();

            $node = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][2]", $root);

            if (empty($node)) {
                $node = $this->http->FindSingleNode("preceding::tr[2][contains(normalize-space(), 'to')]", $root);
            }

            if (preg_match("#^([A-Z]{3})\s*{$this->opt($this->t('to'))}\s*([A-Z]{3})$#", $node, $m)) {
                $s->departure()->code($m[1]);
                $s->arrival()->code($m[2]);
            }

            $date = strtotime($this->http->FindSingleNode("preceding::tr[normalize-space()][1]", $root));

            $node = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]", $root);

            if (empty($node)) {
                $node = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(), 'Operated by')]/preceding::tr[normalize-space()][1]");
            }

            if (empty($node)) {
                $node = $this->http->FindSingleNode("descendant::tr[normalize-space()][last()]", $root, true, "/^([A-Z\d][A-Z]|[A-Z][A-Z\d]\s*\d+)$/");
            }

            if (empty($node)) {
                $node = $this->http->FindSingleNode("descendant::tr[normalize-space()][3]", $root, true, "/^([A-Z\d][A-Z]|[A-Z][A-Z\d]\s*\d+)$/");
            }

            if (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $operator = $this->http->FindSingleNode("//text()[{$this->eq($this->t('status'))}]/following::text()[normalize-space()][1]/ancestor::tr[following-sibling::tr][1]/following-sibling::tr[3]/following::text()[normalize-space()][1][{$this->starts($this->t('Operated by'))}]",
                null, false, "#{$this->opt($this->t('Operated by'))}\s*(.+?)\s*(?:\b{$this->t('dba')}\b|$)#i");

            if (empty($operator)) {
                $operator = $this->http->FindSingleNode("/descendant::text()[{$this->starts($this->t('Operated by'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Operated by'))}\s*(.+)/");
            }

            if (!empty($operator)) {
                $s->airline()->operator($operator);
            }

            if (preg_match("/^CANCELL?ED$/i", $status)) {
                // it-420282840.eml
                $r->general()->cancelled();

                if (str_replace(' ', '', $root->nodeValue) === '') {
                    $s->departure()->day($date)->noDate();
                    $s->arrival()->noDate();

                    return;
                }
            }

            $s->departure()
                ->date(strtotime($this->http->FindSingleNode("descendant::tr[normalize-space()][1]/*[normalize-space()][1]", $root), $date))
                ->name($this->http->FindSingleNode("descendant::tr[normalize-space()][2]/*[normalize-space()][1]", $root))
                ->terminal($this->http->FindSingleNode("descendant::tr[normalize-space()][3]/*[normalize-space()][1][{$this->starts($this->t('Terminal'))}]", $root, false, "#{$this->opt($this->t('Terminal'))}\s*(\w+)$#"), false, true)
            ;

            $s->arrival()
                ->date(strtotime($this->http->FindSingleNode("descendant::tr[normalize-space()][1]/*[normalize-space()][last()]", $root), $date))
                ->name($this->http->FindSingleNode("descendant::tr[normalize-space()][2]/*[normalize-space()][last()]", $root))
                ->terminal($this->http->FindSingleNode("descendant::tr[normalize-space()][3]/*[normalize-space()][last()][{$this->starts($this->t('Terminal'))}]", $root, false, "#{$this->opt($this->t('Terminal'))}\s*(\w+)$#"), false, true)
            ;

            $seatsInfoArray = $this->http->FindNodes("following::tr[1]/ancestor::table[1]/descendant::tr", $root);

            foreach ($seatsInfoArray as $seatInfo) {
                if (preg_match("/^(?<seat>\d+[A-Z])\s*\((?<cabin>\D+)\)\s*\-\s*(?<traveller>[\.[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/", $seatInfo, $m)) {
                    $this->travellers[] = $m['traveller'];

                    $s->extra()
                        ->seat($m['seat'])
                        ->cabin($m['cabin']);
                }
            }
        }

        if (count($this->travellers) > 0) {
            $r->general()
                ->travellers(array_unique($this->travellers));
        }

        //$root = $root->item(0);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Check flight status'], $words['Flight'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Check flight status'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Flight'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
