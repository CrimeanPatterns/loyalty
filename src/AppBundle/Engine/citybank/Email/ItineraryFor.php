<?php

namespace AwardWallet\Engine\citybank\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryFor extends \TAccountChecker
{
    public $mailFiles = "citybank/it-27218994.eml, citybank/it-44960210.eml, citybank/it-50558817.eml, citybank/it-5773847.eml, citybank/it-5777321.eml, citybank/it-59620578.eml, citybank/it-59911983.eml, citybank/it-618574648.eml, citybank/it-619624323.eml, citybank/it-6600908.eml";

    public $subjects = [
        'es' => [
            'de centro de reservaciones de viajes',
            'de viaje del Centro de Reservas de Viajes',
        ],
        'en' => [
            'Travel Reservation Center Trip ID',
            'Travel Reminder: Ready for your trip',
            'Capital One Travel Reservation Trip ID',
            'Travel Reservation Center Cancellation Trip ID',
            'has shared their trip details with you',
        ],
    ];

    public $lang = '';
    public $year;
    public static $dict = [
        'es' => [
            "Flight"                    => "Vuelo",
            "Airline Reference Number"  => "Número de referencia de la aerolínea",
            "Agency Reference Number"   => "Número de referencia de la agencia",
            "Trip ID:"                  => ["Identificación del viaje:", "ID del viaje:"],
            "Passenger"                 => "Pasajero",
            "travelling to destination" => "viajando a destino",
            "Operated by"               => "Operado por",
            "Points"                    => "Puntos",
            "Total ThankYou Points"     => "Recompensas totales:",
            "Total Cash Payment"        => "Pago total en efectivo:",
        ],
        'en' => [
            "Trip ID:"                                 => ["Trip ID:", "Your Trip ID is", "Trip ID"],
            "Total ThankYou Points"                    => ["Total ThankYou Points", "Total Rewards", "Total ThankYou® Points", "Total ThankYou® Points :", 'Total Rewards:'],
            "Total Cash Payment"                       => ["Total Cash Payment", "Total Citi Card Payment:", 'Total Cash Payment:'],
            "Airline Reference Number"                 => ["Airline Reference Number"],
            "Check-Out"                                => ["Check-Out", "Check-out"],
            "you cancelled the following travel plans" => ["you cancelled the following travel plans", "you recently cancelled some or all of your travel booked"],
            "Points"                                   => ["Points", "Miles"],
        ],
    ];
    private $providerCode = '';

    private $langDetectors = [
        'es' => ['Número de referencia de la aerolínea', 'Vuelo de ida'],
        'en' => ['Airline Reference Number', 'Check-Out:', 'Check-out:', "Driver:"],
    ];
    private $tot;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (empty($parser->getHTMLBody())) {
            $body = implode("\n", $parser->getRawBody());
            $body = preg_replace("/^(--[0-9a-z]{30})([0-9a-z]{30})$/m", '$1' . rand(10, 99), $body);
            $this->http->SetEmailBody($body);
        }

        $this->year = date("Y", strtotime($parser->getDate()));
        $this->assignLang();

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (empty($this->providerCode)) {
            $this->detectEmailByBody($parser);
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        return $email;
    }

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        $this->year = date("Y", strtotime($parser->getDate()));
        $this->assignLang();

        $its = $this->parseEmail();

        if (count($its) === 1) {
            $its[0]['SpentAwards'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Total ThankYou Points")) . "]/following::text()[normalize-space(.)][1]");

            if (!empty(array_filter($this->tot))) {
                if ($its[0]['Kind'] === 'T') {
                    $its[0]['TotalCharge'] = $this->tot['Total'];
                    $its[0]['Currency'] = $this->tot['Currency'];
                } elseif ($its[0]['Kind'] === 'R') {
                    $its[0]['Total'] = $this->tot['Total'];
                    $its[0]['Currency'] = $this->tot['Currency'];
                }
            } elseif ($its[0]['Kind'] === 'T') {
                $SpentAwards = $this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t('Flight') . "']/following::text()[normalize-space(.)][1]");

                if (preg_match("#([\d,. ]+\s*" . $this->t('Points') . ")#", $SpentAwards, $m)) {
                    $its[0]['SpentAwards'] = $m[1];
                }

                if (preg_match("#([\d,. ]+\s*[A-Z]{3})\b#", $SpentAwards, $m)) {
                    $tot = $this->getTotalCurrency($m[1]);
                    $its[0]['TotalCharge'] = $tot['Total'];
                    $its[0]['Currency'] = $tot['Currency'];
                }
            }
        } else {
            return [
                'parsedData'   => ['Itineraries' => $its, 'TotalCharge' => ['Amount' => $this->tot['Total'], 'Currency' => $this->tot['Currency']]],
                'emailType'    => "Confirmation" . ucfirst($this->lang),
                'providerCode' => $this->providerCode,
            ];
        }

        return [
            'parsedData'   => ['Itineraries' => $its],
            'emailType'    => "ItineraryFor" . ucfirst($this->lang),
            'providerCode' => $this->providerCode,
        ];
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Travel Center for Citi") or contains(normalize-space(.),"Citigroup Inc.") or contains(normalize-space(.), "Thank you for choosing Wells Fargo Business Rewards") or contains(normalize-space(.), "Thank you for choosing the Travel Rewards Center") or contains(normalize-space(.), "This email was sent by: Travel Center") ]')->length > 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,".citirewards.com/")]')->length > 0;

        if ($condition1 || $condition2) {
            $this->providerCode = 'citybank';

            return true;
        }

        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Capital One Travel")]')->length > 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,".capitalone.com/")]')->length > 0;

        if ($condition1 || $condition2) {
            $this->providerCode = 'capitalcards';

            return true;
        }

        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"JPMorgan Chase & Co")]')->length > 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,".chase.com/")]')->length > 0;

        if ($condition1 || $condition2) {
            $this->providerCode = 'chase';

            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $flProv = self::detectEmailFromProvider($headers['from']);

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false
                    && ($flProv || strpos($headers['subject'], 'Citi ThankYou') !== false
                        || strpos($headers['subject'], 'Capital One Travel') !== false)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@travelcenter') !== false || stripos($from, 'capitalone') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return ['citybank', 'capitalone', 'capitalcards', 'chase'];
    }

    public function assignLang(): bool
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

    private function parseEmail(Email $email)
    {
        // Travel Agency
        $tripNumber = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Trip ID:'))}]/following::text()[string-length(normalize-space(.))>4][1])[1]", null, true, "#^\s*([\dA-Z]+)\s*$#");

        if (empty($tripNumber)) {
            $tripNumber = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Trip ID:'))}])[1]", null, true, "/{$this->opt($this->t('Trip ID:'))}\s*.?(?:\s|[#])([\dA-Z]+)/");
        }
        $email->ota()
            ->confirmation($tripNumber);

        //# FLIGHT ##
        if ($this->http->XPath->query("//text()[normalize-space(.)='" . $this->t('Flight') . "']/following::text()[{$this->contains($this->t('Airline Reference Number'))}]")->length > 0) {
            $year = $this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t('Flight') . "']/following::text()[{$this->contains($this->t('Airline Reference Number'))}][1]/ancestor::tr[1]/descendant::text()[normalize-space(.)][1]", null, true, "#\(.+?(\d{4})#");

            if (!empty($year)) {
                $this->year = $year;
            }

            $f = $email->add()->flight();

            // General
            $confs = array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('Agency Reference Number'))}]/following::text()[string-length(normalize-space(.))>4][1]", null, false, '/^\s*([A-Z\d]{5,6})\s*$/'));

            if (!empty($confs)) {
                foreach ($confs as $conf) {
                    $f->general()
                        ->confirmation($conf);
                }
            } else {
                $f->general()
                    ->noConfirmation();
            }

            $f->general()
                ->travellers(array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(.),'" . $this->t('Passenger') . "')]", null, "#" . $this->opt($this->t('Passenger')) . "[ \d]*:\s+(.+)#")));

            if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("you cancelled the following travel plans")) . "])[1]"))
            ) {
                $f->general()
                    ->cancelled()
                    ->status('Cancelled')
                ;
            }

            $price = $this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t('Flight') . "'][following::text()[{$this->contains($this->t('Airline Reference Number'))}]]/ancestor::td[1]/following-sibling::td[normalize-space()][last()]");

            if (!empty($price)) {
                $price = str_replace('Total:', '', $price);

                if (preg_match("#(?:^|\+)\s*(\d[\d,. ]*\s*" . $this->opt($this->t('Points')) . ")#", $price, $m)) {
                    $f->price()
                        ->spentAwards($m[1]);
                    $price = trim(str_replace($m[0], '', $price), '+ ');
                }

                $tot = $this->getTotalCurrency($price);

                if (!empty(array_filter($tot))) {
                    $f->price()
                        ->total($tot['Total'])
                        ->currency($tot['Currency']);
                }
            }
            $xpath = "//img[contains(@src,'arrow') or @alt='" . $this->t('travelling to destination') . "' or @alt='" . $this->t('Arrow icon') . "' or (@width='30' and @height='30' and not(contains(@src, 'travel-advisory')))]/ancestor::table[1][count(.//text()[normalize-space()]) > 2]";
            // $this->logger->debug($xpath);
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                $s = $f->addSegment();

                $s->extra()
                    ->duration($this->http->FindSingleNode(".//tr[2]/td[2]/descendant::text()[normalize-space()][1]", $root));

                // Departure
                $node = $this->http->FindSingleNode(".//tr[2]/td[1]", $root);

                if (preg_match("#(.+)\s+\(([A-Z]{3})\)(?:Different Airport)?\s*(\d+:\d+\s*(?:[ap]\.?\s*m\.?))\s*(.+)#i", $node, $m)) {
                    $s->departure()
                        ->name($m[1])
                        ->code($m[2])
                        ->date($this->normalizeDate($m[4] . ' ' . str_replace(".", "", $m[3])))
                    ;
                }

                // Arrival
                $node = $this->http->FindSingleNode(".//tr[2]/td[3]", $root);

                if (preg_match("#(.+)\s+\(([A-Z]{3})\)(?:Different Airport)?\s*(\d+:\d+\s*(?:[ap]\.?\s*m\.?))\s*(.+)#i", $node, $m)) {
                    $s->arrival()
                        ->name($m[1])
                        ->code($m[2])
                        ->date($this->normalizeDate($m[4] . ' ' . str_replace(".", "", $m[3])))
                    ;
                }

                // Airline
                $node = $this->http->FindNodes(".//tr[1]//text()[normalize-space(.)]", $root);

                if (preg_match("/([A-Z\d]+)\s*(?:#|N.°)\s*(\d+)/", $node[1] ?? '', $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2])
                    ;
                }
                $rl = $this->http->FindSingleNode("./preceding::text()[{$this->contains($this->t('Airline Reference Number'))}][1]/following::text()[string-length(normalize-space(.))>4][1]", $root, false, '/^\s*([A-Z\d]{5,6})\s*$/');

                if (!empty($rl)) {
                    $s->airline()->confirmation($rl);
                }

                $n = 2;

                if (stripos($node[2] ?? '', $this->t('Operated by'))) {
                    if (preg_match("#" . $this->t('Operated by') . "\s+/?(.+)#", $node[2], $m)) {
                        $s->airline()->operator($m[1]);
                    }
                    $n = 3;
                }

                if (preg_match("#(.+?)\s*\|\s*(.+)#", $node[$n] ?? '', $m)) {
                    $s->extra()
                        ->aircraft($m[1])
                        ->cabin($m[2])
                    ;
                }

                $addNodeXpath = "./following::tr[1][not(.//img[contains(@src,'arrow') or @alt='travelling to destination' or (@width='30' and @height='30')])]";

                if (!empty($s->getCabin())) {
                    $s->extra()
                        ->bookingCode($this->http->FindSingleNode($addNodeXpath . "//text()[contains(., '" . $s->getCabin() . "') and contains(., '(')]", $root, true, "/" . $s->getCabin() . "\s*\(\s*([A-Z]{1,2})\s*\)\s*$/"), true, true);
                }

                $seats = $this->http->FindNodes($addNodeXpath . "//td[not(.//td) and starts-with(normalize-space(), 'Seat') and following-sibling::td[starts-with(normalize-space(), 'Confirmed')]]", $root, "/Seat (\d{1,3}[A-Z])\s*$/");

                if (!empty($seats)) {
                    $s->extra()
                        ->seats($seats);
                }
            }

            if ($nodes->length === 0) {
                $xpath = "//img[contains(@src,'arrow') or @alt='" . $this->t('travelling to destination') . "' or @alt='" . $this->t('Arrow icon') . "' or (@width='30' and @height='30' and not(contains(@src, 'travel-advisory')))]/ancestor::*[count(*[normalize-space()]) = 3][1]";
                // $this->logger->debug($xpath);
                $nodes = $this->http->XPath->query($xpath);

                foreach ($nodes as $ni => $root) {
                    $s = $f->addSegment();

                    $s->extra()
                        ->duration($this->http->FindSingleNode("*[normalize-space()][2]", $root));

                    // Departure
                    $node = implode(' ', $this->http->FindNodes("*[normalize-space()][1]", $root));

                    if (preg_match("#(.+)\s+\(([A-Z]{3})\)(?:Different Airport)?\s*(\d+:\d+\s*(?:[ap]\.?\s*m\.?))\s*(.+)#i", $node, $m)) {
                        $s->departure()
                            ->name($m[1])
                            ->code($m[2])
                            ->date($this->normalizeDate($m[4] . ' ' . str_replace(".", "", $m[3])));
                    }

                    // Arrival
                    $node = $this->http->FindSingleNode("*[normalize-space()][3]", $root);

                    if (preg_match("#(.+)\s+\(([A-Z]{3})\)(?:Different Airport)?\s*(\d+:\d+\s*(?:[ap]\.?\s*m\.?))\s*(.+)#i",
                        $node, $m)) {
                        $s->arrival()
                            ->name($m[1])
                            ->code($m[2])
                            ->date($this->normalizeDate($m[4] . ' ' . str_replace(".", "", $m[3])));
                    }

                    // Airline
                    $node = $this->http->FindNodes("preceding::text()[normalize-space()][1]/ancestor::*[count(*[normalize-space()]) = 1 and *[1]//img][1]//text()[normalize-space(.)]", $root);

                    if (preg_match("/([A-Z\d]+)\s*(?:#|N.°)\s*(\d+)/", $node[1] ?? '', $m)) {
                        $s->airline()
                            ->name($m[1])
                            ->number($m[2]);
                    }
                    $rl = $this->http->FindSingleNode("./preceding::text()[{$this->contains($this->t('Airline Reference Number'))}][1]/following::text()[string-length(normalize-space(.))>4][1]",
                        $root, false, '/^\s*([A-Z\d]{5,6})\s*$/');

                    if (!empty($rl)) {
                        $s->airline()->confirmation($rl);
                    }

                    $n = 2;

                    if (stripos($node[2] ?? '', $this->t('Operated by'))) {
                        if (preg_match("#" . $this->t('Operated by') . "\s+/?(.+)#", $node[2], $m)) {
                            $s->airline()->operator($m[1]);
                        }
                        $n = 3;
                    }

                    if (preg_match("#(.+?)\s*\|\s*(.+)#", $node[$n] ?? '', $m)) {
                        $s->extra()
                            ->aircraft($m[1])
                            ->cabin($m[2]);
                    }

                    $xpathImg = "preceding::img[contains(@src,'arrow') or @alt='" . $this->t('travelling to destination') . "' or @alt='" . $this->t('Arrow icon') . "' or (@width='30' and @height='30' and not(contains(@src, 'travel-advisory')))]";
                    $addNodeXpath = "./following::*[count({$xpathImg}) = " . ($ni + 1) . "]";

                    if (!empty($s->getCabin())) {
                        $s->extra()
                            ->bookingCode($this->http->FindSingleNode($addNodeXpath . "//text()[contains(., '" . $s->getCabin() . "') and contains(., '(')]",
                                $root, true, "/" . $s->getCabin() . "\s*\(\s*([A-Z]{1,2})\s*\)\s*$/"), true, true);
                    }

                    $seats = $this->http->FindNodes($addNodeXpath . "//td[not(.//td) and starts-with(normalize-space(), 'Seat') and following-sibling::td[starts-with(normalize-space(), 'Confirmed')]]",
                        $root, "/Seat (\d{1,3}[A-Z])\s*$/");

                    if (!empty($seats)) {
                        $s->extra()
                            ->seats($seats);
                    }
                }
            }
        }

        //# HOTEL ##

        if ($this->http->XPath->query("//text()[normalize-space(.)='Hotel']/following::text()[contains(normalize-space(.),'Confirmation Number') or contains(normalize-space(.),'Hotel #')]")->length > 0) {
            $xpath = "//text()[normalize-space(.)='Hotel']/following::text()[contains(normalize-space(.),'Hotel #')]/ancestor::*[.//text()[normalize-space(.)='Hotel']][1]";
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                $h = $email->add()->hotel();

                // General
                if ($this->http->XPath->query("./descendant::*[contains(text(),'Confirmation Number')]",
                        $root)->length > 0
                ) {
                    $h->general()->confirmation($this->http->FindSingleNode("./descendant::*[contains(text(),'Confirmation Number')][1]/following::text()[normalize-space(.)!=''][1]",
                        $root, true, "#[A-Z\d\-]+#"));
                } else {
                    $h->general()
                        ->noConfirmation();
                }

                if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("you cancelled the following travel plans")) . "])[1]"))
                    || !empty($this->http->FindSingleNode("./preceding::text()[normalize-space()][1][" . $this->eq($this->t("Cancelled")) . "]", $root))
                ) {
                    $h->general()
                        ->cancelled()
                        ->status('Cancelled')
                    ;
                }
                $h->general()
                    ->traveller($this->http->FindSingleNode("./descendant::text()[contains(normalize-space(.),'Booked for')][1]", $root, true, "/Booked for:\s+(.+)/"));

                // Hotel
                $h->hotel()
                    // ->name($this->http->FindSingleNode("./following-sibling::tr[1]//strong[contains(.,'Check-in')]/preceding::text()[normalize-space(.)][2]", $root))
                    ->name($this->http->FindSingleNode(".//strong[normalize-space() = 'Check-in:']/preceding::text()[normalize-space(.)][2]", $root))
                    ->address($this->http->FindSingleNode(".//strong[normalize-space() = 'Check-in:']/preceding::text()[normalize-space(.)][1]", $root))
                ;

                // Booked
                $h->booked()
                    ->checkIn(strtotime($this->http->FindSingleNode(".//strong[{$this->eq($this->t('Check-in:'))}]/following::text()[normalize-space(.)!=''][1]", $root)))
                    ->checkOut(strtotime($this->http->FindSingleNode(".//strong[{$this->eq($this->t('Check-Out:'))}]/following::text()[normalize-space(.)!=''][1]", $root)))
                    ->guests($this->http->FindSingleNode("(descendant::text()[{$this->contains($this->t('guest'))}])[1]", $root, true, "#^\s*(\d+)\s+guest\(s\)#"))
                ;

                $h->addRoom()->setType($this->http->FindSingleNode(".//strong[contains(.,'Room Type')]/following::text()[normalize-space(.)!=''][1]",
                    $root));

                $price = preg_replace("/\s*\n.*refund.*[\s\S]+/i", '', implode("\n", $this->http->FindNodes("./preceding::text()[normalize-space(.)='" . $this->t('Hotel') . "']/ancestor::td[1]/following-sibling::td[normalize-space()][last()]//text()", $root)));

                if (!empty($price)) {
                    $price = str_replace('Total:', '', $price);

                    if (preg_match("#(?:^|\+)\s*(\d[\d,. ]*\s*" . $this->opt($this->t('Points')) . ")#", $price, $m)) {
                        $h->price()
                            ->spentAwards($m[1]);
                        $price = trim(str_replace($m[0], '', $price), '+ ');
                    }

                    $tot = $this->getTotalCurrency($price);

                    if (!empty(array_filter($tot))) {
                        $h->price()
                            ->total($tot['Total'])
                            ->currency($tot['Currency']);
                    }
                }
            }
        }

        //# CAR ##

        if ($this->http->XPath->query("//text()[normalize-space(.)='Car']/following::text()[contains(normalize-space(.),'Confirmation Number') or contains(normalize-space(.),'Car #')]")->length > 0) {
            $xpath = "//text()[normalize-space(.)='Car']/following::text()[contains(normalize-space(.),'Car #')]/ancestor::*[.//text()[normalize-space(.)='Car']][1]";
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                $r = $email->add()->rental();

                // General
                if ($this->http->XPath->query("./descendant::*[contains(text(),'Confirmation Number')]",
                        $root)->length > 0
                ) {
                    $r->general()->confirmation($this->http->FindSingleNode("./descendant::*[contains(text(),'Confirmation Number')][1]/following::text()[normalize-space(.)!=''][1]",
                        $root, true, "#[A-Z\d\-]+#"));
                } else {
                    $r->general()->noConfirmation();
                }

                if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("you cancelled the following travel plans")) . "])[1]"))
                    || !empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Car #1 Cancelled")) . "])[1]"))) {
                    $r->general()
                        ->cancelled()
                        ->status('Cancelled')
                    ;
                }

                $renter = $this->http->FindSingleNode("./descendant::*[contains(text(),'Driver')][1]", $root, true, "#Driver:\s+(.+)#");

                if (empty($renter)) {
                    $renter = $this->http->FindSingleNode(".//text()[contains(.,'Driver')][1]", $root, true, "#Driver:\s+(.+)#");
                }
                $r->general()
                    ->traveller($renter);

                $price = $this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t('Car') . "'][following::text()[contains(normalize-space(.),'Confirmation Number') or contains(normalize-space(.),'Car #')]]/ancestor::td[1]/following-sibling::td[normalize-space()][last()]");

                if (preg_match("#(?:^|\+)\s*(\d[\d,. ]*\s*" . $this->opt($this->t('Points')) . ")#", $price, $m)) {
                    $r->price()
                        ->spentAwards($m[1]);
                    $price = str_replace($m[0], '', $price);
                }

                $tot = $this->getTotalCurrency($price);

                if (!empty(array_filter($tot))) {
                    $r->price()
                        ->total($tot['Total'])
                        ->currency($tot['Currency']);
                }

                $r->pickup()
                    ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::*[starts-with(text(),'Pick-up')][1]/following::text()[normalize-space(.)!=''][1]", $root)));
                $r->dropoff()
                    ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::*[starts-with(text(),'Drop-off')][1]/following::text()[normalize-space(.)!=''][1]", $root)));

                $node = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space(.)!='']", $root));

                $location = '';

                if (($this->http->XPath->query("./descendant::*[starts-with(normalize-space(text()),'Pick-up') and contains(text(),'Airport Location')][1]",
                            $root)->length > 0) && preg_match("/Pick-up[^\n]+\n[^\n]+\n[^\n]+\n(\d+.+?)\s+Drop-off/s", $node, $m)
                ) {
                    $location = $m[1];
                } else {
                    $location = $this->http->FindPreg("/Pick-up[^\n]+\n[^\n]+\n(.+?)\s+Drop-off/s", false, $node);
                }

                if (preg_match("/^(.+?)\s+Phone: *([^\n]+)/s", $location, $m)) {
                    $r->pickup()
                        ->location($m[1])
                        ->phone($m[2])
                    ;
                } else {
                    $r->pickup()
                        ->location($location)
                    ;
                }

                if (preg_match("#Drop-off[^\n]+\n[^\n]+\n[^\n]+\n(\d+.+?)\s+Phone:#s", $node, $m)) {
                    $r->dropoff()->location($m[1]);
                } elseif (preg_match("#Drop-off[^\n]+\n[^\n]+\s+Same As Pick Up#s", $node)) {
                    $r->dropoff()->same();
                }

                $company = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(),'Car Type')][1]/preceding::text()[normalize-space(.)][1][not({$this->starts($this->t('Driver'))})]", $root);

                if (!empty($company)) {
                    $r->extra()
                        ->company($company);
                }

                $r->car()
                    ->type($this->http->FindSingleNode("./descendant::*[contains(text(),'Car Type')][1]/following::text()[normalize-space(.)!=''][1]", $root));
            }
        }

        // Price
        if (empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("you cancelled the following travel plans")) . "])[1]"))) {
            $spent = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Total ThankYou Points")) . "]/following::text()[normalize-space(.)][1]");

            if (empty($spent)) {
                $spent = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Total ThankYou Points")) . "]/following-sibling::td[normalize-space(.)][1]");
            }

            if (empty($spent)) {
                $spent = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Total :")) . "]/following-sibling::td[normalize-space(.)][1][contains(., 'Point')]");
            }

            if (!empty($spent)) {
                $email->price()
                    ->spentAwards($spent);
            }

            $total = $this->getTotalCurrency($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total Cash Payment")) . "]/following::text()[normalize-space(.)!=''][1]"));

            if (!empty(array_filter($total))) {
                $email->price()
                    ->total($total['Total'])
                    ->currency($total['Currency'])
                ;
            }
        }

        return $email;
    }

    private function normalizeDate($str)
    {
        //		 $this->http->log($str);
        $in = [
            '#([\S\s]*)\s*(\d{2})\s+(\w{3,})\s*$#u',
            '#^\s*([^,]*),\s*(\d{2})\s+(\D{3,})\.\s*(\d+:\d+\s*[ap])\s*(m)\s*$#iu', //jueves, 18 ene. 10:45 a m
            '#^\s*(\w+)\s+(\d+),\s+(\d{4})\s+\-\s+(\d+:\d+\s*[ap])\s*(m)\s*$#iu', //Jun 07, 2018 - 1:00 PM
            '#^\s*([^\d\s]+),\s*(\w+)\s+(\d+)\s+(\d+:\d+\s*[ap])\s*(m)\s*$#iu', //Sat, Jul 20 11:55 AM
            '#^\s*[^,]*,\s*(\d{2})\s+(\w{3,})\.\s*(\d{4})\s+(\d+:\d+\s*[ap])\s*(m)\s*$#iu', //dom., 04 ago. 2019 1:55 pm
        ];
        $out = [
            '$1, $2 $3 ' . $this->year,
            '$1, $2 $3 ' . $this->year . ', $4$5',
            '$2 $1 $3 $4$5',
            '$1, $3 $2 ' . $this->year . ', $4$5',
            '$1 $2 $3, $4$5',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(\d{4})#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^([^, ]+?),\s+(.+)$#", $str, $m)) {
            $dayOfWeekInt = \AwardWallet\Engine\WeekTranslate::number1(trim($m[1]), $this->lang);
            $str = \AwardWallet\Common\Parser\Util\EmailDateHelper::parseDateUsingWeekDay($m[2], $dayOfWeekInt);
        }

        if (is_string($str)) {
            $str = strtotime($str, false);
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function getTotalCurrency($totalText)
    {
        $tot = null;
        $cur = null;

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $totalText, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $totalText, $m)
            || preg_match("#^\s*\\$(?<amount>\d[\d\., ]*)\s*(?<curr>USD)\s*$#", $totalText, $m)
        ) {
            $tot = PriceHelper::cost($m['amount'], ',', '.');
            $cur = $this->currency($m['curr']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
            '฿' => 'THB',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
