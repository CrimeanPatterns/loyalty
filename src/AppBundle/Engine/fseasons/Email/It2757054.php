<?php

namespace AwardWallet\Engine\fseasons\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2757054 extends \TAccountChecker
{
    public $mailFiles = "fseasons/it-2757054.eml, fseasons/it-1645038.eml, fseasons/it-1645216.eml, fseasons/it-2965099.eml, fseasons/it-8383505.eml, fseasons/it-16672857.eml";

    private $subjects = [
        'en' => ['Change confirmed -', 'Confirmation -', 'Preferences Saved -'],
    ];

    private $prov = "Four Seasons";

    private $detects = [
        'Four Seasons Hotels and Resorts, All rights reserved',
        'Reservation Confirmation',
    ];

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect) || $this->http->XPath->query('//node()[contains(normalize-space(),"' . $detect . '")]')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]fourseasons\.com/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }
        $email->setType('Reservations');
        $this->parseEmail($email);

        return $email;
    }

    private function parseEmail(Email $email): void
    {
        $xpath = "//*[contains(text(), 'Reservation #:')]/ancestor::table[2]/..";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length == 0) {
            $this->logger->debug("segments not found: {$xpath}");
        }

        // Parse Rooms
        foreach ($segments as $root) {
            $h = $email->add()->hotel();

            // ConfirmationNumber
            if ($conf = $this->http->FindSingleNode(".//*[contains(text(), 'Reservation #:')]", $root, true, "#Reservation\s*\#:\s+(\d+)#")) {
                $h->general()
                    ->confirmation($conf);
            } elseif ($conf = $this->http->FindSingleNode(".//*[contains(text(), 'Reservation #:')]/following::text()[string-length(normalize-space(.))>2][1]", $root, true, "#[ ]*(\d+)#")) {
                $h->general()
                    ->confirmation($conf);
            }

            $xpathHotel = "//tr[not(.//tr) and contains(.,'Reservation') and contains(.,'Confirmation')]/ancestor::table[1]/ancestor::*[ following-sibling::div[normalize-space()] ][1]/following-sibling::div[normalize-space()][1]";

            // Hotel Name
            if ($hotel = trim($this->http->FindSingleNode($xpathHotel . '//a[1]'))) {
                $h->hotel()
                    ->name($hotel);
            }

            // CheckInDate
            if (
                $checkin = strtotime(
                $this->http->FindSingleNode(".//*[contains(text(), 'Arrival')]/ancestor::td[1]", $root, true, "#Arrival:\s*(.*?)\s*CHECK IN#msi") . ', ' .
                $this->http->FindSingleNode(".//*[contains(text(), 'Arrival')]/ancestor::td[1]", $root, true, "#CHECK IN:\s+(\d+:\d+ [APM]+)#msi")
                )
            ) {
                $h->booked()
                    ->checkIn($checkin);
            }

            // CheckOutDate
            if (
                $checkout = strtotime(
                $this->http->FindSingleNode(".//*[contains(text(), 'Departure')]/ancestor::td[1]", $root, true, "#Departure:\s*(.*?)\s*CHECK OUT#msi") . ', ' .
                $this->http->FindSingleNode(".//*[contains(text(), 'Departure')]/ancestor::td[1]", $root, true, "#CHECK OUT:\s+(\d+:\d+ [APM]+)#msi")
                )
            ) {
                $h->booked()
                    ->checkOut($checkout);
            }

            // Address
            $node = implode("\n", $this->http->FindNodes($xpathHotel . "//text()[normalize-space()]"));

            if (preg_match("#\s*.+\n([\s\S]+?)\s+(?:Tel\.|E\-mail\s*\:)#", $node, $m)) {
                $h->hotel()
                    ->address(preg_replace("#\s*\n\s*#", ', ', trim($m[1])));
            }

            // Phone
            if ($phone = trim($this->http->FindSingleNode($xpathHotel, null, true, "#Tel\.\s*([+(\d][-. \d)(]{5,}[\d)])\s*[A-z]#s"))) {
                $h->hotel()
                    ->phone($phone);
            }

            // Fax
            if ($fax = trim($this->http->FindSingleNode($xpathHotel, null, true, "#Fax\.\s*([+(\d][-. \d)(]{5,}[\d)])\s*[A-z]#s"))) {
                $h->hotel()
                    ->fax($fax);
            }

            // Guests
            $guest = implode(', ', $this->http->FindNodes(".//td[contains(normalize-space(.), 'Guests')]/descendant::text()[normalize-space(.)][not(contains(normalize-space(.), 'Guests')) and not(contains(normalize-space(.), 'adult')) and not(contains(normalize-space(.), 'child'))]", $root));
            $guest = preg_replace('/\s+and\s+/i', ', ', $guest);

            $ps = preg_split('/\s*,\s*/', $guest);

            foreach (array_filter($ps) as $p) {
                $h->addTraveller($p);
            }

            if ($adult = $this->http->FindSingleNode(".//td[contains(normalize-space(.), 'Guests')]", $root, true, '/(?:\D|\b)(\d{1,3}) adult(?:s)?/i')) {
                $h->booked()->guests($adult);
            }

            if ($child = $this->http->FindSingleNode(".//td[contains(normalize-space(.), 'Guests')]", $root, true, '/(?:\D|\b)(\d{1,3}) child(?:een)?/i')) {
                $h->booked()->kids($child);
            }

            // Rate
            $room = $h->addRoom();

            if ($rate = $this->getTd($root, 'Average Rate')) {
                $room->setRate($rate);
            }

            // CancellationPolicy
            $CancellationPolicy = $this->http->FindSingleNode("(//*[contains(normalize-space(.), 'Guarantee, deposit and cancellation policies')]/following-sibling::*[name()='p' or name()='div'][1][string-length(normalize-space(.))>2])[1]");
            $CancellationPolicy = explode('.', $CancellationPolicy);
            $r = [];

            foreach ($CancellationPolicy as $c) {
                if (stripos($c, 'cancel')) {
                    $r[] = $c;
                }
            }
            $h->general()->cancellation(trim(implode('.', $r), '.'), true, true);

            if (!empty($h->getCancellation())) {
                $this->detectDeadline($h, $h->getCancellation());
            }

            // RoomType
            $subj = $this->http->FindSingleNode("descendant::*[(self::b or self::strong) and contains(normalize-space(),'Room Details')]/following::text()[normalize-space()][1]/ancestor::a[1]", $root, true, '/(.+)\s+with/i');

            if (!$subj) {
                $xpath = "//text()[contains(., 'Room Details:')]/ancestor::font[1]/following-sibling::font[1]";
                $subj = implode("|", $this->http->FindNodes($xpath));
            }

            if (!$subj) {
                $xpath = "//text()[contains(., 'Room Details:')]/ancestor::strong[1]/following-sibling::a[1]";
                $subj = implode("|", $this->http->FindNodes($xpath));
            }

            if (!$subj) {
                $xpath = "//text()[contains(., 'Room Details:')]/ancestor::strong[1]/following-sibling::span[1]/descendant::a[1]";
                $subj = implode("|", $this->http->FindNodes($xpath));
            }

            if ($subj) {
                $room->setType($subj);
            }

            // RoomTypeDescription
            if ($rDesc = implode(", ", $this->http->FindNodes(".//*[contains(text(), 'Room Details')]/following-sibling::a", $root))) {
                $room->setDescription($rDesc);
            }

            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            if ($st = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(.), 'Status')][1]", $root, true, '/Status\s*:\s*(\w+)/')) {
                $h->general()->status($st);
            }
            // Cancelled
            // ReservationDate
            // NoItineraries
        }
        // Cost
        if ($cost = $this->getTd(null, 'Total Room Rates', '/([\d\.,]+)/', true)) {
            $email->price()->cost(str_replace(',', '', $cost));
        }
        // Taxes
        // Total
        if ($total = $this->getTd(null, 'Estimated total, including tax and service charges*', '/([\d\.,]+)/', true)) {
            $email->price()->total(str_replace(',', '', $total));
        }
        // Currency
        if ($cur = $this->getTd(null, 'Estimated total, including tax and service charges*', '/([A-Z]{3})\s*[\d\.,]+/', true)) {
            $email->price()->currency($cur);
        }
    }

    private function detectDeadline(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText): void
    {
        if (preg_match('/All cancellations must be received at least (\d{1,2} days) prior to expected arrival/i', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m[1]);
        } elseif (preg_match('/All cancellations and changes must be made by (\d{1,2}:\d{2} [ap]m) San Francisco time on the day prior to expected arrival/i', $cancellationText, $m)) {
            $h->booked()->deadlineRelative('1 day', $m[1]);
        } elseif (preg_match('/All cancellations(?: and changes)? must be received by (?<hour>\d{1,2}:\d{2} [pa]m) [\w ]+ time at least (?<prior>\d{1,3} (?:hours?|days?)) prior to expected arrival/i', $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m['prior'] . ' -1 day', $m['hour']);
        }
    }

    private function getTd(?\DOMNode $node, string $s, ?string $re = null, bool $general = false): ?string
    {
        if ($general) {
            return $this->http->FindSingleNode("//td[normalize-space(.)='{$s}' and not(.//td)][1]/following-sibling::td[string-length(normalize-space(.))>2][1]", null, true, $re);
        } else {
            return $this->http->FindSingleNode("(following::td[normalize-space(.)='{$s}' and not(.//td)][1]/following-sibling::td[string-length(normalize-space(.))>2][1])[1]", $node, true, $re);
        }
    }
}
