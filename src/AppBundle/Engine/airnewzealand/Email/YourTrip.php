<?php

namespace AwardWallet\Engine\airnewzealand\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourTrip extends \TAccountChecker
{
    public $mailFiles = "airnewzealand/it-85092677.eml";
    public $subjects = [
        '/Information to plan your trip to \D+\s*ref\s*[A-Z\d]+/',
    ];

    public $subject;

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@digitalcomms.airnz.co.nz') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Air New Zealand')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your flight'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Operated by'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]digitalcomms.airnz.co.nz$/', $from) > 0;
    }

    public function ParserFlight(Email $email)
    {
        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking reference')]/following::text()[normalize-space()][1]"), 'Booking reference')
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Kia ora')]", null, true, "/{$this->opt($this->t('Kia ora'))}\s*([\D+]+)\,/"));

        if (!empty($status)) {
            $f->general()
                ->status($status);
        }

        $xpath = "//text()[normalize-space()='Seats']/ancestor::table[normalize-space()][2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            if (preg_match("/all booked to fly\s*([A-Z]{2})\s*(\d+)\s*on/", $this->subject, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            } elseif (preg_match("/^([A-Z]{2})\s*(\d+)$/", $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Operated by')]/preceding::img[1]/following::span[1]", $root), $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $arrTime = $this->http->FindSingleNode("./descendant::table/descendant::text()[normalize-space()='Arrives']/following::text()[normalize-space()][1]", $root);
            $arrDate = $this->http->FindSingleNode("./descendant::table/descendant::text()[normalize-space()='Arrives']/following::text()[normalize-space()][2]", $root);
            $arrName = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Operated by')]/preceding::text()[contains(normalize-space(), 'to')][1]", $root, true, "/to\s(\D+)$/");
            $s->arrival()
                ->name($arrName)
                ->noCode()
                ->date(strtotime($arrDate . ', ' . $arrTime));

            $depTime = $this->http->FindSingleNode("./descendant::table/descendant::text()[normalize-space()='Departs']/following::text()[normalize-space()][1]", $root);
            $depDate = $this->http->FindSingleNode("./descendant::table/descendant::text()[normalize-space()='Departs']/following::text()[normalize-space()][2]", $root);
            $depName = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Operated by')]/preceding::text()[contains(normalize-space(), 'to')][1]", $root, true, "/^(\D+)\sto/");
            $s->departure()
                ->name($depName)
                ->noCode()
                ->date(strtotime($depDate . ', ' . $depTime));

            $cabin = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(),'Service Class')]/following::text()[normalize-space()][2]", $root);

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $duration = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(),'Duration')]/following::text()[normalize-space()][1]", $root);

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }
        }

        $statementInfo = $this->http->FindSingleNode("//img[contains(@src, 'header-tier-globe-')]/ancestor::tr[1]");

        if (!empty($statementInfo)) {
            $st = $email->add()->statement();

            if (preg_match("/^\s*(\d+)\s*([\d\.\,]+)/u", $statementInfo, $m)) {
                $st->setNumber($m[1])
                    ->setBalance(cost($m[2]));

                $st->addProperty('Status', $this->http->FindSingleNode("//img[contains(@src, 'header-tier-globe-')]/@src", null, true, "/header\-tier\-globe\-(\D+)\./"));

                $st->addProperty('Name', $f->getTravellers()[0][0]);
            }
        }

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $this->ParserFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
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
