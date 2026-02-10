<?php

namespace AwardWallet\Engine\auberge\Email;

use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "auberge/it-104282962.eml, auberge/it-141750203.eml, auberge/it-166651698.eml, auberge/it-198400618.eml, auberge/it-688394559.eml, auberge/it-85221835.eml, auberge/it-85460525.eml, auberge/it-86642184.eml, auberge/it-91712077.eml";
    public $subjects = [
        '/Auberge\D+\-\s*Reservation Confirmation \-/u',
    ];

    public $lang = 'en';
    public $subject;
    public $text;

    public static $dictionary = [
        "en" => [
            'RESERVATION INFORMATION' => ['RESERVATION INFORMATION', 'Reservation Details', 'RESERVATION'],
            'welcome'                 => ['We are delighted to welcome', 'We look forward to welcoming'],
            'checkInTime'             => ['Check-In Time', 'Check-In Time:', 'Check-In Time :', 'Check In:'],
            'checkOutTime'            => ['Check-Out Time', 'Check-Out Time:', 'Check-Out Time :', 'Check Out:'],
            'nameStart'               => ['Thank you for selecting', 'be staying with us at', 'you will be staying at', 'We are delighted to welcome you to', 'We look forward to welcoming you to', 'We are excited to welcome you to'],
            'nameEnd'                 => ['for your upcoming stay', 'Please review your booking details', 'during your exploration', 'and will be reaching', 'to experience a new generation', ', Auberge Resorts Collection, for a', ', Auberge Resorts Collection. Please take a moment to review', ';'],

            'Reservation #'             => ['Reservation #', 'Reservation Number', 'Confirmation Number', 'Confirmation Numb', 'Confirmation:'],
            'We very much look forward' => ['We very much look forward', 'We are delighted that you', 'Welcome to a'],
            'retreat,'                  => ['retreat,', 'at'],
            '. Below'                   => ['. Below', ', please'],
            'Cancellation:'             => ['Cancellation:', 'Cancellation Policy', 'Cancellations for travel dates', 'Deposit / Cancellation Policy', 'CANCELLATION POLICY', 'Reservation Policy:', 'Cancellation'],
            'Nightly Rate'              => ['Nightly Rate', 'Average Nightly Rate', 'Average Daily Rate'],
            'Total'                     => ['Total', 'Room Total - Excludes Taxes & Fees', 'Total with Taxes', 'Reservation Total', 'Grand Total*'],
            'Accommodations'            => ['Accommodations', 'Accommodation'],
            'Auberge'                   => ['Auberge', '@aubergeresorts'],
            'Adults'                    => ['Adults', 'adult(s)'],
            'Guest Name'                => ['Guest Name', 'Reserved For:'],
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        'phone'         => '[+(\d][-. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
        'travellerName' => '[[:alpha:]][-&.\'’[:alpha:] ]*[[:alpha:]]', // Mr. & Mrs. Marshall Heins
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@expertescapes.com') !== false) {
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
        return ($this->http->XPath->query("//text()[{$this->contains($this->t('Auberge'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('Drury Hotels'))}]")->length > 0)
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('RESERVATION INFORMATION'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('Hotel Information'))}]")->length > 0)
            && ($this->http->XPath->query("//text()[{$this->contains($this->t('Reservation #'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->contains($this->t('Cancellation Number'))}]")->length > 0);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]expertescapes\.com$/', $from) > 0;
    }

    public static function getEmailProviders()
    {
        return ['auberge', 'drury'];
    }

    public function ParseHotel(Email $email): void
    {
        $h = $email->add()->hotel();

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Guest Name')]/following::text()[normalize-space()][1]"))
            ->cancellation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Cancellations for travel dates')]/following::text()[normalize-space()][1]"));

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('welcome'))} and {$this->contains($this->t(' to '))}]", null, true, "/{$this->opt($this->t('welcome'))}[^;:!?]*?{$this->opt($this->t(' to '))}\s*(.{3,}?)[.;:!?]*$/");

        $h->hotel()
            ->name($name)
            ->address($this->http->FindSingleNode("//img[contains(@src, 'pin')]/following::text()[normalize-space()][1]/ancestor::tr[1]"))
            ->phone($this->http->FindSingleNode("//img[contains(@src, 'pin')]/following::text()[normalize-space()][1]/preceding::text()[string-length()>1][1]"));

        $inTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('checkInTime'))}]/following::text()[normalize-space()][1]", null, true, "/^{$this->patterns['time']}/");
        $outTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('checkOutTime'))}]/following::text()[normalize-space()][1]", null, true, "/^{$this->patterns['time']}/");

        if (preg_match("/\-\s*(?<month>\w+)\s(?<dayStart>\d+)\-(?<dayEnd>\d+)\,+\s*(?<year>\d{4})$/", $this->subject, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['dayStart'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $inTime))
                ->checkOut(strtotime($m['dayEnd'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $outTime));
        }

        $roomInfo = $this->re("/Deposit on File\s+(.+)Both rooms have been/s", $this->text);
        $rooms = preg_split("/\n\n/", $roomInfo);

        foreach ($rooms as $r) {
            if (stripos('Room Type', $r) !== false) {
                continue;
            } else {
                if (preg_match("/(?<type>\D+)\n(?<confirm>\d+)\n(?<rate>.+\sper\s*night).+/", $r, $m)) {
                    $h->general()
                        ->confirmation($m['confirm']);

                    $room = $h->addRoom();
                    $room->setType($m['type']);
                    $room->setRate($m['rate']);
                }
            }
        }
    }

    public function ParseHotel3(Email $email)
    {
        $h = $email->add()->hotel();

        $cancellation = implode(' ', $this->http->FindNodes("//tr/*[{$this->starts($this->t('Cancellation:'))}]/following-sibling::*[normalize-space()]/descendant::text()[normalize-space()]"));

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation:'))}]/following::text()[normalize-space()][1]");
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('RESERVATION INFORMATION'))}]/following::text()[{$this->starts($this->t('Cancellation:'))}][1]/following::text()[normalize-space()][1]");
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guarantee, Cancellation and Early Departure Policy'))}]/following::text()[{$this->contains($this->t('Cancellation within'))}][1]");
        }

        if (stripos($cancellation, 'refundable deposit at the time of booking') !== false && stripos($cancellation, 'non-refundable') == false) {
            $text = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'CANCELLATION POLICY')]/ancestor::tr[1]");
            $cancellation = $this->re("/{$this->opt($this->t('CANCELLATION POLICY'))}(.+){$this->opt($this->t('HOTEL INFORMATION'))}/", $text);
        }

        $confirmationText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation #'))}]/following::text()[normalize-space()][1]");

        if (stripos($confirmationText, 'and') !== false) {
            $confirmationArray = explode(' and ', $confirmationText);

            foreach ($confirmationArray as $confirmation) {
                $h->general()
                    ->confirmation($confirmation);
            }
        }

        $h->general()
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest Name'))}]/following::text()[normalize-space()][1]", null, true, "/^[:\s]*({$this->patterns['travellerName']})$/u"))
            ->cancellation($cancellation);

        // hard-code data base
        $hotelLogoFragments = [
            'Mauna Lani'             => ['ML'], // it-85221835.eml
            'Mayflower Inn & Spa'    => ['TM'], // it-85460525.eml
            'Commodore Perry Estate' => ['CPE'],
            'The Lodge at Blue Sky'  => ['LBS'],
        ];

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('welcome'))} and {$this->contains($this->t(' to '))}]", null, true, "/{$this->opt($this->t('welcome'))}[^;:!?]*?{$this->opt($this->t(' to '))}\s*(.{3,}?)[.;:!?]*$/");

        if (empty($name)) {
            $name = trim($this->http->FindSingleNode("//text()[{$this->starts($this->t('We very much look forward'))}]", null, true, "/{$this->opt($this->t('We very much look forward'))}.+{$this->opt($this->t('retreat,'))}\s*(.+){$this->opt($this->t('. Below'))}/"), ',');
        }

        if (empty($name)) {
            $hotelName_temp = $this->http->FindSingleNode("//text()[{$this->contains($this->t('nameStart'))} and {$this->contains($this->t('nameEnd'))}]", null, true, "/{$this->opt($this->t('nameStart'))}\s+(.{3,}?)\s+{$this->opt($this->t('nameEnd'))}/");

            if ($hotelName_temp && $this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 0) {
                $name = $hotelName_temp;
            }
        }

        if (empty($name)) {
            $hotelLogoFilename = $this->http->FindSingleNode("//tr[ *[2]/descendant::img[contains(@src,'ico_phone')] and *[3]/descendant::img[contains(@src,'ico_pin')] ]/*[1]/descendant::img[contains(@src,'.navisperformance.com/') and contains(@src,'/Images/')]/@src", null, true, "/\/Images\/(.+)\.[A-z\d]+/i")
                ?? $this->http->FindSingleNode("//td[ div[2]/descendant::img[contains(@src,'ico_phone')] and div[3]/descendant::img[contains(@src,'ico_pin')] ]/div[1]/descendant::img[contains(@src,'.navisperformance.com/') and contains(@src,'/Images/')]/@src", null, true, "/\/Images\/(.+)\.[A-z\d]+/i");

            if ($hotelLogoFilename) {
                foreach ($hotelLogoFragments as $hName => $hFragments) {
                    if (preg_match("/^{$this->opt($hFragments)}(?:[-_]|$)/i", $hotelLogoFilename)) {
                        $name = $hName;
                    }
                }
            }
        }

        $address = $this->http->FindSingleNode("//img[contains(@src, 'pin')]/following::text()[normalize-space()][1]/ancestor::tr[1]");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//a[contains(normalize-space(), 'www.lauberge.com')]/preceding::text()[normalize-space()][1]");
        }

        $phone = $this->http->FindSingleNode("//img[contains(@src,'pin')]/following::text()[normalize-space()][1]/preceding::text()[string-length()>1][1]", null, true, "/^{$this->patterns['phone']}$/");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//a[contains(normalize-space(),'www.lauberge.com')]/following::text()[normalize-space()][1]", null, true, "/^({$this->patterns['phone']})[|\s]*$/");
        }

        $h->hotel()
            ->name($name)
            ->address($address)
            ->phone(trim($phone, '|'));

        $inDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrival Date'))}]/following::text()[normalize-space()][1]", null, true, "/:?\s*(.{6,})/"));
        $inTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('checkInTime'))}]/following::text()[normalize-space()][1]", null, true, "/{$this->patterns['time']}/");

        if (empty($inTime)) {
            $inTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in Time is'))}]", null, true, "/{$this->opt($this->t('Check-in Time is'))}\s*({$this->patterns['time']})$/");
        }
        $outTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('checkOutTime'))}]/following::text()[normalize-space()][1]", null, true, "/{$this->patterns['time']}/");
        $outDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Departure Date'))}]/following::text()[normalize-space()][1]", null, true, "/:?\s*(.{6,})/"));

        $h->booked()
            ->checkIn(strtotime($inDate . ', ' . $inTime))
            ->checkOut(strtotime($outDate . ', ' . $outTime));

        $this->detectDeadLine($h, $h->getCancellation());

        $freeNight = 0;
        $totalArray = [];
        $currency = '';
        $ratesArray = array_values(array_filter(preg_split("/\n\n/", implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Nightly Rate')]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()]", null, "/\s(\D[\d\,\.]+)/")))));
        $roomsType = $this->http->FindNodes("//text()[{$this->starts($this->t('Room Type'))}]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()]");

        if (count($roomsType) > 0) {
            foreach ($roomsType as $i => $roomType) {
                $room = $h->addRoom();
                $room->setType($roomType);

                $rateArray = explode("\n", $ratesArray[$i]);

                foreach ($rateArray as $y => $rateItem) {
                    if (preg_match("/^(\D0\.00)$/", $rateItem)) {
                        $freeNight++;
                        unset($rateArray[$y]);
                    }
                }

                if (count(array_unique($rateArray)) == 1) {
                    $room->setRate($rateArray[0]);
                } else {
                    $room->setRates(explode("\n", $ratesArray[$i]));
                }

                if (!empty($freeNight)) {
                    $h->setFreeNights($freeNight);
                }

                $totalArray[] = str_replace(',', '', $this->http->FindSingleNode("//text()[normalize-space()='Total']/following::text()[{$this->starts($this->t($roomType))}]/following::text()[normalize-space()][1]", null, true, "/\D([\d\,\.]+)/u"));
                $currency = $this->http->FindSingleNode("//text()[normalize-space()='Total']/following::text()[{$this->starts($this->t($roomType))}]/following::text()[normalize-space()][1]", null, true, "/(\D)[\d\,\.]+/u");
            }
        }

        $h->price()
            ->total(array_sum($totalArray))
            ->currency($currency);
    }

    public function ParseHotel2(Email $email): void
    {
        $h = $email->add()->hotel();

        $cancellationNumber = $this->http->FindSingleNode("//text()[normalize-space() = 'Cancellation Number']/ancestor::tr[1]/descendant::td[2]");

        if (!empty($cancellationNumber)) {
            $h->general()
                ->cancellationNumber($cancellationNumber)
                ->cancelled();
        }

        $cancellation = implode(' ', $this->http->FindNodes("//tr/*[{$this->starts($this->t('Cancellation:'))}]/following-sibling::*[normalize-space()]/descendant::text()[normalize-space()]"));

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guarantee, Cancellation and Early Departure Policy'))}]/following::text()[{$this->contains($this->t('Cancellation within'))}][1]");
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancellation:'))}]/following::text()[normalize-space()][1][not(contains(normalize-space(), 'Pet Policy:'))]");
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('RESERVATION INFORMATION'))}]/following::text()[{$this->starts($this->t('Cancellation:'))}][1]/following::text()[normalize-space()][1][not(contains(normalize-space(), 'Pet Policy:'))]");
        }

        if (stripos($cancellation, 'refundable deposit at the time of booking') !== false && stripos($cancellation, 'non-refundable') == false) {
            $text = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'CANCELLATION POLICY')]/ancestor::tr[1]");
            $cancellation = $this->re("/{$this->opt($this->t('CANCELLATION POLICY'))}(.+){$this->opt($this->t('HOTEL INFORMATION'))}/", $text);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation #'))}]/following::text()[normalize-space()][1]", null, true, "/^[: ]*([-A-Z\d\s]{5,})[*]*$/");
        //if two and more confirmation number, example: Room 1: 2020067 Room 2: 2020068
        if (empty($confirmation)) {
            $confirmations = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation #'))}]/following::text()[normalize-space()][1]/ancestor::td[1]");

            if (preg_match_all("/\:\s*(\d+)/", $confirmations, $m)) {
                foreach ($m[1] as $confirmation) {
                    $h->general()
                       ->confirmation($confirmation);
                }
            }
        } else {
            if (!empty($confirmation)) {
                $h->general()
                    ->confirmation(str_replace(" ", "", $confirmation));
            }
        }
        $confirmation2 = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Online Locator Number:'))}]/following::text()[normalize-space()][1]", null, true, "/^[: ]*([-A-Z\d\s]{5,})[*]*$/");

        if (!empty($confirmation2)) {
            $h->general()
                ->confirmation($confirmation2, $this->http->FindSingleNode("//text()[{$this->eq($this->t('Online Locator Number:'))}]"));
        }

        if (empty($confirmation) && !empty($cancellationNumber)) {
            $h->general()
                ->noConfirmation();
        }

        $travellers = trim($this->http->FindSingleNode("//text()[{$this->starts($this->t('Guest Name'))}]/following::text()[normalize-space()][1][not(contains(normalize-space(), 'confirm'))]"), ':');

        if (stripos($travellers, '&') !== false) {
            $travellers = explode('&', $travellers);
            $h->general()
                ->travellers($travellers);
        } else {
            $h->general()
                ->traveller($travellers);
        }

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation(trim($cancellation, ':'));
        }

        // hard-code data base
        $hotelLogoFragments = [
            'Mauna Lani'             => ['ML'], // it-85221835.eml
            'Mayflower Inn & Spa'    => ['TM'], // it-85460525.eml
            'Commodore Perry Estate' => ['CPE'],
            'The Lodge at Blue Sky'  => ['LBS'],
            'The Vanderbilt'         => ['VAN'],
        ];

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t('welcome'))} and {$this->contains($this->t(' to '))}]", null, true, "/{$this->opt($this->t('welcome'))}[^;:!?]*?{$this->opt($this->t(' to '))}\s*(.{3,}?)[.;:!?]*$/");

        if (empty($name)) {
            $name = trim($this->http->FindSingleNode("//text()[{$this->starts($this->t('We very much look forward'))}]", null, true, "/{$this->opt($this->t('We very much look forward'))}.+{$this->opt($this->t('retreat,'))}\s*(.+){$this->opt($this->t('. Below'))}/"), ',');
        }

        if (empty($name)) {
            $hotelName_temp = $this->http->FindSingleNode("//text()[{$this->contains($this->t('nameStart'))} and {$this->contains($this->t('nameEnd'))}]", null, true, "/{$this->opt($this->t('nameStart'))}\s+(.{3,}?)\s*{$this->opt($this->t('nameEnd'))}/");

            if ($hotelName_temp && $this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 0) {
                $name = trim($hotelName_temp, '.');
            }
        }

        if (empty($name)) {
            $hotelLogoFilename = $this->http->FindSingleNode("//tr[ *[2]/descendant::img[contains(@src,'ico_phone')] and *[3]/descendant::img[contains(@src,'ico_pin')] ]/*[1]/descendant::img[contains(@src,'.navisperformance.com/') and contains(@src,'/Images/')]/@src", null, true, "/\/Images\/(.+)\.[A-z\d]+/i")
                ?? $this->http->FindSingleNode("//td[ div[2]/descendant::img[contains(@src,'ico_phone')] and div[3]/descendant::img[contains(@src,'ico_pin')] ]/div[1]/descendant::img[contains(@src,'.navisperformance.com/') and contains(@src,'/Images/')]/@src", null, true, "/\/Images\/(.+)\.[A-z\d]+/i");

            if ($hotelLogoFilename) {
                foreach ($hotelLogoFragments as $hName => $hFragments) {
                    if (preg_match("/^{$this->opt($hFragments)}(?:[-_]|$)/i", $hotelLogoFilename)) {
                        $name = $hName;
                    }
                }
            }
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'This email was sent by:')]", null, true, "/{$this->opt($this->t('This email was sent by:'))}\s*(.+)/");
        }

        if (empty($name)) {
            $infos = $this->http->FindNodes("//img[contains(@src, 'facebook')]/preceding::text()[normalize-space()][1]/ancestor::td[1][count(.//text()[normalize-space()]) = 3 or count(.//text()[normalize-space()]) = 4][contains(., ', Auberge Resorts Collection')]//text()[normalize-space()]");

            if (count($infos) == 4) {
                // Chileno Bay Resort & Residences, Auberge Resorts Collection
                // Carretera Transpeninsular San Jose-San Lucas Km.
                // 15 Playa Chileno Bay, 23410 Cabo San Lucas, B.C.S., Mexico
                // 844.207.9354
                unset($infos[1]);
                $infos = array_values($infos);
            }

            if (
                preg_match("/^.*, Auberge Resorts Collection\s*$/", $infos[0] ?? '')
                 && preg_match("/^\s*\d+.+$/", $infos[1] ?? '')
                 && preg_match("/^\s*[\d\.]+\s*$/", $infos[2] ?? '')
                 && strlen(preg_replace("/\D/", '', $infos[2] ?? '')) > 6
            ) {
                $name = $infos[0];
                $address = $infos[1];
                $phone = $infos[2];
            }
        }

        if (empty($name)) {
            $hotelText = $this->http->FindHTMLByXpath("(//td[not(.//td)][starts-with(normalize-space(), 'This email was sent by:')])[1]");
            $hotelText = html_entity_decode(strip_tags(preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $hotelText)));

            if (preg_match("/^\s*This email was sent by: *(.+)\n.+\n\s*© *20\d{2} *Auberge/", $hotelText, $m)) {
                $name = $m[1];
            }
        }

        if (empty($name)) {
            $name = $this->re("/Room Reservation Confirmation at\s*(.+)/", $this->subject);
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//img[contains(@src,'/ico_pin_')]/following::text()[normalize-space()][1]/ancestor::tr[1]");
        }

        if (empty($address) && $this->http->XPath->query("//text()[normalize-space()='YOUR JOURNEY BEGINS']")->length > 0) {
            $address = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'This email was sent by')]/following::text()[string-length()>10][1]");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//a[contains(normalize-space(), 'www.lauberge.com')]/preceding::text()[normalize-space()][1]");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'P ')]/preceding::text()[normalize-space()][1]");
        }

        if (empty($address)) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Privacy Policy']/preceding::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()]"));
            $address = $this->re("/This email was sent by.+\n+(.+)\n[©]*/", $hotelInfo);
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'This email was sent by:') and contains(normalize-space(), 'Auberge Stanly Ranch')]/following::text()[normalize-space()][1]");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//img[contains(@src, 'twitter')]/preceding::text()[contains(normalize-space(), 'bsk.reservations@aubergeresorts.com')][1]/preceding::text()[string-length()>5][1]");
        }

        if (empty($address) && !empty($name)) {
            $hotelNameFull = $this->http->FindSingleNode("//text()[contains(normalize-space(), ', Auberge Resorts Collection')]");

            if (preg_match("/$name/iu", $hotelNameFull)) {
                $address = $this->http->FindSingleNode("//text()[contains(normalize-space(), ', Auberge Resorts Collection')]/following::text()[normalize-space()][1]");
            }
        }

        if (empty($name) && !empty($address)) {
            $name = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'This email was sent by:')]/ancestor::td[1]", null, true, "/{$this->opt($this->t('This email was sent by:'))}(.+){$address}/");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//img[contains(@src,'/ico_pin_')]/following::text()[normalize-space()][1]/preceding::text()[string-length()>1][1]",
                null, true, "/^{$this->patterns['phone']}$/");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//a[contains(normalize-space(),'www.lauberge.com')]/following::text()[normalize-space()][1]", null, true, "/^({$this->patterns['phone']})[|\s]*$/");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'P ')]", null, true, "/^P\s*({$this->patterns['phone']})/");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//img[contains(@src, 'ico_phone')]/ancestor::td[normalize-space()][1]", null, true, "/^([\.\d]+)$/");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Should you have any additional questions regarding room reservations, or to begin planning your custom itinerary, please contact us at')]/following::text()[normalize-space()][1]", null, true, "/^([\.\d]+)$/");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Guest Name')]/preceding::text()[contains(normalize-space(), 'or call')]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('or call'))}\s*([\.\d]+)\./");
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//img/ancestor::a[contains(@href, 'tel')][1]/following::text()[normalize-space()][1]", null, true, "/^([\.\d]+)$/");
        }

        if (!empty($name)) {
            $hotelInfo = implode("\n", $this->http->FindNodes("//a[contains(@title, 'Facebook')]/preceding::text()[{$this->starts($name)}][1]/ancestor::table[1]/descendant::text()[normalize-space()]"));

            if (empty($hotelInfo)) {
                $hotelInfo = implode("\n", $this->http->FindNodes("//text()[normalize-space()='View In Browser']/following::text()[contains(normalize-space(), 'Resorts Collection')][last()]/ancestor::table[1]/descendant::text()[normalize-space()]"));
            }
        } else {
            $hotelInfo = implode("\n", $this->http->FindNodes("//a[contains(@title, 'Facebook')]/preceding::text()[normalize-space()][1]/ancestor::table[1]/descendant::text()[normalize-space()]"));
        }

        if (!empty($name) && empty($address) && preg_match("/^(?<name>{$name}.+)\n(?<address>.+(?:\n.+)?)\n+(?<phone>[\d\.]+)$/", $hotelInfo, $m)
        || preg_match("/^(?<name>.+)\n(?<address>.+)\n*(?<phone>[\d\.]+)?$/", $hotelInfo, $m)) {
            $name = $m['name'];
            $address = preg_replace('/\s+/', ' ', $m['address']);

            if (isset($m['phone']) && !empty($m['phone'])) {
                $phone = $m['phone'];
            }
        }

        if (preg_match("/^(.+){$this->opt($this->t('nameEnd'))}/", $name, $m)) {
            $name = $m[1];
        }

        if ($this->http->XPath->query("//text()[normalize-space()='Hotel Information']/preceding::text()[normalize-space()][1][starts-with(normalize-space(), 'Phone:')]")->length > 0) {
            $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Thank you for booking your stay at')]", null, true, "/{$this->opt($this->t('Thank you for booking your stay at'))}\s+(.+)\./");
            $address = $this->http->FindSingleNode("//text()[normalize-space()='Hotel Information']/preceding::text()[normalize-space()][1][starts-with(normalize-space(), 'Phone:')]/preceding::text()[normalize-space()][1]");
            $phone = $this->http->FindSingleNode("//text()[normalize-space()='Hotel Information']/preceding::text()[normalize-space()][1][starts-with(normalize-space(), 'Phone:')]", null, true, "/{$this->opt($this->t('Phone:'))}\s*([\d\-]+)/");
        }

        $h->hotel()
            ->name($name);

        if (!empty($address)) {
            $h->hotel()
                ->address($address);
        } else {
            $h->hotel()
                ->noAddress();
        }

        if (!empty($phone)) {
            $h->hotel()
                ->phone(trim($phone, '|'));
        }

        $inDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrival Date'))}]/following::text()[normalize-space()][1]", null, true, "/:?\s*(.{6,})/"));

        if (empty($inDate)) {
            $inDate = $this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hotel Information')]/following::text()[normalize-space()][{$this->starts($this->t('Arriving'))}][1]/following::text()[normalize-space()][1]", null, true, "/:?\s*(.{6,})/"));
        }

        if (!preg_match("/\d{4}\s*$/", $inDate)) {
            $inDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Arrival Date'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Arrival Date'))}:?\s*(.{6,})/"));
        }

        $inTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('checkInTime'))}]/following::text()[normalize-space()][1]", null, true, "/{$this->patterns['time']}/");

        if (empty($inTime)) {
            $inTime = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in Time is'))}]", null, true, "/{$this->opt($this->t('Check-in Time is'))}\s*({$this->patterns['time']})$/");
        }

        if (preg_match("/^\d+\s*a?p?m\.$/", $inTime)) {
            $inTime = str_replace('.', '', $inTime);
        }

        $outTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('checkOutTime'))}]/following::text()[normalize-space()][1]", null, true, "/{$this->patterns['time']}/");

        if (preg_match("/^\d{2}$/", $outTime)) {
            $outTime = $outTime . ':00';
        }
        $outDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Departure Date'))}]/following::text()[normalize-space()][1]", null, true, "/:?\s*(.{6,})/"));

        if (empty($outDate)) {
            $outDate = $this->normalizeDate($this->http->FindSingleNode("//node()[{$this->eq($this->t('Departure Date:'))}]/following::text()[normalize-space()][1]", null, true, "/:?\s*(.{6,})/"));
        }

        if (empty($outDate)) {
            $outDate = $this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Departing'))}]/following::text()[normalize-space()][1]", null, true, "/:?\s*(.{6,})/"));
        }

        if (empty($inDate) && empty($inTime)) {
            if (!empty($startDate = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Nightly Rate')]/ancestor::td[1]/following::td[1]/descendant::text()[normalize-space()][1]", null, true, "/^(\d{1,2}\/\d{1,2}\/\d{1,2})\s/"))) {
                $nightCount = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Number of Nights')]/ancestor::td[1]/following::td[1]/descendant::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

                if (!empty($nightCount)) {
                    $nightCount = $nightCount > 1 ? $nightCount - 1 : $nightCount;

                    $inDate = $startDate;
                    $inTime = '0:00';
                    $outDateTime = strtotime('+' . $nightCount . ' days', strtotime($startDate . ', 0:00'));

                    $h->booked()
                        ->checkIn(strtotime($inDate . ', ' . $inTime))
                        ->checkOut($outDateTime);
                }
            }
        } else {
            $h->booked()
                ->checkIn(strtotime($inDate . ', ' . $inTime))
                ->checkOut(strtotime($outDate . ', ' . $outTime));
        }

        $this->detectDeadLine($h, $h->getCancellation());

        $roomType = str_replace([$this->t('Room Type'), ':'], "", $this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Type'))}]/following::text()[normalize-space()][1]/ancestor::td[1]", null, true, "/\:?\s*(.+)/"));

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Accommodations'))}]/following::text()[normalize-space()][1]/ancestor::td[1]");
        }

        if (empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your Room:'))}]/following::text()[normalize-space()][1]");
        }

        $rateType = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Rate Info'))}]/following::text()[normalize-space()][1]", null, true, "/\:?\s*(.+)/");
        $rate = implode('; ', $this->http->FindNodes("//tr/*[{$this->starts($this->t('Nightly Rate'))}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]"));

        if (empty($rate)) {
            $rate = implode('; ', $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Nightly Rate')]/following::*[normalize-space()][1]/descendant::text()[normalize-space()]"));
        }

        if (!$rate) {
            $rate = $this->http->FindSingleNode("//tr/*[ descendant::text()[normalize-space()][1][{$this->eq($this->t('Rate'))}] ]/following-sibling::*/descendant::text()[normalize-space()][1]", null, true, "/^(.+?\s*{$this->opt($this->t('per night'))})/i");
        }
        $description = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Room Description'))}]/following::text()[normalize-space()][1]", null, true, "/\:?\s*(.+)/");

        if (!empty($roomType) || !empty($rateType) || !empty($rate) || !empty($description)) {
            $room = $h->addRoom();

            if (!empty($roomType)) {
                $room->setType($roomType);
            }

            if (!empty($rateType)) {
                $room->setRateType($rateType);
            }

            if (!empty($rate)) {
                $room->setRate(str_replace('Average Nightly Rate (including resort fee);', '', $rate));
            }

            if (!empty($description)) {
                $room->setDescription($description);
            }
        }

        $totalPrice = 0;

        $total = implode(', ', $this->http->FindNodes("//text()[starts-with(normalize-space(.), 'Total*')]/ancestor::td[1]/following::td[1]/descendant::text()[contains(normalize-space(), '*')]"));

        if (preg_match_all("/\:\s*(\D)([\d\.\,]+)/", $total, $m)) {
            $total = str_replace(',', '', $m[2]);
            $totalPrice = $m[1][0] . '' . array_sum($total) . '*';
        }

        if (empty($totalPrice)) {
            $totalPrice = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total'))}]/following::text()[normalize-space()][1]", null, true, "/(?:^|:\s*)([^:]*\d[^:]*)$/");
        }

        if ($totalPrice === null) {
            $totalPrice = $this->http->FindSingleNode("//tr/*[ descendant::text()[normalize-space()][1][{$this->starts($this->t('Total'))}] ]/following-sibling::*/descendant::text()[normalize-space()][1]", null, true, "/(?:^|:\s*)([^:]*\d[^:]*)$/");
        }

        if ($totalPrice === null) {
            $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/(?:^|:\s*)([^:]*\d[^:]*)$/");
        }

        if (preg_match('/^(?<currency>[^\d)(]+?)[* ]*(?<amount>\d[,.\'\d ]*)[ *]*$/', $totalPrice, $matches)
        || preg_match("/^\D(?<amount>\d[,.\'\d ]*)[ *]*(?<currency>[A-Z]{3})$/", $totalPrice, $matches)) {
            // $1,459.00*    |    $*4,765.17
            $h->price()
                ->currency($this->normalizeCurrency($matches['currency']))
                ->total($this->normalizeAmount($matches['amount']));

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Stay Amount'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/(?:^|:\s*)\D([^:]*\d[^:]*)$/");

            if (stripos($cost, '*') !== false
                && $this->http->XPath->query("//text()[normalize-space()='*Total excludes tax and service.']")->length > 0) {
                $h->price()
                    ->total($this->normalizeAmount(trim($cost, '*')));
            } elseif (!empty($cost)) {
                $h->price()
                    ->cost($this->normalizeAmount($cost));
            }

            $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/(?:^|:\s*)\D([^:]*\d[^:]*)$/");

            if (!empty($tax)) {
                $h->price()
                    ->tax($this->normalizeAmount($tax));
            }
        }

        $guestsText = $this->http->FindSingleNode("//text()[normalize-space()='Number of Guests']/ancestor::tr[1]/descendant::td[2]");

        if (preg_match("/^(?<adults>\d+)\s*{$this->opt($this->t('Adults'))}[\s\|]+(?:and\s*)?(?<kids>\d+)\s*{$this->opt($this->t('Children'))}$/iu", $guestsText, $m)) {
            $h->booked()
                ->guests($m['adults'])
                ->kids($m['kids']);
        }

        if (empty($h->getGuestCount())) {
            $guests = $this->http->FindSIngleNode("//text()[normalize-space()='Adults']/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

            if (!empty($guests)) {
                $h->booked()
                    ->guests($guests);
            }

            $kids = $this->http->FindSIngleNode("//text()[normalize-space()='Children']/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

            if ($kids !== null) {
                $h->booked()
                    ->kids($kids);
            }
        }

        $accounts = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Drury Rewards:')]/following::text()[normalize-space()][1]", null, true, "/^(\d+)$/");

        if (!empty($accounts)) {
            $h->addAccountNumber($accounts, false);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();
        $this->text = $parser->getBody();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Drury Hotels'))}]")->length > 0) {
            $email->setProviderCode('drury');
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Deposit on File')]")->length > 0) {
            $this->logger->debug('Hotel type: 1');
            $this->ParseHotel($email);
        } elseif ($this->http->XPath->query("//text()[contains(normalize-space(), 'Reservation Numbers')]/ancestor::tr[1]/descendant::td[contains(normalize-space(), 'and')]")->length > 0) {
            $this->logger->debug('Hotel type: 3');
            $this->ParseHotel3($email);
        } else {
            $this->logger->debug('Hotel type: 2');
            $this->ParseHotel2($email);
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function detectDeadLine(Hotel $h, $cancellationText): void
    {
        if (preg_match("/Revisions (?i)or cancell?ations must be made\s+(?<prior>\d{1,3}\s*days?)\s+prior to the scheduled arrival date in order to receive a full refund/", $cancellationText, $m)
            || preg_match("/If (?i)your reservation is cancell?ed outside of\s+(?<prior>\d{1,3}\s*days?)\s+prior to arrival, your deposit will be refunded to the credit card on file\./", $cancellationText, $m)
            || preg_match("/Should you wish to cancel your reservation, please do so more than (?<prior>\d{1,3}\s*days)\s+prior to your arrival/", $cancellationText, $m)
            || preg_match("/Cancell?ations (?i)for bookings must be received by\s+(?<hour>{$this->patterns['time']})\s+[[:alpha:]]+ time at least\s+(?<prior>\d{1,3}\s*days?)\s+prior to expected arrival date\./", $cancellationText, $m)
            || preg_match("/(?<prior>\d+ day) cancel policy \(non\-refundable within 14 days\)/", $cancellationText, $m)
            || preg_match("/contact us at least (?<prior>\d+ days) in advance for a full refund of your deposit./", $cancellationText, $m)
            || preg_match("/Any cancellation made within (?<prior>\d+ days) prior to arrival is subject to a 100% penalty, inclusive of taxes./", $cancellationText, $m)
            || preg_match("/Reservations cancelled less than (?<prior>\d+ days) before arrival will forfeit 100[%] of booking/", $cancellationText, $m)
        ) {
            $hour = empty($m['hour']) ? null : $m['hour'];
            $h->booked()->deadlineRelative($m['prior'], $hour);
        }

        if (preg_match("#All advance purchase reservations are non-refundable#i", $cancellationText, $m)
        || preg_match("#No cancellations, refunds, or modifications allowed#i", $cancellationText, $m)
        || preg_match("#You have selected our Advance Purchase option that cannot be modified or refunded#i", $cancellationText, $m)
        ) {
            $h->booked()
                ->nonRefundable();
        }

        if (preg_match("#For all reservations cancelled within (\d+) days prior to arrival#i", $cancellationText, $m)
        || preg_match("#Reservation must be cancelled (\d+) days prior to arrival to avoid full forfeiture#i", $cancellationText, $m)
        || preg_match("#Reservations canceled within (\d+) days of the arrival date will forfeit the full reservation amount#i", $cancellationText, $m)
        || preg_match("#Reservations require a two night deposit of total lodging cost, including tax, at time of booking and are subject to a forfeiture of entire stay (\d+) days prior to arrival. Full and final payment, including tax, is due 21 days prior to scheduled arrival date. Reservation is non refundable within 21 days prior to arrival.#i", $cancellationText, $m)
        || preg_match("#Cancellation made within (\d+) days prior to arrival is subject to forfeiture of the deposit amount.#i", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . 'day');
        }

        if (preg_match("/If your plans change and you need to cancel, please do so by (?<hour>\d+\:\d+(?:a|p\.m\.)) the day prior to arrival to avoid/", $cancellationText, $m)) {
            $m['hour'] = str_replace('.', '', $m['hour']);
            $h->booked()->deadlineRelative('1 day', $m['hour']);
        }

        if (preg_match("/Reservations canceled after (?<hour>\d+a?p?m) on day of arrival will incur a one night room and tax penalty/", $cancellationText, $m)) {
            $m['hour'] = str_replace('.', '', $m['hour']);
            $h->booked()->deadlineRelative('0 day', $m['hour']);
        }
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        $this->logger->debug($text);

        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // Monday, May 17, 2021
            '/^[[:alpha:]]{2,}\s*,\s*([[:alpha:]]{3,})\s+(\d{1,2})\s*,\s*(\d{2,4})$/u',
            // 08-22-22
            '/^\s*(\d{2})-(\d{2})-(\d{2})\s*$/',
        ];
        $out = [
            '$2 $1 $3',
            '20$3-$1-$2',
        ];

        return preg_replace($in, $out, $text);
    }

    private function normalizeCurrency(?string $string): ?string
    {
        if (empty($string)) {
            return null;
        }
        $string = trim($string);
        $currencies = [
            '$'   => ['$'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'AUD' => ['AU $'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
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
}
