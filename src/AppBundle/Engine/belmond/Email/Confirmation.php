<?php

namespace AwardWallet\Engine\belmond\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "belmond/it-122861684.eml, belmond/it-19519017.eml, belmond/it-20034611.eml, belmond/it-22526066.eml, belmond/it-36336915.eml, belmond/it-40985749.eml, belmond/it-41231563.eml, belmond/it-41545998.eml";
    public $reBody = [
        'en'  => ['YOUR ACCOMMODATION', 'Check out:'],
        'en2' => ['YOUR ACCOMMODATION', 'THANK YOU FOR BOOKING WITH US'],
        'en3' => ['THE ACCOMMODATION', 'THANK YOU FOR BOOKING WITH US'],
        'en4' => ['YOUR ACCOMMODATION', 'THANK YOU FOR YOUR REQUEST'],
        'en5' => ['YOUR ACCOMMODATION', 'BOOKING NO'],
        'en6' => ['THANK YOU FOR BOOKING WITH US', 'GENERAL INFORMATION'],
    ];

    public $lang = '';
    public static $dict = [
        'en' => [
            'Confirmation number:'      => ['Confirmation number:', 'Reservation number:', 'BOOKING NO'],
            'Nightly rate excl. taxes:' => [
                'Nightly rate excl. taxes:',
                'Average room rate:',
                'Daily room rate:',
                'Nightly rate:',
                'DAILY RATES',
                'Rate per night excl. taxes:',
            ],
            'Guest name:'                 => ['Guest name:', 'Guest Name:', 'NAME'],
            'Check in:'                   => ['Check in:', 'CHECK IN'],
            'Check out:'                  => ['Check out:', 'CHECK OUT'],
            'Room total:'                 => ['Room total:', 'ROOM TOTAL', 'ACCOMMODATION TOTAL'],
            'Tax / service charge total:' => [
                'Tax / service charge total:', 'TAX / SERVICE CHARGE TOTAL:',
                'Tax and Service Charge:', 'TAX AND SERVICE CHARGE:',
                'ACCOMMODATION TAX TOTAL',
                'TAX and fees TOTAL', 'TAX AND FEES TOTAL',
            ],
            'GRAND TOTAL:'         => ['GRAND TOTAL', 'GRAND TOTAL:'],
            'Number of adults:'    => ['Number of adults:', 'GUESTS'],
            'Cancellation policy:' => ['Cancellation policy:', 'Cancellation'],
            'Room:'                => ['Room:', 'ROOM'],
            'Rate:'                => ['Rate:', 'RATE'],
        ],
    ];

    private $subjects = [
        'en' => ['Confirmation #', 'Reservation confirmation from', 'Confirmation -'],
    ];

    private $patterns = [
        'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $plain = $parser->getPlainBody();

        if (!empty($plain) && count(array_filter(array_map("trim", explode("\n", $plain)))) > 30) {
            $text = $plain;
        } else {
            $text = $parser->getHTMLBody();
        }

        // it-40985749.eml
        if (preg_match('/We are pleased to confirm the following TWO reservations at/', $text)) {
            $this->parseEmailPlain($email, $text);
        } elseif ($this->http->XPath->query("//text()[normalize-space()='Check in:']/ancestor::tr[contains(.,'YOUR ACCOMMODATION')][1][count(./descendant::text()[contains(.,'YOUR ACCOMMODATION')])=1]")->length
            == $this->http->XPath->query("//text()[normalize-space()='Check in:']/ancestor::tr[contains(.,'YOUR ACCOMMODATION')][1]")->length
        ) {
            $this->parseEmail($email);
        } else {
            // it-40985749.eml
            $text = $this->clearText($text);
            $this->parseEmailPlain($email, $text);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query('//a[contains(@href,"//www.belmond.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(.),"Copyright © Belmond") or contains(.,"www.belmond.com") or contains(.,"@belmond.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'],
                'Belmond') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@belmond.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmailPlain(Email $email, $text): void
    {
        $this->logger->debug(__FUNCTION__);

        $contacts = implode("\n",
            $this->http->FindNodes("(//text()[{$this->eq($this->t('CONNECT'))}]/ancestor::td[1]//a[{$this->contains(['location_and_map', 'location%5fand%5fmap', 'dspl%5faddress', 'cip%5faddress'], '@href')}])[1]/descendant::text()"));

        if (preg_match("#^\s*(?<name>.{3,}?),?\n(?<address>[\s\S]{3,})#", $contacts, $m)) {
            $hotelName_global = rtrim($m['name'], " ,\r\n");
            $address_global = trim(preg_replace('/\s+/', ' ', $m['address']));
        } elseif (preg_match("#\r?\n\r?\n[ ]*CONNECT[ ]*\r?\n(?<name>.{3,})\r?\n(?<address>(?:.{3,}\r?\n){1,3})\r?\n#",
            $text, $m)) {
            $hotelName_global = rtrim($m['name'], " ,\r\n");
            $address_global = trim(preg_replace('/\s+/', ' ', $m['address']));
        }
        $phone_global = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('CONNECT'))}]/following::img[contains(@alt,'Twitter')]/preceding::text()[normalize-space()][2])[1]",
            null, false, "#^[+(\d][-. \d)(]{5,}[\d)]$#");
        $fax_global = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('CONNECT'))}]/following::img[contains(@alt,'Twitter')]/preceding::text()[normalize-space()][1])[1]",
            null, false, "#^[+(\d][-. \d)(]{5,}[\d)]$#");

        if ($str = strstr($text, 'GUARANTEE AND CANCELLATION POLICY', true)) {
            $text = $str;
        }

        if (preg_match_all("#{$this->opt($this->t('Guest name:'))}[ ]*(.+)#", $text, $guestMatches) && count($guestMatches[1]) === 1) {
            $guestName = array_shift($guestMatches[1]);
        }

        $reservations = $this->splitter("#\n(.+\s+{$this->opt($this->t('Check in:'))})#", $text);
        //$this->logger->debug(var_export($reservations,true));
        foreach ($reservations as $reservation) {
            $h = $email->add()->hotel();
            $hotelName = $this->re("#reservations at\s+(.+)#", $reservation);

            if (!$hotelName) {
                $hotelName_temp = $this->re("#(.+)#", $reservation);

                if ($hotelName_temp && stripos($hotelName_temp, 'YOUR RESERVATION') === false) {
                    $hotelName = $hotelName_temp;
                }
            }

            if ($hotelName && isset($hotelName_global) && strcasecmp($hotelName, $hotelName_global) !== 0) {
                $h->hotel()
                    ->name($hotelName)
                    ->noAddress();
            } elseif (isset($hotelName_global) && isset($address_global)) {
                $h->hotel()
                    ->name($hotelName ?? $hotelName_global)
                    ->address($address_global)
                    ->phone($phone_global)
                    ->fax($fax_global);
            }

            $pax = $this->re("#{$this->opt($this->t('Guest name:'))}\s*([\w\s.]+)\n#u", $reservation);

            if (!empty($pax)) {
                $h->general()
                    ->traveller($pax);
            } elseif (isset($guestName)) {
                $h->general()
                    ->traveller($guestName);
            }

            $conf = $this->re("#{$this->opt($this->t('Confirmation number:'))}\s*(\w+)#", $reservation);

            if (preg_match('/(\d+)\s*\((\d+)\)/', $conf, $m)) {
                $h->general()
                    ->confirmation($m[1])
                    ->confirmation($m[2]);
            } elseif (preg_match('/(\d+)/', $conf)) {
                $h->general()
                    ->confirmation($conf);
            } else {
                $h->general()
                    ->noConfirmation();
            }

            if (!empty($status = $this->re("#{$this->opt($this->t('Status:'))}[ ]*(.+)#", $reservation))) {
                $h->general()
                    ->status($status);
            }
            $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('GRAND TOTAL:'))}[ ]*(.+)#", $reservation));

            if (!empty($tot['Total'])) {
                $h->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
            $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Room total:'))}[ ]*(.+)#", $reservation));

            if (!empty($tot['Total'])) {
                $h->price()
                    ->cost($tot['Total'])
                    ->currency($tot['Currency']);
            }
            $tot = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Tax / service charge total:'))}[ ]*(.+)#i",
                $reservation));

            if (!empty($tot['Total'])) {
                $h->price()
                    ->tax($tot['Total'])
                    ->currency($tot['Currency']);
            }

            $guests = $this->re("#{$this->opt($this->t('Number of guests:'))}\s*(\d+)#", $reservation);

            if (!empty($guests)) {
                $h->booked()
                    ->guests($guests);
            }

            // 22nd July 2019
            $h->booked()
                ->checkIn2($this->re('#^(?:NOW)?\s*(.{6,})#i',
                    $this->re("#{$this->opt($this->t('Check in:'))}[ ]*([\w\s]+)\n#", $reservation)))
                ->checkOut2($this->re('/(.{6,}?)(?:\(|$)/',
                    $this->re("#{$this->opt($this->t('Check out:'))}[ ]*([\w\s]+)\s+(:?\()?#", $reservation)));

            $node = $this->re("#{$this->opt($this->t('Check in/out times:'))}\s*([\w\s\d:.,\-]+)\n#", $reservation);

            if ($node) {
                $this->parseTime($h, $node);
            }

            // cancellation
            if ($h->getHotelName() && !empty($cancellation = $this->http->FindSingleNode("//text()[normalize-space()='GUARANTEE AND CANCELLATION POLICY']/following::text()[{$this->eq($h->getHotelName())}]/ancestor::*[./following-sibling::*[normalize-space()!='']][1]/following-sibling::*[1]"))) {
                $h->general()->cancellation($cancellation);
            }

            // deadline
            $this->detectDeadLine($h);

            $rooms = $this->splitter("#\n([ ]*{$this->opt($this->t('Room:'))})#", $reservation);

            if (count($rooms) > 1) {
                foreach ($rooms as $roomRoot) {
                    $room = $h->addRoom();
                    // TODO: maybe can take fe:  it-36336915.eml
                }
            } else {
                $roomStr = $this->re("#^[ ]*{$this->opt($this->t('Room:'))}\s*(\S[\w\s,.]+)\n#mu", $reservation);

                if ($roomsCount = $this->re('/^\s*(\d+)\s+/', $roomStr)) {
                    $h->setRoomsCount($roomsCount);
                }

                $room = $h->addRoom();
                $room
                    ->setRate($this->re("#{$this->opt($this->t('Nightly rate excl. taxes:'))}\s*(.*\d.*)#",
                        $reservation), false, true)
                    ->setType($roomStr)
                    ->setRateType($this->http->FindPreg("#^[ ]*{$this->opt($this->t('Rate:'))}\s*([\s\S]+?)\s+{$this->opt($this->t('Rate inclusions:'))}#im",
                        false, $reservation), false, true)// '/^[A-Z][- A-Z]{0,150}[A-Z]$/'
                ;
            }
        }
    }

    private function parseEmail(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);
        $xpathNoEmpty = 'string-length(normalize-space(.))>2';

        $xpath = "//text()[{$this->eq($this->t('Check in:'))}]/ancestor::*[contains(., 'CONNECT')][1]";
        $nodes = $this->http->XPath->query($xpath);
        $numbers = [];

        foreach ($nodes as $root) {
            $conf = $this->nextText($this->t('Confirmation number:'), $root);

            if (empty($conf) || in_array($conf, $numbers)) {
                continue;
            }
            $numbers[] = $conf;
            $h = $email->add()->hotel();

            if ($name = $this->nextText($this->t('Guest name:'), $root)) {
                $h->general()->traveller($name);
            }

            if (preg_match('/(\d+)\s*\((\d+)\)/', $conf, $m)) {
                $h->general()
                    ->confirmation($m[1])
                    ->confirmation($m[2]);
            } elseif (preg_match('/([\dA-Z]+) and ([\dA-Z]+)/', $conf, $m)) {
                $h->general()
                    ->confirmation($m[1])
                    ->confirmation($m[2]);
            } elseif (preg_match('/(\d+)/', $conf)) {
                $h->general()
                    ->confirmation($conf);
            } else {
                $h->general()
                    ->noConfirmation();
            }

            if (!empty($status = $this->nextText($this->t('Status:'), $root))) {
                $h->general()
                    ->status($status);
            }
            $tot = $this->getTotalCurrency($this->nextText($this->t('GRAND TOTAL:'), $root));

            if (!empty($tot['Total'])) {
                $h->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
            $tot = $this->getTotalCurrency($this->nextText($this->t('Room total:'), $root));

            if (!empty($tot['Total'])) {
                $h->price()
                    ->cost($tot['Total'])
                    ->currency($tot['Currency']);
            }
            $tot = $this->getTotalCurrency($this->nextText($this->t('Tax / service charge total:'), $root));

            if (empty($tot['Total'])) {
                $tot = $this->getTotalCurrency($this->http->FindSingleNode("//*[{$this->contains($this->t('Tax / service charge total:'), 'text()')}]",
                    $root, false, '/:\s*(.+)/'));
            }

            if (!empty($tot['Total'])) {
                $h->price()
                    ->tax($tot['Total'])
                    ->currency($tot['Currency']);
            }

            $guests = $this->re('/^\s*(\d{1,3})\s*$/', $this->nextText($this->t('Number of adults:'), $root));
            $kids = null;

            if (!empty($this->re("#(" . $this->t('Number of children:') . ")#i",
                $this->nextText($this->t('Number of adults:'), $root)))) {
                $nums = array_values(array_filter($this->http->FindNodes("(//text()[" . $this->eq("Number of adults:") . "]/ancestor::td[1][" . $this->contains("Number of children:") . "][1]/following-sibling::td[1])[1]//text()[normalize-space()]",
                    $root, '/^\s*(\d{1,3})\s*$/')));

                if (count($nums) === 2) {
                    if (empty($guests)) {
                        $guests = $nums[0];
                    }

                    if (empty($kids)) {
                        $kids = $nums[1];
                    }
                }
            }

            if (empty($guests)) {
                $numberOfGuests = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t('Number of guests:'))}]/following-sibling::td[normalize-space()][1]",
                    $root);

                if (preg_match("/^\s*(\d{1,3}(?:[ ]*[+][ ]*\d{1,3})+)\s*$/", $numberOfGuests, $m)) {
                    // it-36336915.eml
                    $guests = array_sum(preg_split('/[ ]*[+][ ]*/', $m[1]));
                } elseif (preg_match("#(?:^|[^+\d][ ]*)(\d{1,3})[ ]*(?:{$this->opt($this->t('adult'))}|$)#i",
                    $numberOfGuests, $m)) {
                    $guests = $m[1];
                }
            }

            $xpathTime = "(contains(translate(normalize-space(),'0123456789','dddddddddd'),'dd:dd') or contains(translate(normalize-space(),'0123456789','dddddddddd'),'dddd'))";

            $dateCheckIn = $timeCheckIn = $dateCheckOut = $timeCheckOut = null;

            $checkInVal = $this->http->FindSingleNode("descendant::*[{$this->eq($this->t('Check in:'))}]/following-sibling::*[{$xpathTime}][normalize-space()][1]", $root);

            if (preg_match("#^(?<date>[^)(]{6,}?)\s*\(\s*{$this->opt($this->t('CHECK-IN'))}#", $checkInVal, $m)) {
                // Wednesday, July 10, 2019 (CHECK-IN: 15:00 (3:00 PM))
                $dateCheckIn = $m['date'];
            } elseif (preg_match('/^(?:NOW)?\s*(?<date>.{6,}?)\s*(?:\(|$)/i', $checkInVal, $m)) {
                // Thursday, August 16, 2018
                $dateCheckIn = $m['date'];
            }

            if (preg_match("#{$this->opt($this->t('CHECK-IN'))}[:\s]+(?<time>{$this->patterns['time']})#", $checkInVal, $m)) {
                $timeCheckIn = $m['time'];
            }

            $checkIn = strtotime($dateCheckIn);

            if ($timeCheckIn) {
                $checkIn = strtotime($timeCheckIn, $checkIn);
            }

            $checkOutVal = $this->http->FindSingleNode("descendant::*[{$this->eq($this->t('Check out:'))}]/following-sibling::*[{$xpathTime}][normalize-space()][1]", $root);

            if (preg_match("#^(?<date>[^)(]{6,}?)\s*\(\s*{$this->opt($this->t('CHECK-OUT'))}#", $checkOutVal, $m)) {
                $dateCheckOut = $m['date'];
            } elseif (preg_match('/^(?:NOW)?\s*(?<date>.{6,}?)\s*(?:\(|$)/i', $checkOutVal, $m)) {
                $dateCheckOut = $m['date'];
            }

            if (preg_match("#{$this->opt($this->t('CHECK-OUT'))}[:\s]+(?<time>{$this->patterns['time']})#", $checkOutVal, $m)) {
                $timeCheckOut = $m['time'];
            }

            $checkOut = strtotime($dateCheckOut);

            if ($timeCheckOut) {
                $checkOut = strtotime($timeCheckOut, $checkOut);
            }

            $h->booked()
                ->guests($guests)
                ->kids($kids, false, true)
                ->rooms($this->re('/^\s*(\d{1,3})\s*$/', $this->nextText($this->t('Number of rooms:'), $root)), false,
                    true)
                ->checkIn($checkIn)
                ->checkOut($checkOut);

            $node = $this->nextText($this->t('Check in/out times:'), $root);

            if ($node) {
                $this->parseTime($h, $node);
            }

            // cancellation
            $cancellation = $this->nextText($this->t('Cancellation policy:'), $root);

            if (!$cancellation) {
                $cancellation = $this->nextText($this->t('- Cancellation policy:'), $root);
            }

            if (!$cancellation) {
                $cancelTmp = $this->http->FindSingleNode("(//p[starts-with(normalize-space(.), 'GUARANTEE AND CANCELLATION POLICY')][1])[1]");
                // - From 6 to 1 day (by 13:00 local time) prior to arrival date when written request of cancellation or reduction of the stay is received by the hotel: 50% of the value of the entire booking will be charged as penalty fee.
                if (!preg_match('/From \d{1,3} to \d{1,3} day/', $cancelTmp) || false === stripos($cancelTmp,
                        '50% of the value')) {
                    $cancelTmp = $this->http->FindSingleNode("(//p[starts-with(normalize-space(.), 'GUARANTEE AND CANCELLATION POLICY')][1]/following-sibling::p[normalize-space()][1])[1]");
                }

                if (empty($h->getCancellation()) && preg_match("/cancel/i", $cancelTmp)) {
                    $cancellation = $cancelTmp;
                }
            }

            if ($cancellation) {
                $h->general()->cancellation($cancellation);
            }

            // deadline
            $this->detectDeadLine($h);

            $rooms = $this->http->XPath->query("descendant::text()[{$this->eq($this->t('Room:'))}]", $root);

            if ($rooms->length > 1) {
                // it-36336915.eml
                foreach ($rooms as $roomRoot) {
                    $room = $h->addRoom();

                    $roomType = $this->http->FindSingleNode("ancestor::tr[1]/*[$xpathNoEmpty][2]", $roomRoot);
                    $room->setType($roomType);

                    $xpathNextRow = "ancestor::tr[1]/following::tr[ count(*[$xpathNoEmpty])=2 and *[$xpathNoEmpty][1][not(.//*[self::td or self::th])] and not(contains(normalize-space(.), 'Extra bed:'))]";
                    $roomRate = $this->http->FindSingleNode($xpathNextRow . "[1][ *[$xpathNoEmpty][1]/descendant::text()[{$this->eq($this->t('Nightly rate excl. taxes:'))}] ]/*[$xpathNoEmpty][2]",
                        $roomRoot);
                    $room->setRate($roomRate);

                    $roomRateName = $this->http->FindSingleNode($xpathNextRow . "[2][ *[$xpathNoEmpty][1]/descendant::text()[{$this->eq($this->t('Rate:'))}] ]/*[$xpathNoEmpty][2]",
                        $roomRoot);
                    $room->setRateType($roomRateName, true, true);
                }
            } else {
                $room = $h->addRoom();

                $rate = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Nightly rate excl. taxes:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]",
                    $root);

                if (empty($rate)) {
                    $rate = implode(', ', $this->http->FindNodes("descendant::text()[normalize-space()='DAILY RATES']/ancestor::tr[1]/following::tr[contains(normalize-space(), '(') or contains(normalize-space(), ',')][1]/descendant::tr[not(.//tr) and normalize-space()]", $root));
                }

                if (!empty($rate)) {
                    $room->setRate($rate);
                }

                $rateType = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Rate:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]",
                    $root, true, '/^[A-Z][- A-z]{0,150}[A-z]$/');

                if ($rateType === null) {
                    $rateType = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Rate:'))}]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]",
                        $root, true, '/^[A-Z][- A-z]{0,150}[A-z]$/');
                }
                $room->setRateType($rateType, false, true);

                $type = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Room:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]",
                    $root);

                if ($type === null) {
                    $type = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Room:'))}]/ancestor::p[1]/following-sibling::p[normalize-space()][1]",
                        $root);
                }

                if ($type === null) {
                    $type = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Room:'))}]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]",
                        $root);
                }
                $room->setType($type);
            }

            // Hotel Name
            $node = $this->http->FindNodes("(//text()[{$this->eq($this->t('CONNECT'))}]/ancestor::td[1]//a[{$this->contains(['location_and_map', 'location%5fand%5fmap', 'dspl%5faddress', 'cip%5faddress'], '@href')}])[1]/descendant::text()",
                $root);

            if (empty($node)) {
                // it-41231563.eml
                $node = $this->http->FindNodes("descendant::text()[{$this->eq($this->t('CONNECT'))}]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/../descendant::text()[ normalize-space() and preceding::text()[{$this->eq($this->t('CONNECT'))}] ]",
                    $root);
            }
            $node = implode("\n", $node);

            if (preg_match("#^\s*(.+?),?\n+([\s\S]+)#", $node, $m)) {
                $h->hotel()
                    ->name($m[1])
                    ->address(trim(preg_replace("#\s+#", ' ', $m[2]), ' ,'));

                if ($phone = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('CONNECT'))}]/following::img[contains(@alt,'Twitter')]/preceding::text()[normalize-space()!=''][2])[1]",
                    $root, false, "#^[\d\+\-\(\) ]+$#")) {
                    $h->hotel()->phone($phone);
                }

                if ($fax = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('CONNECT'))}]/following::img[contains(@alt,'Twitter')]/preceding::text()[normalize-space()!=''][1])[1]",
                    $root, false, "#^[\d\+\-\(\) ]+$#")) {
                    $h->hotel()->fax($fax);
                }
            }
        }
    }

    /**
     * parse check in/out time.
     */
    private function parseTime(\AwardWallet\Schema\Parser\Common\Hotel $h, string $node): void
    {
        if (preg_match("#(\d+:\d+) *\/ *(\d+:\d+)#", $node, $m)) {
            $h->booked()
                ->checkIn(strtotime($m[1], $h->getCheckInDate()))
                ->checkOut(strtotime($m[2], $h->getCheckOutDate()));
        } elseif (preg_match("#Arrival is (.+), and departure is (\d{1,2}(?::\d{2})?[ ]*[ap]m|Noon)\.?#i", $node,
            $m)) {
            if (strcasecmp($m[2], 'Noon') === 0) {
                $m[2] = '12:00';
            }
            $h->booked()
                ->checkIn(strtotime($m[1], $h->getCheckInDate()))
                ->checkOut(strtotime($m[2], $h->getCheckOutDate()));
        } // Check in time from 2:00 pm and check out at noon.
        elseif (preg_match("#Check in time from (.+) and check out at (.+)\n*#", $node, $m)) {
            $m[1] = preg_replace(["#^\s*noon\s*$#i", "#^\s*(\d+)\s*([ap]m)\s*$#i"], ["12:00", "$1:00 $2"],
                str_replace('.', '', $m[1]));

            if (preg_match("#^\s*\d+:\d+(\s*[ap]m)?\s*$#i", $m[1])) {
                $h->booked()->checkIn(strtotime($m[1], $h->getCheckInDate()));
            }

            $m[2] = preg_replace(["#^\s*noon\s*$#i", "#^\s*(\d+)\s*([ap]m)\s*$#i"], ["12:00", "$1:00 $2"],
                str_replace('.', '', $m[2]));

            if (preg_match("#^\s*\d+:\d+(\s*[ap]m)?\s*$#i", $m[2])) {
                $h->booked()->checkOut(strtotime($m[2], $h->getCheckOutDate()));
            }
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellation = $h->getCancellation())) {
            if (empty($cancellation = $this->http->FindSingleNode("//text()[contains(., 'cancellation or reduction of the stay is received by the hotel: 50% of the value')]/ancestor::span[1]"))) {
                return;
            }
        }

        if (preg_match('/From (?<prior>\d{1,3}) to \d{1,3} day/', $cancellation, $m) && false !== stripos($cancellation,
                '50% of the value')
            || preg_match('/\b\d{1,3}-(?<prior>\d{1,3}) days? 50pct of stay/i', $cancellation, $m)
        ) {
            $h->booked()->deadlineRelative(($m['prior'] + 1) . ' days', '00:00');
        } elseif (preg_match("#^Cancel (\d+ hours) prior to arrival to avoid a one night room, plus tax penalty.#",
                $cancellation, $m)
            || preg_match("#^One night room and tax charge for cancellations made within (\d+ hours) of arrival.#",
                $cancellation, $m)
        ) {
            $h->booked()->deadlineRelative($m[1]);
        } elseif (preg_match("#^Cancel by (?<time>\d+[ap]m) (?<prior>\d+ days?) prior or pay \d+ nt for(?: every)? \d+ nts? reserved#i",
            $cancellation, $m)) {
            $h->booked()->deadlineRelative($m['prior'], $m['time']);
        } elseif (preg_match('/Cancel (\d{1,2} days) prior/', $cancellation, $m)) {
            $h->booked()->deadlineRelative($m[1], '00:00');
        } elseif (preg_match('/Cancel by (\d+[ap]m) local time (\d+ days) prior/i', $cancellation, $m)) {
            $h->booked()->deadlineRelative($m[2], $m[1]);
        } // Requests of cancellation or reduction of the stay have to be received up to 3 days prior to the arrival date by 13.00 pm CETto avoid any penalty.
        elseif (preg_match('/Requests of cancellation or reduction of the stay have to be received up to (?<days>\d+).*? days? prior to the arrival date by (?<time>\d+[:\.]\d+(?:\s*[ap]m)?) \w+/i',
            $cancellation, $m)) {
            $h->booked()->deadlineRelative($m['days'] . ' days', $this->correctTimeString($m['time']));
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';
        // USD 5,462.20
        // USD 4,405.00
        if (preg_match('/^([A-Z]{3})\s*(\d+[,.\d]+)$/', $node, $m)) {
            $cur = $m[1];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $cur) ? $cur : null;
            $tot = PriceHelper::parse($m[2], $currencyCode);
        }
        // € 2.376,00 EUR
        // € 792,00 EUR
        elseif (preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $cur = $m['c'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $cur) ? $cur : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function nextText($field, $root = null, $regexp = null)
    {
        return $this->http->FindSingleNode("(.//text()[{$this->eq($field)}]/following::text()[normalize-space(.)!=''][1])[1]",
            $root, true, $regexp);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function clearText(?string $text)
    {
        $text = preg_replace("#([\<])([^><\n]+@[^><\n]+)([\>])#", '$2', $text);
        $text = strip_tags($text);
        $NBSP = chr(194) . chr(160);
        $text = str_replace($NBSP, ' ', html_entity_decode($text));
        $text = str_replace("\n>", "\n", $text);
        $text = str_replace("\n>", "\n", $text);

        return $text;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function correctTimeString($time)
    {
        $time = str_replace(".", ":", $time);

        if (preg_match("#(\d+):(\d+)\s*([ap]m)#i", $time, $m)) {
            if (($m[1] == 0 && stripos($m[3], 'am') !== false) || $m[1] > 12) {
                return $m[1] . ":" . $m[2];
            }
        }

        return $time;
    }
}
