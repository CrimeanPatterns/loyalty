<?php

namespace AwardWallet\Engine\flybe\Email;

use AwardWallet\Schema\Parser\Email\Email;

class FlightDelayed extends \TAccountChecker
{
    public $mailFiles = "flybe/it-38198018.eml, flybe/it-38782172.eml, flybe/it-38864568.eml";

    public $reFrom = ["booking@update.flybe.com"];
    public $reBody = [
        'en' => [
            'Flybe would like to apologise for the unexpected delay to your flight',
            'Flybe are writing to advise of a change to your upcoming flight schedule',
            'Flybe are looking forward to welcoming you onboard',
            'Click here for Flybe\'s full conditions of carriage, which apply to your booking with Flybe',
        ],
    ];
    public $reSubject = [
        'Important Flight Information - Your flight has been delayed',
        'Important Flight Information - Your flight has been changed',
        'Important Flight information - Your new itinerary',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'New Itinerary'           => ['New Itinerary', 'NEW ITINERARY', 'FLIGHT INFORMATION'],
            'Flight number'           => 'Flight number',
            'Your Booking Reference:' => ['Your Booking Reference:', 'YOUR BOOKING REFERENCE:'],
        ],
    ];
    private $year = 0;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }
        $this->year = date('Y', strtotime($parser->getDate()));

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'flybe') or contains(@src,'.flybe.com')] | //a[contains(@href,'.flybe.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->stripos($from, $this->reFrom);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        if (isset($headers["subject"]) && $this->stripos($headers["subject"], $this->reSubject)) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $nodes = $this->http->XPath->query("//tr[{$this->eq($this->t('New Itinerary'))}]/following-sibling::tr[{$this->contains($this->t('Departs'))}]");

        if ($nodes->length === 0) {
            $this->logger->debug("other format");

            return false;
        }

        $r = $email->add()->flight();

        // general info
        $r->general()
            ->status('changed')
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->starts($this->t('Your Booking Reference:'))}])[1]",
                null, false, "#{$this->opt($this->t('Your Booking Reference:'))}\s*(.+)#"),
                trim($this->t('Your Booking Reference:'), ":"));

        if ($pax = $this->http->FindSingleNode("//tr[{$this->eq($this->t('New Itinerary'))}]/following::text()[{$this->eq($this->t('Passenger:'))}]/following::text()[normalize-space()!=''][1]")) {
            $r->general()
                ->traveller($pax, false);
        }

        foreach ($nodes as $root) {
            $s = $r->addSegment();
            $date = strtotime($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root));

            // airline
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight number'))}]/following::text()[normalize-space()!=''][1]",
                $root);

            if (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight number'))}]/following::text()[normalize-space()!=''][2]",
                $root);

            if (preg_match("#^\s*Operated by\s*(.+)#", $node, $m)) {
                $s->airline()->operator($m[1]);
                $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight number'))}]/following::text()[normalize-space()!=''][3]",
                    $root);
            }

            if (preg_match('/(\d{1,2} [a-z]+)/i', $node, $m)) {
                $date = strtotime($m[1] . ' ' . $this->year);
                $node = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight number'))}]/following::text()[normalize-space()!=''][3]", $root);
            }

            // dep-arr points
            if (preg_match("#^(.+)\s+\(([A-Z]{3})\)\s+\-\s+(.+)\s+\(([A-Z]{3})\)$#", $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
                $s->arrival()
                    ->name($m[3])
                    ->code($m[4]);
            }

            // dep time
            $time = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departs'))}]/following::text()[normalize-space()!=''][1]",
                $root);
            $s->departure()
                ->date(strtotime($time, $date));

            // arr time
            $time = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arrives'))}]/following::text()[normalize-space()!=''][1]",
                $root);
            $s->arrival()
                ->date(strtotime($time, $date));
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['New Itinerary'], $words['Flight number'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['New Itinerary'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Flight number'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
