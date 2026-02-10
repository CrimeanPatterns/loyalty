<?php

namespace AwardWallet\Engine\flydubai\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Email\Email;

class OnlineCheckinConfirmation extends \TAccountChecker
{
    public $mailFiles = "flydubai/it-10052570.eml, flydubai/it-101305822.eml, flydubai/it-10190840.eml, flydubai/it-11483584.eml, flydubai/it-261726920.eml, flydubai/it-28985881.eml, flydubai/it-29283406.eml, flydubai/it-38315732.eml, flydubai/it-38323747.eml, flydubai/it-6060285.eml, flydubai/it-6064379.eml, flydubai/it-7296902.eml, flydubai/it-7402204.eml, flydubai/it-7462111.eml, flydubai/it-7563426.eml, flydubai/it-7563440.eml, flydubai/it-8559935.eml, flydubai/it-8642184.eml";

    public $reSubject = [
        'en' => 'Online check-in confirmation for',
    ];
    public static $dictionary = [
        'en' => [],
    ];
    private $lang = '';
    private $reBodyPdf = [
        'en' => ['OPEN MEMBERSHIP NO', 'DEPARTING FROM'],
    ];
    private $reBodyHtml = [
        'en' => ['flydubai check-in confirmation', 'Departure'],
    ];
    private $pdfPattern = '[A-Z\d\s]+.pdf';

    public function parsePdf(Email $email, $arrTextPdf)
    {
        $patterns = [
            // FZ 060/EK 2049
            'flight/carrier' => '^(?<airline>[A-Z\d]{2}) (?<flightNumber>\d+)\s*\/\s*(?<airlineCarrier>[A-Z\d]{2}) (?<flightNumberCarrier>\d+)$',
            // FZ 060
            'flight' => '^(?<airline>[A-Z\d]{2}) (?<flightNumber>\d+)$',
            // Sheikh Saad - Terminal R
            'airport' => '/^(.+) - (.+)$/',
            // 21 Sep 2017
            'date' => '\d{1,2}[ ]*[^\d ]{3,}[ ]*\d{2,4}',
            // 19:55
            'time' => '\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?',
        ];

        $airs = [];

        foreach ($arrTextPdf as $text) {
            // parse main table
            $mainTable = preg_replace("/^\s*\n/", "",
                substr($text, $s = strpos($text, "PASSENGER"), strpos($text, "Notification") - $s));

            if (empty($mainTable)) {
                $this->logger->debug("incorrect mainTable parse");

                return false;
            }
            $rows = explode("\n", $mainTable);
            $pos = array_unique(array_merge($this->TableHeadPos($rows[0]), $this->TableHeadPos($rows[1])));
            sort($pos);
            $pos = array_merge([], $pos);
            $mainTable = $this->splitCols($mainTable, $pos);

            if (count($mainTable) < 4) {
                $this->logger->debug("incorrect mainTable parse");

                return false;
            }
            // parse flight table
            $flTable = $this->splitCols(substr($mainTable[0], strpos($mainTable[0], "FLIGHT NUMBER")));

            if (count($flTable) < 2) {
                $this->logger->debug("incorrect flTable parse");

                return false;
            }
            //get rl
            if (!$rl = $this->re("/BOOKING REFERENCE\n+(\w+)/", $mainTable[3])) {
                $this->logger->debug("RL not matched");

                return false;
            }
            $airs[$rl][] = ['mainTable' => $mainTable, 'flTable' => $flTable/*, 'text'=>$text*/];
        }

        foreach ($airs as $rl => $list) {
            $r = $email->add()->flight();

            $r->general()
                ->confirmation($rl);

            $passengers = [];
            $infants = [];
            $accountNumbers = [];

            foreach ($list as $data) {
                $passengerName = $this->re('/PASSENGER(.*?)(?:DATE|FREQUENT FL[YI]ER NUMBER|OPEN MEMBERSHIP NO|EMIRATES SKYWARDS NO\.)/s',
                    $data['mainTable'][3]);

                $passengerName = preg_replace("/ (?:MRS|MS|MR|MSTR|MISS)$/", '',
                    trim(preg_replace('/\n+/', ' ', $passengerName)));

                if (preg_match("/SEAT\s+INF\s+/", $data['mainTable'][3])) {
                    $infants[] = $passengerName;
                } else {
                    $passengers[] = $passengerName;
                }
                $frequentNumber = $this->re('/(?:FREQUENT FL[YI]ER NUMBER|OPEN MEMBERSHIP NO\.?|EMIRATES SKYWARDS NO\.)\s+([A-Z]{2}-\d{6,})/',
                    $data['mainTable'][3]); // EK-446565140

                if ($frequentNumber) {
                    $accountNumbers[] = $frequentNumber;
                }
            }

            if (!empty($passengers[0])) {
                $r->general()
                    ->travellers(array_values(array_unique($passengers)), true);
            }

            if (!empty($infants)) {
                $r->general()
                    ->infants(array_values(array_unique($infants)), true);
            }

            if (!empty($accountNumbers[0])) {
                $r->program()
                    ->accounts(array_values(array_unique($accountNumbers)), false);
            }

            $uniq = [];

            foreach ($list as $data) {
                $mainTable = $data['mainTable'];
                $flTable = $data['flTable'];

                unset($flight, $airline);

                if (preg_match('/' . $patterns['flight/carrier'] . '\s+^BOARDING TIME/m', $flTable[0], $matches)) {
                    $airline = $matches['airline'];
                    $flight = $matches['flightNumber'];
                    $airlineCarrier = $matches['airlineCarrier'];
                    $flightCarrier = $matches['flightNumberCarrier'];
                } elseif (preg_match('/' . $patterns['flight'] . '\s+^BOARDING TIME/m', $flTable[0], $matches)) {
                    $airline = $matches['airline'];
                    $flight = $matches['flightNumber'];
                } elseif (preg_match('/^(?<flightNumber>\d+)$\s+^BOARDING TIME/m', $flTable[0], $matches)) {
                    $flight = $matches['flightNumber'];
                }

                if (!isset($flight)) {
                    return false;
                }

                $ticket = $this->re("/E-ticket number: *(\d+)/", $mainTable[3]);

                if (!empty($ticket)) {
                    $r->issued()
                        ->ticket($ticket, false);
                }
                // for tickets with another person and with same flight
                if (isset($uniq[($airline ?? '') . $flight])) {
                    $seat = $this->re("/SEAT\n+(\d+\w)(\s+|\n)/", $mainTable[3]);

                    if (empty($seat) && preg_match("/SEAT\n+INF(\s+|\n)/", $mainTable[3])) {
                    } else {
                        /** @var FlightSegment[] $segments */
                        $segments = $r->getSegments();

                        foreach ($segments as $segment) {
                            if ($segment->getFlightNumber() === $flight
                                && ((empty($airline) && empty($segment->getAirlineName()))
                                    || ((!empty($airline) && $airline === $segment->getAirlineName())))
                            ) {
                                $segment->extra()->seat($seat);
                            }
                        }
                    }

                    continue;
                }
                $uniq[($airline ?? '') . $flight] = 1;
                $s = $r->addSegment();

                $s->airline()
                    ->number($flight);

                if (isset($airline)) {
                    $s->airline()
                        ->name($airline);
                } else {
                    $s->airline()
                        ->noName();
                }

                if (isset($airlineCarrier, $flightCarrier)) {
                    $s->airline()
                        ->carrierName($airlineCarrier)
                        ->carrierNumber($flightCarrier);
                }
                $depCodeBody = $arrCodeBody = null;
                //search codes from body
                if (isset($flight, $airline)) {
                    $node = $this->http->FindSingleNode("//descendant::*[self::td or self::th][{$this->starts($this->t('Route / Flight no'))}]/following-sibling::*[1]");

                    if (preg_match("/([A-Z]{3})\s*\-([A-Z]{3})\s*\/\s*\(\s*{$airline}\s*{$flight}\s*(?:\/\s*[A-Z\d]{2}\s*\d+\s*)?\)/", $node, $m)) {
                        $depCodeBody = $m[1];
                        $arrCodeBody = $m[2];
                    }
                }

                $s->departure()
                    ->name($this->re("/DEPARTING FROM\n+(.+)/", $mainTable[0]));
                $depCode = $this->re("/DEPARTING FROM\n+.+\n+(?:.*?\s+)?\(([A-Z]{3})\)/", $mainTable[0]);

                if (!empty($depCode)) {
                    $s->departure()
                        ->code($depCode);
                } elseif (isset($depCodeBody)) {
                    $s->departure()
                        ->code($depCodeBody);
                }

                $airportDep = $this->re("/DEPARTING FROM\n+.+\n+(?:(.*?)\s+)?\([A-Z]{3}\)/", $mainTable[0]);

                if (preg_match($patterns['airport'], $airportDep, $matches)) {
                    $s->departure()
                        ->name($matches[1])
                        ->terminal(trim(str_ireplace('Terminal', '', $matches[2])));
                } elseif ($airportDep) {
                    $s->departure()
                        ->terminal(trim(str_ireplace('Terminal', '', $airportDep)));
                } else {
                    $airportDep = $this->re("/DEPARTING FROM\n+.+\n+(.+)\s+ARRIVING IN/", $mainTable[0]);

                    if (!empty($airportDep)) {
                        $s->departure()
                            ->terminal(trim(str_ireplace('Terminal', '', $airportDep)));
                    }
                }

                $dateDep = $this->re('/DATE\n+(' . $patterns['date'] . ')/', $flTable[1]);

                if (!$dateDep) {
                    $dateDep = $this->re('/FLIGHT NUMBER\n+(' . $patterns['date'] . ')/', $flTable[0]);
                }
                $timeDep = $this->re('/DEPARTURE TIME\n+(' . $patterns['time'] . ')/', $flTable[1]);

                if ($dateDep && $timeDep) {
                    $s->departure()
                        ->date(strtotime($this->normalizeDate($dateDep . ', ' . $timeDep)));
                }

                $arrCode = $this->re("/ARRIVING IN\n+.+\n+(?:.*?\s+)?\(([A-Z]{3})\)/", $mainTable[0]);

                if (!$arrCode && $this->re("/ARRIVING IN\n+.+(?:\s+Terminal.+)?\n+(FLIGHT NUMBER)/", $mainTable[0])) {
                    if (isset($arrCodeBody)) {
                        $s->arrival()->code($arrCodeBody);
                    } else {
                        $s->arrival()
                            ->noCode();
                    }
                } else {
                    $s->arrival()
                        ->code($arrCode);
                }

                $s->arrival()
                    ->name($this->re("/ARRIVING IN\n+(.+)/", $mainTable[0]));

                $airportArr = $this->re("/ARRIVING IN\n+.+\n+(?:(.*?)\s+)?\([A-Z]{3}\)/", $mainTable[0]);

                if (preg_match($patterns['airport'], $airportArr, $matches)) {
                    $s->arrival()
                        ->name($matches[1])
                        ->terminal(trim(str_ireplace('Terminal', '', $matches[2])));
                } elseif ($airportArr) {
                    $s->arrival()
                        ->terminal(trim(str_ireplace('Terminal', '', $airportArr)));
                } else {
                    $airportArr = $this->re("/ARRIVING IN\n+.+\n+(.+)\s+FLIGHT NUMBER/", $mainTable[0]);

                    if (!empty($airportArr)) {
                        $s->arrival()
                            ->terminal(trim(str_ireplace('Terminal', '', $airportArr)));
                    }
                }

                if (!empty($s->getDepDate())) {
                    $s->arrival()->noDate();
                }

                $s->extra()
                    ->cabin($this->re("/CABIN CLASS\n+(.+)/", $mainTable[0]))
                ;
                $seat = $this->re("/SEAT\n+(\d+\w)(\s+|\n)/", $mainTable[3]);

                if (empty($seat) && preg_match("/SEAT\n+INF(\s+|\n)/", $mainTable[3])) {
                } else {
                    $s->extra()
                    ->seat($seat);
                }

                // depCode form Body
                if (empty($s->getDepCode()) && !empty($arrCode = $s->getArrCode()) && !empty($airline = $s->getAirlineName()) && !empty($flight)) {
                    $depCode = $this->http->FindSingleNode("//text()[contains(normalize-space(), '" . $airline . ' ' . $flight . "') and contains(normalize-space(), '" . $arrCode . "')]",
                        null, true,
                        "/([A-Z]{3})\s*-\s*" . $arrCode . "\s*\/\s*\(\s*" . $airline . '\s*' . $flight . "\s*\)/");

                    if (!empty($depCode)) {
                        $s->departure()->code($depCode);
                    }
                }
            }
        }

        return true;
    }

    public function parseHtml_1(Email $email)
    {
        $r = $email->add()->flight();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking ref'))}]//ancestor::tr[1]/following-sibling::tr[1]/descendant::text()[normalize-space()!=''][1]"));
        $cabin = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking ref'))}]//ancestor::tr[1]/following-sibling::tr[1]/descendant::text()[normalize-space()!=''][2]");
        $xpath = "//text()[{$this->contains($this->t('Departure'))}]/ancestor::table[1][count(./descendant::text()[{$this->contains($this->t('Seat'))}])=1]";
        $nodes = $this->http->XPath->query($xpath);
        $pax = $infants = [];

        foreach ($nodes as $root) {
            $pax = array_merge(
                $pax,
                $this->http->FindNodes(
                    "./descendant::text()[{$this->contains($this->t('Seat'))}]/ancestor::tr[1]/following-sibling::tr/td[1][not({$this->contains($this->t('Infant'))})]",
                    $root,
                    "/^(.+?)\s*(?:\(|$)/"
                ));

            $infants = array_merge(
                $infants,
                $this->http->FindNodes(
                    "./descendant::text()[{$this->contains($this->t('Seat'))}]/ancestor::tr[1]/following-sibling::tr/td[1][{$this->contains($this->t('Infant'))}]",
                    $root, "/^(.+?)\s*(?:\(|$)/"
                ));

            $seats = array_filter($this->http->FindNodes(
                "./descendant::text()[{$this->contains($this->t('Seat'))}]/ancestor::tr[1]/following-sibling::tr/td[2]",
                $root, "/^\d+[A-Z]$/i"));

            $s = $r->addSegment();
            $s->extra()
                ->seats($seats)
                ->cabin($cabin);
            $node = $this->http->FindSingleNode("./descendant::tr[1]", $root);

            if (preg_match("/([A-Z]{3})\s*\-\s*([A-Z]{3})\s*\((.+)\)\s*Departure\s*\-\s*(?<time>\d+:\d+)\s*(?<depDate>.+)/",
                $node, $m)) {
                $s->departure()
                    ->code($m[1]);
                $s->arrival()
                    ->code($m[2])
                    ->noDate();

                if (preg_match("/^(\d+)$/", trim($m[3]), $v)) {
                    $s->airline()
                        ->noName()
                        ->number($v[1]);
                } elseif (preg_match("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/", trim($m[3]), $v)) {
                    $s->airline()
                        ->name($v[1])
                        ->number($v[2]);
                } elseif (preg_match("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)\s*\/\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/",
                    trim($m[3]), $v)) {
                    $s->airline()
                        ->name($v[1])
                        ->number($v[2])
                        ->carrierName($v[3])
                        ->carrierNumber($v[4]);
                }
                $s->departure()
                    ->date(strtotime($this->normalizeDate($m['depDate'] . ', ' . $m['time'])));
            }
        }

        if (count($pax) > 0) {
            $pax = preg_replace("/^\s*(?:MRS|MS|MR|MSTR|MISS)\s+/", '', $pax);
            $r->general()->travellers($pax, true);
        }

        if (count($infants) > 0) {
            $infants = preg_replace("/^\s*(?:MRS|MS|MR|MSTR|MISS)\s+/", '', $infants);
            $r->general()->infants($infants, true);
        }

        return true;
    }

    public function parseHtml_2(Email $email)
    {
        $r = $email->add()->flight();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking ref'))}]//ancestor::tr[1]/following-sibling::tr[1]/descendant::text()[normalize-space()!=''][1]"));
        $cabin = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking ref'))}]//ancestor::tr[1]/following-sibling::tr[1]/descendant::text()[normalize-space()!=''][2]");
        $xpath = "//text()[{$this->contains($this->t('Passenger name'))}]/ancestor::table[1][count(./descendant::text()[{$this->contains($this->t('Passenger name'))}])=1]";
        $nodes = $this->http->XPath->query($xpath);
        $pax = [];

        foreach ($nodes as $root) {
            $pax = array_merge(
                $pax,
                $this->http->FindNodes(
                    "./descendant::text()[{$this->contains($this->t('Passenger name'))}]/ancestor::tr[1]/following-sibling::tr/td[1]",
                    $root,
                    "/^(.+?)\s*(?:\(|$)/"));

            $s = $r->addSegment();
            $s->extra()
                ->cabin($cabin);

            $node = $this->http->FindSingleNode("./descendant::tr[1]", $root);

            if (preg_match("/Passenger name\s*([A-Z]{3})\s*\-\s*([A-Z]{3})\s*\((.+)\)/",
                $node, $m)) {
                $s->departure()
                    ->code($m[1])
                    ->noDate();
                $s->arrival()
                    ->code($m[2])
                    ->noDate();

                if (preg_match("/^(\d+)$/", trim($m[3]), $v)) {
                    $s->airline()
                        ->noName()
                        ->number($v[1]);
                } elseif (preg_match("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/", trim($m[3]), $v)) {
                    $s->airline()
                        ->name($v[1])
                        ->number($v[2]);
                } elseif (preg_match("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)\s*\/\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/",
                    trim($m[3]), $v)) {
                    $s->airline()
                        ->name($v[1])
                        ->number($v[2])
                        ->carrierName($v[3])
                        ->carrierNumber($v[4]);
                }
            }
        }

        if (count($pax) > 0) {
            $r->general()->travellers($pax);
        }

        return true;
    }

    public function parseHtml_3(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('Booking ref.'))}]/ancestor::table[{$this->contains($this->t('Seat no'))}][1][count(./descendant::text()[{$this->eq($this->t('Booking ref.'))}])=1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Booking ref.'))}]/following::text()[normalize-space()!=''][1]",
                $root);
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $roots) {
            $r = $email->add()->flight();
            $r->general()
                ->confirmation($rl);
            $pax = $this->http->FindNodes("./descendant::tr[{$this->starts($this->t('Passenger(s)'))}]/following-sibling::tr[count(./descendant::td)=3]/td[1]",
                $roots[0], "/^(.+?)\s*(?:\(|$)/");

            if (empty($pax)) {
                $pax = $this->http->FindNodes("./descendant::tr[{$this->starts($this->t('Passenger(s)'))}]/following-sibling::tr[count(td[normalize-space()])=2][td[2][count(.//tr) = 2]/descendant::tr[1]/td[1][@rowspan = 2]]/td[1]",
                    $roots[0], "/^\s*([[:alpha:]][[:alpha:] \-]+[[:alpha:]])\s*(?:\(|$)/");
            }

            if (count($pax) > 0) {
                $r->general()->travellers($pax);
            }

            foreach ($roots as $root) {
                $seats = $this->http->FindNodes("./descendant::tr[{$this->starts($this->t('Passenger(s)'))}]/following-sibling::tr[count(./descendant::td)=3]/td[2]",
                    $roots[0], "/^\d+[A-Z]$/i");

                if (empty($seats)) {
                    $seats = $this->http->FindNodes("./descendant::tr[{$this->starts($this->t('Passenger(s)'))}]/following-sibling::tr[count(td[normalize-space()])=2]/td[2][count(.//tr) = 2]/descendant::tr[1]/td[1][@rowspan = 2]",
                        $roots[0], "/^\d+[A-Z]$/i");
                }

                $s = $r->addSegment();
                $s->extra()
                    ->seats($seats)
                    ->cabin($this->http->FindSingleNode("./descendant::*[self::td or self::th][{$this->starts($this->t('Class of travel'))}]/following-sibling::*[1]",
                        $root));
                $node = $this->http->FindSingleNode("./descendant::*[self::td or self::th][{$this->starts($this->t('Route / Flight no'))}]/following-sibling::*[1]",
                    $root);

                if (preg_match("/([A-Z]{3})\s*\-([A-Z]{3})\s*\/\s*\((.+)\)/", $node, $m)) {
                    $s->departure()
                        ->code($m[1]);
                    $s->arrival()
                        ->code($m[2])
                        ->noDate();

                    if (preg_match("/^(\d+)$/", trim($m[3]), $v)) {
                        $s->airline()
                            ->noName()
                            ->number($v[1]);
                    } elseif (preg_match("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/", trim($m[3]), $v)) {
                        $s->airline()
                            ->name($v[1])
                            ->number($v[2]);
                    } elseif (preg_match("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)\s*\/\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/",
                        trim($m[3]), $v)) {
                        $s->airline()
                            ->name($v[1])
                            ->number($v[2])
                            ->carrierName($v[3])
                            ->carrierNumber($v[4]);
                    }
                }
                $node = $this->http->FindSingleNode("./descendant::*[self::td or self::th][{$this->eq($this->t('Departure'))}]/following-sibling::*[1]",
                    $root);

                if (preg_match("/^(?<time>\d+:\d+(?:\s*[ap]m)?)\s*(?<depDate>.+)$/i", $node, $m)) {
                    $s->departure()
                        ->date(strtotime($this->normalizeDate($m['depDate'] . ', ' . $m['time'])));
                }
                $node = $this->http->FindSingleNode("./descendant::*[self::td or self::th][{$this->eq($this->t('Departure from'))}]/following-sibling::*[1]",
                    $root);

                if (preg_match("/^(.+) - .+$/i", $node, $m) && preg_match("/terminal/i", $m[1])) {
                    $s->departure()
                        ->terminal(trim(preg_replace("/\s*terminal\s*/i", ' ', $m[1])));
                }
            }
        }

        return true;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flydubai.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // html
        $textHtml = $parser->getHTMLBody();

        if ($this->http->XPath->query("//a[contains(@href,'flydubai.')] | //img[contains(@src,'flydubai.')]")->length > 0
            && $this->assignLang($textHtml, false)
        ) {
            return true;
        }

        // pdf
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            if (strpos($textPdf, '<-- Fold here -->') !== false && $this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $type = 'Pdf';
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) > 0) {
            $arrTextPdf = [];

            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null
                    && $this->assignLang($text)
                ) {
                    $arrTextPdf[] = $text;
                }
            }

            if (!empty($arrTextPdf)) {
                $this->parsePdf($email, $arrTextPdf);
            }
        }

        if (count($email->getItineraries()) === 0 && $this->assignLang($parser->getHTMLBody(), false)) {
            if ($this->http->XPath->query("//text()[{$this->starts($this->t('Booking ref'))}]/following::text()[normalize-space()!=''][1][{$this->contains($this->t('Class of travel'))}]")->length > 0) {
                if ($this->http->XPath->query("//text()[{$this->contains($this->t('Departure'))}]/ancestor::table[1][count(./descendant::text()[{$this->contains($this->t('Seat'))}])=1]")->length > 0) {
                    $this->parseHtml_1($email);
                    $type = 'Html1';
                } else {
                    $this->parseHtml_2($email);
                    $type = 'Html2';
                }
            } else {
                $this->parseHtml_3($email);
                $type = 'Html3';
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($type) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $formats = 4; //pdf + 3html;
        $cnt = $formats * count(self::$dictionary);

        return $cnt;
    }

    private function assignLang($text, $byPdf = true)
    {
        if ($byPdf) {
            $reBody = $this->reBodyPdf;
        } else {
            $reBody = $this->reBodyHtml;
        }

        foreach ($reBody as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
        $in = [
            "/^(\d+\s+[^\d\s]+\s+\d{4},\s+\d+:\d+)$/", //05 Oct 2016, 17:25
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/\d+\s+([^\d\s]+)\s+\d{4}/", $str, $m)) {
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

    private function TableHeadPos($row)
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
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
}
