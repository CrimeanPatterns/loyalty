<?php

namespace AwardWallet\Engine\flightfox\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Common\Rental;
use AwardWallet\Schema\Parser\Email\Email;

class BookedItinerary extends \TAccountChecker
{
    public $mailFiles = "flightfox/it-77476884.eml, flightfox/it-77682279.eml, flightfox/it-77558386.eml, flightfox/it-77660662.eml, flightfox/it-77289395.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Source:'      => ['Source:'],
            'Confirmation' => ['Confirmation'],
            'refund'       => ['refund', 'REFUND', 'Refund'],
        ],
    ];

    private $subjects = [
        'en' => ['Booking Confirmation. Trip #'],
    ];

    private $detectors = [
        'en' => ['ITINERARY · BOOKED'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@mail.flightfox.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'Flightfox') === false
        ) {
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,"//flightfox.com/")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"@flightfox.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('BookedItinerary' . ucfirst($this->lang));

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][{$this->contains($this->t('ITINERARY · BOOKED'))}] ]/*[2]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
            // USD 1,879.61
            $email->price()
                ->currency($m['currency'])
                ->total($this->normalizeAmount($m['amount']));
        }

        $year = date('Y', strtotime($parser->getDate()));

        // PHIPPS/ALEX KEITA
        $patterns['travellerName'] = '[[:alpha:]][-.\/\'’[:alpha:] ]*[[:alpha:]]';
        // Thu, 28 Jan
        $patterns['wdayDate'] = '/^(?<wday>[-[:alpha:]]+)\s*,\s*(?<date>\d{1,2}\s+[[:alpha:]]{3,})$/u';
        // 09:15
        $patterns['time'] = '\d{1,2}[:：]\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?';

        $flightReferences = $carReferences = $hotelReferences = [];

        $xpathTime = 'contains(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd")';
        $tripSegments = $this->http->XPath->query("//tr[ count(*)=4 and *[1][{$xpathTime}] and following-sibling::tr[normalize-space()][1][*[1][{$xpathTime}]] ]");

        foreach ($tripSegments as $tSegment) {
            $dates = ['dep' => null, 'arr' => null];
            $timeDep = $this->http->FindSingleNode("*[1]", $tSegment, true, "/^{$patterns['time']}$/");
            $dateDepValue = $this->http->FindSingleNode("*[2]", $tSegment, true, "/^.*\d.*$/");

            if ($timeDep && preg_match($patterns['wdayDate'], $dateDepValue, $m)) {
                $weekDayNumber = WeekTranslate::number1($m['wday']);

                if ($weekDayNumber) {
                    $dateDep = EmailDateHelper::parseDateUsingWeekDay($m['date'] . ' ' . $year, $weekDayNumber);
                    $dates['dep'] = strtotime($timeDep, $dateDep);
                }
            }
            $timeArr = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/*[1]", $tSegment, true, "/^{$patterns['time']}$/");
            $dateArrValue = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/*[2]", $tSegment, true, "/^.*\d.*$/");

            if ($timeArr && preg_match($patterns['wdayDate'], $dateArrValue, $m)) {
                $weekDayNumber = WeekTranslate::number1($m['wday']);

                if ($weekDayNumber) {
                    $dateArr = EmailDateHelper::parseDateUsingWeekDay($m['date'] . ' ' . $year, $weekDayNumber);
                    $dates['arr'] = strtotime($timeArr, $dateArr);
                }
            }

            if ($this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1][{$this->contains($this->t('Marketed by'))}]", $tSegment) !== null
                || $this->http->FindSingleNode("preceding-sibling::tr[normalize-space() and not({$this->contains($this->t('Marketed by'))})][1]", $tSegment, true, '/^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+(?:\s|$)/') !== null
            ) {
                if (!isset($f)) {
                    $f = $email->add()->flight();
                    $flightReferences[] = $f;
                }
                $s = $f->addSegment();
                $this->parseFlightSegment($s, $tSegment, $dates);
            } elseif ($this->http->XPath->query("preceding-sibling::tr[normalize-space()][1][{$this->starts($this->t('Car ·'))}]", $tSegment)->length) {
                $car = $email->add()->rental();
                $carReferences[] = $car;
                $this->parseCar($car, $tSegment, $dates);
            } else {
                $h = $email->add()->hotel();
                $hotelReferences[] = $h;
                $this->parseHotel($h, $tSegment, $dates);
            }
        }

        $flightBookings = $carBookings = $hotelBookings = [];

        $bookingsSegments = $this->http->XPath->query("//tr[ count(*)=1 and *[1][{$this->starts($this->t('Source:'))}] and preceding-sibling::tr[normalize-space()] ]");

        foreach ($bookingsSegments as $key => $bSegment) {
            $type = $travellerName = $subID = null;
            $subTypes = $confirmationNumbers = $eTickets = [];

            if ($this->http->XPath->query("preceding-sibling::tr[normalize-space()][1][{$this->contains($this->t('refund'))}]", $bSegment)->length > 0) {
                /*
                    Accommodation: NOVAIS/PABLO (1 night in ATL) refund
                    or
                    Flight - Refund: BROUSSEAU/MADELEINE JANE (LAX-SJO)
                */
                $subTypes[] = 'refund';
                $this->logger->debug("Booking segment-{$key} is refundable.");
            }

            if ($this->http->XPath->query("preceding-sibling::tr[normalize-space()][1][{$this->starts($this->t('Flight - Bags:'))}]", $bSegment)->length > 0) {
                // Flight - Bags: BROUSSEAU/MADELEINE JANE (LAX-FLL-SJO)
                $subTypes[] = 'bags';
            }

            $title = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]/*[1]", $bSegment);

            if (preg_match("/^(?<type>Flight|Cars|Accommodation)[^:]*[:]+\s*(?<travellerName>{$patterns['travellerName']})?\s*\([ ]*(?<subID>[^)(]+?)[ ]*\).*$/iu", $title, $m)) {
                $type = $m['type'];

                if (!in_array('refund', $subTypes, true) && !empty($m['travellerName'])) {
                    $travellerName = $m['travellerName'];
                }
                $subID = $m['subID'];
            } else {
                $this->logger->debug("Wrong booking segment-{$key}!");

                return $email;
            }
            $source = $this->http->FindSingleNode("*[1]", $bSegment, true, "/{$this->opt($this->t('Source:'))}[\s(]*(.+?)[)\s]*$/");
            $bSegID = $source . ' ____ ' . $subID;

            $payment = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]/*[2]", $bSegment, true, '/^.*\d.*$/');

            $nextRows = $this->http->XPath->query("following-sibling::tr", $bSegment);

            foreach ($nextRows as $row) {
                $rowText = implode(' ', $this->http->FindNodes("*[1]/descendant::text()[normalize-space()]", $row));

                if (preg_match("/^{$this->opt($this->t('Source:'))}/i", $rowText)) {
                    break;
                }

                if (preg_match("/{$this->opt($this->t('Confirmation'))}\s*\d{1,3}\s*#\s*:\s*([-A-Z\d]{5,})$/", $rowText, $m)) {
                    $confirmationNumbers[] = $m[1];
                } elseif (preg_match("/{$this->opt($this->t('eTicket'))}\s*\d{1,3}\s*#\s*:\s*(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3})$/", $rowText, $m)) {
                    $eTickets[] = $m[1];
                }
            }

            if (stripos($type, 'Flight') === 0) {
                if ($travellerName && !in_array('bags', $subTypes, true)) {
                    $flightBookings[$bSegID]['travellers'][] = $travellerName;
                }

                if ($payment) {
                    $flightBookings[$bSegID]['payments'][] = $payment;
                }

                if (count($confirmationNumbers)) {
                    if (in_array('refund', $subTypes, true) && !empty($flightBookings[$bSegID]['confirmationNumbers'])) {
                        $flightBookings[$bSegID]['confirmationNumbers'] = array_diff($flightBookings[$bSegID]['confirmationNumbers'], $confirmationNumbers);
                    } else {
                        $flightBookings[$bSegID]['confirmationNumbers'] = empty($flightBookings[$bSegID]['confirmationNumbers'])
                        ? $confirmationNumbers : array_unique(array_merge($flightBookings[$bSegID]['confirmationNumbers'], $confirmationNumbers));
                    }
                }

                if (count($eTickets)) {
                    if (in_array('refund', $subTypes, true) && !empty($flightBookings[$bSegID]['eTickets'])) {
                        $flightBookings[$bSegID]['eTickets'] = array_diff($flightBookings[$bSegID]['eTickets'], $eTickets);
                    } else {
                        $flightBookings[$bSegID]['eTickets'] = empty($flightBookings[$bSegID]['eTickets'])
                        ? $eTickets : array_unique(array_merge($flightBookings[$bSegID]['eTickets'], $eTickets));
                    }
                }
            } elseif (stripos($type, 'Cars') === 0) {
                if ($travellerName) {
                    $carBookings[$bSegID]['travellers'][] = $travellerName;
                }

                if ($payment) {
                    $carBookings[$bSegID]['payments'][] = $payment;
                }

                if (count($confirmationNumbers) && !in_array('refund', $subTypes, true)) {
                    $carBookings[$bSegID]['confirmationNumbers'] = empty($carBookings[$bSegID]['confirmationNumbers'])
                    ? $confirmationNumbers : array_unique(array_merge($carBookings[$bSegID]['confirmationNumbers'], $confirmationNumbers));
                }
            } elseif (stripos($type, 'Accommodation') === 0) {
                if ($travellerName) {
                    $hotelBookings[$bSegID]['travellers'][] = $travellerName;
                }

                if ($payment) {
                    $hotelBookings[$bSegID]['payments'][] = $payment;
                }

                if (count($confirmationNumbers) && !in_array('refund', $subTypes, true)) {
                    $hotelBookings[$bSegID]['confirmationNumbers'] = empty($hotelBookings[$bSegID]['confirmationNumbers'])
                    ? $confirmationNumbers : array_unique(array_merge($hotelBookings[$bSegID]['confirmationNumbers'], $confirmationNumbers));
                }
            }
        }

        // FLIGHTS
        if (count($flightReferences) && count($flightBookings)) {
            $this->updateItinerary($flightReferences[0], $flightBookings);
        }

        // CARS
        if (count($carReferences) === 1) {
            $this->updateItinerary($carReferences[0], $carBookings);
        } elseif (count($carReferences) > 1 && count($carReferences) === count($carBookings)) {
            $i = 0;

            foreach ($carBookings as $key => $carBooking) {
                $this->updateItinerary($carReferences[$i], [$key => $carBooking]);
                $i++;
            }
        }

        // HOTELS
        if (count($hotelReferences) === 1) {
            $this->updateItinerary($hotelReferences[0], $hotelBookings);
        } elseif (count($hotelReferences) > 1 && count($hotelReferences) === count($hotelBookings)) {
            $i = 0;

            foreach ($hotelBookings as $key => $hotelBooking) {
                $this->updateItinerary($hotelReferences[$i], [$key => $hotelBooking]);
                $i++;
            }
        }

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

    private function parseFlightSegment(FlightSegment $s, \DOMNode $root, array $dates): void
    {
        $flightInfo = implode("\n", $this->http->FindNodes("preceding-sibling::tr[normalize-space()][position()<3]/*[1]", $root));

        if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)(?:\s|$)/m', $flightInfo, $m)) {
            $s->airline()
                ->name($m['name'])
                ->number($m['number']);
        }

        $s->departure()->date($dates['dep']);
        $s->arrival()->date($dates['arr']);

        $airportDep = $this->http->FindSingleNode("*[3]", $root);

        if (preg_match("/^([A-Z]{3})\s+(.+)$/", $airportDep, $m)) {
            $s->departure()
                ->code($m[1])
                ->name($m[2]);
        }

        $airportArr = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/*[3]", $root);

        if (preg_match("/^([A-Z]{3})\s+(.+)$/", $airportArr, $m)) {
            $s->arrival()
                ->code($m[1])
                ->name($m[2]);
        }

        $cabin = $this->http->FindSingleNode("*[4]", $root, true, "/^(Economy|Business)$/i");
        $s->extra()->cabin($cabin, false, true);

        $duration = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/*[4]", $root, true, "/^\d.+/");
        $s->extra()->duration($duration, false, true);
    }

    private function parseCar(Rental $car, \DOMNode $root, array $dates): void
    {
        $company = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]/*[1]", $root, true, "/^{$this->opt($this->t('Car ·'))}\s*(.{2,})$/");

        if (($code = $this->normalizeProvider($company))) {
            $car->program()->code($code);
        } else {
            $car->extra()->company($company);
        }

        $car->pickup()->date($dates['dep']);
        $car->dropoff()->date($dates['arr']);

        $pickUp = $this->http->FindSingleNode("*[3]", $root);
        $car->pickup()->location($pickUp);

        $dropOff = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/*[3]", $root);
        $car->dropoff()->location($dropOff);
    }

    private function parseHotel(Hotel $h, \DOMNode $root, array $dates): void
    {
        $hotelName = $this->http->FindSingleNode("preceding-sibling::tr[normalize-space()][1]/*[1]", $root);

        $h->booked()->checkIn($dates['dep']);
        $h->booked()->checkOut($dates['arr']);

        $address = $this->http->FindSingleNode("*[3]", $root);

        $h->hotel()
            ->name($hotelName)
            ->address($address);

        $roomCount = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/*[3]", $root, true, "/\b(\d{1,3})\s*{$this->opt($this->t('Room'))}/i");
        $h->booked()->rooms($roomCount);
    }

    private function updateItinerary($car, array $carBookings): void
    {
        $travellers = $eTickets = $currencies = $amounts = [];

        foreach ($carBookings as $key => $carBooking) {
            if (!empty($carBooking['travellers'])) {
                $travellers = array_merge($travellers, $carBooking['travellers']);
            }

            if (!empty($carBooking['eTickets'])) {
                $eTickets = array_merge($eTickets, $carBooking['eTickets']);
            }

            if (!empty($carBooking['payments'])) {
                foreach ($carBooking['payments'] as $payment) {
                    if (preg_match("/^(?<currency>[^\d)(]+?)[ ]*(?<amount>[- ]*\d[,.\'\d ]*)$/", $payment, $m)) {
                        // USD 213.34    |    USD -181.06
                        $currencies[] = $m['currency'];
                        $amounts[] = $this->normalizeAmount($m['amount']);
                    }
                }
            }

            if (!empty($carBooking['confirmationNumbers'])) {
                $confirmationTitle = preg_match("/^(.+?)[ ]+____/", $key, $m) ? $m[1] : null;

                foreach ($carBooking['confirmationNumbers'] as $confirmation) {
                    $car->general()->confirmation($confirmation, $confirmationTitle);
                }
            }
        }

        if (count($travellers)) {
            $car->general()->travellers(array_unique($travellers));
        }

        if (count($eTickets)) {
            $car->issued()->tickets(array_unique($eTickets), false);
        }

        if (count(array_unique($currencies)) === 1) {
            $car->price()
                ->currency($currencies[0])
                ->total(array_sum($amounts));
        }
    }

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    private function normalizeProvider(?string $string): ?string
    {
        $string = trim($string);
        $providers = [
            'avis' => ['Avis'],
        ];

        foreach ($providers as $code => $keywords) {
            foreach ($keywords as $keyword) {
                if (strcasecmp($string, $keyword) === 0) {
                    return $code;
                }
            }
        }

        return null;
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
            if (!is_string($lang) || empty($phrases['Source:']) || empty($phrases['Confirmation'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Source:'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Confirmation'])}]")->length > 0
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
