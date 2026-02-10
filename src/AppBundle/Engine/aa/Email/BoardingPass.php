<?php

namespace AwardWallet\Engine\aa\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "aa/it-66266208.eml, aa/it-66646941.eml";
    public $subjects = [
        '/^American Airlines Boarding Pass\(es\)$/',
        '/^Your travel information$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'american_airlines_guest_travel@aa.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Do not forget you can also earn AAdvantage miles with every qualified car rental and hotel stay')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Verification Card'))}]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Boarding Time'))}]")->count() > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/american_airlines_guest_travel[@.]aa\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $xpath = "//text()[normalize-space() = 'Flight']/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $f = $email->add()->flight();
            $f->general()
                ->traveller(preg_replace("/(.+?) *\/ *(.+)/", "$2 $1",
                    $this->http->FindSingleNode("(./descendant::text()[{$this->contains($this->t('/'))}])[1]", $root)))
                ->confirmation($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Record Locator:'))}]/ancestor::span[1]/descendant::strong[1]", $root));

            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight'))}]/following::text()[normalize-space()][1]", $root, true, "/^([A-Z]{2})\s+\d{2,4}$/"))
                ->number($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight'))}]/following::text()[normalize-space()][1]", $root, true, "/^[A-Z]{2}\s+(\d{2,4})$/"));

            $timeDepart = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departing at'))}]", $root, true, "/([\d\:]+\s*A?P?M)\s*\(/");
            $timeArriv = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arriving at:'))}]/ancestor::tr[1]", $root, true, "/([\d\:]+\s*A?P?M)\s*\(/");
            $date = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t(' to '))}]/following::text()[normalize-space()][1]", $root);

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t(' to '))}]/preceding::text()[normalize-space()][2]", $root))
                ->date(strtotime($date . ', ' . $timeDepart));

            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t(' to '))}]/preceding::text()[normalize-space()][1]", $root))
                ->date(strtotime($date . ', ' . $timeArriv));

            $seat = ($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Seat'))}]/ancestor::td[1]/descendant::strong[1]", $root));

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }
        }
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
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
}
