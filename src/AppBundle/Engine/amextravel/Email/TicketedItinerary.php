<?php

namespace AwardWallet\Engine\amextravel\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// pdf parse in tzell/ItineraryPdf.php
class TicketedItinerary extends \TAccountChecker
{
    public $mailFiles = "amextravel/it-10731601.eml, amextravel/it-10972847.eml, amextravel/it-10972876.eml, amextravel/it-12635737.eml, amextravel/it-1732436.eml, amextravel/it-1995032.eml, amextravel/it-2123807.eml, amextravel/it-2131443.eml, amextravel/it-22.eml, amextravel/it-23.eml, amextravel/it-24.eml, amextravel/it-2437431.eml, amextravel/it-2509097.eml, amextravel/it-2518570.eml, amextravel/it-2526046.eml, amextravel/it-2569036.eml, amextravel/it-2802015.eml, amextravel/it-2802056.eml, amextravel/it-2808917.eml, amextravel/it-2898892.eml, amextravel/it-2898931.eml, amextravel/it-2899211.eml, amextravel/it-2924357.eml, amextravel/it-2931628.eml, amextravel/it-3041076.eml, amextravel/it-3041077.eml, amextravel/it-3041080.eml, amextravel/it-31767495.eml, amextravel/it-3657886.eml, amextravel/it-3657888.eml, amextravel/it-43658960.eml, amextravel/it-53037544.eml, amextravel/it-55835800.eml, amextravel/it-86896560.eml";

    public static $detectProvider = [
        'ctmanagement' => [
            'from' => ['@travelctm.com'],
            'body' => ['travelctm.com', 'IHS MARKIT'],
        ],
        'uob' => [
            'from' => ['@uobtravel.com'],
            'body' => ['uobtravel.com', 'UOB Travel'],
        ],
        'uniglobe' => [
            'from' => ['southwesttravel.be', '@uniglobe'], //@uniglobealliancetravel.nl
            'body' => ['Uniglobe '],
        ],
        'tzell' => [
            'from' => ['@tzell.com'],
            'body' => ['Tzell'],
        ],
        'royalcaribbean' => [
            'from' => ['@rccl.com'],
            'body' => [],
        ],
        'frosch' => [
            'from' => ['@Frosch.com', '@FROSCH.COM'],
            'body' => ['FROSCH'],
        ],
        'directravel' => [
            'from' => ['@dt.com'],
            'body' => ['Direct Travel'],
        ],
        'aaatravel' => [
            'from' => ['aaane.com'],
            'body' => ['notify AAA of any'],
        ],
        'toneinc' => [
            'from' => ['traveloneinc'],
            'body' => ['Travel One, Inc'],
        ],
        'wtravel' => [
            'from' => ['worldtrav.com', 'globalknowledge.com'],
            'body' => ['Travel One, Inc', 'Global Knowledge Travel Center'],
        ],
        'cornerstone' => [
            'from' => ['iqcx.com', 'ciswired.com'],
            'body' => ['ciswired.com', 'Totus.Travel'],
        ],
        'amextravel' => [// or other without provider code
            'from' => ['@luxetm.com', '@travelwithvista.com', '@accenttravel.com', '@nextgen.com', '@vistat.com', '@traveltrust.com',
                '@casto.com', '@totus.com', '@plazatravel.com', '@sanditz.com', '@montrosetravel.com', '@travelwithvista.com', '@youngstravel.com', ],
            'body' => ['American Express Travel', 'Traveltrust Corporation'],
        ],
    ];

    public $lang = "en";

    private $detectLang = [
        "es" => ['AIRE', 'Teléfono', 'Teléfono:', 'Teléfono :'],
        "en" => ['AIR', 'CAR', 'HOTEL', 'Rail'],
    ];

    private $detectSubject = [
        "en"  => "Ticketed itinerary for",
        "en2" => "Eticket/s and itinerary for",
    ];

    private static $dictionary = [
        "en" => [
            "Record Locator number is"=> ["Record Locator number is", "Confirmation number is"],
            "Agency Reference Number:"=> ["Agency Reference Number:", "Agency Record Locator:", "Booking locator:", "Record Locator:"],
            "Ticket Number"           => ["Ticket Number", "Ticket Nbr"],
        ],

        "es" => [
            "Agency Reference Number:" => "Booking locator:",
            "Passengers"               => "Pasajeros",

            //hotel
            "Confirmation Number" => "Número de confirmación",
            "Number of Rooms"     => "Número de habitaciones",
            "Phone"               => "Teléfono",
            "Check Out:"          => "Check-Out:",
            "Rate"                => "Tarifa",
            "Room Type"           => "Tipo de habitación",

            //air
            "AIR"                      => "AIRE",
            "air"                      => "aire",
            'From'                     => 'Origen',
            'To'                       => 'Destino',
            'DEPARTS '                 => 'SALIDAS ',
            'DEPARTS'                  => 'SALIDAS',
            'Depart'                   => 'Salida',
            'ARRIVES '                 => 'LLEGADAS ',
            'ARRIVES'                  => 'LLEGADAS',
            'Arrive'                   => 'Llegada',
            'Equipment:'               => 'Equipo:',
            'Miles:'                   => 'Millas:',
            'Class:'                   => 'Clase:',
            'Duration:'                => 'Duración:',
            'MEAL:'                    => 'COMIDA:',
            'Stops:'                   => 'Escalas:',
            'Flight Number'            => 'Número de vuelo',
            "Record Locator number is" => "Confirmation number is",
            //car

            //rail
        ],
    ];
    private $date = null;
    private $providerCode;

    public function parseEmail(Email $email): void
    {
        $patterns = [
            'phone' => '[+(\d][-. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
        ];

        $xpathCell = '(self::td or self::th)';

        // Travel Agency
        if (!empty($this->providerCode)) {
            $email->ota();
            $email->setProviderCode($this->providerCode);
        }

        $tripNumber = null;
        $tripNumberText = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Agency Reference Number:"))}]");

        if (preg_match("#^({$this->opt($this->t("Agency Reference Number:"))})[:\s]*([A-Z\d]{5,})$#", $tripNumberText, $m)) {
            $email->ota()->confirmation($m[2], rtrim($m[1], ': '));
            $tripNumber = $m[2];
        }

        $passengers = array_values(array_filter(array_map('trim',
            $this->http->FindNodes("//td//b[{$this->contains($this->t('Passengers'))}]/ancestor::td[1]/following-sibling::td[1]//b//text()",
                null, '/^[A-Z\s\-\/]{4,}/'))));

        // split html
        $rsegments = [];
        $xpath = "//text()[" . $this->eq($this->t("AIR")) . " or " . $this->eq($this->t("HOTEL")) . " or " . $this->eq($this->t("CAR")) . " or " . $this->eq($this->t("Rail")) . "]/ancestor::tr[1]";

        foreach ($this->http->XPath->query($xpath) as $hrow) {
            $html = $hrow->ownerDocument->saveHTML($hrow);
            $row = $hrow;

            while (($row = $this->http->XPath->query("following-sibling::tr[1][not(normalize-space(*[{$xpathCell}][1]))]",
                    $row)->item(0)) !== null) {
                $html .= $row->ownerDocument->saveHTML($row);
            }

            $http = clone $this->http;
            $http->SetEmailBody($html);
            $type = $http->FindSingleNode("/html/body/tr[1]/*[{$xpathCell}][1]");
            $rsegments[strtolower($type)][] = $http;
        }

        //##################
        //##   FLIGHTS   ###
        //##################

        $airs = [];

        if (isset($rsegments[$this->t('air')])) {
            $airs = $rsegments[$this->t('air')];
        }

        if (!empty($airs)) {
            $f = $email->add()->flight();

            // General
            $f->general()
                ->noConfirmation();

            if (!empty($passengers)) {
                $f->general()
                    ->travellers($passengers);
            }

            // Issued
            $tickets = [];
            $ticketsTexts = array_filter($this->http->FindNodes('//text()[' . $this->contains($this->t("Ticket Number")) . ']'));

            foreach ($ticketsTexts as $value) {
                if (preg_match_all("#" . $this->opt($this->t("Ticket Number")) . "\s*:\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{10})\b#",
                    $value, $ticketMatches)) {
                    $tickets = array_merge($tickets, $ticketMatches[1]);
                }

                if (preg_match_all("#" . $this->opt($this->t("Ticket Number")) . "\s*:\s*(\d{13})\b#", $value, $ticketMatches)) {
                    $tickets = array_merge($tickets, $ticketMatches[1]);
                }
            }

            if (!empty($tickets)) {
                $f->issued()
                    ->tickets(array_unique($tickets), false);
            }

            $ffNumbers = [];

            foreach ($airs as $segment) {
                $s = $f->addSegment();

                $date = $this->normalizeDate($segment->FindSingleNode("/html/body/tr[1]/*[{$xpathCell}][2]"));

                if (empty($date)) {
                    return;
                }

                // Airline
                $s->airline()
                    ->number($segment->FindSingleNode("//text()[" . $this->eq($this->t('Flight Number')) . "]/ancestor::td[1]",
                        null, true, "#{$this->opt($this->t('Flight Number'))}\s*:\s*(\d+)#"));
                $node = implode("\n", $segment->FindNodes("/html/body/tr[2]/td[2]//text()[normalize-space()!='']"));

                if (preg_match("#([\s\S]+?)\s+{$this->opt($this->t('Operated By'))}[:]?\s*(.+)#i", $node, $m)) {
                    $s->airline()
                        ->name(preg_replace("#\s*\n\s*#", ' ', trim($m[1])))
                        ->operator($m[2] ?? null, true, true);
                } else {
                    $s->airline()
                        ->name($this->re("#^\s*(.+)#", $node));
                }
                $rl = implode(' ',
                    $segment->FindNodes("//text()[{$this->contains($this->t("Record Locator number is"))}]/ancestor::td[1]/descendant::text()[normalize-space()]"));

                if (preg_match("#{$this->opt($this->t("Record Locator number is"))}\s*([A-Z\d]{5,7})\b#", $rl, $m)
                    && $m[1] !== $tripNumber
                ) {
                    $s->airline()->confirmation($m[1]);
                }

                // Departure
                $node = $segment->FindSingleNode("//text()[" . $this->eq($this->t('From')) . "]/ancestor::td[1]", null, true,
                    "#:\s*(.+)#");

                if (preg_match("#^\s*\(([A-Z]{3})\)(.+)$#", $node, $m)) {
                    $s->departure()
                        ->code($m[1])
                        ->name(trim($m[2]));
                } else {
                    $s->departure()
                        ->name($node);
                }

                $timeDep = $segment->FindSingleNode("//text()[" . $this->eq($this->t('Depart')) . "]/ancestor::td[1]", null, true,
                    "#{$this->opt($this->t('Depart'))}\s*:\s+(.+)#");

                if ($timeDep === 'OPEN') {
                    $s->departure()
                        ->day($date)
                        ->noDate();
                } else {
                    $s->departure()->date($this->normalizeDate($timeDep, $date));
                }
                $s->departure()
                    ->terminal(trim(preg_replace("#terminal#i", '',
                        $segment->FindSingleNode("//text()[" . $this->starts($this->t('DEPARTS ')) . "]", null, true,
                            "#{$this->opt($this->t('DEPARTS'))}\s+[A-Z]{3}\s+(.*?{$this->opt($this->t('TERMINAL'))}.*?)(?: - {$this->opt($this->t('ARRIVES'))} .+)?$#"))), true, true);

                if (empty($s->getDepCode())) {
                    $code = $segment->FindSingleNode("//text()[" . $this->starts($this->t('DEPARTS ')) . "]", null, true,
                        "#{$this->opt($this->t('DEPARTS'))}\s+([A-Z]{3})\s+(?:.*\s+)?{$this->opt($this->t('TERMINAL'))}#");

                    if (!empty($code)) {
                        $s->departure()
                            ->code($code);
                    } elseif (!empty($s->getDepName())) {
                        $s->departure()
                            ->noCode();
                    }
                }

                // Arrival
                $node = $segment->FindSingleNode("//text()[" . $this->eq($this->t('To')) . "]/ancestor::td[1]", null, true,
                    "#:\s*(.+)#");

                if (preg_match("#^\s*\(([A-Z]{3})\)(.+)$#", $node, $m)) {
                    $s->arrival()
                        ->code($m[1])
                        ->name(trim($m[2]));
                } else {
                    $s->arrival()
                        ->name($node);
                }

                $timeArr = $segment->FindSingleNode("//text()[" . $this->eq($this->t('Arrive')) . "]/ancestor::td[1]", null, true,
                    "#{$this->opt($this->t('Arrive'))}\s*:\s+(.+)#");

                if ($timeDep === 'OPEN') {
                    $s->arrival()
                        ->day($date)
                        ->noDate();
                } else {
                    $s->arrival()->date($this->normalizeDate($timeArr, $date));
                }
                $s->arrival()
                    ->terminal(trim(preg_replace("#terminal#i", '',
                        $segment->FindSingleNode("//text()[" . $this->contains($this->t('ARRIVES ')) . "]", null, true,
                            "#{$this->opt($this->t('ARRIVES'))}\s+[A-Z]{3}\s+(.*{$this->opt($this->t('TERMINAL'))}.*)#"))), true, true);

                if (empty($s->getArrCode())) {
                    $code = $segment->FindSingleNode("//text()[" . $this->contains($this->t('ARRIVES ')) . "]", null, true,
                        "#{$this->opt($this->t('ARRIVES'))}\s+([A-Z]{3})\s+(?:.*\s+)?{$this->opt($this->t('TERMINAL'))}#");

                    if (!empty($code)) {
                        $s->arrival()
                            ->code($code);
                    } elseif (!empty($s->getArrName())) {
                        $s->arrival()
                            ->noCode();
                    }
                }

                // Extra
                $s->extra()
                    ->aircraft($segment->FindSingleNode("//text()[" . $this->starts($this->t('Equipment:')) . "]", null, true,
                        "#{$this->opt($this->t('Equipment:'))}\s*(.+)#"), true, true)
                    ->miles($segment->FindSingleNode("//text()[" . $this->starts($this->t('Miles:')) . "]", null, true,
                        "#{$this->opt($this->t('Miles:'))}\s*(\d+)\s*\/#"), true, true)
                    ->cabin($segment->FindSingleNode("//text()[" . $this->starts($this->t('Class:')) . "]", null, true,
                        "#{$this->opt($this->t('Class:'))}\s*[A-Z]-(.+)#"), true, true)
                    ->bookingCode($segment->FindSingleNode("//text()[" . $this->starts($this->t('Class:')) . "]", null, true,
                        "#{$this->opt($this->t('Class:'))}\s*([A-Z])-.+#"), true, true)
                    ->duration($segment->FindSingleNode("//text()[" . $this->starts($this->t('Duration:')) . "]", null, true,
                        "#{$this->opt($this->t('Duration:'))}\s*(.+)#"), true, true)
                    ->meal($segment->FindSingleNode("//text()[" . $this->starts($this->t('MEAL:')) . "]", null, true,
                        "#{$this->opt($this->t('MEAL:'))}\s*(.+)#"), true, true);

                if (preg_match_all("#\b(\d{1,3}[A-Z])\b#",
                    $segment->FindSingleNode("//text()[" . $this->starts(["Seats:", "SEAT"]) . "]", null, true,
                        "#(?:Seats:?|SEAT)\s*(.+)#"), $m)) {
                    $s->extra()->seats($m[1]);
                }

                $node = $segment->FindSingleNode("//text()[" . $this->starts($this->t('Stops:')) . "]", null, true,
                    "#{$this->opt($this->t('Stops:'))}\s*(.+)#");

                if (preg_match("#Nonstop#i", $node)) {
                    $s->extra()->stops(0);
                } elseif (preg_match("#\b(\d+)\b#i", $node, $m)) {
                    $s->extra()->stops($m[1]);
                }

                if (preg_match("/^([A-Z\d]{7,}?)\s*(?: applied to | for |$)/", $segment->FindSingleNode("//text()[{$this->starts($this->t('Frequent Flyer Number:'))}]", null, true, "/{$this->opt($this->t('Frequent Flyer Number:'))}\s*(.+)/"), $m)) {
                    $ffNumbers[] = $m[1];
                }
            }

            if (count($ffNumbers)) {
                $f->program()->accounts(array_unique($ffNumbers), false);
            }
        }
        //##################
        //##    RAILS    ###
        //##################

        $rails = [];

        if (isset($rsegments['rail'])) {
            $rails = $rsegments['rail'];
        }

        if (!empty($rails)) {
            $t = $email->add()->train();

            // General
            $t->general()
                ->noConfirmation();

            if (!empty($passengers)) {
                $t->general()
                    ->travellers($passengers);
            }
        }

        foreach ($rails as $segment) {
            if (!empty($segment->FindSingleNode("//text()[contains(normalize-space(),'ORDER TRAIN TICKETS FROM')]"))
                && empty($segment->FindSingleNode("//text()[starts-with(normalize-space(),'From')]"))
            ) {
                continue;
            }

            if (count($segment->FindNodes("//text()[contains(normalize-space(.), 'TO') and contains(normalize-space(.), ':')]")) > 1) {
                $this->logger->notice('TWO AND MORE');
                $xpath = "//text()[contains(normalize-space(.), 'TO') and contains(normalize-space(.), ':')]";
                $segments = $segment->XPath->query($xpath);
                $this->logger->notice('COUNT ' . $segments->count());

                foreach ($segments as $root) {
                    $s = $t->addSegment();
                    //Departure

                    $depName = $segment->FindSingleNode("./ancestor::tr[1]", $root, true, '/^\D+[:]\s*(\D+)\s*TO/');
                    $this->logger->notice('NAME ' . $depName);

                    if (!empty($depName)) {
                        $s->departure()
                            ->name($depName);
                    }

                    $depDate = $segment->FindSingleNode("./ancestor::tr[1]/preceding::tr[contains(normalize-space(), 'Rail')][1]/descendant::td[2]", $root);
                    $depTime = $segment->FindSingleNode("./ancestor::tr[1]", $root, true, '/^\D+[:]\s*\D+\s*TO\s*\D+\s*([\d\:]+(?:P|A))/');
                    $depDate = $this->normalizeDate($depDate . ' ' . $depTime);

                    if (!empty($depDate)) {
                        $s->departure()
                            ->date($depDate);
                    }

                    //Arrival
                    $arrName = $segment->FindSingleNode("./ancestor::tr[1]", $root, true, '/^\D+[:]\s*\D+\s*TO\s*(\D+)\s*\d/');

                    if (!empty($arrName)) {
                        $s->arrival()
                            ->name($arrName);
                    }

                    $arrDate = $segment->FindSingleNode("./ancestor::tr[1]/preceding::tr[contains(normalize-space(), 'Rail')][1]/descendant::td[2]", $root);
                    $arrTime = $segment->FindSingleNode("./ancestor::tr[1]", $root, true, '/^\D+[:]\s*\D+\s*TO\s*\D+\s*[\d\:]+(?:P|A)[-]([\d\:]+(?:P|A))/');
                    $arrDate = $this->normalizeDate($arrDate . ' ' . $arrTime);

                    if (!empty($arrDate)) {
                        $s->arrival()
                            ->date($arrDate);
                    }

                    $s->extra()
                        ->noNumber();
                }
            } else {
                $s = $t->addSegment();

                $date = $this->normalizeDate($segment->FindSingleNode("/html/body/tr[1]/*[{$xpathCell}][2]"));

                if (empty($date)) {
                    return;
                }

                // Departure
                $depName = $segment->FindSingleNode("//text()[" . $this->starts($this->t("From")) . "]/ancestor::td[1]",
                    null, true, "#:\s*(.+)#");

                if (empty($depName)) {
                    $depName = $segment->FindSingleNode("//text()[" . $this->starts($this->t("TRAIN FROM")) . "]/ancestor::td[1]",
                        null, true, "#TRAIN FROM\s(.+)\sTO#");
                }

                if (empty($depName)) {
                    $depName = $segment->FindSingleNode("//text()[" . $this->starts($this->t("DEP")) . "]/ancestor::td[1]",
                        null, true, "#DEP\s(.+)\s\d{1,2}:\d{1,2}[AP]M$#");
                }

                if (!empty($depName)) {
                    $s->departure()
                        ->name($depName);
                }
                $depDate = $this->normalizeDate($segment->FindSingleNode("//text()[" . $this->starts("Depart") . "]/ancestor::td[1]",
                    null, true, "#Depart\s*:\s+(.+)#"), $date);

                if (empty($depDate)) {
                    $depDate = $this->normalizeDate($segment->FindSingleNode("//text()[" . $this->contains("AT") . "]",
                        null, true, "#^AT\s(\d{1,2}:\d{1,2}[AP]M)\s#"), $date);
                }

                if (empty($depDate)) {
                    $depDate = $this->normalizeDate($segment->FindSingleNode("//text()[" . $this->contains("DEP") . "]",
                        null, true, "#(\d{1,2}:\d{1,2}[AP]M)$#"), $date);
                }

                if (!empty($depDate)) {
                    $s->departure()
                        ->date($depDate);
                }

                // Arrival
                $arrName = $segment->FindSingleNode("//text()[" . $this->starts("To") . "]/ancestor::td[1]", null, true,
                    "#:\s*(.+)#");

                if (empty($arrName)) {
                    $arrName = $segment->FindSingleNode("//text()[" . $this->contains("TO") . "]/ancestor::td[1]", null,
                        true, "#TRAIN FROM\s.+\sTO\s(.+)#");
                }

                if (empty($arrName)) {
                    $arrName = $segment->FindSingleNode("//text()[" . $this->contains("ARR") . "]/ancestor::td[1]", null,
                        true, "#ARR\s(.+)\s\d{1,2}:\d{1,2}[AP]M$#");
                }

                if (!empty($arrName)) {
                    $s->arrival()
                        ->name($arrName);
                }

                $arrDate = $this->normalizeDate($segment->FindSingleNode("//text()[" . $this->starts("Arrive") . "]/ancestor::td[1]",
                    null, true, "#Arrive\s*:\s+(.+)#"), $date);

                if (empty($arrDate)) {
                    $arrDate = $this->normalizeDate($segment->FindSingleNode("//text()[" . $this->contains("ARR") . "]",
                        null, true, "#ARR\s(\d{1,2}:\d{1,2}[AP]M)\s#"), $date);
                }

                if (empty($arrDate)) {
                    $arrDate = $this->normalizeDate($segment->FindSingleNode("//text()[" . $this->contains("ARR") . "]",
                        null, true, "#(\d{1,2}:\d{1,2}[AP]M)$#"), $date);
                }

                if (!empty($arrDate)) {
                    $s->arrival()
                        ->date($arrDate);
                }

                // Extra
                $s->extra()
                    ->noNumber()
                    ->cabin($segment->FindSingleNode("//text()[" . $this->starts("Class:") . "]", null, true,
                        "#Class:\s*[A-Z]{1,2}-(.+)#"), true, true)
                    ->bookingCode($segment->FindSingleNode("//text()[" . $this->starts("Class:") . "]", null, true,
                        "#Class:\s*([A-Z]{1,2})-.*#"), true, true);
            }
        }

        if (isset($t) && $t->getType() === 'train' && count($t->getSegments()) === 0) {
            $rails = [];
            $email->removeItinerary($t);
        }

        //#################
        //##   HOTELS   ###
        //#################

        $hotels = [];

        if (isset($rsegments['hotel'])) {
            $hotels = $rsegments['hotel'];
        }

        foreach ($hotels as $segment) {
            $h = $email->add()->hotel();
            // Booked

            $checkOut = $this->normalizeDate($segment->FindSingleNode("//text()[{$this->eq("Check Out:")}]/ancestor::td[1]",
                null, true, "#{$this->opt($this->t('Check Out:'))}\s+(.+)#"));
            $rooms = $segment->FindSingleNode("//text()[{$this->eq($this->t('Number of Rooms'))}]/ancestor::td[1]", null, true,
                "#{$this->opt($this->t('Number of Rooms'))}\s*:\s+(\d+)\b#");

            if (empty($checkOut)) {
                $checkOut = $this->normalizeDate($segment->FindSingleNode("(//text()[{$this->eq($this->t('Check Out:'))}]/ancestor::td[1]/descendant::p[" . $this->contains($this->t("Check Out:")) . "])[1]",
                    null, true, "#{$this->opt($this->t('Check Out:'))}\s+(.+)#"));
            }

            if (empty($rooms)) {
                $rooms = $segment->FindSingleNode("(//text()[{$this->eq($this->t('Number of Rooms'))}]/ancestor::td[1]/descendant::p[" . $this->contains($this->t("Number of Rooms")) . "])[1]", null, true,
                    "#{$this->opt($this->t('Number of Rooms'))}\s*:\s+(\d+)\b#");
            }

            $h->booked()
                ->checkIn($this->normalizeDate($segment->FindSingleNode("/html/body/tr[1]/*[{$xpathCell}][2]")))
                ->checkOut($checkOut)
                ->rooms($rooms);
            // Hotel
            $h->hotel()
                ->name($segment->FindSingleNode("/html/body/tr[2]/td[2]/descendant::text()[normalize-space()][1]"))
                ->address($segment->FindSingleNode("/html/body/tr[2]/td[2]/descendant::text()[normalize-space()][last()]"))
                ->fax($segment->FindSingleNode("//text()[{$this->starts("Fax:")}]", null, true, "/^Fax:\s*({$patterns['phone']})$/"), false, true)
            ;

            $phone = str_replace('~', '',
                $segment->FindSingleNode("//text()[{$this->eq($this->t('Phone'))}]/ancestor::td[1]", null, true,
                    "#{$this->opt($this->t('Phone'))}\s*:\s+(.+)#"));

            if (!empty($phone)) {
                $h->hotel()
                    ->phone($phone);
            }

            $conf = $segment->FindSingleNode("//text()[" . $this->eq($this->t('Confirmation Number')) . "]/ancestor::td[1]", null,
                true, "#{$this->opt($this->t('Confirmation Number'))}\s*:\s+(\w+)#");
            $guestNames = [];
            $reservedFor = $segment->FindSingleNode("//text()[{$this->starts($this->t('Reserved For'))}]/ancestor::td[1]", null,
                true, "#{$this->opt($this->t('Reserved For'))}\s*:+\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$#u");

            if ($reservedFor && !empty($h->getHotelName()) && stripos($h->getHotelName(),
                    $reservedFor) === false) { // it-10972847.eml
                $guestNames[] = $reservedFor;
            }

            if (!empty($conf)) {
                $h->general()
                    ->confirmation($conf);
            } else {
                $h->general()
                    ->noConfirmation();
            }

            if (!empty($guestNames)) {
                $h->general()
                    ->travellers($guestNames);
            } elseif (!empty($passengers)) {
                $h->general()
                    ->travellers($passengers);
            }

            $cancellationPolicy = $segment->FindSingleNode("//text()[{$this->starts($this->t('Hotel cancellation policy:'))}]",
                null, true, "#{$this->opt($this->t('Hotel cancellation policy:'))}\s*(.+)#");

            if (empty($cancellationPolicy)) {
                $cancellationPolicy = $segment->FindSingleNode("//text()[{$this->contains($this->t('CANCEL POLICY'))}]", null,
                    true, "#^\d{1,3} HOURS? CANCEL POLICY$#i");
            }

            if (!empty($cancellationPolicy)) {
                $h->general()->cancellation($cancellationPolicy);

                if (preg_match("/^(?<prior>\d{1,3} (?:HOURS?|DAYS?)) CANCELL? POLICY$/i", $cancellationPolicy, $m)
                    || preg_match("/PERMITTED UP TO (?<prior>\d{1,3} (?:HOURS?|DAYS?)) BEFORE ARRIVA/i", $cancellationPolicy, $m)
                    || preg_match("/cancell? (?<prior>\d{1,3} (?:hours?|days?)) prior to arrival date/i", $cancellationPolicy, $m)
                ) {
                    // PERMITTED UP TO 03 DAYS BEFORE ARRIVAL
                    // cancel 3 days prior to arrival date
                    $h->booked()->deadlineRelative($m['prior']);
                } elseif (preg_match("/^\w+\s*(\d{4}) HTL TIME ON (\d+[A-Z]+\d+)-/i", $cancellationPolicy, $m)) {
                    // CXL 1200 HTL TIME ON 21SEP20-FEE 1 NIGHT-INCL
                    $h->booked()->deadline2("{$m[2]}, {$m[1]}");
                }
            }

            // Rooms
            $rate = $segment->FindSingleNode("//text()[" . $this->eq($this->t('Rate')) . "]/ancestor::td[1]", null, true,
                "#{$this->opt($this->t('Rate'))}\s*:\s+(.+)#");
            $type = $segment->FindSingleNode("//text()[" . $this->starts($this->t('Room Type')) . "]/ancestor::td[1]", null, true,
                "#{$this->opt($this->t('Room Type'))}\s*:\s+(.+)#");

            if (!empty($rate) || !empty($type)) {
                $h->addRoom()
                    ->setRate($rate, true, true)
                    ->setType($type, true, true);
            }

            // Program
            $account = $segment->FindSingleNode("//text()[" . $this->starts($this->t('Hotel membership')) . "]/ancestor::td[1]",
                null, true, "#\s*:\s+(.+)#");

            if (!empty($account)) {
                $h->program()
                    ->account($account, false);
            }

            // Price
            $total = $segment->FindSingleNode("//text()[" . $this->starts($this->t('Approximate total')) . "]", null, true,
                "#\s*:\s+(.+)#");

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[,.\'\d ]*)\s*(?:\||$)#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[,.\'\d ]*)\s*(?<curr>[^\d\s]{1,5})\s*(?:\||$)#", $total, $m)
            ) {
                $h->price()
                    ->total($this->amount($m['amount']))
                    ->currency($this->currency($m['curr']));
            }
        }

        //#################
        //##    CARS    ###
        //#################

        $cars = [];

        if (isset($rsegments['car'])) {
            $cars = $rsegments['car'];
        }

        foreach ($cars as $segment) {
            $service = $segment->FindSingleNode("//text()[" . $this->starts("Service Information:") . "]/ancestor::td[1]");

            if (preg_match("/LIMO SERVICE/i", $service)) {
                continue;
            }

            $r = $email->add()->rental();

            // Extra
            $r->extra()->company($segment->FindSingleNode("/html/body/tr[2]/td[2]/descendant::text()[normalize-space()][1]"));

            // General
            $conf = $segment->FindSingleNode("//text()[" . $this->eq("Confirmation Number") . "]/ancestor::td[1]", null,
                true, "#Confirmation Number\s*:\s+([A-Z\d]{5,})\b#");

            if (empty($conf)) {
                $conf = $segment->FindSingleNode("//text()[{$this->eq('CAR')}]/following::text()[{$this->eq("Confirmation Number")}][1]/ancestor::td[1]", null,
                    true, "#Confirmation Number\s*:\s+([A-Z\d]{5,})\b#");
            }
            $renterNames = [];
            $reservedFor = $segment->FindSingleNode("//text()[{$this->starts("Reserved For")}]/ancestor::td[1]", null,
                true, "#{$this->opt("Reserved For")}\s*:+\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$#u");

            if ($reservedFor && !empty($r->getCompany()) && stripos($r->getCompany(),
                    $reservedFor) === false) { // it-43658960.eml
                $renterNames[] = $reservedFor;
            }
            $r->general()
                ->confirmation($conf)
            ;

            if (!empty($renterNames)) {
                $r->general()
                    ->travellers($renterNames);
            } elseif (!empty($passengers)) {
                $r->general()
                    ->travellers($passengers);
            }

            // Pick Up
            $phone = $segment->FindSingleNode("//text()[" . $this->starts("Phone") . "]/ancestor::td[1]", null, true,
                "#Phone[\s:]+([\d\+\-\(\) ]{5,})\b#");
            $location = $segment->FindSingleNode("//text()[" . $this->starts("Location") . "]/ancestor::td[1]", null,
                true, "#\s*:\s+(.+)#");

            if (preg_match("#(.+?)\s*Phone\s*(.+)#", $location, $m)) {
                $location = $m[1];
                $phone = !empty($phone) ? $phone : $m[2];
            }

            if (!empty($phone)) {
                $r->pickup()->phone($phone);
            }

            $pickup = $segment->FindSingleNode("//text()[" . $this->starts("Pickup") . "]/ancestor::td[1]", null, true,
                "#\s*:\s*(.+)#");

            if (preg_match("#^\s*([A-Z]{4}\d{2})\s*$#", $pickup)) {
                $r->pickup()->location($location);
            } else {
                $r->pickup()->location($location . ', ' . $pickup);
            }
            $date = $segment->FindSingleNode("/html/body/tr[1]/*[{$xpathCell}][2]");
            $time = $segment->FindSingleNode("//text()[" . $this->starts("Pick up Time") . "]/ancestor::td[1]", null,
                true, "#\s*:\s+(.+)#");

            if (!empty($date)) {
                $r->pickup()
                    ->date($this->normalizeDate($date . ' ' . $time));
            }

            // Drop Off
            $dropoff = $segment->FindSingleNode("//text()[" . $this->starts("Drop Off") . "]/ancestor::td[1]", null, true,
                "#\s*:\s+(.+)#");
            $this->logger->error($dropoff);

            if (!empty($dropoff)) {
                if ($pickup === $dropoff) {
                    $r->dropoff()->same();
                } else {
                    $r->dropoff()->location($dropoff);
                }
            }
            $r->dropoff()
                ->date($this->normalizeDate($segment->FindSingleNode("//text()[" . $this->starts("Return") . "]/ancestor::td[1]",
                    null, true, "#\s*:\s+(.+)#")));

            // Car
            $r->car()
                ->type($segment->FindSingleNode("//text()[" . $this->starts("Type") . "]/ancestor::td[1]", null, true,
                    "#\s*:\s+(.+)#"));

            // Program
            $account = $segment->FindSingleNode("//text()[" . $this->starts("Car membership Nbr") . "]/ancestor::td[1]",
                null, true, "#\s*:\s+(.+)#");

            if (!empty($account)) {
                $r->program()
                    ->account($account, false);
            }

            // Price
            $total = $segment->FindSingleNode("//text()[" . $this->starts("Approximate total") . "]", null, true,
                "#\s*:\s+(.+)#");

            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
                $r->price()
                    ->total($this->amount($m['amount']))
                    ->currency($this->currency($m['curr']));
            }
        }

        $total = $this->amount($this->http->FindSingleNode("//text()[" . $this->contains("Total Amount:") . "]", null,
            true, "#Total Amount:\s*(.+)#"));
        $currency = $this->http->FindSingleNode("(//text()[" . $this->contains("Base:") . "])[1]", null, true,
            "# ([A-Z]{3})$#");

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("(//text()[" . $this->contains("Base:") . "])[1]", null, true,
                "#\s+Base[ ]*:[ ]*[\d\.\, ]+[ ]*([A-Z]{3})(?:\s+|$)#");
        }

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("(//text()[" . $this->contains("Amount:") . "])[1]", null, true,
                "#\s+Amount[ ]*:[ ]*[\d\.\, ]+[ ]*([A-Z]{3})(?:\s+|$)#");
        }

        if (empty($currency)) {
            $currency = $this->http->FindSingleNode("(//text()[" . $this->contains(" Tax:") . "])[1]", null, true,
                "#\s+Tax[ ]*:[ ]*[\d\.\, ]+[ ]*([A-Z]{3})(?:\s+|$)#");
        }

        if ($total !== null && !empty($currency)) {
            if (empty($airs) || empty($rails)) {
                foreach ($email->getItineraries() as $key => $it) {
                    if ($it->getType() == 'flight' || $it->getType() == 'train') {
                        $email->getItineraries()[$key]->price()
                            ->total($total)
                            ->currency($currency);
                    }
                }
            } else {
                $email->price()
                    ->total($total)
                    ->currency($currency);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->detectProvider();

        $email->setType('TicketedItinerary' . ucfirst($this->lang));

        $this->date = strtotime($parser->getDate());

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        if (!isset(self::$detectProvider) || !array_key_exists('amextravel', self::$detectProvider)
            || !is_array(self::$detectProvider['amextravel'])
            || !array_key_exists('from', self::$detectProvider['amextravel'])
            || !is_array(self::$detectProvider['amextravel']['from'])
        ) {
            return false;
        }

        foreach (self::$detectProvider['amextravel']['from'] as $value) {
            if (stripos($from, $value) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        $foundFroms = false;

        foreach (self::$detectProvider as $code => $values) {
            if (!empty($values['from'])) {
                foreach ($values['from'] as $dFrom) {
                    if (strpos($headers["from"], $dFrom) !== false) {
                        $foundFroms = true;
                        $this->providerCode = $code;

                        break 2;
                    }
                }
            }
        }

        if ($foundFroms === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->assignLang() == true) {
            if ($this->http->XPath->query('//img[contains(@src, "www.iqcx.com/uploadfile/v3/air.png") or contains(@src, "www.iqcx.com/uploadfile/v3/car.png") or contains(@src, "www.iqcx.com/uploadfile/v3/hotel.png")]')->length > 0
                || ($this->striposArray($parser->getHTMLBody(), ['AIR', 'CAR', 'HOTEL', 'Rail']) !== false
                    && $this->http->XPath->query("//text()[" . $this->eq("AIR") . " or " . $this->eq("HOTEL") . " or " . $this->eq("CAR") . " or " . $this->eq("Rail") . "]/ancestor::tr[1][count(./td)>3 or count(./th)>3]")->length > 0)
            ) {
                return true;
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

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    private function detectProvider(): void
    {
        $body = $this->http->Response['body'];

        foreach (self::$detectProvider as $code => $values) {
            if (!empty($values['body'])) {
                foreach ($values['body'] as $dBody) {
                    if (stripos($body, $dBody) !== false) {
                        if (empty($this->providerCode)) {
                            $this->providerCode = $code;
                        }

                        break 2;
                    }
                }
            }

            if (!empty($values['from'])) {
                foreach ($values['from'] as $dFrom) {
                    if ($this->http->XPath->query('//a[contains(@href, "' . trim($dFrom, '@') . '")]')->length > 0) {
                        if (!empty($this->providerCode)) {
                            $this->providerCode = $code;
                        }

                        break 2;
                    }
                }
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr, $relDate = false)
    {
        $this->logger->notice('IN-' . $instr);

        if ($relDate === false) {
            $relDate = $this->date;
        }
        $in = [
            "#^(?<week>[^\s\d]+) (\d+)\. ([^\s\d]+) (\d+:\d+) Uhr$#",
            //Fr 23. Mrz 17:00 Uhr
            "#^(\d+:\d+) Uhr$#",
            //17:00 Uhr
            "#^[^\s\d]+,\s*([^\s\d]+)\s*(\d+),\s*(\d{4})$#",
            //Wednesday, Feb 08, 2017
            "#^[^\s\d]+, (\d{1,2})/(\d{1,2})/(\d{2})$#",
            //Friday, 18/01/19
            "#^[^\s\d]+,\s*(\d+)\s*([^\s\d]+)\s*,\s*(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$#i",
            //Thursday, 07 Feb,2019 17:00
            "#^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*(\d{1,2})/(\d{1,2})/(\d{2})$#",
            //06:15 14/02/19
            "#^\w+[,]\s*(\d+)(\w+)\s*(\d{4})\s([\d\:]+)((?:A|P))$#",
            //Friday, 27MAR 2020 9:00A
            "#^\w+[,]\s*(\d+)(\w+)\s*(\d{4})$#u",
            //Friday, 27MAR 2020
        ];
        $out = [
            "$2 $3 %Y%, $4",
            "$1",
            "$2 $1 $3",
            "$1.$2.20$3",
            "$1 $2 $3, $4",
            "$2.$3.20$4, $1",
            "$1 $2 $3, $4 $5M",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d',
                strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
    }

    private function striposArray($haystack, $arrayNeedle): bool
    {
        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount(?string $price): ?float
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
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
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function starts($field): string
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '#');
        }, $field)) . ')';
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//*[{$this->eq($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
