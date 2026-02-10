<?php

namespace AwardWallet\Engine\fseasons\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "fseasons/it-18988854.eml, fseasons/it-2091250.eml, fseasons/it-2295691.eml, fseasons/it-2931866.eml, fseasons/it-30296680.eml, fseasons/it-33276921.eml, fseasons/it-34793992.eml, fseasons/it-34945589.eml, fseasons/it-42423887.eml, fseasons/it-72284986.eml";

    private $langDetectors = [
        'en' => ['Number of Guests', 'No. of guests', 'Thank you for your reservation'],
    ];
    private $lang = '';
    private static $dict = [
        'en' => [
            'welcomeVariants' => [
                'confirm the following reservation and looking forward to welcoming you to',
                'confirm the following reservation and we are looking forward to welcoming you to',
            ],
            'We look forward to welcoming you to' => [
                'We look forward to welcoming you to',
                'look forward to welcoming you to',
                'back to our magical paradise at',
            ],
            'contactFieldNames'    => ['Tel:', 'Tel.', 'Fax:', 'Fax.', 'BB:', 'E-mail:', 'Email', 'Web:', 'Web.', 'Twitter:'],
            'Tel:'                 => ['Tel:', 'Tel.'],
            'Fax:'                 => ['Fax:', 'Fax.'],
            'We are pleased to'    => ['We are pleased to', 'I am pleased to', 'We are delighted to'],
            'Arrival Date'         => ['Arrival Date', 'Arrival date', 'Arrival Date:', 'Arrival date'],
            'Departure Date'       => ['Departure Date', 'Departure date', 'Departure Date:', 'Departure date:'],
            'Confirmation Number'  => ['Confirmation Number', 'Confirmation Number:', 'Confirmation Numbers', 'Confirmation Numbers:', 'Confirmation number', 'Cancellation Number', 'Conf. Number'],
            'Cancellation Notice'  => ['Cancellation Notice', 'Cancellation Policy:'],
            'Guest Name'           => ['Guest Name', 'Guest Name:', 'Guest name', 'Guest Names'],
            'Check-in Time'        => ['Check-in Time', 'Check-In Time', 'Check­in Time', 'Check In Time'],
            'Check-in time is'     => ['Check-in time is', 'check-in time is', 'check in time is', 'We are pleased to welcome you from'],
            'Check-out Time'       => ['Check-out Time', 'Check-Out Time', 'Check­out Time', 'Check Out Time'],
            'Check-out time is'    => ['Check-out time is', 'check-out time is', 'check out time is', 'Departure time is at'],
            'Number of Guests'     => ['Number of guests:', 'Number of Guests', 'Number of Guests:', 'No. of guests', 'Number of Persons'],
            'Accommodation'        => ['Accommodation', 'Room Type', 'Room type'],
            'Nightly Rate'         => ['Nightly Rate', 'Room Rate', 'Special rate'],
            'Terms and Conditions' => ['Terms and Conditions', 'Policies:'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Four Seasons') !== false
            || stripos($from, '@fourseasons.com') !== false
            || stripos($from, 'fourseasons.com@mailgun.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Reservation Confirmation for') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()['
                . 'contains(normalize-space(),"welcoming you to Four Seasons")'
                . ' or contains(normalize-space(),"hope you enjoy your stay at Four Seasons")'
                . ' or contains(normalize-space(),"FOUR SEASONS MOBILE APP")'
                . ' or contains(normalize-space(),"Four Seasons Mobile App")'
                . ' or contains(normalize-space(),"IHG Rewards Club Membership")'
                . ' or contains(.,"@fourseasons.com") or contains(.,"www.fourseasons.com")'
                . ' or contains(.,"@lestroisrois.com") or contains(.,"www.lestroisrois.com")'
                . ']')->length === 0
            && $this->http->XPath->query('//a['
                . 'contains(@href,"//www.fourseasons.com")'
                . ' or contains(@href,"www.dangleterre.com")'
                . ' or contains(@href,"www.lestroisrois.com")'
                . ' or contains(.,"@ihg.com")'
                . ']')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('Reservation' . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
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
        $patterns = [
            'phone'         => '[+(\d][-. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
            'time'          => '\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.
            'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]\.?', // Mrs. Thomas Hauske Jr.
        ];

        $h = $email->add()->hotel();

        // for emails with many reservations
        // TODO: bad practice to use styles in xpath
        $bgRule = "({$this->contains(['#ECEBD9', '#ecebd9', 'rgb(236,235,217)'], 'translate(@style," ","")')} or {$this->contains(['#ECEBD9', '#ecebd9', 'rgb(236,235,217)'], 'translate(@bgcolor," ","")')})";
        $hotelTables = $this->http->XPath->query("//text()[{$this->eq($this->t('ROOM RESERVATION DETAILS'))}]/ancestor::*[$bgRule]/ancestor-or-self::tr[ preceding-sibling::*[normalize-space(.)] and following-sibling::*[normalize-space(.)] ][1]/ancestor::table[1]");
        // TODO: add foreach($hotels as $root){..}
//        $root = $hotelTables->length === 1 ? $hotelTables->item(0) : $this->http->XPath->query('/')->item(0);
        $root = $hotelTables->length === 1 ? $hotelTables->item(0) : null;

        $phonesDesc = ['Reservations', 'Reservations Office', 'Concierge', 'Main Telephone', 'Reservations Fax', 'Guest Fax', 'Tel:'];
        $addedPhones = [];

        foreach ($phonesDesc as $pd) {
            $node = $this->http->FindSingleNode("descendant::text()[normalize-space()='{$pd}' and not(ancestor::a)]/ancestor::td[1]/following-sibling::td[1]", $root, false, "/^\s*({$patterns['phone']})\s*(?:$|[[:alpha:]])/u");

            if (!empty($node) && !in_array($node, $addedPhones)) {
                $h->program()
                    ->phone($node, $pd);
                $addedPhones[] = $node;
            }
        }

        $hotelContactsTexts = $this->http->FindNodes("descendant::text()[{$this->starts($this->t('contactFieldNames'))}]/ancestor::td[{$this->contains(['Reservation Confirmation', 'Reservation Cancellation'])}][1]/descendant::text()[normalize-space(.)]", $root);
        $hotelContactsText = implode("\n", $hotelContactsTexts);

        if (empty($hotelContactsText)) {
            $hotelContactsTexts = $this->http->FindNodes("descendant::text()[{$this->starts($this->t('contactFieldNames'))}]/ancestor::*[count(*[(self::table or self::td) and string-length(normalize-space())>1])=2][1]/descendant::text()[normalize-space()]", $root);
            $hotelContactsText = implode("\n", $hotelContactsTexts);
        }

        if (empty($hotelContactsText)) {
            $hotelContactsTexts = $this->http->FindNodes("descendant::text()[{$this->starts($this->t('contactFieldNames'))}]/ancestor::*[count(*[(self::table or self::td) and string-length(normalize-space())>1])=1][1]/descendant::text()[normalize-space()]", $root);
            $hotelContactsText = implode("\n", $hotelContactsTexts);
        }

        // hotelName
        // address
        $patterns['hotel'] = '/'
            . 'Reservation (?:Confirmation|Cancellation)\s*:?'
            . '\s+[^\n]+' // November 9, 2018 to November 17, 2018
            . '\s+(?<hotelName>[^\n]+)' // Four Seasons Resort Hualalai
            . '\s+(?<address>.*?)'
            . '\s+' . $this->opt($this->t('contactFieldNames'))
            . '/s';

        if (preg_match($patterns['hotel'], $hotelContactsText, $matches)) {
            if (strlen($matches['hotelName']) > 150) {
                if (preg_match("/{$this->opt($this->t('Thank you very much for choosing'))}\s(.+)\s{$this->opt($this->t('for your upcoming visit to the'))}/", $matches['hotelName'], $m)) {
                    $h->hotel()
                        ->name($m['1']);
                }
                $hotelInfo = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Reservations Tel.:')]/ancestor::tr[1]");

                if (preg_match("/^{$h->getHotelName()}\s*(.+)Tel\.\:\s*([\+\d\s]+)\s*\|/", $hotelInfo, $m)) {
                    $h->hotel()
                        ->address($m[1])
                        ->phone($m[2]);
                }
            } else {
                $h->hotel()
                    ->name($matches['hotelName'])
                    ->address(preg_replace('/\s*\n\s*/', ', ', $matches['address']));
            }
        } else {
            $hotelName = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('welcomeVariants'))}]/ancestor::*[1]", $root, true, "/{$this->opt($this->t('welcomeVariants'))}\s*([^,.!]{3,}?)(?:[,.!]+|$)/");

            if ($hotelName && preg_match("/^" . preg_quote($hotelName, '/') . "[ ]+(.{3,}?)[ ]+{$this->opt($this->t('contactFieldNames'))}/", $hotelContactsText, $m)) {
                // it-30296680.eml
                $h->hotel()
                    ->name($hotelName)
                    ->address($m[1])
                ;
            }
        }

        $node = str_replace(' ', '\s+',
            $this->http->FindSingleNode("//text()[{$this->starts('We are delighted to confirm the')}]", null, false,
                "/(?:to|at) (Four Seasons.+)(?:\.|\!)/"));

        if (empty($node)) {
            $node = 'Four Seasons (?:Resort|Hotel) .+?';
        }
        $re = '/(' . $node . ')\n(.+)\s+Tel[\.\:](.+?\n)(?:Fax[\.\:](.+?)\n)?(?:BB\:.+?)?E\-?mail/s';

        if (empty($h->getHotelName()) && preg_match($re, implode("\n", $hotelContactsTexts), $m)) {
            $h->hotel()
                ->name(trim(preg_replace("/\s+/", ' ', $m[1])))
                ->address(trim(preg_replace("/\s+/", ' ', $m[2])))
                ->phone(trim(preg_replace("/\s+/", ' ', $m[3])));

            if (!empty($m[4])) {
                $h->hotel()->fax(trim(preg_replace("/\s+/", ' ', $m[4])));
            }
        }

        if (empty($h->getHotelName())) {
            $hotelName = trim($this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Thank you for choosing'))}]/following::text()[normalize-space(.)][1]", $root), " .");

            if (empty($hotelName)) {
                // it-42423887
                $hotelName = $this->http->FindSingleNode("//text()[normalize-space()='Phone']/ancestor::*[contains(.,'|')][1][contains(.,'Fax')]/descendant::text()[normalize-space()!=''][1]",
                    $root);
                $footerContacts = implode("\n",
                    $this->http->FindNodes("//text()[normalize-space()='Phone']/ancestor::*[contains(.,'|')][1][contains(.,'Fax')]/descendant::text()[normalize-space()!=''][position()>1]",
                        $root));

                if (!empty($footerContacts)) {
                    $h->hotel()
                        ->name(trim($hotelName))
                        ->address(trim(preg_replace("/\n/", ' ', $this->re("#^(.+?)\s*?\nPhone#s", $footerContacts))))
                        ->phone(trim($this->re("#\nPhone\s+([\d\+\- \(\)]+)\n#", $footerContacts)))
                        ->fax(trim($this->re("#\nFax\s+([\d\+\- \(\)]+)$#", $footerContacts)));
                }
            }

            if (empty($hotelName)) {
                // it-2295691.eml
                $hotelName_temp = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('We look forward to welcoming you to'))}]/ancestor::*[1]", $root, false, "#{$this->opt($this->t('We look forward to welcoming you to'))}(?:\s+the)?\s+([^,.!]{3,}?)[ ]*(?:and wish you|[,.!]+|$)#i");

                if ($this->http->XPath->query("//text()[{$this->contains($hotelName_temp)}]")->length > 1) {
                    $hotelName = $hotelName_temp;
                }
            }
            $h->hotel()->name($hotelName);

            if (empty($h->getAddress())) {
                $h->hotel()->noAddress();
            }
        }

        // phone
        if (preg_match("/\b{$this->opt($this->t('Tel:'))}\s*({$patterns['phone']})/", $hotelContactsText, $matches)) {
            $h->hotel()->phone($matches[1]);
        }

        // fax
        if (preg_match("/\b{$this->opt($this->t('Fax:'))}\s*({$patterns['phone']})/", $hotelContactsText, $matches)) {
            $h->hotel()->fax($matches[1]);
        }

        // status
        $status = $this->http->FindSingleNode('(descendant::text()[' . $this->contains($this->t('We are pleased to')) . '])[1]', $root, true, '/' . $this->opt($this->t('We are pleased to')) . '\s+(\w+)/u');

        if ($status && $status !== 'welcome') {
            $h->general()->status($status);
        }

        $xpathFragmentBug = "descendant::text()[{$this->eq($this->t('Arrival Date'))}]/ancestor::td[ following-sibling::td[normalize-space()] ][1]";

        if ($this->http->XPath->query($xpathFragmentBug . '/descendant::node()[normalize-space()]/preceding-sibling::br', $root)->length > 0) {
            // DOM fragment normalize (for it-34793992.eml)

            $replaceNode = $this->http->XPath->query($xpathFragmentBug . '/..', $root)->item(0);

            $tdLeftText = $this->htmlToText($this->http->FindHTMLByXpath($xpathFragmentBug, null, $root));
            $tdRightText = $this->htmlToText($this->http->FindHTMLByXpath($xpathFragmentBug . '/following-sibling::td[normalize-space()][1]', null, $root));

            $tdLeftRows = explode("\n", $tdLeftText);
            $tdRightRows = explode("\n", $tdRightText);

            $html = '';

            foreach ($tdLeftRows as $key => $rowLeft) {
                $html .= '<tr><td><strong>' . trim($rowLeft) . '</strong></td><td>' . (isset($tdRightRows[$key]) ? trim($tdRightRows[$key]) : '') . '</td></tr>';
            }

            $htmlFragment = $this->http->DOM->createDocumentFragment();
            $htmlFragment->appendXML($html);
            $replaceNode->parentNode->replaceChild($htmlFragment, $replaceNode);
        }

        $xpathNextCell = '/ancestor::td[1]/following-sibling::td[normalize-space()][1]';

        // confirmation number
        $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Confirmation Number'))}]" . $xpathNextCell, $root);

        if (preg_match_all('/\b([A-Z\d]{5,})\b/', $confirmation, $matches, PREG_SET_ORDER)) {
            $confirmationTitle = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Confirmation Number'))}]", $root);
            $confirmationTitle = preg_replace('/\s*:\s*$/', '', $confirmationTitle);

            foreach ($matches as $m) {
                $h->general()->confirmation($m[1], $confirmationTitle);
            }
        }

        if (!empty($this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Reservation Cancellation'))}][1]", $root))) {
            $h->general()
                ->noConfirmation()
                ->cancelled()
                ->status('Cancelled');
        }

        // travellers
        $travellers = [];
        $guestNamesHtml = $this->http->FindHTMLByXpath("descendant::text()[{$this->eq($this->t('Guest Name'))}]" . $xpathNextCell);
        $guestNamesText = $this->htmlToText($guestNamesHtml);
        $guestNamesText = preg_replace("/^[ ]*{$this->opt($this->t('Room'))}.*$/m", '', $guestNamesText);
        $guestNamesText = preg_replace("/^[ ]sharing with/mi", '', $guestNamesText);
        $guestNamesText = preg_replace("/^.+:/m", '', $guestNamesText);

        if (preg_match_all("/^[ ]*({$patterns['travellerName']})[ ]*$/m", $guestNamesText, $matches)) {
            foreach ($matches[1] as $guestName) {
                $guestNames = preg_split("#(?:^| )(?:Mr|Ms|Miss|Mrs|Dr)[. ]\s*#", $guestName);
                $guestNames = array_filter(preg_replace("#\s*\b(&|Sharing with|and)\s*$#", '', $guestNames));

                if (count($guestNames)) {
                    $travellers = array_merge($travellers, $guestNames);
                }
            }
        }

        if (count($travellers)) {
            $h->general()->travellers(array_unique($travellers));
        }

        // checkInDate
        $checkInTime = null;
        $dateCheckIn = $this->http->FindSingleNode('descendant::text()[' . $this->eq($this->t('Arrival Date')) . ']' . $xpathNextCell, $root);

        if ($dateCheckIn && preg_match('/^(.{6,}?)\s*(?:\(([^)(]+)\))?$/', $dateCheckIn, $m)) {
            // Thursday, October 17, 2019 (Early Check-in Requested)
            if ($dateCheckInNormal = $this->normalizeDate($m[1])) {
                $h->booked()->checkIn2($dateCheckInNormal);
            }

            if (!empty($m[2]) && preg_match("/Early Check[- ]*in confirmed at ({$patterns['time']})$/i", $m[2], $matches)) {
                $checkInTime = $matches[1];
            } elseif (!empty($m[2]) && preg_match('/\d/', $m[2])) {
                $h->booked()->checkIn(null); // for 100% failed
            }
        }

        // checkOutDate
        $checkOutTime = null;
        $dateCheckOut = $this->http->FindSingleNode('descendant::text()[' . $this->eq($this->t('Departure Date')) . ']' . $xpathNextCell, $root);

        if ($dateCheckOut && preg_match('/^(.{6,}?)\s*(?:\(([^)(]+)\))?$/', $dateCheckOut, $m)) {
            // Monday, October 21, 2019 (Late Check- Out confirmed at 1:00pm)
            if ($dateCheckOutNormal = $this->normalizeDate($m[1])) {
                $h->booked()->checkOut2($dateCheckOutNormal);
            }

            if (!empty($m[2]) && preg_match("/Late Check[- ]*Out confirmed at ({$patterns['time']})$/i", $m[2], $matches)) {
                $checkOutTime = $matches[1];
            } elseif (!empty($m[2]) && preg_match('/\d/', $m[2])) {
                $h->booked()->checkOut(null); // for 100% failed
            }
        }

        if (empty($node = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Cancellation Notice'))}]/ancestor::td[1]/following-sibling::td[1]", $root))) {
            $node = $this->re("#(.+?)\.#", implode(' ',
                $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Cancellation Notice'))}]/following::text()[normalize-space()!=''][position()<4]", $root)));
        }

        if (empty($node)) {
            $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Please note that cancellations or modifications are'))}]");
        }

        if (!empty($node)) {
            $h->general()
                ->cancellation($node);
            $this->detectDeadLine($h, $node);
        }

        // r.type
        $roomType = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Accommodation'))}]{$xpathNextCell}[not(.//a)]", $root);

        if (!empty($roomType)) {
            $r = $h->addRoom();
            $r->setType($roomType, false, true);
        } else {
            $types = [];
            $rows = $this->http->XPath->query("descendant::text()[{$this->starts($this->t('Accommodation'))}]{$xpathNextCell}//text()[normalize-space()]");

            foreach ($rows as $rRoot) {
                if (!empty($this->http->FindSingleNode("./ancestor::a[1]", $rRoot))) {
                    $types[] = $rRoot->nodeValue;
                } elseif (isset($types[count($types) - 1]) && false === stripos($rRoot->nodeValue, 'Connecting') && false === stripos($rRoot->nodeValue, 'Four Seasons') && false === stripos($rRoot->nodeValue, 'Daily full') && false === stripos($rRoot->nodeValue, 'Credit, once during stay')) {
                    $types[count($types) - 1] .= $rRoot->nodeValue;
                }
            }

            foreach ($types as $type) {
                $r = $h->addRoom()->setType($type);
            }
        }

        $xpathRate = "descendant::text()[{$this->starts($this->t('Nightly Rate'))}]" . $xpathNextCell;

        if (!$roomType && count($rooms = $this->http->FindNodes('descendant::text()[' . $this->starts($this->t('Guest Name')) . ']' . $xpathNextCell . '//text()[normalize-space(.)][' . $this->starts($this->t('Room')) . ']', $root, "#{$this->opt($this->t('Room'))} \d+[ :]+(.+)#")) > 0) {
            $r = $h->addRoom();
            $r->setType(array_shift($rooms));
            $nightlyRate = $this->http->FindSingleNode("({$xpathRate}/descendant::text()[({$this->contains($this->t('per night'))}) and ({$this->contains($r->getType())})])[1]", $root, false, "#{$this->opt($r->getType())}[ \-:]+(.+)#");

            if (!empty($nightlyRate)) {
                $r->setRate($nightlyRate);
            }

            foreach ($rooms as $room) {
                $r = $h->addRoom();
                $r->setType($room);
                $nightlyRate = $this->http->FindSingleNode("({$xpathRate}/descendant::text()[({$this->contains($this->t('per night'))}) and ({$this->contains($room)})])[1]", $root, false, "#{$this->opt($room)}[ \-:]+(.+)#");

                if (!empty($nightlyRate)) {
                    $r->setRate($nightlyRate);
                }
            }
        } elseif ((!empty($types) && count($types) == 1) || !empty($roomType)) {
            // r.rate
            $nightlyRate = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Nightly Rate'))}]{$xpathNextCell}/descendant::text()[normalize-space()][1]", $root, true, "/^(.+{$this->opt($this->t('per night'))}.*|\d[,.\'\d ]*[ ]*[A-Z]{3}|[A-Z]{3}[ ]*\d[,.\'\d ]*)$/");

            if ($nightlyRate) { // it-2295691.eml
                // $349 per night    |    USD 349
                $r->setRate($nightlyRate);
            } else {
                $nightlyRateText = '';
                $rateRows = $this->http->XPath->query($xpathRate . "/descendant::tr[normalize-space() and (preceding-sibling::tr[normalize-space()] or following-sibling::tr[normalize-space()])]", $root);

                if ($rateRows->length === 0) {
                    $rateRows = $this->http->XPath->query($xpathRate . "/descendant::p[normalize-space() and (preceding-sibling::p[normalize-space()] or following-sibling::p[normalize-space()])]", $root);
                }

                foreach ($rateRows as $rateRow) {
                    // it-2091250.eml
                    $nightlyRateHtml = $this->http->FindHTMLByXpath('.', null, $rateRow);
                    $nightlyRateText .= $this->htmlToText($nightlyRateHtml) . "\n";
                }

                if (empty($nightlyRateText) && $rateRows->length === 0) {
                    $nightlyRateHtml = $this->http->FindHTMLByXpath($xpathRate, null, $root);
                    $nightlyRateText .= $this->htmlToText($nightlyRateHtml) . "\n";
                }

                // Examples:
                // Saturday, December 27    USD 4536.80
                // 2019/12/27    4536.80 USD    |    17.10.19 - 19.10.19 765.00 EUR
                if (preg_match_all('/\b\d{1,2}\s+-?[ ]*(?<currency>[A-Z]{3})[ ]*(?<amount>\d[,.\'\d]*)[ ]*$/m', $nightlyRateText, $rateMatches)
                    || preg_match_all('/[\/.]\d{1,2}[ ]+(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[A-Z]{3})[ ]*$/m', $nightlyRateText, $rateMatches)
                ) {
                    if (count(array_unique($rateMatches['currency'])) === 1) {
                        $rateMatches['amount'] = array_map(function ($item) {
                            return $this->normalizeAmount($item);
                        }, $rateMatches['amount']);

                        $rateMin = min($rateMatches['amount']);
                        $rateMax = max($rateMatches['amount']);

                        if ($rateMin === $rateMax) {
                            $r->setRate(number_format($rateMatches['amount'][0], 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night');
                        } else {
                            $r->setRate(number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night');
                        }
                    }
                }
            }
        }

        $numberGuests = $this->http->FindSingleNode('descendant::text()[' . $this->eq($this->t('Number of Guests')) . ']' . $xpathNextCell, $root);

        // guestCount
        if (preg_match('/\b(\d{1,3})\s*Adult/i', $numberGuests, $m)) {
            $h->booked()->guests($m[1]);
        } elseif (preg_match('/^\d{1,3}$/', $numberGuests)) {
            $h->booked()->guests($numberGuests);
        }

        // kidsCount
        if (preg_match('/\b(\d{1,3})\s*(?:Child|Teen)/i', $numberGuests, $m)) {
            $h->booked()->kids($m[1]);
        }

        // p.total
        // p.currencyCode
        $estimatedTotal = $this->http->FindSingleNode('descendant::text()[' . $this->starts($this->t('Estimated Total')) . ']' . $xpathNextCell, $root);

        if (!$estimatedTotal) {
            $estimatedTotal = $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(.),'Total Room Stay')][1]", $root);
        }
        // USD 4536.80
        if (preg_match('/(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d]*)/', $estimatedTotal, $matches)) {
            $h->price()->currency($matches['currency']);
            $h->price()->total($this->normalizeAmount($matches['amount']));

            // p.cost
            $totalRoomRates = $this->http->FindSingleNode('descendant::text()[' . $this->starts($this->t('Total Room Rates')) . ']' . $xpathNextCell, $root);

            if (preg_match('/^' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d]*)/', $totalRoomRates, $m)) {
                $h->price()->cost($this->normalizeAmount($m['amount']));
            }
        }

        // checkInDate
        if (!$checkInTime) {
            $checkInTime = $this->http->FindSingleNode('descendant::text()[' . $this->starts($this->t('Check-in Time')) . ']' . $xpathNextCell, $root, true, '/(' . $patterns['time'] . ')/');
        }

        if (!$checkInTime) {
            $checkInTime = $this->http->FindSingleNode('descendant::text()[' . $this->starts($this->t('Check-in Time')) . ']/following::text()[normalize-space(.)][1]', $root, true, '/(' . $patterns['time'] . ')/');
        }

        if (!$checkInTime) {
            $checkInTime = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Check-in time is'))}]", $root, true, "/{$this->opt($this->t('Check-in time is'))}(?:\s*{$this->opt($this->t('after'))})?\s*(noon|\d{1,2}(?:[:]+\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)\s*(?:[,.;|(]|and|$)/i");
        }

        if (!$checkInTime) {
            $checkInTime = $this->http->FindSingleNode("//text()[normalize-space() = 'Check In / Check Out']/ancestor::tr[1]/descendant::td[2]", null, true, "/^([\d\:]+)\s*\/\s*[\d\:]+$/");
        }

        if (!empty($h->getCheckInDate()) && $checkInTime) {
            $checkInTime = preg_replace("/^\s*(\d+)\s*([apm.\s]*)$/i", '$1:00 $2', $checkInTime);
            $checkInTime = preg_replace("/^noon$/i", '12:00', $checkInTime);
            $h->booked()->checkIn(strtotime($checkInTime, $h->getCheckInDate()));
        }

        // checkOutDate
        if (!$checkOutTime) {
            $checkOutTime = $this->http->FindSingleNode('descendant::text()[' . $this->starts($this->t('Check-out Time')) . ']' . $xpathNextCell, $root, true, '/(' . $patterns['time'] . ')/');
        }

        if (!$checkOutTime) {
            $checkOutTime = $this->http->FindSingleNode('descendant::text()[' . $this->starts($this->t('Check-out Time')) . ']/following::text()[normalize-space(.)][1]', $root, true, '/(' . $patterns['time'] . ')/');
        }

        if (!$checkOutTime) {
            $checkOutTime = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Check-out time is'))}]", $root, true, "/{$this->opt($this->t('Check-out time is'))}\s*(noon|\d{1,2}(?:[:]+\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)\s*(?:[,.;(]|latest|$)/i");
        }

        if (!$checkOutTime) {
            $checkOutTime = $this->http->FindSingleNode("//text()[normalize-space() = 'Check In / Check Out']/ancestor::tr[1]/descendant::td[2]", null, true, "/^[\d\:]+\s*\/\s*([\d\:]+)$/");
        }

        if (!empty($h->getCheckOutDate()) && $checkOutTime) {
            $checkOutTime = preg_replace("/^\s*(\d+)\s*([apm.\s]*)$/i", '$1:00 $2', $checkOutTime);
            $checkOutTime = preg_replace("/^noon$/i", '12:00', $checkOutTime);
            $h->booked()->checkOut(strtotime($checkOutTime, $h->getCheckOutDate()));
        }

        // cancellation
        // deadline
        $cancellationText = '';
        $termsAndConditions = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Terms and Conditions'))}]" . $xpathNextCell, $root);

        if ($termsAndConditions) {
            $termsAndConditionsParts = preg_split('/[.]+\s*\b/', $termsAndConditions);
            $termsAndConditionsParts = array_filter($termsAndConditionsParts, function ($item) {
                return stripos($item, 'cancel') !== false || stripos($item, 'prior to arrival') !== false;
            });
            $cancellationText = implode('. ', $termsAndConditionsParts);

            if (mb_strlen($cancellationText) > 1000) {
                for ($i = 0; $i < 20; $i++) {
                    $cancellationText = preg_replace('/^(.+\w\s*\.).+?\.$/s', '$1', $cancellationText);

                    if (mb_strlen($cancellationText) < 1001) {
                        break;
                    }
                }
            }
        } else {
            $cancellationText = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('The cancellation policy for the current booking is as follows:'))}]/following::td[normalize-space(.)][1][contains(., 'Cancel') or contains(., 'cancel')]");

            if (!$cancellationText) {
                $cancellationText = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('We offer free cancellation until'))}]");
            }

            if (!$cancellationText) {
                $cancellationText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation Policy'))}]/ancestor::tr[1]/descendant::td[2]");
            }
        }

        if (!empty($cancellationText)) {
            $h->general()->cancellation($cancellationText);
            $this->detectDeadLine($h, $cancellationText);
        }

        $accountNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('IHG Rewards Club Membership'))}]/ancestor::tr[1]/descendant::td[2]", null, true, "/^(\d+)\s*\/\s*Club$/u");

        if (!empty($accountNumber)) {
            $h->program()
                ->account($accountNumber, false);

            $h->setProviderCode('ichotelsgroup');
        }
    }

    private function normalizeDate(string $string)
    {
        if (preg_match('/([^\d\W]{3,})\s+(\d{1,2})(?:th|nd|d|st)?\s*,?\s*(\d{4})$/u', $string, $matches)) { // Saturday, August 4, 2018  | Wednesday, December 26th, 2018
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/(\d{1,2})\.?\s*([^\d\W]{3,}),?\s+(\d{4})$/u', $string, $matches)) { // Saturday, 10. November 2018
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
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

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(Hotel $h, string $cancellationText)
    {
        $dayWords = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];
        $patterns['dayWords'] = implode('|', $dayWords);

        if (
            preg_match('/Cancellations must be made by (?<hour>\d+:\d+(?: *[ap]m)?) 24 hours prior to the day of arrival/i', $cancellationText, $m)
        ) {
            // it-18988854.eml
            $h->booked()->deadlineRelative('2 days', $m['hour']);
        } elseif (
            preg_match('/please notify the hotel (?<prior>\d{1,3} days?) prior to the day of arrival/i', $cancellationText, $m)
            || preg_match('/\b(?<prior>\d{1,3} days?) prior arrival date, free cancellation/i', $cancellationText, $m)
            || preg_match('/Our cancellation policy applies (?<prior>\d{1,3} days?) prior to the arrival date\./i', $cancellationText, $m)
            || preg_match('/Please notify the Resort of any changes or cancellations\s*(?<prior>\d{1,3} days?)\s*prior to the date of arrival/i', $cancellationText, $m)
            || preg_match('/^Please note that cancellations or modifications are applicable free of charge until\s*(?<prior>\d{1,3} days?)\s*prior to arrival/i', $cancellationText, $m)
            || preg_match('/All cancellations and changes for a guest room reservation must be received at least (?<prior>\d{1,2} days) prior to expected arrival date/', $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'], '00:00');
        } elseif (
            preg_match('/by (?<hour>\d{1,2}\s*[AP]M)\s+(?<prior1>\d{1,3})\s*(?<prior2>days?)\s*prior to arrival/i', $cancellationText, $m)
            || preg_match('/All cancellations must be received by\s*(?<hour>\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*Costa Rica time at least\s*(?<prior1>\d{1,3})\s*(?<prior2>days?)\s*prior to expected arrival, or the Resort will retain the full deposit/i', $cancellationText, $m)
            || preg_match("/it will be necessary to advise the hotel of any amendments or cancellations by\s*(?<hour>\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*(?<prior1>\d{1,3}|{$patterns['dayWords']})\s*(?<prior2>days?)\s*prior to arrival/i", $cancellationText, $m)
            || preg_match('/cancellations must be received by\s*(?<hour>\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*[\w ]+ time at least\s*(?<prior1>\d{1,3})\s*(?<prior2>days?)\s*prior to expected arrival date./i', $cancellationText, $m)
            || preg_match("/Guaranteed reservations cancelled or changed after (?<hour>\d{1,2}[ap]m) local time, (?<prior1>\d{1,2}) (?<prior2>hours?) prior to arrival will be subject to a one night's room and tax charge/", $cancellationText, $m)
            || preg_match("/We offer free cancellation until the (?<prior2>day) of arrival \((?<hour>noon|\d{1,2}:\d{2}(?:\s*[AP]M)?)\)\. In the event of cancellation, no-show or early departure we charge 100%/i", $cancellationText, $m)
        ) {
            if (empty($m['prior1'])) {
                $m['prior1'] = '0';
            }
            $m['prior1'] = strtolower($m['prior1']);
            $prior1 = in_array($m['prior1'], $dayWords) ? array_search($m['prior1'], $dayWords) : $m['prior1'];
            $m['hour'] = preg_replace('/^noon$/i', '12:00', $m['hour']);
            $h->booked()->deadlineRelative($prior1 . ' ' . $m['prior2'] . ' -1 day', $m['hour']);
        } elseif (
           preg_match('/All cancellations must be received by\s*(?<hour>\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*[\w ]+? time at least\s*(?<prior>\d{1,3} hours?)\s*prior to expected arrival, or a penalty equal to one /i', $cancellationText, $m)
           || preg_match("/All cancellations and changes must be made\s*(?<prior>\d{1,3} days?)\s*prior to arrival by\s*(?<hour>\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*[\w ]+? time or a penalty of one night's room and tax will be charged/i", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['prior'] . ' -1 day', $m['hour']);
        } elseif (
        preg_match('/Canceling your reservation before\s*([\d\:]+\s*A?P?M)\s*\(local\s*hotel\s*time\)\son\s*\w+\,\s+(\d+)\s*(\w+)\,\s+(\d{4})\s*will/i', $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3] . ', ' . $m[4]));
        }
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
