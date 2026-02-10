<?php

namespace AwardWallet\Engine\fastpark\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Welcome extends \TAccountChecker
{
    public $mailFiles = "fastpark/statements/it-84731358.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@thefastpark.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Welcome to FastPark') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".thefastpark.com/") or contains(@href,"www.thefastpark.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"@thefastpark.com")]')->length === 0
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

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $number = $memberSince = null;

        $rootHtml = $this->http->FindHTMLByXpath('.', null, $root);
        $rootText = $this->htmlToText($rootHtml);

        /*
            John Black
            Card Number 899 19123
            Member Since 2021
        */

        if (preg_match("/^\s*(?<name>{$patterns['travellerName']})[ ]*\n+[ ]*Card Number[ ]+(?<number>\d[\d ]{4,}?)[ ]*\n+[ ]*Member Since[ ]+(?<since>.{4,}?)\s*$/iu", $rootText, $m)) {
            $name = $m['name'];
            $number = str_replace(' ', '', $m['number']);
            $memberSince = $m['since'];
        }

        if ($name) {
            $st->addProperty('UserName', $name);
        }

        if ($number) {
            $st->setNumber($number);
        }

        if ($memberSince) {
            $st->addProperty('MemberSince', $memberSince);
        }

        if ($name || $number || $memberSince) {
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
        return $this->http->XPath->query("//td[ not(.//td) and descendant::text()[starts-with(normalize-space(),'Card Number')] and descendant::text()[starts-with(normalize-space(),'Member Since')] ]");
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
