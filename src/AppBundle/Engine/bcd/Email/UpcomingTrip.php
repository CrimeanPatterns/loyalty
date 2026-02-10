<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class UpcomingTrip extends \TAccountChecker
{
    public $mailFiles = "bcd/it-557724024.eml, bcd/it-557724271.eml";
    public $subjects = [
        "You're going to need this for your upcoming",
    ];

    public $lang = 'en';
    public $airlineArray = [];

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@bcdtravel.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'BCD Travel')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Take this barcode with you'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('This barcode is not a boarding pass'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]bcdtravel\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->Flight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function Flight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts('Hi,')}]", null, true, "/{$this->opt($this->t('Hi,'))}\s+(\D+)\./"))
            ->noConfirmation();

        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Airline Record Locator:')]");

        foreach ($nodes as $root) {
            $airlineInfo = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);

            if (preg_match("/.+\s+(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})$/", $airlineInfo, $m)) {
                if (!in_array($m['aName'] . $m['fNumber'], $this->airlineArray) || count($this->airlineArray) === 0) {
                    $s = $f->addSegment();

                    $s->airline()
                        ->name($m['aName'])
                        ->number($m['fNumber']);

                    $conf = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root);

                    if (!empty($conf) && trim($conf) !== 'PASSIVE') {
                        $s->setConfirmation($conf);
                    }

                    $depDate = $this->http->FindSingleNode("./following::text()[normalize-space()='Departure'][1]/ancestor::table[1]", $root, null, "/{$this->opt($this->t('Departure'))}\s*(.+)/s");
                    $depTime = $this->http->FindSingleNode("./following::img[contains(@src, 'right-small-icon')][1]/preceding::text()[normalize-space()][1]", $root, true, "/^([\d\:]+)\s*A?P?M?$/");

                    $s->departure()
                        ->code($this->http->FindSingleNode("./following::img[contains(@src, 'right-small-icon')][1]/preceding::text()[normalize-space()][2]", $root, true, "/^([A-Z]{3})$/"))
                        ->date(strtotime($depDate . ', ' . $depTime));

                    $arrDate = $this->http->FindSingleNode("./following::text()[normalize-space()='Arrival'][1]/ancestor::table[1]", $root, true, "/{$this->opt($this->t('Arrival'))}\s*(.+)/s");
                    $arrTime = $this->http->FindSingleNode("./following::img[contains(@src, 'right-small-icon')][1]/following::text()[normalize-space()][4]", $root, true, "/^([\d\:]+)\s*A?P?M?$/");
                    $s->arrival()
                        ->code($this->http->FindSingleNode("./following::img[contains(@src, 'right-small-icon')][1]/following::text()[normalize-space()][3]", $root, true, "/^([A-Z]{3})$/"))
                        ->date(strtotime($arrDate . ', ' . $arrTime));

                    $duration = $this->http->FindSingleNode("./following::img[contains(@src, 'right-small-icon')][1]/following::text()[normalize-space()][1]", $root, true, "/^(\d+(?:h|m).+)/");

                    if (!empty($duration)) {
                        $s->setDuration($duration);
                    }

                    $seat = $this->http->FindSingleNode(".//following::text()[normalize-space()='Departure'][1]/ancestor::table[1]/following::table[1][contains(normalize-space(), 'Seat')]", $root, true, "/{$this->opt($this->t('Seat'))}\s*(\d+[A-Z])$/");

                    if (!empty($seat)) {
                        $s->extra()
                            ->seat($seat);
                    }

                    $this->airlineArray[] = $m['aName'] . $m['fNumber'];
                } else {
                    continue;
                }
            }
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
