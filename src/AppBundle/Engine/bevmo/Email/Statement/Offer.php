<?php

namespace AwardWallet\Engine\bevmo\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Offer extends \TAccountChecker
{
    public $mailFiles = "bevmo/statements/it-80291628.eml";

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]bevmo\.com/i', $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".bevmo.com/") or contains(@href,"www.bevmo.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"BevMo.com. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $patterns['travellerName'] = '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $number = $login = null;

        $nameHtml = $this->http->FindHTMLByXpath("tr[starts-with(normalize-space(),'CLUBBEV! CUSTOMER')]", null, $root);
        $nameText = $this->htmlToText($nameHtml);

        if (preg_match("/CLUBBEV! CUSTOMER.*\n+[ ]*({$patterns['travellerName']})\s*$/u", $nameText, $m)) {
            $name = $m[1];
        }
        $st->addProperty('Name', $name);

        $numberHtml = $this->http->FindHTMLByXpath("tr[starts-with(normalize-space(),'ACCOUNT#')]", null, $root);
        $numberText = $this->htmlToText($numberHtml);

        if (preg_match("/ACCOUNT#.*\n+[ ]*([-A-Z\d]{5,})\s*$/", $numberText, $m)) {
            $number = $m[1];
        }
        $st->setNumber($number);

        $login = $this->http->FindSingleNode("//text()[contains(normalize-space(),'This email was sent to BevMo! subscriber')]", null, true, "/This email was sent to BevMo! subscriber[:\s]+(\S+@\S+\.\w+\b)/");
        $st->setLogin($login);

        if ($name || $number || $login) {
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
        return $this->http->XPath->query("//*[ tr[starts-with(normalize-space(),'CLUBBEV! CUSTOMER')]/following-sibling::tr[starts-with(normalize-space(),'ACCOUNT#')] ]");
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
