<?php

namespace AwardWallet\Engine\contourair\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "contourair/it-680251704.eml";
    public $subjects = [
        'Check-in',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@contourairlines.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Contour Airlines')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your boarding pass for'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('can be downloaded by clicking on the passenger(s) name below'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]contourairlines\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseBoardingPass($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseBoardingPass(Email $email)
    {
        $bFlightText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your boarding pass for')]");

        if (preg_match("/on\s*(?<dateTime>[\d\/]+\s*\d+\:\d+\s*A?P?M?)\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})\s+(?<depName>.+)\s+to\s+(?<arrName>.+)\s+can be downloaded/", $bFlightText, $m)) {
            $dateTime = strtotime(str_replace('/', '.', $m['dateTime']));

            $f = $email->add()->flight();

            $f->general()
                ->traveller($this->http->FindSingleNode("//a"))
                ->noConfirmation();

            $s = $f->addSegment();

            $s->airline()
                ->name($m['aName'])
                ->number($m['fNumber']);

            $s->departure()
                ->noCode()
                ->name($m['depName'])
                ->date($dateTime);

            $s->arrival()
                ->noCode()
                ->noDate()
                ->name($m['arrName']);

            $b = $email->add()->bpass();

            $b->setDepDate($dateTime);
            $b->setFlightNumber($m['aName'] . $m['fNumber']);
            $b->setTraveller($this->http->FindSingleNode("//a"));
            $b->setUrl($this->http->FindSingleNode("//a/@href"));
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }
}
