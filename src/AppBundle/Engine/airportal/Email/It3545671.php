<?php

namespace AwardWallet\Engine\airportal\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It3545671 extends \TAccountChecker
{
    public $mailFiles = "airportal/it-11596026.eml, airportal/it-2771038.eml, airportal/it-3545671.eml, airportal/it-3545673.eml, airportal/it-3563557.eml, airportal/it-6618353.eml, airportal/it-6618433.eml, christopherson/it-2771038.eml, christopherson/it-3545671.eml, christopherson/it-3545673.eml, christopherson/it-3563557.eml, christopherson/it-6618353.eml, christopherson/it-6618433.eml";

    private $subjects = [
        'en' => ['AirPortal - Airtinerary:'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@cbtravel.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        return $this->http->XPath->query('//img[contains(@src,"app.cbtat.com/images/airtinerary/air.png")]')->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        if ($this->http->XPath->query('//*[self::th or self::td]/ancestor::*[1][not(self::tr)]')->length > 0) {
            // for damaged html-documents (examples: it-2771038.eml)
            $this->http->FilterHTML = true;
            $this->http->SetEmailBody($parser->getHTMLBody());
        }

        $this->parseEmail($email);
        $email->setType('Flight');

        return $email;
    }

    public static function getEmailProviders()
    {
        return ['andavo', 'airportal', 'christopherson'];
    }

    private function assignProvider($headers)
    {
        if ($this->http->XPath->query('//node()[contains(.,"www.andavotravel.com")]')->length > 0
            || $this->http->XPath->query('//a[contains(@href,"//www.andavotravel.com")]')->length > 0) {
            $this->providerCode = 'andavo';

            return true;
        }

        if ($this->http->XPath->query('//a[contains(@href,"@cbtravel.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(.,"@cbtravel.com")]')->length > 0
        ) {
            $this->providerCode = 'christopherson';

            return true;
        }

        // last
        $condition1 = stripos($headers['subject'], 'AirPortal - Airtinerary') !== false;
        $condition2 = $this->http->XPath->query('//node()[contains(normalize-space(.),"AirPortal - Airtinerary")]')->length > 0;
        $condition3 = $this->http->XPath->query('//a[contains(@href,"app.cbtat.com")]')->length > 0;

        if ($condition1 || $condition2 || $condition3) {
            $this->providerCode = 'airportal';

            return true;
        }

        return false;
    }

    private function parseEmail(Email $email): void
    {
        $patterns = [
            'time'           => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            'travellerName'  => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'statusVariants' => 'Confirmed',
        ];

        $agencyLocator = $this->http->FindSingleNode("//text()[normalize-space()='Agency Locator:']/following::text()[normalize-space()][1]", null, true, "/^[-A-Z\d]{5,}$/");
        $email->ota()->confirmation($agencyLocator);

        //#################
        //##   FLIGHT   ###
        //#################

        $f = $email->add()->flight();
        $f->general()->noConfirmation();

        $travellers = $ffNumbers = [];

        $xpath = "//img[contains(@src, 'app.cbtat.com/images/airtinerary/air.png')]/ancestor::tr[1]/..";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $date = strtotime($this->http->FindSingleNode("./tr[1]/td[2]", $root));

            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("(./tr[2]//text()[normalize-space()])[1]", $root))
                ->number($this->http->FindSingleNode("(./tr[2]//text()[normalize-space()])[2]", $root, true, "/\d+/"))
                ->confirmation($this->getField("Confirmation:", $root))
            ;

            $s->departure()
                ->name($this->http->FindSingleNode("tr[2]/td[2]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#"))
                ->code($this->http->FindSingleNode("tr[2]/td[2]", $root, true, "#\(([A-Z]{3})\)#"))
                ->date(strtotime(preg_replace("/(\d+)(\d{2}\s+[AaPp][Mm])/", "$1:$2", $this->http->FindSingleNode("tr[4]/td[1]", $root)), $date))
            ;

            $s->arrival()
                ->name($this->http->FindSingleNode("tr[2]/td[3]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#"))
                ->code($this->http->FindSingleNode("tr[2]/td[3]", $root, true, "#\(([A-Z]{3})\)#"))
                ->date(strtotime(preg_replace("/(\d+)(\d{2}\s+[AaPp][Mm])/", "$1:$2", $this->http->FindSingleNode("tr[4]/td[2]", $root)), $date))
            ;

            $s->extra()
                ->status($this->http->FindSingleNode("(./tr[2]//text()[normalize-space()])[3]", $root, true, "/^(?:{$patterns['statusVariants']})$/i"))
                ->aircraft($this->getField("Aircraft:", $root))
                ->seats($this->http->FindNodes(".//text()[normalize-space()='Seat']/ancestor::tr[1]/following-sibling::tr/td[2]", $root))
                ->duration($this->getField("Duration:", $root), true, true)
                ->meal($this->http->FindSingleNode("descendant::tr[ *[6][normalize-space()='Meal'] ]/following-sibling::tr[*[5]]/*[6]", $root, false), false, true)
            ;

            $travellers[] = $this->http->FindSingleNode("descendant::tr[ *[1][normalize-space()='Passenger Name'] ]/following-sibling::tr[*[5]]/*[1]", $root, true, "/^{$patterns['travellerName']}$/u");

            $ffNumber = $this->http->FindSingleNode("descendant::tr[ *[5][normalize-space()='Frequent Flyer #'] ]/following-sibling::tr[*[5]]/*[5]", $root, true, "/^[-A-Z\d ]{5,}$/");

            if ($ffNumber) {
                $ffNumbers[] = $ffNumber;
            }

            $class = $this->http->FindSingleNode(".//text()[normalize-space()='Class']/ancestor::tr[1]/following-sibling::tr[1]/td[3]", $root);

            if (preg_match("/(?<cabin>.*?)\s+\((?<code>[A-Z]{1,2})\)/", $class, $m)) {
                // Economy (Q)
                $s->extra()->cabin($m['cabin'])->bookingCode($m['code']);
            } elseif (preg_match("/^[A-Z]{1,2}$/", $class)) {
                // Q
                $s->extra()->bookingCode($class);
            } elseif ($class) {
                // Economy
                $s->extra()->cabin($class);
            }

            $operator = $this->http->FindSingleNode(".//text()[contains(normalize-space(),'OPERATED BY')]", $root, true, "/OPERATED BY[\s\/]+(.{2,}?)(?:\s+DBA|$)/i");
            $s->airline()->operator($operator, false, true);
        }

        if (count($travellers)) {
            $f->general()->travellers(array_unique($travellers));
        }

        if (count($ffNumbers)) {
            $f->program()->accounts(array_unique($ffNumbers), false);
        }

        $ticketNumbers = array_filter($this->http->FindNodes("//tr[ *[1][normalize-space()='Ticket #'] ]/following-sibling::tr[*[8]]/*[1]", null, "/^\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}$/"));

        if (count($ticketNumbers)) {
            $f->issued()->tickets($ticketNumbers, false);
        }

        $totalPrice = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),'Total Charged:')][last()]", null, true, "/Total Charged:\s*(.+)$/");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $825.60
            $f->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency']);
        }

        //##############
        //##   CAR   ###
        //##############

        $xpath = "//img[contains(@src, 'app.cbtat.com/images/airtinerary/car.png')]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $car = $email->add()->rental();

            $car->general()->confirmation(re("#\w+#", $this->getField("Confirmation:", $root)));

            $pickUpDate = strtotime(
                $this->http->FindSingleNode("./tr[5]/td[1]", $root, true, "#\w+\s+(\w+\s+\d+,\s+\d{4})#") . ', ' .
                preg_replace("#(\d+)(\d{2}\s+[AP]M)#", "$1:$2", $this->http->FindSingleNode("./tr[4]/td[1]", $root))
            );

            $car->pickup()
                ->date($pickUpDate)
                ->location($this->http->FindSingleNode("(./tr[2]/td[2]//text()[normalize-space()])[1]", $root))
                ->phone($this->getField("Pick-up Phone:", $root))
                ->openingHours($this->getField("Pick-Up Location Hours:", $root))
            ;

            $dropOffDate = strtotime(
                $this->http->FindSingleNode("./tr[5]/td[2]", $root, true, "#\w+\s+(\w+\s+\d+,\s+\d{4})#") . ', ' .
                preg_replace("#(\d+)(\d{2}\s+[AP]M)#", "$1:$2", $this->http->FindSingleNode("./tr[4]/td[2]", $root))
            );

            $car->dropoff()
                ->date($dropOffDate)
                ->location($this->http->FindSingleNode("(./tr[2]/td[3]//text()[normalize-space()])[1]", $root))
                ->phone($this->getField("Drop-off Phone:", $root))
                ->openingHours($this->getField("Drop-Off Location Hours:", $root))
            ;

            $company = $this->http->FindSingleNode("(./tr[2]//text()[normalize-space()])[1]", $root, true, "#(.*?)\s+\(#");

            if (($code = $this->normalizeProvider($company))) {
                $car->program()->code($code);
            } else {
                $car->extra()->company($company);
            }

            $carStatus = $this->http->FindSingleNode("(./tr[2]//text()[normalize-space()])[2]", $root, true, "/^(?:{$patterns['statusVariants']})$/i");

            if ($carStatus) {
                $car->general()->status($carStatus);
            }

            $car->car()->type($this->getField("Car Type:", $root));

            $car->general()->traveller($this->getField("Renter Name:", $root));
        }

        //################
        //##   HOTEL   ###
        //################

        $xpath = "//img[contains(@src, 'app.cbtat.com/images/airtinerary/hotel.png')]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()->confirmation($this->getField("Confirmation:", $root));

            $h->hotel()
                ->name($this->http->FindSingleNode("(./tr[2]//text()[normalize-space()])[1]", $root, true, "#(.*?)\s+\(#"))
                ->address($this->http->FindSingleNode("(./tr[2]//text()[normalize-space()])[1]/following::a[1]", $root))
                ->phone($this->getField("Phone:", $root))
            ;

            $hStatus = $this->http->FindSingleNode("(./tr[2]//text()[normalize-space()])[2]", $root, true, "/^(?:{$patterns['statusVariants']})$/i");

            if ($hStatus) {
                $h->general()->status($hStatus);
            }

            $checkInDate = strtotime(
                $this->http->FindSingleNode("tr[2]/td[2]", $root, true, "/\w+\s+(\w+\s+\d+,\s+\d{4})/") . ', ' .
                preg_replace("/(\d+)(\d{2}\s+[AaPp][Mm])/", "$1:$2", $this->http->FindSingleNode("tr[4]/td[1]", $root))
            );
            $checkOutDate = strtotime(
                $this->http->FindSingleNode("tr[2]/td[3]", $root, true, "/\w+\s+(\w+\s+\d+,\s+\d{4})/") . ', ' .
                preg_replace("/(\d+)(\d{2}\s+[AaPp][Mm])/", "$1:$2", $this->http->FindSingleNode("tr[4]/td[2]", $root))
            );
            $h->booked()
                ->checkIn($checkInDate)
                ->checkOut($checkOutDate)
                ->guests($this->cell("Number of Guests", $root))
                ->kids($this->cell("Number of Children", $root))
                ->rooms($this->cell("Number of Rooms", $root))
            ;

            $h->general()->traveller($this->cell("Guest Name", $root));

            $roomType = $this->getField("Room:", $root);
            $roomRate = $this->getField("Rate Info:", $root);

            if ($roomType || $roomRate) {
                $room = $h->addRoom();
                $room->setType($roomType, false, true);
                $room->setRate($roomRate, false, true);
            }

            $cancellation = $this->getField("Cancellation Policy:", $root);
            $h->general()->cancellation($cancellation);

            if (preg_match("/^CXL\s+(?<prior>\d{1,3}\s*HRS?)\s+PRIOR TO ARRIVAL TO AVOID 1NT PNLTY/i", $cancellation, $m)
                || preg_match("/^CXL\s+(?<prior>\d{1,3}\s*HRS?)\s+PRIOR TO HOTEL CHECK IN TIME/i", $cancellation, $m)
                || preg_match("/^CXL\s+(?<prior>\d{1,3}\s*DAYS?)\s+PRIOR TO ARRIVAL/i", $cancellation, $m)
                || preg_match("/^(?<hour>{$patterns['time']})\s+CANCELL? DAY OF ARRIVAL$/i", $cancellation, $m)
            ) {
                if (empty($m['prior'])) {
                    $m['prior'] = '0 hours';
                }

                if (empty($m['hour'])) {
                    $m['hour'] = '00:00';
                }
                $m['prior'] = preg_replace("/^(\d{1,3})\s*HRS?$/i", '$1 hours', $m['prior']);
                $h->booked()->deadlineRelative($m['prior'], $m['hour']);
            }

            $fGuestNumber = $this->getField("Frequent Guest Number:", $root);

            if ($fGuestNumber) {
                $h->program()->account($fGuestNumber, false);
            }
        }
    }

    private function getField($str, $root = null)
    {
        return $this->http->FindSingleNode(".//text()[normalize-space(.)='{$str}']/following::text()[normalize-space(.)][1]", $root);
    }

    private function cell($name, $root = null)
    {
        $cell = ".//*[(self::td or self::th) and normalize-space(.)='{$name}']";
        $num = count($this->http->FindNodes("{$cell}/preceding-sibling::*", $root)) + 1;

        return $this->http->FindSingleNode("{$cell}/ancestor::tr[1]/following-sibling::tr[1]/*[self::td or self::th][{$num}]", $root);
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

    /**
     * @param string|null $string Provider keyword
     *
     * @return string|null Provider code
     */
    private function normalizeProvider(?string $string): ?string
    {
        $string = trim($string);
        $providers = [
            'avis'         => ['Avis'],
            'alamo'        => ['Alamo'],
            'perfectdrive' => ['Budget'],
            'dollar'       => ['Dollar'],
            'rentacar'     => ['Enterprise'],
            'europcar'     => ['Europcar'],
            'national'     => ['National'],
            'thrifty'      => ['Thrifty'],
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
}
