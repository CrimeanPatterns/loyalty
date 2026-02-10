<?php

namespace AwardWallet\Engine\delta\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EventItinerary extends \TAccountChecker
{
    public $mailFiles = "delta/it-85471077.eml";
    public $subjects = [
        '/Your Event Itinerary/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'HOTEL INFORMATION'  => ['HOTEL INFORMATION', 'Hotel Information', 'HOTEL ACCOMODATIONS AND EVENT LOCATION', 'Hotel Accomodations And Event Location'],
            'checkIn'            => ['Check-in Date', 'Check-In Date'],
            'checkOut'           => ['Check-out Date', 'Check-Out Date'],
            'FLIGHT INFORMATION' => ['FLIGHT INFORMATION', 'Flight Information', 'FLIGHTS', 'Flights'],
            'confNo'             => ['Confirmation', 'Confirmation #'],
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@bcdtravel.com') !== false) {
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
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Delta Air Lines')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('FLIGHT INFORMATION'))} or {$this->contains($this->t('HOTEL INFORMATION'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]bcdtravel\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email, ?string $traveller): void
    {
        $h = $email->add()->hotel();

        $h->general()->traveller($traveller);

        $hotelTexts = $this->http->FindNodes("//p[ normalize-space() and preceding::text()[{$this->eq($this->t('HOTEL INFORMATION'))}] and following::tr/*[{$this->eq($this->t('checkIn'))}] ]");

        if (count($hotelTexts) > 1 && count($hotelTexts) < 6) {
            $hotelName = array_shift($hotelTexts);
            $address = implode(' ', $hotelTexts);
            $h->hotel()->name($hotelName)->address($address);
        }

        $xpathPart = "//text()[{$this->eq($this->t('HOTEL INFORMATION'))}]/following::tr[ *[{$this->eq($this->t('checkIn'))}] ]";

        $dateCheckIn = strtotime(
            $this->http->FindSingleNode($xpathPart . "[ *[normalize-space()][1][{$this->eq($this->t('checkIn'))}] ]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][1]", null, true, "/^.*\d.*$/")
            ?? $this->http->FindSingleNode($xpathPart . "[ *[normalize-space()][2][{$this->eq($this->t('checkIn'))}] ]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][2]", null, true, "/^.*\d.*$/")
        );
        $dateCheckOut = strtotime(
            $this->http->FindSingleNode($xpathPart . "[ *[normalize-space()][2][{$this->eq($this->t('checkOut'))}] ]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][2]", null, true, "/^.*\d.*$/")
            ?? $this->http->FindSingleNode($xpathPart . "[ *[normalize-space()][3][{$this->eq($this->t('checkOut'))}] ]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][3]", null, true, "/^.*\d.*$/")
        );

        $timeCheckIn = $this->http->FindSingleNode("//tr/*[{$this->starts($this->t('Hotel Check-In Time'))}]/following-sibling::*[normalize-space()]", null, true, "/^{$this->patterns['time']}$/");

        if ($timeCheckIn) {
            $dateCheckIn = strtotime($timeCheckIn, $dateCheckIn);
        }

        $timeCheckOut = $this->http->FindSingleNode("//tr/*[{$this->starts($this->t('Hotel Check-Out Time'))}]/following-sibling::*[normalize-space()]", null, true, "/^{$this->patterns['time']}$/");

        if ($timeCheckOut) {
            $dateCheckOut = strtotime($timeCheckOut, $dateCheckOut);
        }

        $h->booked()->checkIn($dateCheckIn)->checkOut($dateCheckOut);

        $confirmation = $this->http->FindSingleNode($xpathPart . "[ *[normalize-space()][3][{$this->eq($this->t('confNo'))}] ]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][3]", null, true, "/^[-A-Z\d]{5,}$/")
            ?? $this->http->FindSingleNode($xpathPart . "[ *[normalize-space()][4][{$this->eq($this->t('confNo'))}] ]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][4]", null, true, "/^[-A-Z\d]{5,}$/")
        ;
        $confirmationTitle = $this->http->FindSingleNode($xpathPart . "/*[normalize-space()][3][{$this->eq($this->t('confNo'))}]")
            ?? $this->http->FindSingleNode($xpathPart . "/*[normalize-space()][4][{$this->eq($this->t('confNo'))}]")
        ;
        $h->general()->confirmation($confirmation, $confirmationTitle);
    }

    public function ParseFlight(Email $email, ?string $traveller): void
    {
        $f = $email->add()->flight();

        $f->general()->traveller($traveller)->noConfirmation();

        $xpath = "//text()[{$this->eq($this->t('FLIGHT INFORMATION'))}]/following::tr[starts-with(normalize-space(), 'Date') and contains(normalize-space(), 'From')]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->noName()
                ->number($this->http->FindSingleNode("./descendant::td[2]", $root, true, "/^(\d+)$/"));

            $date = $this->http->FindSingleNode("./descendant::td[1]", $root);

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::td[3]", $root))
                ->date(strtotime($date . ', ' . $this->http->FindSingleNode("./descendant::td[5]", $root)));

            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::td[4]", $root))
                ->date(strtotime($date . ', ' . $this->http->FindSingleNode("./descendant::td[6]", $root)));

            $s->setConfirmation($this->http->FindSingleNode("descendant::td[7]", $root, true, "/^([A-Z\d]{5,7})(?:\s*\(|$)/"));
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$this->patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('FLIGHT INFORMATION'))}]")->length > 0) {
            $this->ParseFlight($email, $traveller);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('HOTEL INFORMATION'))}]")->length > 0) {
            $this->ParseHotel($email, $traveller);
        }

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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
