<?php

namespace AwardWallet\Engine\alitalia\Email;

use AwardWallet\Schema\Parser\Email\Email;

class UpgradeRequest extends \TAccountChecker
{
    public $mailFiles = "alitalia/it-33796752.eml, alitalia/it-33933009.eml";

    public $reFrom = ["@alitalia."];
    public $reBody = [
        'en' => ['Summary of your offer', 'Destination'],
    ];
    public $reSubject = [
        '',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];
    private $keywordProv = 'Alitalia';

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
        if ($this->http->XPath->query("//a[contains(@href,'.alitalia.com')]")->length > 0) {
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
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
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
        $r = $email->add()->flight();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Code'))}]", null,
                false, "#{$this->opt($this->t('Booking Code'))}[\s:]+([A-Z\d]{5,})#"));

        $xpath = "//text()[{$this->eq($this->t('Date'))}]/ancestor::tr[1][{$this->contains($this->t('Destination'))}]/ancestor::table[1]/descendant::tr[position()>1][count(./td)>5]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH]: " . $xpath);

        foreach ($nodes as $root) {
            if ($this->http->XPath->query("./preceding-sibling::tr[1][count(./td)<5]", $root)->length > 0) {
                continue;
            }

            $s = $r->addSegment();

            $s->departure()
                ->noDate()
                ->day(strtotime($this->http->FindSingleNode("./td[1]", $root)));
            $node = $this->http->FindSingleNode("./td[3]", $root);

            if (preg_match("#(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
            }
            $node = $this->http->FindSingleNode("./td[4]", $root);

            if (preg_match("#(.+?)\s*\(([A-Z]{3})\)#", $node, $m)) {
                $s->arrival()
                    ->noDate()
                    ->name($m[1])
                    ->code($m[2]);
            }
            $node = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if ($this->http->XPath->query("//text()[{$this->eq($this->t('Date'))}]/ancestor::tr[1][{$this->contains($this->t('Destination'))}]/td[6][{$this->contains($this->t('Upgrade To'))}]")->length > 0) {
                $s->extra()
                    ->cabin($this->http->FindSingleNode("./td[6]", $root));
            }
        }

        return true;
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
