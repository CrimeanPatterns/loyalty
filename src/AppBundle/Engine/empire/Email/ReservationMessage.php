<?php

namespace AwardWallet\Engine\empire\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationMessage extends \TAccountChecker
{
    public $mailFiles = "empire/it-138343403.eml, empire/it-137582432.eml, empire/it-137670989.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'       => ['Reservation #'],
            'Pickup Date/Time' => ['Pickup Date/Time'],
            'Vehicle Info'     => ['Vehicle Info', 'Vehicle Make/Model', 'Requested Vehicle Type'],
        ],
    ];

    private $detectors = [
        'en' => ['RESERVATION LOCATIONS'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@empirecls.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (!$this->detectBody() || !$this->assignLang()) {
            return false;
        }

        return $this->http->XPath->query("//tr/*[{$this->starts($this->t('Company Name'))}]/following-sibling::*[contains(.,'EmpireCLS')]")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('ReservationMessage' . ucfirst($this->lang));

        $this->parseTransfer($email);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseTransfer(Email $email): void
    {
        $transfer = $email->add()->transfer();

        $s = $transfer->addSegment();

        $vehicle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[{$this->starts($this->t('Vehicle Info'))}]/following-sibling::*[normalize-space()][1]");
        $s->extra()->model($vehicle, false, true);

        $reservationNo = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[{$this->starts($this->t('Reservation #'))}]/following-sibling::*[normalize-space()][1]", null, true, '/^([A-Z\d]{5,})(?:\*\d{1,3})?$/');

        if ($reservationNo) {
            $reservationNoTitle = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[{$this->starts($this->t('Reservation #'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $transfer->general()->confirmation($reservationNo, $reservationNoTitle);
        }

        $datePickup = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[{$this->starts($this->t('Pickup Date/Time'))}]/following-sibling::*[normalize-space()][1]");
        $s->departure()->date2($datePickup);

        // BRIAN MARQUIS 2
        $passengerName = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[{$this->starts($this->t('Passenger Name'))}]/following-sibling::*[normalize-space()][1]", null, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]][.\s\d]*$/u");
        $transfer->general()->traveller($passengerName, true);

        $locationPickup = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[{$this->eq($this->t('Pickup:'))}]/following-sibling::*[normalize-space()][1]");

        if (preg_match("/^(?<code>[A-Z]{3})\s*-.+\d/", $locationPickup, $m)) {
            // MSY - Delta Air Lines 1193 - from LAX at: 05:57 PM [PU Point: CURBSIDE]
            $s->departure()->code($m['code']);
        } else {
            $s->departure()->address($locationPickup);
        }

        $locationDropoff = $this->http->FindSingleNode("//tr[count(*[normalize-space()])=2]/*[{$this->eq($this->t('Dropoff:'))}]/following-sibling::*[normalize-space()][1]");

        if (preg_match("/^(?<code>[A-Z]{3})\s*-.+\d/", $locationDropoff, $m)) {
            // LAX-Delta Air Lines 1193 depart at:  12:26 PM Departing to MSY
            $s->arrival()->code($m['code']);
        } elseif (preg_match("/^.*?\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?\s*,\s*(?<address>.{3,})$/", $locationDropoff, $m)) {
            // As directed until approx 22:00, NEW ORLEANS, LA 70140
            $s->arrival()->address($m['address']);
        } elseif (preg_match("/(?:Inbound Greeter|as directed)\s*,\s*(?<address>.{3,})$/i", $locationDropoff, $m)) {
            // as directed, ATLANTA, GA 39901
            $s->arrival()->address($m['address']);
        } else {
            $s->arrival()->address($locationDropoff);
        }
        $s->arrival()->noDate();

        $xpathContacts = "//tr[{$this->eq($this->t('CONTACT INFORMATION'))}]/following::";
        $phone = $this->http->FindSingleNode($xpathContacts . "tr[count(*[normalize-space()])=2]/*[{$this->eq($this->t('Phone:'))}]/following-sibling::*[normalize-space()][1]", null, true, "/^[+(\d][-+. \d)(]{5,}[\d)]$/");
        $transfer->program()->phone($phone);
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['Pickup Date/Time'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Pickup Date/Time'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }
}
