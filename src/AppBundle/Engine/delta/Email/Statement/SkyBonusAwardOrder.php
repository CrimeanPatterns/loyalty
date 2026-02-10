<?php

namespace AwardWallet\Engine\delta\Email\Statement;

use AwardWallet\Schema\Parser\Common\Coupon;
use AwardWallet\Schema\Parser\Email\Email;

class SkyBonusAwardOrder extends \TAccountChecker
{
    public $mailFiles = "delta/statements/it-211001572.eml, delta/statements/it-216777337.eml";

    private $detectFrom = "skybonus@delta.com";
    private $detectSubject = [
        // en
        'Delta Air Lines SkyBonus Award Order',
    ];

    public $lang;
    public static $dictionary = [
        'en' => [
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"//www.delta.com")]')->length == 0) {
            return false;
        }

        if ($this->http->XPath->query('//node()[contains(.,"with the SkyBonus flight reward(s) listed below.")]')->length > 0) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function parseEmail(Email $email)
    {
        $xpath = "//tr[*[3][normalize-space()='Certificate Number'] and *[5][normalize-space()='Certificate Must Be Redeemed By']]/following-sibling::tr[normalize-space()]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $coupon = $email->addCoupon();
            $coupon
                ->setCategory(Coupon::CAT_AIRLINE)
                ->setType(Coupon::TYPE_CERTIFICATE)
                ->setNumber($this->http->FindSingleNode("*[3]", $root))
                ->setValue($this->http->FindSingleNode("preceding-sibling::tr[last()]/preceding::text()[normalize-space()][position() < 8][contains(., 'Quantity ')]/ancestor::td[1]",
                    $root, null, "/Delta eCredit - (.+?)\s*\(/"))
                ->setExpirationDate(strtotime($this->http->FindSingleNode("*[5]", $root)))
                ->setCanExpire(true)
            ;
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }
        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) use ($text) {
                return 'contains(' . $text . ',"' . $s . '")';
            }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
                return 'normalize-space(.)="' . $s . '"';
            }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
                return 'starts-with(normalize-space(.),"' . $s . '")';
            }, $field)) . ')';
    }
}