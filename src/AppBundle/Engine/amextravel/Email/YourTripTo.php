<?php

namespace AwardWallet\Engine\amextravel\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourTripTo extends \TAccountChecker
{
    public $mailFiles = "amextravel/it-75886861.eml, amextravel/it-75980627.eml, amextravel/it-76055461.eml, amextravel/it-76210742.eml, amextravel/it-76308185.eml";
    public $subjects = [
        '/^Your Trip to \D+ is Coming Soon\! [\d\-]+$/',
        '/^Your Trip to is Coming Soon\! [A-Z]+$/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'AMERICAN EXPRESS TRIP ID:' => ['AMERICAN EXPRESS TRIP ID:', 'AMERICAN EXPRESS RECORD LOCATOR:', 'American Express Record Locator:'],
            'TRAVELER INFORMATION'      => ['TRAVELER INFORMATION', 'Traveler Information'],
            'CAR CONFIRMATION'          => ['CAR CONFIRMATION', 'Car Confirmation'],
            'FLIGHT CONFIRMATION'       => ['FLIGHT CONFIRMATION', 'Flight Confirmation'],
            'LIMOUSINE CONFIRMATION'    => ['LIMOUSINE CONFIRMATION', 'Limousine Confirmation'],
            'HOTEL CONFIRMATION'        => ['HOTEL CONFIRMATION', 'Hotel Confirmation'],
            'E-TICKET'                  => ['E-TICKET', 'E-Ticket'],
            'FLIGHT'                    => ['FLIGHT', 'Flight'],
            'DEPARTING'                 => ['DEPARTING', 'Departing'],
            'ARRIVING'                  => ['ARRIVING', 'Arriving'],
            'FLIGHT INFORMATION'        => ['FLIGHT INFORMATION', 'Flight Information'],
            'nonStop'                   => ['Non-stop', 'Non Stop'],
            'Check In'                  => ['Check In', 'Check-In', 'CHECK IN'],
            'Check Out'                 => ['Check Out', 'CHECK OUT'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@myamextravel.com') !== false) {
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
        if ($this->http->XPath->query('//a[contains(@href,".americanexpress.com/") or contains(@href,"www.americanexpress.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"American Express. All rights reserved") or contains(.,"www.americanexpress.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]myamextravel\.com$/', $from) > 0;
    }

    public function ParseCar(Email $email, array $travellers): void
    {
        $xpath = "//text()[normalize-space()='Rental Car Details']";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->rental();
            $r->general()
                ->confirmation($this->http->FindSingleNode("following::text()[{$this->starts($this->t('CAR CONFIRMATION'))}][1]/following::text()[normalize-space()][1]", $root), 'Car Confirmation');

            $r->general()
                ->travellers($travellers, true);

            $pickUpArray = $this->http->FindNodes("./following::text()[normalize-space()='Pick Up']/ancestor::td[2]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Pick Up'))]", $root);
            $r->pickup()
                ->date(strtotime($pickUpArray[0]))
                ->location($pickUpArray[1]);

            $dropOffArray = $this->http->FindNodes("./following::text()[normalize-space()='Drop Off']/ancestor::td[2]/descendant::text()[normalize-space()][not(contains(normalize-space(), 'Drop Off'))]", $root);

            if (count($dropOffArray) == 2) {
                $r->dropoff()
                    ->date(strtotime($dropOffArray[0]))
                    ->location($dropOffArray[1]);
            } elseif (count($dropOffArray) == 1) {
                $r->dropoff()
                    ->date(strtotime($dropOffArray[0]))
                    ->same();
            }
        }
    }

    public function ParseTransfer(Email $email, array $travellers): void
    {
        $xpath = "//text()[normalize-space()='Limousine Details']";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $t = $email->add()->transfer();

            $t->general()
                ->confirmation($this->http->FindSingleNode("following::text()[{$this->starts($this->t('LIMOUSINE CONFIRMATION'))}][1]/following::text()[normalize-space()][1]", $root), 'Limousine Confirmation')
                ->travellers($travellers, true);

            $s = $t->addSegment();

            $s->departure()
                ->address($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(.), 'Pick Up')][1]/following::text()[normalize-space()][2]", $root))
                ->date(strtotime($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(.), 'Pick Up')][1]/following::text()[normalize-space()][1]", $root)));

            if (preg_match("/^\s*([a-zA-Z\d][a-zA-Z]|[a-zA-Z][a-zA-Z\d]) ?\d{1,5}\s+\d{4}[AP]\s*$/", $s->getDepAddress())) {
                // Ua1154 0225P
                $s->departure()
                    ->address('');
            }
            $s->arrival()
                ->name($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(.), 'Drop Off')][1]/following::text()[normalize-space()][1]", $root))
                ->noDate();
        }
    }

    public function ParseHotel(Email $email, array $travellers): void
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('HOTEL CONFIRMATION'))}]/following::text()[normalize-space()][1]"), 'Hotel Confirmation')
            ->travellers($travellers, true);

        $cancellation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Cancellation Policy:')]", null, true, "/{$this->opt($this->t('Cancellation Policy:'))}\s*(.+)/s");

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[normalize-space() = 'Hotel Details']/following::text()[normalize-space()][1]"));

        $addressInfo = $this->http->FindSingleNode("//text()[normalize-space() = 'Address:']/ancestor::td[1]", null, true, "/{$this->opt($this->t('Address:'))}\s*(.+)/");

        if (preg_match("/^(?<address>.+)\s+Telephone\:\s*(?<phone>[\d\-]+)\s*(?:Status\:(?<status>\w+))?$/u", $addressInfo, $match)) {
            $h->hotel()
                ->address($match['address'])
                ->phone($match['phone']);

            if (isset($match['status']) && !empty($match['status'])) {
                $h->setStatus($match['status']);
            }
        } else {
            $h->hotel()
                ->address($addressInfo);
        }

        $phone = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Telephone:')]/following::text()[normalize-space()][1]", null, true, "/^([\d\-]+)$/");

        if (!empty($phone)) {
            $h->hotel()
                ->phone($phone);
        }

        $h->booked()
            ->checkIn(strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Check In'))}]/following::text()[normalize-space()][1]")))
            ->checkOut(strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Check Out'))}]/following::text()[normalize-space()][1]")));

        $roomType = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Room Type:')]", null, true, "/{$this->opt($this->t('Room Type:'))}\s*(.+)/");

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        $this->detectDeadLine($h, $h->getCancellation());
    }

    public function ParseFlight(Email $email, array $travellers): void
    {
        $f = $email->add()->flight();
        $f->general()
            ->noConfirmation();

        if (count($travellers)) {
            $f->general()->travellers($travellers, true);
        }

        $tickets = array_filter($this->http->FindNodes("//tr[{$this->eq($this->t('E-TICKET'))}]/following::tr[normalize-space()][1]", null, "/^(?:Ticket)?\s*(?:[A-Z\s]*)?(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3})[A-Z]*$/"));
        $f->setTicketNumbers(array_unique(array_filter($tickets)), false);

        $accounts = $this->http->FindNodes("//text()[normalize-space()='Loyalty Program']/following::text()[normalize-space()][1]", null, "/^[A-Z]*\s*([A-Z\d]*)$/");

        if (count($accounts) > 0) {
            $f->setAccountNumbers(array_unique(array_filter($accounts)), false);
        }

        $xpath = "//text()[{$this->eq($this->t('FLIGHT CONFIRMATION'))} or {$this->eq($this->t('FLIGHT INFORMATION'))}]/ancestor::table[ descendant::text()[{$this->eq($this->t('DEPARTING'))}] ][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            if (empty($this->http->FindSingleNode("descendant::text()[contains(normalize-space(), ' to ')]/following::text()[{$this->starts($this->t('FLIGHT'))}][1]/ancestor::td[1]", $root, true, "/^{$this->opt($this->t('FLIGHT'))}\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])/"))) {
                continue;
            }

            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(), ' to ')]/following::text()[{$this->starts($this->t('FLIGHT'))}][1]/ancestor::td[1]", $root);

            if (preg_match("/^{$this->opt($this->t('FLIGHT'))}\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/", $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $operator = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Operated By')][1]", $root, true, "/{$this->opt($this->t('Operated By'))}\s*(.+)/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $s->departure()
                ->date(strtotime($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('DEPARTING'))}][1]/following::text()[normalize-space()][1]", $root)))
                ->strict()
                ->code($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('DEPARTING'))}][1]/following::text()[normalize-space()][3]", $root, true, "/\(([A-Z]{3})\)/"));

            $s->arrival()
                ->date(strtotime($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('ARRIVING'))}][1]/following::text()[normalize-space()][1]", $root)))
                ->strict()
                ->code($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('ARRIVING'))}][1]/following::text()[normalize-space()][3]", $root, true, "/\(([A-Z]{3})\)/"));

            $class = $this->http->FindSingleNode("descendant::text()[ normalize-space() and preceding::text()[normalize-space() and not({$this->starts($this->t('Your Seats:'))})][1][{$this->starts($this->t('FLIGHT INFORMATION'))}] and following::text()[normalize-space()][1][{$this->starts($this->t('Equipment:'))}] ]", $root);

            if (preg_match("/^([A-Z]{1,2})\s+([A-z]{3,}[A-z ]*)$/", $class, $m)) {
                // J Business Class
                $s->extra()
                    ->bookingCode($m[1])
                    ->cabin($m[2]);
            } elseif (preg_match("/^[A-Z]{1,2}$/", $class)) {
                // J
                $s->extra()->bookingCode($class);
            } elseif (preg_match("/^[A-z]{3,}[A-z ]*$/", $class)) {
                // Business Class
                $s->extra()->cabin($class);
            }

            $equipment = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Equipment:'))}]", $root, true, "/{$this->opt($this->t('Equipment:'))}\s*(.{2,})$/");
            $s->extra()->aircraft($equipment, false, true);

            $meals = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Meals:'))}]", $root, true, "/{$this->opt($this->t('Meals:'))}\s*(.{2,})$/");
            $s->extra()->meal($meals, false, true);

            $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('FLIGHT CONFIRMATION'))}][1]/following::text()[normalize-space()][1]", $root, true, "/:\s*([-A-Z\d]{5,})$/");

            if (!empty($confirmation)) {
                $s->airline()->confirmation($confirmation);
            }

            $seat = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Your Seats:'))}]", $root, true, "/{$this->opt($this->t('Your Seats:'))}\s+(.+)/");

            if (!empty($seat)) {
                $s->extra()
                    ->seats(explode(',', $seat));
            }

            if ($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('nonStop'))}]", $root)) {
                $s->extra()->stops(0);
            }

            $duration = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Duration:'))}]/following::text()[normalize-space()][1]", $root, true, "/^\d[\d HrsMin]+$/i");
            $s->extra()->duration($duration, false, true);

            $terminalDep = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Departure Terminal:'))}]/following::text()[normalize-space()][1]", $root, true, "/^(?:{$this->opt($this->t('Terminal'))}[-\s:]+)?([A-z\d ]+)$/i");
            $s->departure()->terminal($terminalDep, false, true);

            $terminalArr = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Arrival Terminal:'))}]/following::text()[normalize-space()][1]", $root, true, "/^(?:{$this->opt($this->t('Terminal'))}[-\s:]+)?([A-z\d ]+)$/i");
            $s->arrival()->terminal($terminalArr, false, true);

            $status = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('FLIGHT CONFIRMATION'))}][1]/following::text()[normalize-space()][2]", $root, true, "/^\s*([[:alpha:]\-]{4,})$/u");

            if (!empty($status)) {
                $s->extra()->status($status);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('AMERICAN EXPRESS TRIP ID:'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($otaConfirmation) {
            $otaConfirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('AMERICAN EXPRESS TRIP ID:'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        }

        $travellers = [];
        $travellerRows = $this->http->XPath->query("//text()[{$this->eq($this->t('E-TICKET'))}]/preceding::text()[normalize-space()][1]");

        if ($travellerRows->length == 0) {
            $travellerRows = $this->http->XPath->query("//tr[{$this->eq($this->t('TRAVELER INFORMATION'))}]/following::tr[normalize-space() and not(.//tr) and not({$this->contains($this->t('E-TICKET'))})]");
        }

        foreach ($travellerRows as $tRow) {
            $rowContent = $this->http->FindSingleNode('.', $tRow);

            if (preg_match("/^\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}$/", $rowContent)) {
                continue;
            } elseif (preg_match("/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u", $rowContent)) {
                $travellers[] = $rowContent;
            } else {
                break;
            }
        }

        if (count($travellers)) {
            $travellers = array_unique($travellers);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('FLIGHT CONFIRMATION'))} or {$this->contains($this->t('FLIGHT INFORMATION'))}]")->length > 0) {
            $this->ParseFlight($email, $travellers);
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Hotel Details')]")->length > 0) {
            $this->ParseHotel($email, $travellers);
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Limousine Details')]")->length > 0) {
            $this->ParseTransfer($email, $travellers);
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Rental Car Details')]")->length > 0) {
            $this->ParseCar($email, $travellers);
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['AMERICAN EXPRESS TRIP ID:']) || empty($phrases['TRAVELER INFORMATION'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['AMERICAN EXPRESS TRIP ID:'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['TRAVELER INFORMATION'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, $cancellationText)
    {
        //Here we describe various variations in the definition of dates deadLine
        if (preg_match("#There is no charge for cancellations made before ([\d\:]+) \(property local time\) on (\w+) (\d+), (\d{4})\.#u", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[3] . ' ' . $m[2] . ' ' . $m[4] . ', ' . $m[1]));
        }
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
}
