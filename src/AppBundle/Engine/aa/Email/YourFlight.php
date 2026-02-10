<?php

namespace AwardWallet\Engine\aa\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourFlight extends \TAccountChecker
{
    public $mailFiles = "aa/it-65289866.eml";
    public $subjects = [
        '/Your flight from [A-Z]{3}$/',
        '/Confirm upcoming trip on American Airlines/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || stripos($headers['from'], '@notify.email.aa.com') === false
            && stripos($headers['from'], '@info.email.aa.com') === false
        ) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (preg_match($subject, $headers['subject'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'American Airlines, Inc')]")->length === 0
            || $this->http->XPath->query("//a[contains(@href, '.aa.com') or contains(@href, '//aa.com')]")->length < 3
        ) {
            return false;
        }

        if ( $this->http->XPath->query("//text()[{$this->contains('CURRENT FLIGHT')}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains(['We\'re expecting a busy flight from', 'You can move flights at no charge.', 'We\'re touching base to see if you still plan to travel on'])}]")->count() > 0) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:notify|info)\.email\.aa\.com$/', $from) > 0;
    }

    public function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Record locator:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z]{6})$/"),
                trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('Record locator:'))}]"), ':'));

        $xpath = "//text()[{$this->eq($this->t('CURRENT FLIGHT'))}]/following::text()[normalize-space()][1]/ancestor::table[1]";
        $this->logger->debug($xpath);
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $depDate = $this->http->FindSingleNode("./descendant::tr[normalize-space()][3]", $root);
            $depTime = $this->http->FindSingleNode("./descendant::tr/descendant::img/ancestor::tr[1]/td[1]", $root);
            $arrTime = $this->http->FindSingleNode("./descendant::tr/descendant::img/ancestor::tr[1]/td[3]", $root);

            $s->departure()
                ->date($this->normalizeDate($depDate . ', ' . $depTime))
                ->code($this->http->FindSingleNode("./descendant::tr/descendant::img/ancestor::tr[1]/preceding::tr[1]/descendant::td[string-length()>=3][1]", $root));

            $s->arrival()
                ->date($this->normalizeDate($depDate . ', ' . $arrTime))
                ->code($this->http->FindSingleNode("./descendant::tr/descendant::img/ancestor::tr[1]/preceding::tr[1]/descendant::td[string-length()>=3][2]", $root));

            $s->airline()
                ->noNumber()
                ->name('AA');
        }

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);

        return $email;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\,\s+(\w+)\s+(\d+)\,\s+(\d{4})\,\s+([\d\:]+\s+A?P?M)$#", //Friday, September 4, 2020, 8:41 AM
        ];
        $out = [
            "$2 $1 $3, $4",
        ];

        return strtotime(preg_replace($in, $out, $str));
    }
}
