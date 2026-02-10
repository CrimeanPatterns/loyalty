<?php

namespace AwardWallet\Engine\costravel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "costravel/it-115584660.eml, costravel/it-115681606.eml, costravel/it-115798377.eml, costravel/it-119129124.eml, costravel/it-119327678.eml, costravel/it-127350700.eml, costravel/it-133665015.eml, costravel/it-147656404.eml, costravel/it-160670789.eml, costravel/it-160687643.eml, costravel/it-162316923.eml, costravel/it-162543455.eml, costravel/it-323941739.eml";

    private $detectFrom = "customercare@costcotravel.com";
    private $detectSubject = [
        'en' => [': Booking #', ': Final documents for your car rental reservation'],
    ];
    private $detectCompany = 'Costco Travel';
    private $detectBody = [
        "en" => ["Costco Travel Confirmation Number", "Costco Travel account"],
    ];

    private $lang = "en";

    private static $dictionary = [
        "en" => [
            'Costco Travel Confirmation Number:' => ['Costco Travel Confirmation Number:', 'Costco Travel: Confirmation #'],
            'Rental Car Confirmation Number:'    => ['Rental Car Confirmation Number:', 'Enterprise Confirmation Number:', 'Alamo Confirmation Number:', 'Budget Confirmation Number:', 'Confirmation Number:'],
            "Total Package Price"                => ["Total Package Price", "Total Rental Price"],
            'or similar'                         => ['or similar', 'or Similar'],
            'Night Cruise from'                  => ['Night Cruise', 'Night Cruise from'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        foreach($this->reBody2 as $lang=>$re){
        //			if(strpos($this->http->Response["body"], $re) !== false){
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseHtml($email, $parser);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers['subject'])) {
            return false;
        }

        if ($this->detectEmailFromProvider($headers['from']) === false
                && stripos($headers['subject'], 'Costco Travel') === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            foreach ($detectSubject as $dSubject) {
                if (stripos($headers['subject'], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response["body"];

        if (strpos($body, $this->detectCompany) === false && stripos($parser->getSubject(), $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            foreach ($dBody as $reBody) {
                if (strpos($body, $reBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email, PlancakeEmailParser $parser)
    {
        $this->parseFlights($email);
        $this->parseHotels($email);
        $this->parseRentals($email);
        $this->parseTransfers($email);
        $this->parseRails($email);
        $this->parseCruises($email);

        // Travel Agency
        $email->obtainTravelAgency();

        $conf = $this->nextText($this->t("Costco Travel Confirmation Number:"), null, "/^\s*([\dA-Z]{5,})\s*$/");

        if (empty($conf)) {
            $conf = $this->re("/{$this->t("Costco Travel: Booking #")}\s*([\dA-Z]{5,})\s*$/", $parser->getSubject());
        }

        $email->ota()
            ->confirmation($conf, 'Costco Travel Confirmation Number');

        // Travel Agency Account
        $membershipNumber = $this->http->FindSingleNode('//text()[' . $this->eq("Costco Membership #:") . ']/following::text()[normalize-space(.)][1]', null, true, "/^\s*(\d{5,})\s*$/");

        if (!empty($membershipNumber)) {
            $email->ota()
                ->account($membershipNumber, false);

            if (stripos(implode(' ', $parser->getFrom()), 'customercare@costcotravel.com') !== false && count($email->getItineraries()) > 0) {
                // не убирать условие count($email->getItineraries()) > 0,
                // без него в парсер попадают письма другого формата, резервации не парсятся и остается только стейтмент
                $st = $email->createStatement();
                $st
                    ->setMembership(true)
                    ->setNoBalance(true)
                    ->addProperty('Login', $membershipNumber);
                $name = $this->nextText($this->t("Member Name:"));

                if (!empty($name)) {
                    $st->addProperty('Name', $name);
                }
            }
        }

        // Price
        $totalPrice = $this->http->FindSingleNode("(.//text()[{$this->eq(["Total Package Price", "Total Rental Price", "Total Price"])}])[last()]/following::text()[normalize-space(.)][1]");
        // $16,125.62 | $X,XXX.67 | $2,441.42 CAD
        if (preg_match('#^\s*([^\d\s]*)?(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b#', $totalPrice, $m)
                || preg_match('#^\s*(?<currency>[^\d\s]*)\s*(?<amount>\d[,.\'\d]*)\s*$#', $totalPrice, $m)
            || preg_match('#^\s*(?<currency>[A-Z]{2}\s*\S)\s*(?<amount>\d[,.\'\d]*)\s*$#', $totalPrice, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $email->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
        }

        $basePrice = $this->nextText(["Base Package Price", "Base Car Rental"]);
        // $16,125.62 | $X,XXX.67 | $2,441.42 CAD
        if (preg_match('#^\s*([^\d\s]*)?(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b#', $basePrice, $m)
            || preg_match('#^\s*(?<currency>[^\d\s]*)\s*(?<amount>\d[,.\'\d]*)\s*$#', $basePrice, $m)
            || preg_match('#^\s*(?<currency>[A-Z]{2}\s*\S)\s*(?<amount>\d[,.\'\d]*)\s*$#', $basePrice, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $email->price()
                ->cost(PriceHelper::parse($m['amount'], $currency))
            ;
        }

        if (empty($basePrice) && count($email->getItineraries()) == 1) {
            $basePrice = $this->nextText(["Cruise Package Price", "Base Car Rental"]);

            if (preg_match('#^\s*([^\d\s]*)?(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b#', $basePrice, $m)
                || preg_match('#^\s*(?<currency>[^\d\s]*)\s*(?<amount>\d[,.\'\d]*)\s*$#', $basePrice, $m)
                || preg_match('#^\s*(?<currency>[A-Z]{2}\s*\S)\s*(?<amount>\d[,.\'\d]*)\s*$#', $basePrice, $m)
            ) {
                $currency = $this->currency($m['currency']);
                $email->price()
                    ->cost(PriceHelper::parse($m['amount'], $currency));
            }
        }

        if (!empty($basePrice) || !empty($totalPrice)) {
            $taxes = $this->nextText(["Taxes and Fees*", "Taxes and Fees"]);
            // $16,125.62 | $X,XXX.67 | $2,441.42 CAD
            if (preg_match('#^\s*([^\d\s]*)?(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b#', $taxes, $m)
                || preg_match('#^\s*(?<currency>[^\d\s]*)\s*(?<amount>\d[,.\'\d]*)\s*$#', $taxes, $m)
                || preg_match('#^\s*(?<currency>[A-Z]{2}\s*\S)\s*(?<amount>\d[,.\'\d]*)\s*$#', $taxes, $m)
            ) {
                $currency = $this->currency($m['currency']);
                $email->price()
                    ->tax(PriceHelper::parse($m['amount'], $currency));
            }
        }

        // examples: Mrs. EMILY ST MARIE    |    Mr. JEFFREY ST. MARIE
        $travellers = array_filter($this->http->FindNodes("//td[not(.//tr) and starts-with(normalize-space(),'Age at the time of travel')]/preceding-sibling::td[normalize-space()][1]", null, "/^\s*(?:[[:alpha:]]{2,6}[.]\s+)?([-.\'’[:alpha:] ]+)\s*(?:[A-Z\.])?$/u"));

        if (count($travellers) == 0) {
            $travellers = array_filter(str_replace(['Mr. ', 'Mrs. ', 'Ms. ', 'Master '], '', array_unique($this->http->FindNodes("//text()[normalize-space()='Full Name']/ancestor::td[1]", null, "/{$this->opt($this->t('Full Name'))}\s*(.+)/"))));
        }

        if (count($travellers) == 0) {
            $travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Member Name')]/following::text()[normalize-space()][1]", null, "/([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])/");
        }

        $bookingDate = $this->normalizeDate($this->nextText("Booking Date:"));

        if (!empty($travellers) || !empty($bookingDate)) {
            foreach ($email->getItineraries() as $value) {
                if (!empty($travellers)) {
                    $value->general()->travellers($travellers, true);
                }

                if (!empty($bookingDate)) {
                    $value->general()->date($bookingDate);
                }
            }
        }

        return $email;
    }

    private function parseRails(Email $email)
    {
        $xpath = "//text()[contains(normalize-space(), 'Rail to')]/following::text()[contains(normalize-space(), 'Average Travel Time:')][1]/ancestor::table[2]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            return;
        }

        $train = $email->add()->train();

        $confirmation = array_filter($this->http->FindNodes($xpath, null, "/{$this->opt($this->t('Confirmation Number:'))}\s*([A-Z\d]+\/PNR\-[A-Z\d]{6})/u"));

        if (count($confirmation) > 0) {
            foreach ($confirmation as $conf) {
                $train->general()
                    ->confirmation($conf);
            }
        } else {
            $train->general()
                ->noConfirmation();
        }

        foreach ($nodes as $root) {
            $s = $train->addSegment();

            $pointInfo = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'to')][1]", $root);

            if (preg_match("/^(?<depName>.+)\s+to\s+(?<arrName>.+)\s+\-\s+(?<class>.+\s+Class)$/", $pointInfo, $m)) {
                $s->departure()
                    ->name($m['depName']);

                $s->arrival()
                    ->name($m['arrName']);

                $s->extra()
                    ->cabin($m['class']);
            }

            $trainNumber = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Train Number:'][1]/following::text()[normalize-space()][1]", $root);

            if (!empty($trainNumber)) {
                $s->setNumber($trainNumber);
            }

            $s->extra()
                ->duration($this->http->FindSingleNode("./descendant::text()[normalize-space()='Average Travel Time:'][1]/following::text()[normalize-space()][1]", $root));

            $depNamePart = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Depart:'][1]/ancestor::td[1]/following::td[1]/descendant::text()[normalize-space()][2]");

            if (!empty($depNamePart)) {
                $s->departure()
                    ->name($depNamePart . ', ' . $s->getDepName());
            }

            $arrNamePart = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Arrive:'][1]/ancestor::td[1]/following::td[1]/descendant::text()[normalize-space()][2]");

            if (!empty($arrNamePart)) {
                $s->arrival()
                    ->name($arrNamePart . ', ' . $s->getArrName());
            }

            $depDate = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Departing on:'][1]/following::text()[normalize-space()][1]", $root);
            $arrDate = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Arriving on:'][1]/following::text()[normalize-space()][1]", $root);

            if (!empty($depDate) && !empty($arrDate)) {
                $s->departure()
                    ->date($this->normalizeDate($depDate));

                $s->arrival()
                    ->date($this->normalizeDate($arrDate));
            } elseif (empty($depDate) && empty($arrDate) && $this->http->XPath->query("./descendant::text()[contains(normalize-space(), 'First Choice:')]")->length > 0) {
                $email->removeItinerary($train);
            }
        }
    }

    private function parseFlights(Email $email): void
    {
        $xpath = "//tr[count(.//img) = 1 and *[2][contains(translate(., '0123456789', '##########'), '#:##') and contains(., '|')]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            return;
        }
        $this->logger->debug("[XPATH-flight]: " . $xpath);

        $f = $email->add()->flight();

        // General
        $confs = array_unique($this->nextTexts("Flight Confirmation Number:"));

        if (count($confs) > 0) {
            foreach ($confs as $conf) {
                $f->general()
                    ->confirmation($conf);
            }
        } else {
            $f->general()
                ->noConfirmation();
        }

        // Segments
        foreach ($nodes as $root) {
            $dateStr = $this->http->FindSingleNode("preceding::tr[not(.//tr)][normalize-space()][1]/td[normalize-space()][1]", $root);
            $date2 = null;

            if (!preg_match("/Layover/i", $dateStr)) {
                $date = $this->normalizeDate($this->re("/:\s*(.+?)?(?:[\+\-] ?\d+)?\s*$/", $dateStr));
            } elseif (!empty($date) && isset($s) && !empty($s->getArrDate()) && preg_match("/Layover:\s*(\d+)h\s*(\d+)m/", $dateStr, $m)) {
                $date2 = strtotime(" +" . $m[1] . "hours +" . $m[2] . "minutes", $s->getArrDate());
            }
            $s = $f->addSegment();

            if (empty($date)) {
                $f->removeSegment($s);

                return;
            }

            // Airline
            $s->airline()
                ->name($this->http->FindSingleNode("td[1]/descendant::text()[normalize-space()][1]", $root, null, "/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d{1,5}\s*$/"))
                ->number($this->http->FindSingleNode("td[1]/descendant::text()[normalize-space()][1]", $root, null, "/^\s*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\s*$/"))
            ;

            $info = implode("\n", $this->http->FindNodes("*[2]//td[not(.//td)][position() < last()]//text()[normalize-space()]", $root));
            /*
                 Philadelphia (PHL) to Los Angeles (LAX)
                 6:00am - 8:50am
                 Economy (O) | Seats: 33F, 33D
            */
            $regexp = "/^\s*(?<dName>.+)\s*\((?<dCode>[A-Z]{3})\)\s*to\s*(?<aName>.+)\s*\((?<aCode>[A-Z]{3})\)(?:\s*Next day\s+)?\s*(?<dTime>\d{1,2}:\d{2}(?:\s*[aApP\.]+[mM\.]+)?)\s*-\s*(?<aTime>\d{1,2}:\d{2}(?:\s*[aApP\.]+[mM\.]+)?)(?<nextDay>\s*Next day)?\n(?<cabin>\D+)?\s*\((?<BookingCode>([A-Z]))\)?/";

            if (preg_match($regexp, $info, $m)) {
                if (!empty($date2) && $date2 === strtotime($m['dTime'], strtotime('00:00', $date2))) {
                    $date = $date2;
                }
                // Departure
                $s->departure()
                    ->name($m['dName'])
                    ->code($m['dCode'])
                    ->date(strtotime($m['dTime'], $date))
                ;

                // Arrival
                $s->arrival()
                    ->name($m['aName'])
                    ->code($m['aCode'])
                    ->date(strtotime($m['aTime'], $date))
                ;

                if (!empty($s->getArrDate()) && !empty($m['nextDay'])) {
                    $days = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), 'Return')][1]/ancestor::tr[1]/td[1]", $root, true, "/[+](\d+)/");

                    if (empty($days)) {
                        $days = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(), 'Depart')][1]/ancestor::tr[1]/td[1]", $root, true, "/[+](\d+)/");
                    }
                    $s->arrival()
                        ->date(strtotime("+{$days} day", $s->getArrDate()));
                }
            }

            // Extra
            if (preg_match("/\d{1,2}:\d{2}[\s\S]+?\n\s*([[:alpha:] ]+)\s*\(([A-Z]{1,2})\)\n/", $info, $m)) {
                // Departure
                $s->extra()
                    ->cabin($m[1])
                    ->bookingCode($m[2])
                ;
            }

            if (preg_match("/Seats:\s*(\d{1,3}[A-Z](?:\s*,\s*\d{1,3}[A-Z])*)(?:\n|$)/", $info, $m)) {
                // Departure
                $s->extra()
                    ->seats(array_map('trim', explode(",", $m[1])))
                ;
            }

            $s->extra()
                ->aircraft($this->http->FindSingleNode("td[1]/descendant::text()[normalize-space()][3]", $root))
                ->duration($this->http->FindSingleNode("*[2]//td[not(.//td)][last()]", $root, true, "/^(?:\s*\d*\s*(?:h|m)\s*)+\s*$/i"));
        }
    }

    private function parseCruises(Email $email): void
    {
        $xpath = "//tr[*[1][count(.//img) = 1 and not(normalize-space())] and *[2][{$this->contains($this->t("Night Cruise from"))}]]";
        //tr[*[1][count(.//img) = 1] and *[2][contains(normalize-space(.), "Night Cruise from")]]
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH-cruises]: " . $xpath);

        if ($nodes->length == 0) {
            return;
        }

        if ($nodes->length > 1) {
            $c = $email->add()->cruise();
            $this->logger->debug("no examples for this case");

            return;
        }
        $this->logger->debug("[XPATH-cruises]: " . $xpath);

        $c = $email->add()->cruise();

        // General
        $company = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $nodes->item(0));
        $this->logger->error($company);

        if (!empty($company)) {
            $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Cruise']/following::text()[" . $this->eq($company) . "][1]/following::text()[normalize-space()][1][" . $this->eq("Confirmation Number:") . "][1]/following::text()[normalize-space()][1]");

            if (empty($confirmation)) {
                $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Cruise']/preceding::text()[" . $this->eq($company) . "][1]/following::text()[normalize-space()][1][" . $this->eq("Confirmation Number:") . "][1]/following::text()[normalize-space()][1]");
            }

            if (empty($confirmation)) {
                $confirmation = $this->http->FindSingleNode("//text()[normalize-space()='Cruise']/preceding::text()[" . $this->starts($company . ' Confirmation Number:') . "][1]/ancestor::tr[1]", null, true, "/\:\s*([A-Z\d]+)/");
            }

            $c->general()
                ->confirmation($confirmation);
        }

        // Details
        $c->details()
            ->description($this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $nodes->item(0)))
            ->room($this->nextText("Stateroom Number:"))
        ;
        // Segments
        $sXpath = "//tr[count(*)=3 and *[3][(contains(., '(A)') and count(*[contains(., '(A)')]) < 2) or (contains(., '(D)') and count(*[contains(., '(D)')]) < 2 )]]";
        $nodes = $this->http->XPath->query($sXpath);
        $seg = ['setAboard' => false, 'setAshore' => false];
        $overnight = false;

        foreach ($nodes as $i => $root) {
            $dateStr = $this->http->FindSingleNode("*[1]", $root);

            if (empty($dateStr)) {
                $this->logger->debug("empty dateStr");
                $s = $c->addSegment();

                break;
            }

            $name = $this->http->FindSingleNode("*[2]", $root);

            $dTime = $this->http->FindSingleNode("*[3]//text()[contains(., 'D')]", $root, true, "/^(.+?)\s*\(/");
            $aTime = $this->http->FindSingleNode("*[3]//text()[contains(., 'A')]", $root, true, "/^(.+?)\s*\(/");

            if ($overnight === true && isset($s) && $name === $s->getName() && empty($aTime)) {
                $s->setAboard($this->normalizeDate($dateStr . ', ' . $dTime));
                $overnight = false;

                continue;
            }

            $s = $c->addSegment();
            $s->setName($name);

            if ($i !== 0) {
                if (!empty($aTime)) {
                    $s->setAshore($this->normalizeDate($dateStr . ', ' . $aTime));
                } else {
                    $this->logger->debug("get confused in segments(setAshore)");
                    $s = $c->addSegment();
                }
            }

            if ($i !== $nodes->length - 1) {
                if (!empty($dTime)) {
                    $s->setAboard($this->normalizeDate($dateStr . ', ' . $dTime));
                } elseif (!empty($s->getAshore())) {
                    $overnight = true;
                } else {
                    $this->logger->debug("get confused in segments(setAboard)");
                    $s = $c->addSegment();
                }
            }
        }
    }

    private function parseHotels(Email $email): void
    {
        $xpath = "//text()[normalize-space() = 'Check-In:']/ancestor::*[" . $this->contains(['Lead Passenger', 'Lead Traveler', 'Adult']) . "][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH-hotel]: " . $xpath);
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            if (preg_match("/^\s*Room\s*\d+\s*of\s*\d/", $root->nodeValue)) {
                $this->logger->debug("go to costravel/ZConfirmation");

                return;
            }

            // General
            $conf = $this->nextText('Hotel Confirmation Number:');

            if (preg_match("/^[A-Z]\s*\d{6}\s*\d$/", $conf)) {
                $conf = str_replace(' ', '', $conf);
            }

            if (preg_match("/^([A-Z]+\s*\d{6}\s*\d)\s*\\/{2}\s*(\d{5,})\s*$/", $conf, $m)) {
                $conf = str_replace(' ', '', $m[1]);
                $conf2 = str_replace(' ', '', $m[2]);
            }

            if (!empty($conf)) {
                if (!empty($conf2)) {
                    $h->general()->confirmation($conf, null, true);
                    $h->general()->confirmation($conf2);
                } else {
                    $h->general()->confirmation(str_replace([' ', '#'], '', $conf));
                }

                if ($this->http->XPath->query("descendant::text()[normalize-space()='Hotel Confirmation Number:']/preceding::text()[normalize-space()][1][contains(normalize-space(),'Canceled on')]", $root)->length > 0) {
                    $h->general()->cancelled()->status('Canceled');
                }
            } else {
                $h->general()->noConfirmation();
            }

            $cancellation = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Cancellation Policy']/following::text()[normalize-space()][1]/ancestor::tr[1]");

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation($cancellation);
            }

            $xpathHotel = "descendant::tr[ count(*)=2 and *[1][normalize-space()='' and descendant::img] ]/*[2][{$this->contains($this->t('Check-In:'))}]";

            $addressText = $this->htmlToText($this->http->FindHTMLByXpath($xpathHotel . "/descendant::h3[ descendant::text()[normalize-space()] ][1]/following::*[normalize-space()][1]", null, $root));
            $address = preg_replace('/\s+/', ' ', $this->re("/^\s*(.{3,}?)(?:\s*Ph:|$)/s", $addressText));

            // Hotel
            $hotelName = $this->http->FindSingleNode($xpathHotel . "/descendant::h3[ descendant::text()[normalize-space()] ][1]", $root);

            if (empty($hotelName)) {
                $hotelName = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root);
            }

            if (empty($address)) {
                $address = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $root);
            }

            $phone = $this->http->FindSingleNode($xpathHotel . "/descendant::text()[normalize-space()='Ph:']/following::text()[normalize-space()][1]", $root, true, "/^[+(\d][-+. \d)(]{5,}[\d)\-]$/");

            if (empty($phone)) {
                $phone = $this->http->FindSingleNode("./descendant::text()[string-length()>3][not(contains(normalize-space(), 'Ph'))][3]", $root);
            }
            $h->hotel()
                ->name($hotelName)
                ->address($address)
                ->phone($phone, false, true)
            ;

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate($this->nextText("Check-In:", $root)))
                ->checkOut($this->normalizeDate($this->nextText("Check-Out:", $root)))
            ;

            $guests = array_filter($this->http->FindNodes("descendant::text()[starts-with(translate(normalize-space(),'1234567890','dddddddddd'), 'Room d:')]/following::text()[normalize-space()][2]", $root, "/\b(\d{1,2})\s*adult/i"));

            if (count($guests)) {
                $h->booked()->guests(array_sum($guests));
            }
            $kids = array_filter($this->http->FindNodes("descendant::text()[starts-with(translate(normalize-space(),'1234567890','dddddddddd'), 'Room d:')]/following::text()[normalize-space()][2]", $root, "/\b(\d{1,2})\s*child/i"));

            if (count($kids)) {
                $h->booked()->kids(array_sum($kids));
            }

            // Rooms
            $roomTypes = array_filter($this->http->FindNodes(".//text()[starts-with(translate(normalize-space(),'1234567890','dddddddddd'), 'Room d:')]", $root, "#\d+\s*:\s*(.+)#"));

            foreach ($roomTypes as $type) {
                $h->addRoom()->setType($type);
            }

            $roomCount = count($this->http->FindNodes("./descendant::text()[starts-with(normalize-space(), 'Room ')]", $root));

            if ($roomCount > 0) {
                $h->booked()
                    ->rooms($roomCount);
            }

            $this->detectDeadLine($h);
        }
    }

    private function parseRentals(Email $email): void
    {
        $xpath = "//text()[normalize-space()='Pick-up:']/ancestor::*[contains(normalize-space(), 'Drop-off:')][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $this->logger->debug("[XPATH-rental]: " . $xpath);
        }

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            // General
            $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Rental Car Confirmation Number:'))}]/following::text()[normalize-space()][1]", $root, true, '/^\s*([-A-Z\d]{5,})\s*$/');

            if (empty($confirmation)) {
                $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rental Car Confirmation Number:'))}]/following::text()[normalize-space()][1]", null, true, '/^\s*([-A-Z\d]{5,})\s*$/');
            }

            if (empty($confirmation)) {
                $confirmation = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Rental Car Confirmation Number:'))}]/following::text()[normalize-space()][1]", null, true, '/\s*([-A-Z\d]{5,})\s*$/');
            }

            if (!empty($confirmation)) {
                $r->general()->confirmation($confirmation);
            } elseif (empty($this->http->FindSingleNode("//text()[{$this->contains($this->t('Confirmation'))}]"))) {
                $r->general()->noConfirmation();
            }

            /*
                Nov 06, 2021 Time: 10:39 AM
                Honolulu
                3055 N Nimitz Hwy
                Honolulu, HI, 96819
                Ph:
                8449130736
            */
            $regexp = "/^\s*(?<date>.+)\s*Time:\s*(?<time>\d{1,2}:\d{2}.*)\n\s*(?<address>[\s\S]+?)(\n\s*Ph:\s*(?<phone>[\d \-+\(\)]{5,}))?\s*$/";
            $regexp2 = "/^\s*(?<date>.+)\s*(?<time>\d{1,2}:\d{2}\s*a?\.?p?\.?m\.?)\n\s*(?<address>[\s\S]+?)(\n\s*Ph:\s*(?<phone>[\d \-+\(\)]{5,}))?\s*\n(?<hours>.+)$/";

            // Pick Up
            $pickUpText = implode("\n", $this->http->FindNodes(".//td[{$this->eq($this->t('Pick-up:'))}]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match($regexp, $pickUpText, $m) || preg_match($regexp2, $pickUpText, $m)) {
                $r->pickup()
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']))
                    ->location(preg_replace('/\s+/', ' ', trim($m['address'])))
                    ->phone(empty($m['phone']) ? null : $m['phone'], false, true);

                if (isset($m['hours'])) {
                    $r->pickup()
                        ->openingHours($m['hours']);
                }
            }

            // Drop Off
            $dropOffText = implode("\n", $this->http->FindNodes(".//td[{$this->eq($this->t('Drop-off:'))}]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match($regexp, $dropOffText, $m) || preg_match($regexp2, $dropOffText, $m)) {
                $r->dropoff()
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['time']))
                    ->location(preg_replace('/\s+/', ' ', trim($m['address'])))
                    ->phone(empty($m['phone']) ? null : $m['phone'], false, true);

                if (isset($m['hours'])) {
                    $r->dropoff()
                        ->openingHours($m['hours']);
                }
            }

            $xpathCarType = "descendant::img[contains(@src,'logos/car')]/preceding::text()[normalize-space()][1]/ancestor::h3";

            // Car
            $carType = $this->http->FindSingleNode($xpathCarType, $root, true, "/(.+?)(?:\s*Car)?\s*$/i");

            if (!empty($carType)) {
                $r->car()
                    ->type($carType);
            }

            $carModel = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('or similar'))}]", $root, true, "#^(.+?)\s+{$this->opt($this->t('or similar'))}#");

            if (!empty($carModel)) {
                $r->car()
                    ->model($carModel);
            }

            $image = $this->http->FindSingleNode(".//img[contains(@src, 'vehicle')]/@src");

            if (!empty($image)) {
                $r->car()
                    ->image($image);
            }

            $company = $this->http->FindSingleNode("descendant::img[ ancestor::*[normalize-space()][1][not(starts-with(normalize-space(),'Canceled on'))] ][1][contains(@src,'logo')]/@alt", $root);

            if (empty($company)) {
                $company = $this->http->FindSingleNode("./descendant::img[ ancestor::*[normalize-space()][1]][1]/@alt", $root);
            }
            $r->extra()
                ->company($company);
            $provider = $this->normalizeProvider($company);

            if (empty($r->getConfirmationNumbers()[0])) {
                $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number:'))}]/ancestor::tr[1]", null, true, "/^\s*{$r->getCompany()}\s*{$this->opt($this->t('Confirmation Number:'))}\s*([-A-Z\d]{5,})\s*$/");

                if (!empty($confirmation)) {
                    $r->general()
                        ->confirmation($confirmation);
                }
            }

            if (empty($r->getConfirmationNumbers()[0]) && $provider == 'rentacar') {
                $r->general()
                    ->noConfirmation();
            }

            if (!empty($provider)) {
                $r->setProviderCode($provider);
            }

            // Price
            $totalPrice = $this->nextText("Total Rental Price");
            // $16,125.62 | $X,XXX.67 | $2,441.42 CAD
            if (preg_match('#^\s*([^\d\s]*)?(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b#', $totalPrice, $m)
                || preg_match('#^\s*(?<currency>[^\d\s]*)\s*(?<amount>\d[,.\'\d]*)\s*$#', $totalPrice, $m)
            ) {
                $currency = $this->currency($m['currency']);
                $r->price()
                    ->total(PriceHelper::parse($m['amount'], $currency))
                    ->currency($currency);
            }

            $basePrice = $this->nextText("Base Car Rental");
            // $16,125.62 | $X,XXX.67 | $2,441.42 CAD
            if (preg_match('#^\s*([^\d\s]*)?(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b#', $basePrice, $m)
                || preg_match('#^\s*(?<currency>[^\d\s]*)\s*(?<amount>\d[,.\'\d]*)\s*$#', $basePrice, $m)
            ) {
                $currency = $this->currency($m['currency']);
                $r->price()
                    ->cost(PriceHelper::parse($m['amount'], $currency));
            }
            $taxes = $this->http->FindSingleNode("//text()[normalize-space()=\"Base Car Rental\"]/ancestor::tr[1]/following-sibling::tr[contains(normalize-space(), 'Taxes and Fees')]/descendant::td[normalize-space()][2]");
            // $16,125.62 | $X,XXX.67 | $2,441.42 CAD
            if (preg_match('#^\s*([^\d\s]*)?(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b#', $taxes, $m)
                || preg_match('#^\s*(?<currency>[^\d\s]*)\s*(?<amount>\d[,.\'\d]*)\s*$#', $taxes, $m)
            ) {
                $currency = $this->currency($m['currency']);
                $r->price()
                    ->tax(PriceHelper::parse($m['amount'], $currency));
            }

            $rAccount = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Loyalty Program Number:')]/ancestor::tr[1]", null, true, "/^\s*{$this->opt($this->t('Loyalty Program Number:'))}\s*{$r->getCompany()}\D+\s([A-z\d]{6,})$/su");

            if (!empty($rAccount)) {
                $r->program()
                    ->account($rAccount, false);
            }

            if ($confirmation && $this->http->XPath->query($xpathCarType . "/preceding::text()[normalize-space()][1][contains(normalize-space(),'Canceled on')]", $root)->length > 0) {
                // it-133665015.eml
                $r->general()->cancelled()->status('Canceled');
            }
        }
    }

    private function parseTransfers(Email $email): void
    {
        $xpath = "//text()[normalize-space()='Transfer Details:']/ancestor::tr[1]/ancestor::*[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            return;
        }

        $this->logger->debug("[XPATH-transfer]: " . $xpath);

        $t = $email->add()->transfer();
        // General
        $t->general()
            ->noConfirmation()
        ;

        foreach ($nodes as $root) {
            $info = implode("\n", $this->http->FindNodes(".//td[{$this->eq($this->t('Transfer Details:'))}]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]", $root));
//            $this->logger->debug('$info = '.print_r( $info,true));
            /*
                Dec 26, 2021 - 11:41 PM
                From Airport (HNL) to Hotel by Shared Transfer with Lei Greeting - Airport/Hotel/Airport Required
            */
            if (preg_match("/^\s*(?<date>.+)\s+.*?\bFrom\s*(\D+\s*)?Airport\s*\((?<from>[A-Z]{3})\) to (?<to>Hotel) /i", $info, $m)
            || preg_match("/^\s*(?<date>.+)\s+.*?\n(?:From|Arrival Transfer - )\s*(?<fromName>\D+\s*Airport)\s*(?:\((?<from>[A-Z]{3})\))? to (?<to>\D+)\n/i", $info, $m)) {
                $s = $t->addSegment();

                if (isset($m['from']) && !empty($m['from'])) {
                    $s->departure()
                        ->code($m['from']);
                } elseif (isset($m['fromName']) && !empty($m['fromName'])) {
                    $s->departure()
                        ->name($m['fromName']);
                }
                $s->departure()
                    ->date($this->normalizeDate($m['date']));

                $date = strtotime('00:00', $this->normalizeDate($m['date']));

                if (!empty($date)) {
                    $name = null;
                    $address = null;
                    $errorName = false;
                    $name2 = [];
                    $address2 = [];

                    foreach ($email->getItineraries() as $it) {
                        /** @var \AwardWallet\Schema\Parser\Common\Hotel $it */
                        if ($it->getType() == 'hotel') {
                            if ($date === $it->getCheckInDate()) {
                                if ($date >= $it->getCheckInDate() && $date <= $it->getCheckOutDate()) {
                                    $name2[] = $it->getHotelName();
                                    $address2[] = $it->getAddress();
                                }

                                if (!empty($name) && $name !== $it->getHotelName()) {
                                    $name = null;
                                    $errorName = true;

                                    break;
                                }
                                $name = $it->getHotelName();
                                $address = $it->getAddress();
                            }
                        }
                    }

                    if (empty($name) && empty(array_filter($name2))) {
                        $t->removeSegment($s);

                        continue;
                    }
                }
                $s->arrival()
                    ->name($name)
                    ->address($address)
                    ->noDate();
            } elseif (preg_match("/^\s*(?<date>.+)\s+.*?\bFrom (?<from>Hotel) to\s*(\D+)?Airport\s*\((?<to>[A-Z]{3})\) /", $info, $m)
                || preg_match("/^\s*(?<date>.+)\s+.*?\n(?:From|Departure Transfer - )\s*(\D+\s*Hotel)\s*(?:\((?<from>[A-Z]{3})\))? to (?<toName>\D+)in\D+\n/i", $info, $m)) {
                $s = $t->addSegment();

                $date = strtotime('00:00', $this->normalizeDate($m['date']));
                $name = null;
                $address = null;
                $errorName = false;
                $name2 = [];
                $address2 = [];

                if (!empty($date)) {
                    foreach ($email->getItineraries() as $it) {
                        /** @var \AwardWallet\Schema\Parser\Common\Hotel $it */
                        if ($it->getType() == 'hotel') {
                            if ($date > $it->getCheckInDate() && $date < $it->getCheckOutDate()) {
                                $name2[] = $it->getHotelName();
                                $address2[] = $it->getAddress();
                            }

                            if ($date === $it->getCheckOutDate()) {
                                if (!empty($name) && $name !== $it->getHotelName()) {
                                    $name = null;

                                    $errorName = true;
                                }
                                $name = $it->getHotelName();
                                $address = $it->getAddress();
                            }
                        }
                    }

                    if ($errorName === true) {
                        $name = null;
                        $address = null;
                    }

                    if (!empty($name) && !empty($address) && $errorName === false) {
                        $s->departure()
                            ->name($name)
                            ->address($address)
                            ->noDate();
                    } elseif (empty($name) && empty($address) && $this->http->XPath->query("//text()[normalize-space()='Itinerary Gap']")->length > 0) {
                        $t->removeSegment($s);
                    } elseif (empty($name) && empty($address) && $errorName === false && !empty($name2) && !empty($address2)) {
                        if (count(array_unique($name2)) == 1) {
                            $s->departure()
                                ->name($name2[0])
                                ->address($address2[0])
                                ->noDate();
                        }
                    }
                }

                if (isset($m['to']) && !empty($m['to'])) {
                    $s->arrival()
                        ->code($m['to']);
                } elseif (isset($m['toName']) && !empty($m['toName'])) {
                    $s->arrival()
                        ->name($m['toName']);
                }

                $s->arrival()
                    ->date($this->normalizeDate($m['date']));
            }
        }

        if (count($t->getSegments()) == 0) {
            $email->removeItinerary($t);
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
            'avis'         => ['Avis'],
            'alamo'        => ['Alamo'],
            'perfectdrive' => ['Budget'],
            'rentacar'     => ['Enterprise'],
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $str = preg_replace("/\,\s+(\d\:\d+\s*a?\.?p?\.?m\.?)$/", "$1", $str);

        $in = [
            //            "#^(\d+:\d+)\s*\|\s*[^\d\s]+,(\d+)-([^\d\s]+)-(\d{2})$#", //09:25| Tue,30-Dec-14
            //            '#^(\w+)\.\s*(\d{1,2}),\s*(\d{4})$#u', //Jan. 01, 2016
            '#^\s*([[:alpha:]]+)\s*(\d{1,2}),\s*(\d{4})\s*-\s*(\d+:\d+\s*[APM\.]*)\s*$#u', //Jan. 1, 2016 Time: 12:00PM   |   Oct 28, 2019 Time: 01:00PM
            '#^\s*([[:alpha:]]+)\s*(\d{1,2}),\s*(\d{4})\s*([\d\:]+\s*a?p?m)$#', //Apr 21, 2022 1, 2:00pm
            //            '#^\d{1,2}\/\d{1,2}\/\d{2}[ ]*\-[ ]*(\d{1,2})\/(\d{1,2})\/(\d{2})$#', // 6/18/19  - 7/27/19
            '#^(\w+)\s*(\d+)\,\s*(\d+)\s*at\s+([\d\:]+A?P?M)$#', //Apr 19, 2022 at 12:10PM
            '#^(\w+)\.?\s*(\d{1,2}),\s*(\d{4})\s*-\s*(\d+:\d+\s*[apm\.]+)\s*$#u', //May 16, 2022 - 09:24 p.m.
        ];
        $out = [
            //            "$2 $3 $4, $1",
            //            '$2 $1 $3',
            '$2 $1 $3 $4',
            '$2 $1 $3 $4',
            //            '$3-$1-$2',
            '$2 $1 $3, $4',
            '$2 $1 $3, $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€'    => 'EUR',
            '$'    => 'USD',
            '£'    => 'GBP',
            'CA $' => 'CAD',
            'US $' => 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null, $regex = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root, true, $regex);
    }

    private function nextTexts($field, $root = null, $regex = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindNodes(".//text()[{$rule}]/following::text()[normalize-space(.)][1]", $root, $regex);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
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

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/if the reservation is canceled on or after\s*(\w+\s*\d+\,\s*\d{4})\s*\(/u", $cancellationText, $m)) {
            $h->setDeadline(strtotime($m[1]));
        }
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
}
