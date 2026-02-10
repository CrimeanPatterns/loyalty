<?php

namespace AwardWallet\Engine\chinasouthern\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightItinerary extends \TAccountChecker
{
    public $mailFiles = "chinasouthern/it-631974960.eml";
    public $subjects = [
        'China Southern Airlines Itinerary',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@csair.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'China Southern Affiliates')]")->length > 0
            || $this->http->XPath->query("//a[contains(@href, 'csair.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Your Booking'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Itinerary'))}]")->length > 0;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]csair\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your Booking')]", null, true, "/\(([A-z\d]{5,})\)/"));
        $this->ParseFlight($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();
        $f->general()
            ->noConfirmation()
            ->travellers(preg_replace("/\s*CHD$/", "", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Travel Doc. No.')]/ancestor::tr[1]/following::tbody[1]/descendant::tr[1]/descendant::td[1]")));

        $tickets = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Ticket Number')]/ancestor::tr[1]/following::tbody[1]/descendant::tr[1]/descendant::td[3]");

        foreach ($tickets as $ticket) {
            $pax = preg_replace("/\s*CHD$/", "", $this->http->FindSingleNode("//text()[{$this->eq($ticket)}]/ancestor::tr[1]/descendant::text()[normalize-space()][1]"));

            if (!empty($pax)) {
                $f->addTicketNumber($ticket, false, $pax);
            } else {
                $f->addTicketNumber($ticket, false);
            }
        }

        $nodes = $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Departure')]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $airlineInfo = $this->http->FindSingleNode("./following::tr[1]/descendant::td[1]", $root);

            if (preg_match("/^(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{2,4})(?:\(|$)/u", $airlineInfo, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);
            }

            $depArrText = $this->http->FindSingleNode("./preceding::div[1]/descendant::text()[normalize-space()][1]", $root);

            if (preg_match("/^\s*\w+\,\s*(?<date>\d+\s*\w+\s*\d{4})\s*\|\s*(?<depName>.+)\s+\-\s+(?<arrName>.+)$/", $depArrText, $m)) {
                $depTerminal = $this->http->FindSingleNode("./following::tr[1]/descendant::td[2]", $root, true, "/{$this->opt($this->t('Terminal'))}\s*(.+)/us");

                if (!empty($depTerminal)) {
                    $s->departure()
                        ->terminal($depTerminal);
                }

                $depTime = $this->http->FindSingleNode("./following::tr[2]/descendant::td[2]", $root);
                $s->departure()
                    ->name($m['depName'])
                    ->noCode()
                    ->date(strtotime($m['date'] . ' ' . $depTime));

                $arrTerminal = $this->http->FindSingleNode("./following::tr[1]/descendant::td[3]", $root, true, "/{$this->opt($this->t('Terminal'))}\s*(.+)/us");

                if (!empty($arrTerminal)) {
                    $s->arrival()
                        ->terminal($arrTerminal);
                }

                $arrTime = $this->http->FindSingleNode("./following::tr[2]/descendant::td[3]", $root);
                $s->arrival()
                    ->name($m['arrName'])
                    ->noCode();

                if (preg_match("/^(\d+\:\d+)\s+\([+]\d+\D\)$/", $arrTime, $match)) {
                    $arrTime = $match[1];
                    $s->arrival()
                        ->date(strtotime('+1 day', strtotime($m['date'] . ' ' . $arrTime)));
                } else {
                    $s->arrival()
                        ->date(strtotime($m['date'] . ' ' . $arrTime));
                }
            }

            $cabinInfo = implode("\n", $this->http->FindNodes("./following::tr[2]/descendant::td[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<aircraft>.+)\n*Class\s*(?<bookingCode>[A-Z])\s*\((?<cabin>.+)\)/", $cabinInfo, $m)) {
                $s->extra()
                    ->aircraft($m['aircraft'])
                    ->bookingCode($m['bookingCode'])
                    ->cabin($m['cabin']);
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }
}
