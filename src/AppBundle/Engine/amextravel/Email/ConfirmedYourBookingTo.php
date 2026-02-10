<?php

namespace AwardWallet\Engine\amextravel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmedYourBookingTo extends \TAccountChecker
{
    public $mailFiles = "amextravel/it-450563812.eml, amextravel/it-458651393.eml, amextravel/it-459305083.eml, amextravel/it-466398621.eml, amextravel/it-656725958.eml, amextravel/it-657665475.eml, amextravel/it-657982740.eml, amextravel/it-670898769.eml, amextravel/it-696633521.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Flight Number:'        => 'Flight Number:',
            'Room Type:'            => 'Room Type:',
            'Driver Name:'          => 'Driver Name:',
            'Check in:'             => ['Check in:', 'Check-in:', 'Check In:'],
            'Check out:'            => ['Check out:', 'Check-out:', 'Check Out:'],
            'Booking Cost'          => ['Booking Cost', 'Cost Information:'],
            'Points Used:'          => ['Points Used:', 'Points used:'],
            'Your booking #'        => ['Your booking #', 'Your trip #'],
            'Price:'                => ['Price:', 'Cost:'],
            'Taxes and Charges:'    => ['Taxes and Charges:', 'Taxes & Fees:', 'Taxes and Fees:'],
            'Guest Name(s):'        => ['Guest Name(s):', 'Guest Name:'],
            'Pick up Date:'         => ['Pick up Date:', 'Pick-up Date:'],
            'Pick up Location:'     => ['Pick up Location:', 'Pick-up Location:'],
            'Drop Off Date:'        => ['Drop Off Date:', 'Drop-off Date:'],
            'Drop Off Location:'    => ['Drop Off Location:', 'Drop-off Location:'],
            'Airline Confirmation:' => ['Airline Confirmation:', 'Airline Confirmation #:'],
        ],
    ];

    private $detectFrom = "AmericanExpress@welcome.americanexpress.com";
    private $detectSubject = [
        // en
        'Confirmed: your booking to ',
        'Confirmed: your trip to',
    ];
    private $detectBody = [
        'en' => [
            'Please review carefully all the details mentioned below',
            'Please carefully review all the details mentioned below',
            'review details for your upcoming trip',
            'Receive a room upgrade upon arrival, when available',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]welcome.americanexpress\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['//go.amex/', '%2F%2Fgo.amex%2F'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['with American Express Travel', 'through American Express Travel'])}]")->length === 0
        ) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Flight Number:"]) && $this->http->XPath->query("//*[{$this->contains($dict['Flight Number:'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }

            if (!empty($dict["Room Type:"]) && $this->http->XPath->query("//*[{$this->contains($dict['Room Type:'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }

            if (!empty($dict["Check in:"]) && $this->http->XPath->query("//*[{$this->contains($dict['Check in:'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }

            if (!empty($dict["Driver Name:"]) && $this->http->XPath->query("//*[{$this->contains($dict['Driver Name:'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }

            if (!empty($dict["Pick up Date:"]) && $this->http->XPath->query("//*[{$this->contains($dict['Pick up Date:'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        // Travel Agency
        $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking #'))}]",
            null, true, "/{$this->opt($this->t('Your booking #'))}\s*(\d[\d\-]{5,})\W+/");

        if (!empty($conf)) {
            $email->ota()
                ->confirmation($conf);
        }

        $this->parseFlights($email);
        $this->parseHotels($email);
        $this->parseRentals($email);

        // Price
        $points = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Cost'))}]/following::*[{$this->eq($this->t('Points Used:'))}]/following-sibling::*[normalize-space()][1]");

        if (!empty($points)) {
            $email->price()
                ->spentAwards($points);
        }

        $pointsValue = 0.0;
        $pointsValueText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Cost'))}]/following::*[{$this->eq($this->t('Points Value:'))}]/following-sibling::*[normalize-space()][1]");

        if ($pointsValueText !== null) {
            $pointsValue = $this->getTotal($pointsValueText)['amount'];
        }

        $totalText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Cost'))}]/following::*[{$this->eq($this->t('Total Billed by American Express:'))}]/following-sibling::*[normalize-space()][1]");
        $total = $this->getTotal($totalText);

        if (!empty($totalText)) {
            $email->price()
                ->total($total['amount'] - $pointsValue)
                ->currency($total['currency']);
        }

        if (empty($totalText)) {
            $totalText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Cost'))}]/following::*[{$this->eq($this->t('Dollars used:'))}]/following-sibling::*[normalize-space()][1]");
            $total = $this->getTotal($totalText);

            if ($total['amount'] !== null) {
                $email->price()
                    ->total($total['amount'])
                    ->currency('USD');
            }
        }

        if (empty($totalText)) {
            $totalText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Cost'))}]/following::*[{$this->eq($this->t('Total Cost:'))}]/following-sibling::*[normalize-space()][1]");
            $total = $this->getTotal($totalText);

            if (!empty($totalText)) {
                if ($this->http->XPath->query("//*[{$this->starts($this->t('Dollars used:'))} or {$this->starts($this->t('Total Billed by'))}]")->length === 0) {
                    $email->price()
                        ->total($total['amount'] - ($pointsValue ?? 0.0))
                        ->currency($total['currency']);
                } else {
                    $totalText = null;
                }
            }
        }

        /*if (empty($totalText)) {
            $email->price()
                ->total(null);
        }*/

        $cost = $this->getTotal($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Cost'))}]/following::*[{$this->eq($this->t('Price:'))}]/following-sibling::*[normalize-space()][1]"));

        if ($cost['amount'] !== null) {
            $email->price()
                ->cost($cost['amount']);
        }

        $taxes = $this->http->FindNodes("//text()[{$this->eq($this->t('Booking Cost'))}]/following::*[{$this->eq($this->t('Taxes and Charges:'))}]/ancestor::*[not({$this->eq($this->t('Taxes and Charges:'))})][1]");

        foreach ($taxes as $tax) {
            if (preg_match("/^\s*(.+?)\s*:\s*(.+)/", $tax, $m)) {
                $email->price()
                    ->fee($m[1], $this->getTotal($m[2])['amount']);
            }
        }

        return true;
    }

    private function parseFlights(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('From:'))}][following::td[not(.//td)][normalize-space()][position() < 10][{$this->starts($this->t('Flight Number:'))}]]/preceding::text()[normalize-space()][1]/ancestor::*[count(.//text()[{$this->starts($this->t('Flight Number:'))}]) = 1][1]";
        //$this->logger->debug('$xpath = ' . print_r($xpath, true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0 && $this->http->XPath->query("//text()[{$this->eq($this->t('Flight Number:'))}]")->length > 0) {
            $email->add()->flight();
            $this->logger->debug('contains flights, but not found');

            return true;
        }

        if ($nodes->length === 0) {
            return true;
        }

        $f = $email->add()->flight();

        $travellers = preg_split("/\s*,\s*/", implode(",",
            $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers:'))}]/following::tr[1]/descendant::text()[normalize-space()]", null, "/^(.+?)\s*(?:\(|E\-ticket|$)/")));

        if (count(array_filter($travellers)) === 0) {
            $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Traveler Information'))}]/ancestor::tr[1]/following::table[1]/descendant::text()[contains(normalize-space(), '(')]/ancestor::tr[1]", null, "/^(.+?)\s*(?:\(|$)/");
        }

        // General
        $f->general()
            ->noConfirmation();

        if (count($travellers) > 0) {
            $f->general()
                ->travellers(array_filter($travellers));
        }

        // Issued
        $tickets = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('E-ticket'))}]",
            null, "/\(\s*{$this->opt($this->t('E-ticket'))}\s+(\d{8,})\)/"));

        if (count($tickets) === 0) {
            $tickets = array_filter($this->http->FindNodes("//text()[(normalize-space(.)='Ticket Number:')]/following::text()[normalize-space()][1]", null, "/^(\d{12,})$/"));
        }

        foreach ($tickets as $ticket) {
            $pax = $this->http->FindSingleNode("//text()[{$this->eq($ticket)}]/ancestor::tr[1]/preceding::tr[not({$this->contains($this->t('Ticket Number:'))})][1]/descendant::td[1]", null, true, "/^(.+)\s+\(/");

            if (!empty($pax)) {
                $f->addTicketNumber($ticket, false, $pax);
            } else {
                $f->addTicketNumber($ticket, false);
            }
        }

        $accounts = array_filter($this->http->FindNodes("//text()[(normalize-space(.)='Loyalty Program:')]/following::text()[normalize-space()][2]", null, "/^([A-Z\d]{8,})$/"));

        if (count($accounts) > 0) {
            foreach ($accounts as $account) {
                $pax = $this->http->FindSingleNode("//text()[{$this->eq($account)}]/ancestor::tr[3]/preceding::text()[normalize-space()][1]/ancestor::tr[3][{$this->contains($this->t('Ticket Number:'))}]/preceding::tr[1]", null, true, "/^(.+)\s+\(/");

                if (!empty($pax)) {
                    $f->addAccountNumber($account, false, $pax);
                } else {
                    $f->addAccountNumber($account, false);
                }
            }
        }

        // Segments
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $node = implode(" ", $this->http->FindNodes(".//*[{$this->eq($this->t('Flight Number:'))}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+)(?:\s+|$)/", $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            $conf = $this->http->FindSingleNode(".//*[{$this->eq($this->t('Airline Confirmation:'))}]/following-sibling::*[normalize-space()][1]",
                $root, true, "/^\s*([A-Z\d]{5,7})\s*$/");

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Airline Confirmation:'))}]",
                    $root, true, "/^{$this->opt($this->t('Airline Confirmation:'))}\s*([A-Z\d]{5,7})\s*$/");
            }

            if (!empty($conf)) {
                $s->airline()
                    ->confirmation($conf);
            }

            $date = strtotime($this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root));

            if (empty($date)) {
                $date = strtotime($this->http->FindSingleNode("preceding::text()[normalize-space()][2]", $root, true, "/^(\w+\,\s*\w+\s*\d+\,\s*\d{4})$/"));
            }

            $re = "/^.+:\s+(?<code>[A-Z]{3})(?<terminal>\n.+)?\n(?<name>.+\n.+)\n(?<time>\d{1,2}:\d{2}\s*a?p?m?)\s*(?:[-+]|$)/";
            $re2 = "/^.+:\s+(?<code>[A-Z]{3})(?<terminal>\n.+)?\n(?<name>.+\n*.+)\n(?<time>\d{1,2}:\d{2}\s*a?p?m?)\s*(?:[-+]|$)/";

            // Departure
            $node = implode("\n", $this->http->FindNodes(".//text()[{$this->eq($this->t('From:'))}]/ancestor::td[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match($re, $node, $m) || preg_match($re2, $node, $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name(preg_replace("/\s+/", ' ', trim($m['name'])))
                    ->terminal(trim($m['terminal'] ?? ''), true, true)
                    ->date($date ? strtotime($m['time'], $date) : null)
                ;
            }

            // Arrival
            $node = implode("\n", $this->http->FindNodes(".//text()[{$this->eq($this->t('To:'))}]/ancestor::td[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match($re, $node, $m) || preg_match($re2, $node, $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name(preg_replace("/\s+/", ' ', trim($m['name'])))
                    ->terminal(trim($m['terminal'] ?? ''), true, true)
                    ->date($date ? strtotime($m['time'], $date) : null)
                ;
            }

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode(".//*[{$this->eq($this->t('Duration:'))}]/following-sibling::*[normalize-space()][1]",
                    $root, true, "/^\s*([A-Z\d ]{4,9}?)\s*(?:\,|$)/"))
                ->aircraft($this->http->FindSingleNode(".//*[{$this->eq($this->t('Aircraft Type:'))}]/following-sibling::*[normalize-space()][1]", $root), true, true);

            $cabin = $this->http->FindSingleNode(".//*[{$this->eq($this->t('Class of Travel:'))}]/following-sibling::*[normalize-space()][1]", $root);

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $seats = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Seats:')]/following::text()[normalize-space()][1]", $root, true, "/^([A-Z\s\,\d]+)$/");

            if (!empty($seats)) {
                $s->setSeats(explode(', ', $seats));
            }
        }

        return true;
    }

    private function parseHotels(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('Check in:'))}]/ancestor::*[{$this->contains($this->t('Hotel Confirmation #:'))}][1]/preceding::text()[normalize-space()][1]/ancestor::*[count(.//text()[{$this->eq($this->t('Check in:'))}]) = 1][1]";
        //$this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0 && $this->http->XPath->query("//text()[{$this->eq($this->t('Room Type:'))}]")->length > 0) {
            $email->add()->hotel();
            $this->logger->debug('contains hotels, but not found');
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            // General
            $confs = array_unique($this->http->FindNodes(".//*[{$this->eq($this->t('Hotel Confirmation #:'))}]/following-sibling::*[normalize-space()][1]", $root));

            if (empty($confs)) {
                $confs = array_unique($this->http->FindNodes(".//*[{$this->starts($this->t('Hotel Confirmation #:'))}]", $root, "/{$this->opt($this->t('Hotel Confirmation #:'))}\s*([A-Z\d]{6,})/"));
            }

            foreach ($confs as $conf) {
                $h->general()
                    ->confirmation($conf);
            }

            $travellers = preg_replace('/\s*\(\d+\)\s*$/', '',
                preg_split("/\s*,\s*/", implode(",", $this->http->FindNodes(".//*[{$this->eq($this->t('Guest Name(s):'))}]/following-sibling::*[normalize-space()][1]//descendant::text()[normalize-space()]", $root))));

            if (count(array_filter($travellers)) === 0) {
                $travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Account Ending:')]/preceding::text()[normalize-space()][1]");
            }

            if (count(array_filter($travellers)) > 0) {
                $h->general()
                    ->travellers($travellers);
            }

            $cancellation = array_unique($this->http->FindNodes(".//*[{$this->eq($this->t('Cancellation Policy:'))}]/following-sibling::*[normalize-space()][1]", $root));

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation(implode('. ', $cancellation));
            }

            // Hotel
            $info = implode("\n", $this->http->FindNodes(".//text()[{$this->eq($this->t('Check in:'))}]/preceding::text()[normalize-space()][1]/ancestor::*[count(.//text()[{$this->eq($this->t('Check in:'))}]) = 0][last()][not(.//text()[{$this->eq($this->t('Hotel Confirmation #:'))}])]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<name>.+)\n(?<address>[\s\S]+?)\n(?:Tel\:)?(?<phone>[\d\- \+\(\)]{5,})?\s*$/", $info, $m)) {
                $h->hotel()
                    ->name($m['name'])
                    ->address(preg_replace("/\s+/", ' ', $m['address']))
                    ->phone(trim($m['phone'] ?? ''));
            } else {
                $phone = $this->http->FindSingleNode("//@alt[{$this->eq(['Telephone Icon', 'Image removed by sender. Telephone Icon'])}]/ancestor::*[normalize-space()][1]");

                if (preg_match("/^\s*[\d\- \+\(\)]{5,}\s*$/", $phone, $m)) {
                    $h->hotel()
                        ->phone(trim($phone));
                }
                $info = implode("\n", $this->http->FindNodes("//@alt[{$this->eq(['Telephone Icon', 'Image removed by sender. Telephone Icon'])}]/ancestor::img[1]/preceding::text()[normalize-space()][1]/ancestor::*[normalize-space()][not(.//img)][last()]//text()[normalize-space()][not(contains(normalize-space(), 'Hotel Confirmation'))]"));

                if (empty($info)) {
                    $info = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Check in:'))}]/preceding::text()[normalize-space()][2][{$this->eq($this->t('Hotel Confirmation #:'))}]/preceding::text()[normalize-space()][1]/ancestor::*[not(.//text()[{$this->eq($this->t('Hotel Confirmation #:'))}])][not(.//img)][last()]//text()[normalize-space()][not(contains(normalize-space(), 'Hotel Confirmation'))]"));
                }

                if (preg_match("/^\s*(?<name>.+)\n(?<address>[\s\S]+?)$/", $info, $m)
                    && $this->http->XPath->query("//text()[{$this->eq($m['name'])}]")->length > 1
                ) {
                    $h->hotel()
                        ->name($m['name'])
                        ->address(preg_replace("/\s+/", ' ', $m['address']));
                }
            }

            // Booked
            $h->booked()
                ->checkIn(strtotime($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Check in:'))}]/ancestor::td[normalize-space()][1]", $root, true, "/:\s*(.+)/")))
                ->checkOut(strtotime($this->http->FindSingleNode(".//text()[{$this->eq($this->t('Check out:'))}]/ancestor::td[normalize-space()][1]", $root, true, "/:\s*(.+)/")))
            ;

            // Rooms
            $types = $this->http->FindNodes(".//*[{$this->eq($this->t('Room Type:'))}]/following-sibling::*[normalize-space()][1]", $root);

            foreach ($types as $type) {
                $h->addRoom()
                    ->setType($type);
            }

            $guests = array_sum($this->http->FindNodes(".//*[{$this->eq($this->t('Number of Guests:'))}]/following-sibling::*[normalize-space()][1]", $root));

            if (!empty($guests)) {
                $h->booked()
                    ->guests($guests);
            }

            $this->detectDeadLine($h);
        }

        return true;
    }

    private function parseRentals(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('Car Rental Confirmation #:'))}]/preceding::text()[normalize-space()][1]/ancestor::*[count(.//text()[{$this->eq($this->t('Car Rental Confirmation #:'))}]) = 1][1]";
        $this->logger->debug('$xpath = ' . print_r($xpath, true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = "//text()[{$this->starts($this->t('Car Rental Confirmation #:'))}]/ancestor::table[7]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length == 0 && $this->http->XPath->query("//text()[{$this->eq($this->t('Driver Name:'))}]")->length > 0) {
            $email->add()->rental();
            $this->logger->debug('contains rental, but not found');
        }

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            // General
            $conf = $this->http->FindSingleNode(".//*[{$this->eq($this->t('Car Rental Confirmation #:'))}]/following-sibling::*[normalize-space()][1]", $root);

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Car Rental Confirmation #:'))}][1]", $root, true, "/{$this->opt($this->t('Car Rental Confirmation #:'))}\s*([A-Z\d]+)$/");
            }

            $travellers = preg_split("/\s*,\s*/", implode(",", $this->http->FindNodes(".//*[{$this->eq($this->t('Driver Name:'))}]/following-sibling::*[normalize-space()][1]", $root)));

            if (empty($traveller)) {
                $travellers = [$this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Account Ending:')]/preceding::text()[normalize-space()][1]")];
            }
            $r->general()
                ->confirmation($conf)
                ->travellers($travellers);

            // Pick Up
            $r->pickup()
                ->date(strtotime($this->http->FindSingleNode(".//*[{$this->eq($this->t('Pick up Date:'))}]/following-sibling::*[normalize-space()][1]", $root)))
                ->location(implode(", ", $this->http->FindNodes(".//*[{$this->eq($this->t('Pick up Location:'))}]/following-sibling::*[normalize-space()][1]//text()[normalize-space()]", $root)));

            // Drop Off
            $r->dropoff()
                ->date(strtotime($this->http->FindSingleNode(".//*[{$this->eq($this->t('Drop Off Date:'))}]/following-sibling::*[normalize-space()][1]", $root)))
                ->location(implode(", ", $this->http->FindNodes(".//*[{$this->eq($this->t('Drop Off Location:'))}]/following-sibling::*[normalize-space()][1]//text()[normalize-space()]", $root)));

            // Car
            $carModel = $this->http->FindSingleNode(".//*[{$this->eq($this->t('Car Type:'))}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()][1]", $root);

            if (!empty($carModel)) {
                $r->car()
                    ->model($carModel);
            }

            $carType = $this->http->FindSingleNode(".//*[{$this->eq($this->t('Car Type:'))}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()][2]", $root);

            if (!empty($carType)) {
                $r->car()
                    ->type($carType);
            }
        }

        return true;
    }

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            // '€' => 'EUR',
            // '$' => 'USD',
            // '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return $s;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
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

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date) || empty($this->date)) {
            return null;
        }

        $in = [
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1 :43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return $date;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function detectDeadLine($h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
            preg_match("/Hotel cancellations made on or after (?<date>\d+\s*\w+\s*\d{4}) (?<time>[\d\:]+) \(property local time\)/", $cancellationText, $m)
        // There  is no charge for cancellations made before 18:00 (property local time) on May 3, 2024.
            || preg_match("/There is no charge for cancellations made before (?<time>[\d\:]+) \(property local time\) on (?<date>\w+\s*\w+\s*,?\s*\d{4})\./", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadline(strtotime($m['date'] . ', ' . $m['time']));
        }
    }
}
