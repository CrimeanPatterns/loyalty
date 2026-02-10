<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// parsers with similar formats: travelinc/ItineraryPDF, bcd/TravelPlanPDF, bcd/Itinerary1, bcd/ReceiptFor, wtravel/It2388019, transport/It1995087

class TravelReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "bcd/it-11487789.eml, bcd/it-141588472.eml, bcd/it-14891941.eml, bcd/it-22.eml, bcd/it-2222064.eml, bcd/it-2222766.eml, bcd/it-2224034.eml, bcd/it-23.eml, bcd/it-2603347.eml, bcd/it-2603375.eml, bcd/it-2603377.eml, bcd/it-35841242.eml, bcd/it-60393119.eml, bcd/it-61971176.eml, bcd/it-6376153.eml, bcd/it-6587792.eml, bcd/it-6731084.eml, bcd/it-8711141.eml";

    public $subjects = [
        'pt' => ['Recibo de viagem para'],
        'en' => ['Travel Receipt for', 'Travel arrangements for'],
    ];

    public $lang = '';

    public static $reBody = [
        'bcd'         => 'BCD Travel', 'BOEING AIRFARE RECEIPT',
        'ctraveller'  => 'Corporate Traveler',
        'ctraveller+' => 'Corporate Traveller',
        'tleaders'    => 'Travel Leaders',
        'Black & Veatch',
        'Ticket/Invoice Information:',
        'Travel Summary – ',
        'TTI Travel',
        'fcmtravel'   => 'FCM Travel Solutions',
        'amextravel'  => 'American Express Global Business Travel',
        'stagescreen' => 'Stage & Screen Travel Services',
        'otg'         => 'Thank you for choosing Ovation Travel Group',
        ' Protravel',
        'Globetrotter Corporate Travel',
        'to access Print My Invoice',
    ];

    public $langDetectors = [
        'pt' => ['Resumo de Viagem'],
        'en' => ['Travel Summary', 'Invoice Information', 'FF Number:'],
    ];

    //	public $pdfPattern = "("
    //		. "(Invoice - Itinerary|Travel Receipt|Itinerary) (Communication|Confirmation) Attachment - [A-Z\d]+ - ([^\s\d]+ \d+ \d{4}|\d+_\d+_\d+|[^\s\d]+, [^\s\d]+ \d+, \d{4}).*.pdf|" // en
    //		. "Anexo com comunicado de recibo de viagem - [A-Z\d]+ - \d+ de [^\s\d]+ de \d{4}.PDF" // pt
    //		. ")";

    public static $dictionary = [
        'pt' => [
            "Add to Calendar"=> "(?:Acrescentar ao Calendário|\s+-\s+Localizador da)",
            "Status:"        => "Situação da reserva:",
            "Record Locator:"=> "Número de reserva:",
            "AIR"            => "Reserva de Voo",
            "HOTEL"          => "Reservas Hotel",
            "Date"           => "Data",
            //			"CAR"=>"",
            "Travel Summary"       => "Resumo de Viagem",
            "Total Amount:"        => "Valor Total:",
            "Ticket Amount:"       => "Valor Total:",
            "Estimated trip total" => "Total estimado da viagem",
            "Agency Record Locator"=> "Localizador da Agência",

            "totalAir"   => "Aéreo",
            "totalCar"   => "Carro",
            "totalHotel" => "Hotel",
            "totalOther" => "Outro",

            // FLIGHT
            "Traveler"    => "Passageiro",
            "Flight"      => "Voo",
            "Depart:"     => "De:",
            "Arrive:"     => "Para:",
            "Operated By:"=> "NOTTRANSLATED",
            "Equipment:"  => "Equipamento:",
            "Distance:"   => "Distância:",
            "Seat:"       => "Lugar:",
            "Duration:"   => "Duração de Voo:",
            "Nonstop"     => "Directo",
            "with"        => "NOTTRANSLATED",
            "Stop\(s\):"  => "NOTTRANSLATED",
            "Meal:"       => "Refeição:",

            // HOTEL
            "Confirmation:"       => "Confirmação:",
            "Frequent Guest ID:"  => "NOTTRANSLATED",
            "Address:"            => "Endereço:",
            "Check In/Check Out:" => "Check in/Check out:",
            "Tel:"                => "Telefone:",
            "Fax:"                => "Fax:",
            "Number of Persons:"  => "Número de Pessoas:",
            "Number of Nights:"   => "Número de noites:",
            "Number of Rooms:"    => "Número de quartos:",
            "Rate per night:"     => "Preço por noite:",
            "Cancellation Policy:"=> "Condições de",
            "Description:"        => "Descrição:",
            "Guaranteed:"         => "Garantia:",

            "ItineraryEnd" => [],
        ],
        'en' => [
            "Add to Calendar"      => "(?:Add to Calendar|\s+­\s+Agency Record Locator)",
            "Record Locator:"      => "(?:Record Locator:|Booking Reference:)",
            "Traveler"             => "(?:Traveler|Traveller)",
            "Total Amount:"        => ["Total Amount:", 'Total Invoiced Amount:'],
            "Ticket Amount:"       => ["Ticket Amount:", 'Amount:'],
            "Estimated Total:"     => ["Estimated Total:", "Est. Total", "Total:", 'Approx total:'],
            "Agency Record Locator"=> ["Agency Record Locator", "- Record"],
            "Ticket Number:"       => ["Ticket Number:", "YOUR E-TICKET NUMBER IS", "Electronic Ticket Number:"],
            "Seat:"                => ["Seat:", "SEAT"],
            "ItineraryEnd"         => ["\nUseful Links\n", "\nTerms and Conditions\n"],

            "Tel:"      => ["Tel:", "Tel"],
            "Fax:"      => ["Fax:", "Fax"],
            "Nonstop"   => "Non[­-]*stop",
            "Distance:" => ["Distance:", "Mileage:"],

            "Rate per night:" => ["Rate per night:", "Rate:", "Rate Per Night:"],

            "totalAir"   => "Air",
            "totalCar"   => "Car",
            "totalHotel" => "Hotel",
            "totalOther" => "Other",
            // CAR
            //            "Estimated Total:" => ["Estimated Total:", "Est. Total:"],
        ],
    ];

    public $text = '';

    public function parsePdf(Email $email)
    {
        $text = str_ireplace(['&shy;', '&173;', '­'], '-', $this->text);
        $textCodes = $this->cutText($this->t('Travel Summary'), $this->t('AIR'), $text);
        $codes = [];
        $classes = [];

        if (preg_match_all('/\b(?<date>\d{1,2}\/\d{1,2}\/\d{4})[ ]+(?<codes>[A-Z]{3}[ ]*-[ ]*[A-Z]{3})[ ]+(?<flight>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+).*/', $textCodes, $m)) {
            // 09/30/2018    ADD-NLA    ET 875
            foreach ($m['codes'] as $i => $cs) {
                $fn = $m['date'][$i] . ' ' . preg_replace('/\s+/', '', $m['flight'][$i]);
                $codes[$fn] = $cs;

                if (preg_match("#[ ]{2,}(\w+) Class ?/ ?([A-Z]{1,2})\s*$#u", $m[0][$i], $mat)) {
                    $classes[$fn]['class'] = $mat[1];
                    $classes[$fn]['code'] = $mat[2];
                }
            }
        }

        $agencyRecordLocator = $this->re('/' . $this->opt($this->t("Agency Record Locator")) . '[ ]+([A-Z\d]{5,})/', $text);

        if (!empty($agencyRecordLocator)) {
            $email->ota()
                ->confirmation($agencyRecordLocator);
        }
        $itineraryText = $this->cutText($this->t('Travel Summary'), $this->t('ItineraryEnd'), $text);

        if ($itineraryText == false) {
            $itineraryText = $text;
        }

        if (strpos($text, $this->t("Add to Calendar")) !== false) { // it-2603377.eml
            $segments = $this->split("#\n([^\n]*" . $this->t("Add to Calendar") . ")#", $text);
        } else { // it-11487789.eml
            $segments = $this->split('/^[ ]*((?:' . $this->t('AIR') . '|' . $this->t('HOTEL') . '|' . $this->t('CAR') . '|' . $this->t('LIMO') . '|' . $this->t('RAIL') . ')\b.+\n.+)/m', $itineraryText);
        }
        $airs = [];
        $hotels = [];
        $cars = [];
        $limos = [];
        $rails = [];

        foreach ($segments as $stext) {
            $type = trim($this->re("#^(.*?)[\­\-]#u", $stext));

            switch ($type) {
                case $this->t('AIR'):
                    if (!$rl = $this->re("#" . $this->t("Status:") . ".*" . $this->t("Record Locator:") . "\s+\*?(\w+)#",
                        $stext)) {
                        if (!$rl = $this->re("#Record Locator\s+\*?(\w+)#", $stext)) {
                            if ($this->re("#" . $this->t("Status:") . "\s*(\w+)\s*\n#", $stext)) {
                                $rl = 'undefined';
                            } else {
                                $this->logger->alert('RL not matched!');
                                $this->logger->debug('in segment: ' . print_r($stext, true));

                                return;
                            }
                        }
                    }
                    $airs[$rl][] = $stext;

                    break;

                case $this->t('HOTEL'):
                    $hotels[] = $stext;

                    break;

                case $this->t('CAR'):
                    $cars[] = $stext;

                    break;

                case $this->t('LIMO'):
                    $limos[] = $stext;

                    break;

                case $this->t('RAIL'):
                    $rl = $this->re("#" . $this->t("Confirmation:") . "[ ]*(\w+)#", $stext);

                    if (empty($rl)) {
                        $rl = 'undefined';
                    }
                    $rails[$rl][] = $stext;

                    break;

                case $this->t('Travel Summary'):
                    break;

                    break;

                default:
                    $this->logger->alert("Unknown segment type {$type}!");

                    return;
            }
        }

        $totalPosBegin = strpos($text, $this->t('Estimated trip total'));

        if (!empty($totalPosBegin)) {
            if (preg_match("#" . $this->t('Estimated trip total') . ".*\n([ ]*" . $this->t('totalAir') . "[ ]+" . $this->t('totalCar') . ".+)\s*\n([ ]*\d[\d\.\,]+.+)#", substr($text, $totalPosBegin, 500), $m)) {
                $titles = array_map('trim', array_filter(preg_split("#\s{2,}#", $m[1])));

                foreach ([$this->t('totalAir'), $this->t('totalHotel'), $this->t('totalCar'), $this->t('totalOther')] as $key => $value) {
                    if (($pos = stripos($m[1], $value)) !== false && preg_match("#^.{" . (($pos - 3) > 0 ? ($pos - 3) : 0) . "}[ ]{1,3}(\d[\d\.\, ]+)\s*([A-Z]{3})#", $m[2], $mat)) {
                        $email->price()
                            ->total($this->amount($mat[1]))
                            ->currency($mat[2]);
                    }
                }
            }
        }

        $pax = $this->re("#\n\s*" . $this->opt($this->t("Traveler")) . "\n(.+)#", $text);

        if (empty($pax)) {
            $table = $this->re("#\n({$this->opt($this->t("Traveler"))}\s+.+){$this->opt($this->t("Date"))}#ms", $text);
            $table = $this->SplitCols($table, $this->ColsPos($table, 10));

            if (count($table) === 3) {
                $pax = $this->re("#" . $this->t("Traveler") . "\n(.+)#", $table[0]);
                $tripNum = $this->re("#" . $this->opt($this->t("Reference #")) . "\s+([A-Z\d]{5,})#", $table[1]);
                $accNums = array_values(array_filter(array_map("trim", explode(",", $this->re("#" . $this->opt($this->t("Frequent Flyer #")) . "\s+(.+)#s", $table[2])))));
            }
        }

        if (!empty($pax)) {
            $pax = preg_replace("#^\s*(\S.+?)\s{2,}.*#s", '$1', $pax);
        }
        $pax = [$pax];

        $ticketNumbers = [];

        if (preg_match_all("#\n\s*" . $this->opt($this->t("Ticket Number:")) . "[ ]*([A-Z\d \-]{8,})#", $text, $m)) {
            foreach ($m[1] as $key => $value) {
                if (false !== strpos($value, '    ')) {
                    $ticketNumbers[] = explode("    ", $value)[0];
                } else {
                    $ticketNumbers[] = $value;
                }
            }
        } elseif (preg_match_all("/\d+\/\d+\/\d{4}\s+([A-Z]{1,2}\d{9,})\s+/", $text, $m)) {
            $ticketNumbers = array_merge($ticketNumbers, $m[1]);
        } elseif (preg_match_all("#\s+" . $this->t("Ticket Number") . "[ ]*([\d\-]{8,})#", $text, $m)) {
            $ticketNumbers = array_unique($m[1]);
        }

        //#################
        //##   FLIGHT   ###
        //#################
        foreach ($airs as $rl => $segments) {
            $flight = $email->add()->flight();

            // RecordLocator
            if ($rl === 'undefined') {
                $flight->general()
                    ->noConfirmation();
            } else {
                $flight->general()
                    ->confirmation($rl);
            }

            if (count(array_filter($pax)) > 0) {
                $flight->general()
                    ->travellers(preg_replace("/(?:MR\s*\*?|\*\s*\d+)$$/", "", $pax));
            }

            // AccountNumbers
            $accountNumbers = [];

            if (isset($accNums) && !empty($accNums)) {
                $accountNumbers = $accNums;
            }
            // TicketNumbers
            if (1 < count($ticketNumbers)) {
                $flight->issued()
                    ->tickets(array_unique($ticketNumbers), false);
            } elseif ($tn = array_shift($ticketNumbers)) {
                $flight->issued()
                    ->ticket($tn, false);
            }

            // TotalCharge
            // Currency
            if (count($airs) === 1) {
                if (preg_match("#" . $this->preg_implode($this->t("Ticket Amount:")) . "[ ]+[^\d\s]*(\d[\d\,\. ]+)[ ]?([A-Z]{3})#", $text, $m)) {
                    $flight->price()
                        ->total($this->amount($m[1]))
                        ->currency($m[2]);
                } elseif (preg_match("#" . $this->preg_implode($this->t("Ticket Amount:")) . "[ ]+([^\d\s]{1,5}) ?(\d[\d\,\. ]+)\n#", $text, $m)) {
                    $flight->price()
                        ->total($this->amount($m[2]))
                        ->currency($this->currency($m[1]));
                } elseif (!empty($total[$this->t('totalAir')])) {
                    $flight->price()
                        ->total($total[$this->t('totalAir')])
                        ->currency($total['Currency']);
                }
            }

            $passengers = [];

            foreach ($segments as $stext) {
                $seg = $flight->addSegment();
                // AccountNumbers
                if (preg_match_all('/\s+([A-Z\d]+\d+[A-Z\d]+)\s+[­-][ ]+[A-Z\/ ]+/', $stext, $m)) {
                    foreach ($m[1] as $cc) {
                        if (strlen($cc) > 5) {
                            $accountNumbers[] = $cc;
                        }
                    }
                }

                $seats = [];

                if (preg_match_all('/\b(\d{1,3}[A-Z])[ ]+\w+[ ]+\-[ ]+([A-Z\/ ]+)(?:\n|$)/', $stext, $m)) {
                    foreach ($m[2] as $name) {
                        $passengers[] = trim($name);
                    }

                    foreach ($m[1] as $s) {
                        $seats[] = $s;
                    }
                }

                $date = strtotime($this->normalizeDate($this->re("#[­-]\s+(.*?)(?:\s*[­-]\s+|\n|\s{2,})#", $stext)));

                // AirlineName
                // FlightNumber
                if (preg_match('/' . $this->t("Flight") . '[ ]+([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)/', $stext, $matches)) {
                    $seg->airline()
                        ->name($matches[1])
                        ->number($matches[2]);
                }

                // DepCode
                // ArrCode
                if ($date && !empty($seg->getAirlineName()) && !empty($seg->getFlightNumber())) {
                    if ((!empty($codes[date('m/d/Y', $date) . ' ' . $seg->getAirlineName() . $seg->getFlightNumber()])
                            && preg_match('/([A-Z]{3})[ ]*-[ ]*([A-Z]{3})/', $codes[date('m/d/Y', $date) . ' ' . $seg->getAirlineName() . $seg->getFlightNumber()], $m))
                        || (!empty($codes[date('d/m/Y', $date) . ' ' . $seg->getAirlineName() . $seg->getFlightNumber()])
                            && preg_match('/([A-Z]{3})[ ]*-[ ]*([A-Z]{3})/', $codes[date('d/m/Y', $date) . ' ' . $seg->getAirlineName() . $seg->getFlightNumber()], $m))
                    ) {
                        $seg->departure()->code($m[1]);
                        $seg->arrival()->code($m[2]);
                    }

                    if (!empty($classes[date('m/d/Y', $date) . ' ' . $seg->getAirlineName() . $seg->getFlightNumber()])) {
                        $seg->extra()
                            ->cabin($classes[date('m/d/Y', $date) . ' ' . $seg->getAirlineName() . $seg->getFlightNumber()]['class'])
                            ->bookingCode($classes[date('m/d/Y', $date) . ' ' . $seg->getAirlineName() . $seg->getFlightNumber()]['code'])
                        ;
                    }
                }

                // DepName
                $seg->departure()
                    ->name($this->re("#" . $this->t("Depart:") . "\s+(.*?)(?:,|[ ]{2}|\n)#", $stext));

                // DepartureTerminal
                $terminalDep = $this->re('/' . $this->t("Depart:") . '.+, ((?:[^,]* )?Terminal(?: \S.*?)?)(?:[ ]{2}|$)/mi', $stext);

                if ($terminalDep) {
                    $seg->departure()
                        ->terminal(trim(preg_replace('#\s*Terminal\s*#i', ' ', $terminalDep)));
                }

                // DepDate
                $seg->departure()
                    ->date(strtotime($this->normalizeDate($this->re("#" . $this->t("Depart:") . ".*?(\d+:\d+[^\n]+)#ms", $stext)), $date));

                // ArrName
                $seg->arrival()
                    ->name($this->re("#" . $this->t("Arrive:") . "\s+(.*?)(?:,|[ ]{2}|\n)#", $stext));

                // ArrivalTerminal
                $terminalArr = $this->re('/' . $this->t("Arrive:") . '.+, ((?:[^,]* )?Terminal(?: \S.*?)?)(?:[ ]{2}|$)/mi', $stext);

                if ($terminalArr) {
                    $seg->arrival()
                        ->terminal(trim(preg_replace('#\s*Terminal\s*#i', ' ', $terminalArr)));
                }

                // ArrDate
                $seg->arrival()->date(strtotime($this->normalizeDate($this->re("#" . $this->t("Arrive:") . ".*?(\d+:\d+[^\n]+)#ms", $stext)), $date));

                // Operator
                if ($operator = $this->re("#" . $this->t("Operated By:") . "\s+(.+)#", $stext)) {
                    if (!preg_match("/{$this->opt($this->t('Seat:'))}/", $operator)) {
                        $operator = preg_replace('/^\s*OPERATED BY\s+/', '', $operator);
                        $seg->airline()
                            ->operator($operator);
                    }
                }

                // Aircraft
                $seg->extra()
                    ->aircraft($this->re("#" . $this->t("Equipment:") . "\s+(.+)#", $stext), true, true);

                // TraveledMiles
                if ($traveledMiles = $this->re("#" . $this->preg_implode($this->t("Distance:")) . "\s+(.+)#", $stext)) {
                    $seg->extra()->miles($traveledMiles);
                }

                // Cabin
                if (empty($seg->getCabin()) && $class = $this->re("#" . $this->t("Flight") . "\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+\s+(.*?)(?:\n|[^\n\S]{2,})#", $stext)) {
                    if (preg_match("#^\s*([A-Z]{1,2})\s*-\s*(.+)\s*#", $class, $m)) {
                        $seg->extra()
                            ->cabin($m[2])
                            ->bookingCode($m[1])
                        ;
                    } else {
                        $seg->extra()->cabin(trim(str_ireplace(['Class'], '', $class)));
                    }
                }

                // Seats
                if (($seat = $this->re('/' . $this->opt($this->t("Seat:")) . '\s+(\d{1,2}[A-Z])\b/', $stext)) && !in_array($seat, $seats)) {
                    $seg->extra()->seat($seat);
                } else {
                    $seg->extra()->seats(array_filter(array_unique($seats)));
                }

                // Duration
                $seg->extra()->duration($this->re("#" . $this->t("Duration:") . "\s+(.*?\d.*?)?(?:\s+" . $this->t("Nonstop") . "|\s+" . $this->t("with") . "\s+|\n)#", $stext));

                // Meal
                if ($meal = $this->re("#" . $this->t("Meal:") . "\s+(.+)#", $stext)) {
                    $seg->extra()->meal($meal);
                }

                // Stops
                if (!$stops = $this->re("#" . $this->t("Duration:") . "\s+.*\s+(" . $this->t("Nonstop") . ")#", $stext)) {
                    if (!$stops = $this->re("#" . $this->t("Duration:") . "\s+.*\s+" . $this->t("with") . "\s+(.+)#", $stext)) {
                        $stops = $this->re("#" . $this->t("Stop\(s\):") . "[ ]+(.+)#", $stext);
                    }
                }

                if (!empty($stops)) {
                    if (!empty($this->re("/((?:Non[ ­-]*stop|Directo))/i", $stops))) {
                        $stops = 0;
                    }

                    if (is_integer($stops)) {
                        $seg->extra()
                            ->stops($stops);
                    }
                }

                // DepCode
                // ArrCode
                if (!empty($seg->getDepName()) && !empty($seg->getArrName()) && empty($seg->getDepCode()) && empty($seg->getArrCode())) {
                    $seg->departure()->noCode();
                    $seg->arrival()->noCode();
                }
            }

            if (!empty($accountNumbers[0])) {
                $flight->ota()->accounts(array_values(array_unique($accountNumbers)), false);
            }

            $passengers = array_filter(array_unique($passengers));

            if (0 < count($passengers)) {
                foreach ($passengers as $pass) {
                    if (!in_array($pass, array_column($flight->getTravellers(), 0))) {
                        $flight->general()->traveller($pass);
                    }
                }
            }
        }

        //################
        //##   HOTEL   ###
        //################
        foreach ($hotels as $htext) {
            if (!preg_match("/{$this->opt($this->t('Reservation Name:'))}/", $htext) && !preg_match("/{$this->opt($this->t('Number of Nights:'))}/", $htext)) {
                continue;
            }
            $hotel = $email->add()->hotel();

            // ConfirmationNumber
            $hotel->general()
                ->confirmation(trim($this->re("#" . $this->t("Confirmation:") . "\s+(.+)#", $htext), '-'));

            // AccountNumbers
            if ($frequentGuestID = $this->re("#" . $this->t("Frequent Guest ID:") . "\s+(.+)#", $htext)) {
                $hotel->ota()->account($frequentGuestID, false);
            }

            // HotelName
            $hotelName = $this->re("#\n\s*(.+)\n\s*" . $this->t("Address:") . "#", $htext);

            if (empty($hotelName)) {
                $hotelName = $this->re("#\n\s*(.+)\n\s*" . $this->t("Check In/Check Out:") . "#", $htext);
            }

            if (!empty($hotelName)) {
                $hotel->hotel()
                    ->name($hotelName);
            }

            // CheckInDate
            $checkInDate = strtotime($this->normalizeDate($this->re("#" . $this->t("Check In/Check Out:") . "\s+(.*?\d{4}) [­-]? #", $htext)));

            if (empty($checkInDate)) {
                $checkInDate = strtotime($this->normalizeDate($this->re("#^" . $this->t("HOTEL") . "[­\- ]+(.+?)[ ]{2,}#", $htext)));
            }

            if (!empty($checkInDate)) {
                $hotel->booked()
                    ->checkIn($checkInDate);
            }

            // CheckOutDate
            $checkOutDate = strtotime($this->normalizeDate($this->re("#" . $this->t("Check In/Check Out:") . "\s+.*?\d{4} [­-]? (.+)#", $htext)));

            if (empty($checkOutDate)) {
                $checkOutDate = strtotime($this->normalizeDate($this->re("#\s+" . $this->t("Check Out:") . "[ ]*(.+)\s+#", $htext)));
            }

            if (!empty($checkOutDate)) {
                $hotel->booked()
                    ->checkOut($checkOutDate);
            }

            // Address

            $address = $this->re("#{$this->t("Address:")}\s+(.+?)\s+(?:{$this->opt($this->t("Check In/Check Out:"))}|{$this->opt($this->t("Tel:"))}|{$this->opt($this->t("Fax:"))})#s", $htext);
            $address = preg_replace("#[ ]{5,}Weather\n#", "\n", $address);
            $address = preg_replace("#\s*\(Directions\)\s*$#", '', $address);
            $address = trim(preg_replace("#\s+#", ' ', $address));

            if (!empty($address)) {
                $hotel->hotel()
                    ->address($address);
            } else {
                $hotel->hotel()
                    ->noAddress();
            }

            // Phone
            $phone = trim($this->re(
                "#{$this->opt($this->t("Tel:"))}\s+(.+?)(?:{$this->opt($this->t("Fax:"))}|\n)#",
                $htext));

            if (!empty($phone)) {
                $hotel->hotel()
                    ->phone($phone);
            }

            // Fax
            $fax = $this->re("#" . $this->opt($this->t("Fax:")) . "[ ]+(.+)#", $htext);

            if (!empty($fax)) {
                $hotel->hotel()
                    ->fax($fax);
            }

            // GuestNames

            $travellerText = $this->re("#" . $this->t("Traveler") . "\n(.+)#", $text);
            $travellers = preg_split('/( {3,}|\n)/', $travellerText);
            $travellers = array_filter($travellers, function ($v) {
                if (preg_match('/^([A-Z\\/ ]+)[\*\d]*$/', $v)) {
                    return true;
                } else {
                    return false;
                }
            });

            if (!empty($travellers)) {
                $hotel->general()
                    ->travellers(preg_replace("/(?:MR\s*\*|\*\s*\d+)$/", "", $travellers));
            }

            // Guests
            $guests = $this->re("#" . $this->t("Number of Persons:") . "\s+(.+)#", $htext);

            if (!empty($guests)) {
                $hotel->booked()
                    ->guests($guests);
            }

            // Rooms
            $rooms = $this->re("#" . $this->t("Number of Rooms:") . "\s+(.+)#", $htext);

            if (!empty($rooms)) {
                $hotel->booked()
                    ->rooms($this->re("#" . $this->t("Number of Rooms:") . "\s+(.+)#", $htext));
            }

            // CancellationPolicy
            $cancellation = $this->re("#" . $this->t("Cancellation Policy:") . "\s+(.+)#", $htext);

            if (!empty($cancellation)) {
                $hotel->general()
                    ->cancellation($cancellation);

                $this->detectDeadLine($hotel, $cancellation);
            }

            // Rate
            $room = $hotel->addRoom();

            $roomRate = '';

            if (stripos($htext, $this->t('Guaranteed:')) !== false) {
                $roomRate = $this->re("#" . $this->opt($this->t("Rate per night:")) . "\s+(.+)" . $this->opt($this->t("Guaranteed:")) . "#us", $htext);
            } elseif (stripos($htext, $this->t('Confirmation:')) !== false) {
                $roomRate = $this->re("#{$this->opt($this->t("Rate per night:"))}\s+(.+)\s+{$this->opt($this->t("Confirmation:"))}#us", $htext);
            }

            if (!empty($roomRate)) {
                $room->setRate(preg_replace("/\s+/", " ", str_replace("\n", ", ", $roomRate)));
            }

            // RoomType
            // RoomTypeDescription
            $roomType = $this->re("#" . $this->t("Room Type:") . "\s+(.+)#", $htext);

            if (!empty($roomType) && !preg_match("/Number of Nights/u", $roomType)) {
                $room->setType($roomType);
            }

            $roomDescription = $this->re("#" . $this->t("Description:") . "\s+(.+)#", $htext);

            if (!empty($roomDescription)) {
                $room->setDescription($roomDescription);
            }

            if (empty($room->getType()) && !empty($this->re("#" . $this->t("Description:") . "\s+(.*?);#", $htext))) {
                $room->setType($this->re("#" . $this->t("Description:") . "\s+(.*?);#", $htext));
                $room->setDescription($this->re("#" . $this->t("Description:") . "\s+.*?;\s*(.+)#", $htext));
            }

            if (empty($room->getType()) && empty($room->getDescription()) && empty($room->getRate())) {
                $hotel->removeRoom($room);
            }

            // Total
            // Currency
            if (count($hotels) == 1 && !empty($total[$this->t('totalHotel')])) {
                $hotel->price()
                    ->total($total[$this->t('totalHotel')])
                    ->currency($total['Currency']);
            } elseif (preg_match("#Est. Total Rate:\s+([A-Z]{3}) +(\d[\d\.]+)#", $htext, $m)) {
                $hotel->price()
                    ->total($m[2])
                    ->currency($m[1]);
            }

            // Status
            $status = $this->re("#" . $this->t("Status:") . "\s+(.+)#", $htext);

            if (!empty($status)) {
                $hotel->general()
                    ->status($status);
            }
        }

        //##############
        //##   CAR   ###
        //##############
        foreach ($cars as $ctext) {
            $car = $email->add()->rental();

            $a = [];
            $car->general()
                ->confirmation(str_replace('*', '-', $this->re("#Confirmation:\s+(.+)#", $ctext)));

            $date = strtotime($this->normalizeDate($this->re("#^" . $this->t("CAR") . "[­\- ]+(.+?)[ ]{2,}#", $ctext)));
            // PickupDatetime
            $pickup = implode("\n", array_map("trim", explode("\n", trim($this->re("#Pick Up:\s+(.*?)Drop Off:#ms", $ctext)))));
            $pickup = preg_replace("#[ ]{5,}Weather\n#", "\n", $pickup);
            $pickup = preg_replace("#\s*\(Directions\)\s*#u", '', $pickup);
            $dateStr = $a[count($a = explode("\n", $pickup)) - 1];

            if (preg_match("#^\s*Tel:#", $dateStr) && count($a) > 2) {
                $dateStr = $a[count($a = explode("\n", $pickup)) - 2];
            } elseif (preg_match("#\d{4}\s*Tel\:\s*([\d\-]+)#", $dateStr) && count($a) > 2) {
                $dateStr = $a[count($a = explode("\n", $pickup)) - 1];
            }

            $car->pickup()
                ->date(strtotime($this->normalizeDate($dateStr)));

            if ($car->getPickUpDateTime() < 1000000000 && preg_match("#^\D*\d+:\d+\D*$#", $dateStr) && !empty($date)) {
                $car->pickup()
                    ->date(strtotime($dateStr, $date));
            }

            // PickupLocation
            $pickUpLocation = preg_replace("#\s+#", " ", $this->re("#(.*?);#ms", $pickup));

            if (empty($pickUpLocation)) {
                $pickUpLocation = preg_replace("#\s+#", " ", $this->re("#([\s\S]+?)\n.+\n\s*Tel:\s+#", $pickup));
            }
            $car->pickup()
                ->location($pickUpLocation);

            // DropoffDatetime
            $dropoff = implode("\n", array_map("trim", explode("\n", trim($this->re("#Drop Off:\s+(.*?)Type:#ms", $ctext)))));
            $dropoff = preg_replace("#[ ]{5,}Weather\n#", "\n", $dropoff);
            $dropoff = preg_replace("#\s*\(Directions\)\s*$#", '', $dropoff);
            $dateStr = $a[count($a = explode("\n", $dropoff)) - 1];

            if (preg_match("#^\s*Tel:#", $dateStr) && count($a) > 2) {
                $dateStr = $a[count($a = explode("\n", $dropoff)) - 2];
            }

            $car->dropoff()
                ->date(strtotime($this->normalizeDate($dateStr)));

            // DropoffLocation
            $dropOffLocation = trim(preg_replace("#\s+#", " ", $this->re("#(.*?);#ms", $dropoff)));

            if (empty($dropOffLocation)) {
                $dropOffLocation = preg_replace("#\s+#", " ", $this->re("#([\s\S]+?)\n.+\n\s*Tel:\s+#", trim($dropoff)));
            }

            if (empty($dropOffLocation)) {
                $dropOffLocation = preg_replace("#\s+#", " ", trim($dropoff));
            }
            $car->dropoff()
                ->location($dropOffLocation);
            // PickupPhone
            $car->pickup()
                ->phone(str_replace("\n", " ", $this->re("#Tel:\s+(.*?)(?:;|$|\n)#ms", $pickup)));

            // PickupFax
            $pickUpFax = str_replace("\n", " ", $this->re("#Fax:\s+(.*?)(?:;|\n)#ms", $pickup));

            if (empty($pickUpFax) || strlen($pickUpFax) < 5) {
                $pickUpFax = str_replace("\n", " ", $this->re("#Fax:\s+([+]\d+\n[\d\(\)\s\-]+)\n#ms", $pickup));
            }

            if (!empty($pickUpFax)) {
                $car->pickup()
                    ->fax($pickUpFax);
            }

            // DropoffPhone
            $car->dropoff()
                ->phone(str_replace("\n", " ", $this->re("#Tel:\s+(.*?)(?:;|$|\n)#ms", $dropoff)), true, true);

            // DropoffFax
            $dropOffFax = str_replace("\n", " ", $this->re("#Fax:\s+(.*?)(?:;|\n)#ms", $dropoff));

            if (empty($dropOffFax) || strlen($dropOffFax) < 5) {
                $dropOffFax = str_replace("\n", " ", $this->re("#Fax:\s+([+]\d+\n[\d\(\)\s\-]+)\n#ms", $dropoff));
            }

            if (!empty($dropOffFax)) {
                $car->dropoff()
                    ->fax($dropOffFax);
            }

            // RentalCompany
            $car->setCompany($this->re("#\n\s*(.+)\n\s*Pick Up:#", $ctext));

            // CarType
            $carType = $this->re("#Type:\s+(.+)(?:Status)?#", $ctext);

            if (!empty($carType) && empty($this->re("/({$this->t('Status:')})/", $carType))) {
                $car->car()
                    ->type($carType);
            }

            // RenterName
            $traveller = $this->re("#(?:Traveler|Traveller)\n(.+)#", $text);

            if (!empty($traveller)) {
                $car->general()->traveller(preg_replace("/(?:MR\s*\*?|\*\s*\d+)$/", "", $traveller));
            }

            // TotalCharge
            $total = $this->re("#" . $this->opt($this->t("Estimated Total:")) . "\s+[A-Z]{3}\s+([\d\,\.]+)#", $ctext);

            if (empty(trim($total))) {
                $total = $this->re("#" . $this->opt($this->t("Estimated Total:")) . "\s+([\d\,\.]+)\s*[A-Z]{3}#", $ctext);
            }

            $currency = $this->re("#" . $this->opt($this->t("Estimated Total:")) . "\s+([A-Z]{3})\s+[\d\,\.]+#", $ctext);

            if (empty($currency)) {
                $currency = $this->re("#" . $this->opt($this->t("Estimated Total:")) . "\s+[\d\,\.]+\s*([A-Z]{3})#", $ctext);
            }
            $car->price()
                ->total($total)
                ->currency($currency);

            if (empty($car->getPrice()->getTotal()) && count($cars) == 1 && !empty($total[$this->t('totalCar')])) {
                $car->price()
                    ->total($total[$this->t('totalCar')])
                    ->currency($total['Currency']);
            }

            // Status
            $status = $this->re("#Status:\s+(.+)#", $ctext);

            if (!empty($status)) {
                $car->general()
                    ->status($status);
            }
        }

        //###############
        //##   LIMO   ###
        //###############
        foreach ($limos as $ctext) {
            $car = $email->add()->rental();

            $car->general()
                ->confirmation(str_replace('*', '-', $this->re("#Confirmation Number:\s+(.+)#", $ctext)));

            // PickupDatetime
            $pickup = trim($this->re("#Pickup Date and Time:\s+(.+)#", $ctext));
            $car->pickup()
                ->date(strtotime($this->normalizeDate($pickup)));

            // PickupLocation
            $car->pickup()
                ->location(str_replace("\n", " ", $this->re("#Pickup Location:\s*(.+)#", $ctext)));

            // DropoffDatetime
            $car->dropoff()
                ->noDate();

            // DropoffLocation
            $car->dropoff()
                ->location(str_replace("\n", " ", $this->re("#Dropoff Location:\s*(.+)#", $ctext)));

            // PickupPhone
            // DropoffPhone
            $phone = str_replace("\n", " ", $this->re("#\n\s*Phone Number:\s+(.+)#", $ctext));

            $car->pickup()
                ->phone($phone);

            $car->dropoff()
                ->phone($phone);

            // RentalCompany
            $car->setCompany(trim($this->re("#\n\s*Vendor:\s+(.+)#", $ctext)));

            // CarType
            $car->car()->type($this->re("#\bVEHICLE-(\w+)#", $text));

            // RenterName
            $car->general()->traveller($this->re("#(?:Traveler|Traveller)\n(.+)#", $text));

            // TotalCharge
            // Currency
            if (count($limos) === 1 && !empty($total[$this->t('totalOther')])) {
                $car->price()
                    ->total($total[$this->t('totalOther')])
                    ->currency($total['Currency']);
            }

            // Status
            $car->general()->status($this->re("#Status:\s+(.+)#", $ctext));
        }

        //#################
        //##    RAIL    ###
        //#################
        foreach ($rails as $rl => $segments) {
            $train = $email->add()->train();

            // RecordLocator
            if ($rl === 'undefined') {
                $train->general()
                    ->noConfirmation();
            } else {
                $train->general()
                    ->confirmation($rl);
            }
            $train->general()
                ->travellers($pax);

            $passengers = [];

            foreach ($segments as $stext) {
                $seg = $train->addSegment();

                $date = strtotime($this->normalizeDate($this->re("#[­-]\s+(.*?)(?:\s*[­-]\s+|\n|\s{2,})#", $stext)));

                $seg->extra()
                    ->noNumber();

                // DepName
                $seg->departure()
                    ->name($this->re("#" . $this->t("Depart:") . "\s+(.*?)(?:[ ]{2}|\n)#", $stext));

                // DepDate
                $seg->departure()
                    ->date(strtotime($this->normalizeDate($this->re("#" . $this->t("Depart:") . ".*?(\d+:\d+[^\n]+)#ms", $stext)), $date));

                // ArrName
                $seg->arrival()
                    ->name($this->re("#" . $this->t("Arrive:") . "\s+(.*?)(?:[ ]{2}|\n)#", $stext));

                // ArrDate
                $seg->arrival()->date(strtotime($this->normalizeDate($this->re("#" . $this->t("Arrive:") . ".*?(\d+:\d+[^\n]+)#ms", $stext)), $date));

                // Duration
                $seg->extra()->duration($this->re("#" . $this->t("Duration:") . "\s+(.*?\d.*?)?(?:\s+" . $this->t("Nonstop") . "|\s+" . $this->t("with") . "\s+|\n)#", $stext));
            }
        }

        return true;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@bcdtravel.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $check = false;

            foreach (self::$reBody as $re) {
                if (strpos($textPdf, $re) !== false || strpos($this->http->Response['body'], $re) !== false) {
                    $check = true;

                    break;
                }
            }

            if (!$check) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = false;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($textPdf)) {
                $this->text = $textPdf;

                break;
            }
        }

        if (!$this->text) {
            return false;
        }

        $this->parsePdf($email);

        $total = $this->re("#(?:" . $this->t("Estimated trip total") . ")\s+(.+)#", $this->text);

        if (empty($total)) {
            $total = $this->re("#(?:" . $this->preg_implode($this->t("Total Amount:")) . ")\s+(.+)#", $this->text);
        }

        if (!empty($total)) {
            $email->price()
                ->total($this->amount($total))
                ->currency($this->currency($total), true, true);
        } else {
            $totalPosBegin = strpos($this->text, $this->t('Total Amount Due'));

            if (!empty($totalPosBegin)) {
                if (preg_match("#\n\s*" . $this->t('Total Amount Due') . "[ ]+.*?[ ]{2,}([^\d\s]{1,5} ?\d[\d\.\,]+)\n#", substr($this->text, $totalPosBegin - 20, 500), $m)) {
                    if (preg_match("#^(?<curr>[^\d\s]{1,5}) ?(?<amount>\d[\d\.\, ]+)#", $m[1], $mat)) {
                        $currency = $this->re("#Balance Due:[ ]*([A-Z]{3})\b#", substr($this->text, $totalPosBegin - 20));

                        if (empty($currency)) {
                            $currency = $this->currency($mat['curr']);
                        }
                        $email->price()
                            ->total($this->amount($mat['amount']))
                            ->currency($currency);
                    }
                }
            }
        }

        foreach (self::$reBody as $prov => $re) {
            if (strpos($this->text, $re) !== false) {
                if (!is_int($prov)) {
                    $email->setProviderCode(trim($prov, '+'));
                }
            }
        }

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

    public static function getEmailProviders()
    {
        return array_values(array_unique(str_replace('+', '', array_filter(array_keys(self::$reBody), "is_string"))));
    }

    protected function assignLang($text)
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function cutText(string $start, $end, string $text)
    {
        if (empty($end) || empty($text)) {
            return false;
        }

        if (empty($start)) {
            $begin = $text;
        } else {
            $begin = stristr($text, $start);
        }

        if (is_array($end)) {
            foreach ($end as $e) {
                $r = stristr($begin, $e, true);

                if ($r !== false) {
                    return $r;
                }
            }

            return false;
        }

        return stristr($begin, $end, true);
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
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+:\d+ [AP]M) [^\s\d]+, ([^\s\d]+) (\d+) (\d{4})$#", //09:54 AM Thursday, June 27 2013
            "#^[^\s\d]+, ([^\s\d]+) (\d+) (\d{4})$#", //Thursday, June 27 2013
            "#^(\d+:\d+) [^\s\d]+, (\d+ [^\s\d]+ \d{4})$#", //13:05 Monday, 21 July 2014
            "#^[^\s\d]+, (\d+) de ([^\s\d]+) de (\d{4})$#", //Segunda-Feira, 9 de Outubro de 2017
            "#^(\d+:\d+) [^\s\d]+, (\d+) de ([^\s\d]+) de (\d{4})$#", //07:10 Segunda-Feira, 9 de Outubro de 2017
            "#^([\d\:]+\s*A?P?M)\s*\w+\,\s*(\w+)\s*(\d+)\s*(\d{4})\s*Tel\:\s*([\d\-]+)$#", //12:45 PM Wednesday, February 23 2022Tel: 410-684-7900
        ];
        $out = [
            "$3 $2 $4, $1",
            "$2 $1 $3",
            "$2, $1",
            "$1 $2 $3",
            "$2 $3 $4, $1",
            "$3 $2 $4, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . str_replace('#', '\#', implode("|", $field)) . ')';
    }

    private function amount($s)
    {
        $s = str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#(\d[\d\,\.]+)#", $s)));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map('preg_quote', $field)) . ')';
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, $cancellationText)
    {
        if (preg_match("#cancel (\d+) hours prior to arrival date#i", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ' hours');
        }

        if (preg_match("#[\dA-Z]+ CANCEL (\d+) DAYS PRIOR TO ARRIVAL#i", $cancellationText, $m)
        || preg_match("/Cancel by [A-Z\d]+\s*CANCEL\s+(\d+)\s+DAY\s+PRIOR\s*TO\s*ARRIVA/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days');
        }
    }
}
