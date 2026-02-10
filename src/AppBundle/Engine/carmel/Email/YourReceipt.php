<?php

namespace AwardWallet\Engine\carmel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourReceipt extends \TAccountChecker
{
    public $mailFiles = "carmel/it-35242846.eml, carmel/it-35243359.eml, carmel/it-35243400.eml, carmel/it-35336464.eml";

    public $reFrom = ["reservations@carmellimo.com"];
    public $reBody = [
        'en' => ['Trip Receipt', 'Thank you for choosing Carmel'],
    ];
    public $reSubject = [
        '#Your Receipt For Carmel Reservation \#\d+#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'From'                 => 'From',
            'Date'                 => 'Date',
            'regSubjectWithConfNo' => '#Your Receipt For Carmel Reservation \#\s*(\d+)#',
        ],
    ];
    private $keywordProv = 'Carmel';
    private $subject;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (empty($this->http->Response['body'])) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }
        $this->subject = $parser->getSubject();

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($this->http->Response['body'])) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        if ($this->detectBody()) {
            return $this->assignLang();
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
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && preg_match($reSubject, $headers["subject"]) > 0)
                    || strpos($headers["subject"], $this->keywordProv) !== false
                ) {
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
        $r = $email->add()->transfer();

        if (preg_match($this->t('regSubjectWithConfNo'), $this->subject, $m)) {
            $r->general()
                ->confirmation($m[1]);
        } else {
            $r->general()->noConfirmation();
        }
        $s = $r->addSegment();
        $from = $this->nextTd($this->t('From:'));
        // LAS:AA 2341:West Palm Beach, FL (PBI) (OUT)
        if (preg_match("#^([A-Z]{3})\s*:\s*[A-Z\d]{2}\s*\d+:.+?\(OUT\)#", $from, $m)) {
            $s->departure()
                ->code($m[1]);
        } else {
            $s->departure()
                ->name($from);
        }
        $date = strtotime($this->nextTd($this->t('Date:')));
        $s->departure()
            ->date(strtotime($this->nextTd($this->t('Time:')), $date));

        $s->arrival()
            ->name($this->nextTd($this->t('To:')))
            ->noDate();

        if (!empty($total = $this->nextTd('Total:'))) {
            $cnt1 = $this->http->XPath->query("//text()[{$this->eq($this->t('Fare:'))}]/ancestor::tr[1]/preceding-sibling::tr")->length;
            $cnt2 = $this->http->XPath->query("//text()[normalize-space()='Total:']/ancestor::tr[1]/preceding-sibling::tr")->length;
            $nums = $cnt2 - $cnt1;

            if ($nums > 1) {
                $roots = $this->http->XPath->query("//text()[normalize-space()='Total:']/ancestor::tr[1]/preceding-sibling::tr[position()<{$nums}]");

                foreach ($roots as $root) {
                    $fee = trim($this->http->FindSingleNode("./td[1]", $root), ":");
                    $r->price()
                        ->fee($fee,
                            PriceHelper::cost($this->http->FindSingleNode("./td[2]", $root, false, "#([\d\.\,]+)#")));
                }
            }

            if ($cnt1 > 0) {
                $r->price()
                    ->cost(PriceHelper::cost($this->http->FindSingleNode("//text()[{$this->eq($this->t('Fare:'))}]/ancestor::tr[1]/td[2]",
                        null, false, "#([\d\.\,]+)#")));
            }
        } else {
            $total = $this->nextTd('Fare:');
        }
        $total = $this->getTotalCurrency($total);
        $r->price()
            ->total($total['Total'])
            ->currency($total['Currency']);

        return true;
    }

    private function nextTd($field, $root = null, $starts = false)
    {
        if ($starts) {
            return $this->http->FindSingleNode("//text()[{$this->starts($field)}]/ancestor::td[1]/following-sibling::td[1]",
                $root);
        } else {
            return $this->http->FindSingleNode("//text()[{$this->eq($field)}]/ancestor::td[1]/following-sibling::td[1]",
                $root);
        }
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

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["From"], $words["Date"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['From'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Date'])}]")->length > 0
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
