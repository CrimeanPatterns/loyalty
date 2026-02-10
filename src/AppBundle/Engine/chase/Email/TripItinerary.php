<?php

namespace AwardWallet\Engine\chase\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TripItinerary extends \TAccountChecker
{
    public $mailFiles = "chase/it-561525524.eml, chase/it-562547566.eml, chase/it-630400195.eml";
    public $subjects = [
        'has shared their trip itinerary with you',
    ];

    public $lang = 'en';
    public $lastDateIn;
    public $lastDateOut;

    public $lastDate;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@travelcenter.res11.com') !== false) {
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
        if ($this->http->XPath->query("//text()[{$this->contains(['Chase Privacy Operations', 'Travel Rewards Center'])}]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Trip overview'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Email Security Information'))}]")->length > 0) {
            return true;
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('has shared their trip itinerary with you'))}]")->length > 0) {
            return true;
        }

        return true;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]travelcenter\.res11\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $conf = $this->http->FindSingleNode("//text()[normalize-space()='Trip overview']/following::text()[starts-with(normalize-space(), 'Trip ID')][1]/following::text()[normalize-space()][1]", null, true, "/^[#]\s*([A-Z\d]+)\s*$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->contains(['and have the Trip ID', 'and have your Trip ID'])}]", null, true, "/ Trip ID ([A-Z\d]{5,}) /");
        }
        $email->ota()
            ->confirmation($conf);

        if ($this->http->XPath->query("//text()[normalize-space()='Check-in']")->length > 0) {
            $this->ParseHotel($email);
        }

        if ($this->http->XPath->query("//text()[normalize-space()='Pick-up']")->length > 0) {
            $this->ParseCar($email);
        }

        if ($this->http->XPath->query("//text()[normalize-space()='Depart']")->length > 0) {
            $this->ParseFlight($email);
        }

        foreach ($email->getItineraries() as $it) {
            $status = $this->http->FindSingleNode("//text()[normalize-space()='Status']/following::text()[normalize-space()][1]");

            if (!empty($status)) {
                $it->general()
                    ->status($status);
            }

            $date = strtotime($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booked on')]", null, true, "/{$this->opt($this->t('Booked on'))}\s*(.+)/"));

            if (!empty($date)) {
                $it->general()
                    ->date($date);
            }
            $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Email intended for:']/following::text()[normalize-space()][1]");

            if (empty($traveller)) {
                $traveller = $this->http->FindSingleNode("(//text()[{$this->contains('has shared their trip itinerary with you')}])[last()]",
                    null, true, "/^\s*([A-Z][A-Z\W]+) has shared/");
            }

            if (empty($traveller)) {
                $traveller = $this->http->FindSingleNode("(//text()[{$this->contains(' has shared ')}])[last()]",
                    null, true, "/^\s*([A-Z][A-Z\W]+) has shared /");
            }
            $it->general()
                ->traveller($traveller);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseHotel(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[normalize-space()='Check-in']");

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->confirmation($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Reference number:')][1]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Reference number:'))}\s*([\d\-]+)/"));

            $h->hotel()
                ->name($this->http->FindSingleNode("./ancestor::td[1]/following::text()[normalize-space()][1]", $root))
                ->address($this->http->FindSingleNode("./ancestor::td[1]/following::text()[starts-with(normalize-space(), 'Reference number: ')][1]/preceding::text()[normalize-space()][1]/ancestor::td[1]", $root));

            $xpath = "./following::text()[{$this->eq($h->getHotelName())}]/ancestor::td[1]";

            $dateIn = $this->http->FindSingleNode("./following::text()[{$this->eq($h->getHotelName())}]/ancestor::td[1]/preceding::td[normalize-space()][1][contains(normalize-space(), 'Check-in')]/preceding::text()[normalize-space()][1]", $root, true, "/^(\w+\,\s*\w+\s*\d+\,\s*\d{4})$/");

            if (!empty($dateIn)) {
                $this->lastDateIn = $dateIn;
            } else {
                $dateIn = $this->lastDateOut;
            }
            $timeIn = $this->http->FindSingleNode($xpath . "/following::td[normalize-space()][1][starts-with(normalize-space(), 'Check-in:')][1]", $root, true, "/{$this->opt($this->t('Check-in:'))}\s*(.+)/");

            if (!empty($dateIn) && !empty($timeIn)) {
                $h->booked()
                    ->checkIn(strtotime($dateIn . ', ' . $timeIn));
            }

            $dateOut = $this->http->FindSingleNode($xpath . "/preceding::td[normalize-space()][1][contains(normalize-space(), 'Check-out')]/preceding::text()[normalize-space()][1]", $root, true, "/^(\w+\,\s*\w+\s*\d+\,\s*\d{4})$/");

            if (!empty($dateOut)) {
                $this->lastDateOut = $dateOut;
            } else {
                $dateOut = $this->http->FindSingleNode($xpath . "/preceding::td[normalize-space()][1][contains(normalize-space(), 'Check-out')]/preceding::text()[normalize-space()='Check-in'][1]/preceding::text()[normalize-space()][1]", $root, true, "/^(\w+\,\s*\w+\s*\d+\,\s*\d{4})$/");
            }
            $timeOut = $this->http->FindSingleNode($xpath . "/following::td[normalize-space()][1][starts-with(normalize-space(), 'Check-out:')]", $root, true, "/{$this->opt($this->t('Check-out:'))}\s*([\d\:]+\s*A?P?M)/");

            if (!empty($dateOut) && !empty($timeOut)) {
                $h->booked()
                    ->checkOut(strtotime($dateOut . ', ' . $timeOut));
            }

            $h->addRoom()->setType($this->http->FindSingleNode("./ancestor::td[1]/following::text()[starts-with(normalize-space(), 'Reference number: ')][1]/preceding::text()[normalize-space()][1]/ancestor::td[1]/preceding::td[1]", $root));
        }
    }

    public function ParseCar(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[normalize-space()='Pick-up']");

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $r->general()
                ->confirmation($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Reference number:')][1]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Reference number:'))}\s*([\d\-A-Z]+)/"));

            $company = $this->http->FindSingleNode("./ancestor::td[1]/following::text()[normalize-space()][1]", $root);
            $r->extra()
                ->company($company);

            $dateIn = $this->http->FindSingleNode("./following::text()[{$this->eq($company)}]/ancestor::td[1]/preceding::td[normalize-space()][1][contains(normalize-space(), 'Pick-up')]/preceding::text()[normalize-space()][not({$this->eq('Car')})][1]", $root, true, "/^(\w+\,\s*\w+\s*\d+\,\s*\d{4})$/");
            $timeIn = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $root);

            if (!empty($dateIn) && !empty($timeIn)) {
                $r->pickup()
                    ->date(strtotime($dateIn . ', ' . $timeIn));
            }
            $location = $this->http->FindSingleNode("./ancestor::td[1]/following::text()[normalize-space()][2]", $root);
            $r->pickup()
                ->location($location);

            $xpath = "./following::text()[{$this->eq($company)}]/ancestor::td[1]";
            $dateOut = $this->http->FindSingleNode($xpath . "/preceding::td[normalize-space()][1][contains(normalize-space(), 'Drop-off')]/preceding::text()[normalize-space()][not({$this->eq('Car')})][1]", $root, true, "/^(\w+\,\s*\w+\s*\d+\,\s*\d{4})$/");
            $timeOut = $this->http->FindSingleNode($xpath . "/preceding::text()[normalize-space()][2][normalize-space() = 'Drop-off']/following::text()[normalize-space()][1]", $root, true, "/^\s*([\d\:]+(\s*[AP]M)?)\s*$/i");

            if (!empty($dateOut) && !empty($timeOut)) {
                $r->dropoff()
                    ->date(strtotime($dateOut . ', ' . $timeOut));
            }
            $location = $this->http->FindSingleNode($xpath . "[preceding::text()[normalize-space()][2][normalize-space() = 'Drop-off']]/following::text()[normalize-space()][1]", $root);
            $r->dropoff()
                ->location($location);

            $r->car()
                ->model($this->http->FindSingleNode("./ancestor::td[1]/following::text()[normalize-space()][3][contains(. , 'OR SIMILAR')]", $root));
        }
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $nodes = $this->http->XPath->query("//text()[normalize-space()='Depart']");
        $confs = [];

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->setConfirmation($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Airline reference number:')][1]", $root, true, "/\:\s*([A-Z\d]{6})\b/"));
            $confs[] = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Agency reference number:')][1]", $root, true, "/\:\s*([A-Z\d]{6})/");

            $depDate = $this->http->FindSingleNode("./preceding::text()[normalize-space()][not({$this->eq($this->t('Flight'))})][1]", $root, true, "/^(\w+\,\s*\w+\s*\d+\,\s+\d{4})$/");

            if (!empty($depDate)) {
                $this->lastDate = $depDate;
            } else {
                $depDate = $this->lastDate;
            }
            $depTime = $this->http->FindSingleNode("./ancestor::td[1]", $root, true, "/{$this->opt($this->t('Depart'))}\s*([\d\:]+(?:\s*[AP]M)?)\s*$/i");

            if (!empty($depDate) && !empty($depTime)) {
                $s->airline()
                    ->name($this->http->FindSingleNode("./following::text()[contains(normalize-space(), '(')][1]/following::text()[normalize-space()][not(contains(normalize-space(), 'Next day arrival'))][2]", $root, true, "/^([A-Z][A-Z\d]|[A-Z\d][A-Z])/"))
                    ->noNumber();

                $s->departure()
                    ->date(strtotime($depDate . ', ' . $depTime))
                    ->code($this->http->FindSingleNode("./following::text()[contains(normalize-space(), '(')][1]", $root, true, "/\(([A-Z]{3})\)/"));
            }

            $arrDate = $this->http->FindSingleNode("./following::text()[normalize-space()='Arrive'][1]/preceding::text()[normalize-space()][1]", $root, true, "/^(\w+\,\s*\w+\s*\d+\,\s+\d{4})$/");

            if (empty($arrDate)) {
                $arrDate = $depDate;
            } else {
                $this->lastDate = $arrDate;
            }

            $arrTime = $this->http->FindSingleNode("./following::text()[normalize-space()='Arrive'][1]/ancestor::td[1]", $root, true, "/{$this->opt($this->t('Arrive'))}\s*([\d\:]+(?:\s*[AP]M)?)\s*$/i");

            if (!empty($arrDate) && !empty($arrTime)) {
                $s->arrival()
                    ->date(strtotime($arrDate . ', ' . $arrTime))
                    ->code($this->http->FindSingleNode("./following::text()[normalize-space()='Arrive'][1]/following::text()[contains(normalize-space(), ')')][1]", $root, true, "/\(([A-Z]{3})\)/"));
            }
        }

        $confs = array_unique($confs);

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
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
}
