<?php

namespace AwardWallet\Engine\aa\Email;

use AwardWallet\Schema\Parser\Email\Email;

class FlightChanged extends \TAccountCheckerAa
{
    public $mailFiles = "aa/it-58293722.eml, aa/it-70624663.eml";

    public $traveller;

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'                => ['Record locator'],
            'Depart'                    => ['Depart'],
            'statusVariants'            => ['delayed', 'update'],
            'Departure Gate Update for' => ['Departure Gate Update for', 'Flight Delay impacting'],
        ],
    ];

    private $subjects = [
        'en' => ['Flight Delay impacting', 'Departure Gate Update for'],
    ];

    private $detectors = [
        'en' => ['Your flight is delayed', 'Gate update'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@notify.email.aa.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".aa.com/") or contains(@href,"www.aa.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"American Airlines, Inc. All Rights Reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->traveller = $this->re("/{$this->opt($this->t('Departure Gate Update for'))}\s(\D+)\-?\s*{$this->opt($this->t('Record Locator:'))}/iu", $parser->getSubject());

        $this->parseFlight($email);
        $email->setType('FlightChanged' . ucfirst($this->lang));

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

    private function parseFlight(Email $email): void
    {
        $patterns = [
            'time' => '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?', // 8:24 PM
        ];

        $f = $email->add()->flight();

        if (!empty($this->traveller)) {
            $f->general()
                ->traveller(trim($this->traveller, '-'), true);
        }

        $status = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Your flight is'))}]", null, true, "/{$this->opt($this->t('Your flight is'))}\s+({$this->opt($this->t('statusVariants'))})[,.;!?\s]*$/");

        if (empty($status)) {
            $status = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Gate'))}]", null, true, "/{$this->opt($this->t('Gate'))}\s+({$this->opt($this->t('statusVariants'))})[,.;!?\s]*$/");
        }
        $f->general()->status($status);

        $xpathRecordLocator = "//text()[{$this->starts($this->t('confNumber'))}]/ancestor::table[1]/following-sibling::table[normalize-space()][1]/descendant::tr[count(*[normalize-space()])=2][1]";

        $confirmation = $this->http->FindSingleNode($xpathRecordLocator . '/*[normalize-space()][1]', null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:]*$/');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $airline = $this->http->FindSingleNode($xpathRecordLocator . '/*[normalize-space()][2]');

        $segments = $this->http->XPath->query("//tr[ *[2][{$this->eq($this->t('Flight'))}] and *[6][{$this->eq($this->t('Terminal'))}] ]/following-sibling::tr[count(*[normalize-space()])>1]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = 0;
            $airportDep = $airportArr = null;

            $segHeaderRows = $this->http->FindNodes("ancestor::table[1]/preceding-sibling::table[normalize-space()][1]/descendant::*[count(tr[normalize-space()])=2]/tr[normalize-space()]", $root);

            if (count($segHeaderRows) === 2) {
                if (preg_match("/^(.{3,})[ ]+{$this->opt($this->t('to'))}[ ]+(.{3,})$/", $segHeaderRows[0], $m)) {
                    // Washington Reagan to Atlanta
                    $airportDep = $m[1];
                    $airportArr = $m[2];
                }
                $date = strtotime($segHeaderRows[1]);
            }

            $flight = $this->http->FindSingleNode('*[2]', $root, true, '/^\d+$/');
            $s->airline()
                ->name($airline)
                ->number($flight);

            $timeDep = $this->http->FindSingleNode('*[3]', $root, true, "/^{$patterns['time']}$/");
            $timeArr = $this->http->FindSingleNode('*[4]', $root, true, "/^{$patterns['time']}$/");

            $terminal = $this->http->FindSingleNode('*[6]', $root, true, '/^[-A-z\d\s]+$/');
            $s->departure()
                ->name($airportDep)
                ->noCode()
                ->date(strtotime($timeDep, $date))
                ->terminal($terminal);

            $s->arrival()
                ->name($airportArr)
                ->date(strtotime($timeArr, $date))
                ->noCode();
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['Depart'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Depart'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
