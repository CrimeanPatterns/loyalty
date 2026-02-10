<?php

namespace AwardWallet\Engine\carey\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Reminder extends \TAccountChecker
{
    public $mailFiles = "carey/it-42471226.eml";

    public $reFrom = ["@carey.com", "@careyconnect.com"];
    public $reBody = [
        'en' => ['This is a reminder of the upcoming Carey Service for'],
    ];
    public $reSubject = [
        'Carey Service Reminder for',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Date of Service' => 'Date of Service',
            'Vehicle Type'    => 'Vehicle Type',
        ],
    ];
    private $keywordProv = 'Carey';

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
        if ($this->http->XPath->query("//img[@alt='Carey' or contains(@src,'.careyconnect.com')] | //a[contains(@href,'.careyconnect.com')]")->length > 0) {
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
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || stripos($headers["subject"], $this->keywordProv) !== false)
                    && stripos($headers["subject"], $reSubject) !== false
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
        $xpath = "//text()[{$this->eq($this->t('Date of Service'))}]/ancestor::tr[{$this->contains($this->t('Vehicle Type'))}][1]/following-sibling::tr";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $r = $email->add()->transfer();

            $r->general()
                ->confirmation($this->http->FindSingleNode("./td[2]", $root))
                ->traveller($this->http->FindSingleNode("./td[1]", $root));

            $s = $r->addSegment();

            $s->departure()
                ->name($this->http->FindSingleNode("./td[6]", $root))
                ->date(strtotime($this->http->FindSingleNode("./td[5]", $root),
                    strtotime($this->http->FindSingleNode("./td[3]", $root))));

            $s->arrival()
                ->noDate()
                ->name($this->http->FindSingleNode("./td[7]", $root));

            $s->extra()->type($this->http->FindSingleNode("./td[4]", $root));
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
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Vehicle Type'], $words['Date of Service'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Vehicle Type'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Date of Service'])}]")->length > 0
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
}
