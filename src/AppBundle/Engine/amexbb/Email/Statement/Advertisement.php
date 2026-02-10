<?php

namespace AwardWallet\Engine\amexbb\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Advertisement extends \TAccountChecker
{
    public $mailFiles = "amexbb/statements/it-111424066.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@email.bluebird.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'americanexpress@email.bluebird.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".bluebird.com/") or contains(@href,"email.bluebird.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you, The Bluebird Team")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = null;

        $headerText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[starts-with(normalize-space(),'Account ending in')]/ancestor::*[self::td or self::th][1]"));

        if (preg_match("/^\s*Hello[,:! ]*\n+[ ]*(?<name>{$patterns['travellerName']})[ ]*\n+[ ]*Account ending in/iu", $headerText, $m)) {
            /*
                Hello,
                Gitanjali Appadu
                Account ending in - 7093
            */
            $name = $m['name'];
        }

        if ($name) {
            $st->addProperty('Name', $name);
        }

        if ($name) {
            $st->setNoBalance(true);
        } elseif (stripos($parser->getCleanFrom(), 'americanexpress@email.bluebird.com') !== false
            || $this->isMembership() === true
        ) {
            $st->setMembership(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(): bool
    {
        return $this->http->XPath->query('//*[contains(normalize-space(),"We noticed that you have not activated your Bluebird Card.")]')->length > 0;
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
