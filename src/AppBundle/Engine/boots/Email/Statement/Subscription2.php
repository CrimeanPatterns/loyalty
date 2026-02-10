<?php

namespace AwardWallet\Engine\boots\Email\Statement;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Subscription2 extends \TAccountChecker
{
    public $mailFiles = "boots/statements/it-494496122.eml";

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]boots\.com$/i', $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".boots.com/") or contains(@href,"mail.boots.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"This email was sent by Boots")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]{0,25}[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $st = $email->add()->statement();

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $td1Text = $this->htmlToText($this->http->FindHTMLByXpath('*[normalize-space()][1]', null, $root));

        $cardNumber = preg_match("/account[:\s]+([-A-Z\d]{4,})$/im", $td1Text, $m) ? $m[1] : null;

        if (preg_match("/^[Xx]{4,}([-A-Z\d]+)$/", $cardNumber, $m)) {
            // XXXX1394
            $st->setNumber($m[1])->masked();
        } elseif (preg_match("/^([-A-Z\d]+?)[Xx]{4,}$/", $cardNumber, $m)) {
            // 1394XXXX
            $st->setNumber($m[1])->masked('right');
        } else {
            // 1394
            $st->setNumber($cardNumber);
        }

        $balanceDate = preg_match("/\sAS OF\s+(\S.{3,16}\b\d{2,4}|\d{8})\s+HAS\b/i", $td1Text, $m) ? strtotime($this->normalizeDate($m[1])) : null;
        $st->setBalanceDate($balanceDate);

        $td2Text = $this->htmlToText($this->http->FindHTMLByXpath('*[normalize-space()][2]', null, $root));

        $pointsVal = preg_match("/^(.*?)\s*worth of points(?: to spend)?$/i", $td2Text, $m) ? $m[1] : '';

        if (preg_match("/^(?:[^\-\d)(]+)?[ ]*(\d[,.‘\'\d ]*)$/u", $pointsVal, $matches)) {
            // £5.31    |    €5.31
            $st->setBalance(PriceHelper::parse($matches[1]));
        }

        $travellerNames = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(),'Hi')]", null, "/^Hi[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
            $st->addProperty('Name', $traveller);
        }

        $youEmail = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'You are subscribed as')]", null, true, "/^You are subscribed as\s+(\S+@\S+)$/i");

        if ($youEmail) {
            $st->setLogin($youEmail);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][starts-with(normalize-space(),'Boots Advantage Card')] and *[normalize-space()][2][contains(normalize-space(),'worth of points to spend')] ]");
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

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 22/08/2022
            '/^(\d{1,2})\s*\/\s*(\d{1,2})\s*\/\s*(\d{2,4})$/u',
            // 22082022
            '/^(\d{2})(\d{2})(\d{4})$/u',
        ];
        $out = [
            '$2/$1/$3',
            '$2/$1/$3',
        ];

        return preg_replace($in, $out, $text);
    }
}
