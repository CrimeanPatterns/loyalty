<?php

namespace AwardWallet\Engine\chickfil\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Verify extends \TAccountChecker
{
    public $mailFiles = "chickfil/statements/it-113781650.eml";

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Chick-fil-A One') !== false
            || stripos($from, '@chick-fil-a.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers['subject'], 'New login detected on your Chick-fil-A One account') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".chick-fil-a.com/") or contains(@href,"my.chick-fil-a.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"@email.chick-fil-a.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length === 1) {
            $root = $roots->item(0);
            $verificationCode = $this->http->FindSingleNode("self::*/@href", $root);
            $verificationCode = str_replace(['%2F', '%3F', '%3D', '%26', '%2523'], ['/', '?', '=', '&', '#'], $verificationCode);

            $otc = $email->add()->oneTimeCode();
            $otc->setCodeAttr("/https?:\/\/manage\.my\.chick-fil-a\.com\/device\/verification\?deviceDetails=[-.A-z\d]+(?:&|$)/i", 3000);
            $otc->setCode($verificationCode);
        }

        $login = $this->http->FindSingleNode("//text()[contains(normalize-space(),\"We’ve noticed a new device logged in to your Chick‑fil‑A One® account with\")]/following::*[normalize-space()][1]", null, true, "/^\S+@\S+$/i");

        if ($login) {
            $st = $email->add()->statement();
            $st->setLogin($login);
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//a[normalize-space()='Verify device' and contains(@href,'my.chick-fil-a.com')]");
    }
}
