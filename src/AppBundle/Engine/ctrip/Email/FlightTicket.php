<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\ItineraryArrays\AirTripSegment;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge methods `FlightTicket::parsePdf2` and `ItineraryFlight::parsePdf` (in favor of `ItineraryFlight::parsePdf`)

class FlightTicket extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-10211507-pdf.eml, ctrip/it-11117361-pdf.eml, ctrip/it-11161407-pdf.eml, ctrip/it-11200587-pdf.eml, ctrip/it-11215774-pdf.eml, ctrip/it-11231478.eml, ctrip/it-11339848.eml, ctrip/it-12097144.eml, ctrip/it-13522222.eml, ctrip/it-14264424-pdf2.eml, ctrip/it-44966296-pdf2.eml, ctrip/it-69601914.eml, ctrip/it-71708672.eml, ctrip/it-7230992.eml, ctrip/it-7804065.eml, ctrip/it-7889055.eml, ctrip/it-7973210.eml, ctrip/it-7978892.eml";

    private $detectFrom = ['@trip.com', '@ctrip.com'];
    private $detectSubject = [
        // en
        'Flight reservation confirmation',
        'Flight Booking Confirmation',
        'Flight Payment Successful',
        'E-receipt and Itinerary',
        'Flight Booking Payment Successful',
        'Flight Change Payment Successful',
        'Flight Change Successful',
        'Flight Booking Canceled',
        // th
        'ชำระเงินการจองเที่ยวบินเรียบร้อยแล้ว',
    ];

    private $detectsHtml = [
        'en'  => 'Thank you for choosing Ctrip. Payment for your flight has been received',
        'en2' => 'Please find attached your e-receipt and itinerary',
        'en3' => 'Please find your e-receipt and itinerary attached',
        'en4' => 'Thank you for choosing Trip.com',
        'en5' => 'Flight Booking Confirmation',
        'en6' => 'Your ticket(s) have been issued, and your booking information is listed below',
        'th'  => 'ขอบคุณที่เลือกใช้ Trip.com',
    ];

    private $detectsPdf = [
        'en'  => 'Thank you for choosing Ctrip. Payment for your flight has been received',
        'en2' => 'Please find attached your e-receipt and itinerary',
        'en3' => 'Trip.com Account',
        'en4' => 'Ctrip Account',
        'en5' => 'We advise you print out your itinerary and take it',
    ];

    private $lang = 'en';

    private $langDetectHtml = [
        'en' => ['Manage My Booking', 'Booking Details'],
        'th' => ['รายละเอียดการจอง', 'จัดการกับการจองของฉัน'],
    ];

    private $pdfPattern = '.+\.pdf';
    private $otaConf = [];
    private $format = '';

    private static $dictionary = [
        'en' => [
            // Html
            //			"PNR" => "",
            "Booking No" => ["Booking No.:", "Booking No", "Booking No."],
            //			"Passenger" => "",
            //			"Ticket" => "",
            "Total amount" => ['Total amount', 'Total Payment', 'Total :', 'Total'],
            "Departure"    => ["Departure", "Return", "Flight 1", "Flight 2", "Flight 3", "Flight Details", 'Flight Info', 'New itinerary'],
            "Depart"       => ["Depart", "Departing", "Departure"],
            "Arrival"      => ["Arrival", "Arriving"],
        ],
        'th' => [
            // Html
            //			"PNR" => "",
            "Booking No" => "หมายเลขการจอง",
            "Passenger"  => "ผู้โดยสาร",
            //			"Ticket" => "",
            "Total amount" => ['ทั้งหมด :'],
            "Departure"    => ["เที่ยวบินขาออก"],
            "Depart"       => ["ออกเดินทาง"],
            "Arrival"      => ["เดินทางถึง"],
        ],
    ];

    private $patterns = [
        'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) > 0) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

            if (strpos($textPdf, 'Airline Booking Reference') !== false) {
                $this->parsePdf2($email, $textPdf);
            } else {
                $this->parsePdf($email, $textPdf);
            }
        }

        if ($this->format == 'html' || count($pdfs) == 0) {
            $body = $this->http->Response['body'];

            foreach ($this->langDetectHtml as $lang => $decects) {
                foreach ($decects as $decect) {
                    if (stripos($body, $decect) !== false) {
                        $this->lang = $lang;

                        break;
                    }
                }
            }
            $this->parseHtml($email);
        }

        $this->otaConf = array_unique($this->otaConf);

        foreach ($this->otaConf as $conf) {
            $email->ota()
                ->confirmation($conf);
        }

        $email->setType('FlightTicket' . ucfirst($this->format) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (stripos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        //html
        $anchor = false;

        if ($this->http->XPath->query('//a[' . $this->contains(["//www.trip.com", "//Trip.com", ".trip.com/", ".ctrip.com", "/ctrip.com"], '@href') . ']')->length > 0
            || $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for choosing Trip.com")]')->length > 0
        ) {
            $anchor = true;
        }

        if ($anchor) {
            foreach ($this->detectsHtml as $detect) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $detect . '")]')->length > 0) {
                    return true;
                }
            }
        }

        //pdf
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (count($pdfs) === 0) {
            return false;
        }
        $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));
        $anchor = false;

        foreach (['Ctrip', 'Trip.com', 'Trip. com'] as $str) {
            if (stripos($textPdf, $str) !== false) {
                $anchor = true;

                break;
            }
        }

        if ($anchor) {
            foreach ($this->detectsPdf as $detect) {
                if (stripos($textPdf, $detect) !== false) {
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

    private function parseHtml(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $otaConf = $this->getNode($this->t('Booking No'));

        if (empty($otaConf)) {
            $otaConf = $this->getNode($this->t('Booking No'), '/\:\s*(\w+)/', false);
        }

        if (empty($otaConf)) {
            $otaConf = $this->http->FindSingleNode("//td[" . $this->starts($this->t('Booking No')) . "]", null, true, "#:\s*([A-Z\d]{5,})\s*$#");
        }

        if (!empty($otaConf)) {
            $this->otaConf[] = $otaConf;
        }

        if (empty($otaConf)) {
            $confArray = array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t('Booking No')) . "][1]/following::text()[normalize-space()][1]"));

            if (count($confArray) > 0) {
                foreach ($confArray as $conf) {
                    $this->otaConf[] = $conf;
                }
            }
        }

        $f = $email->add()->flight();
        $confirmation = $this->http->FindSingleNode("//td[(" . $this->eq($this->t("PNR")) . ") and not(descendant::td)]/following-sibling::td[normalize-space()][1]");

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//td[(" . $this->eq($this->t("PNR")) . ") and not(descendant::td)]");
        }

        if (!empty($confirmation)) {
            $f->general()
                ->confirmation($confirmation);
        } elseif (empty($this->http->FindSingleNode("(//*[(" . $this->contains($this->t("PNR")) . ")])[1]"))) {
            $f->general()
                ->noConfirmation();
        }

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Your booking has been canceled'))}]")->count() > 0) {
            $f->general()
                ->cancelled()
                ->status('canceled');
        }

        $xpathRow = '(self::tr or self::p)';

        // Passengers
        $xpath = ".//td[(" . $this->contains($this->t("Passenger")) . ") and (" . $this->contains($this->t("Ticket")) . ") and not(.//td)]/following-sibling::td[1]/descendant::tr";
        $travellers = $this->http->FindNodes($xpath . '/td[1]');

        if (count($travellers) == 0) {
            $travellers = $this->http->FindNodes("//tr[ count(*)=2 and *[1][{$this->starts($this->t("Passenger"))}] ]/*[2]/descendant-or-self::*[*[{$xpathRow}]][1]/*[{$xpathRow}]");
        }
        $travellers = preg_replace(['/\s*\(\s*Adult ticket\s*\)/i', "/(.+?)\s*\/\s*(.+)/"], ['', '$2 $1'], $travellers);
        $f->general()
            ->travellers($travellers);

        // TicketNumbers
        $ticketNumbers = array_filter($this->http->FindNodes($xpath . '/td[2]', null, "/^{$this->patterns['eTicket']}$/"));

        if (count($ticketNumbers) > 0) {
            $f->issued()->tickets(array_unique($ticketNumbers), false);
        }

        $total = $this->http->FindSingleNode("(//td[(" . $this->eq($this->t('Total amount')) . ") and not(descendant::td)]/following-sibling::td[normalize-space()][1])[1]");

        if ($total === null) {
            $total = $this->http->FindSingleNode("//td[(" . $this->contains($this->t('Total amount')) . ") and not(descendant::td)]");
        }

        if ($total !== null && empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t('Flight Change Successful')) . "])[1]"))) {
            $f->price()
                ->total($this->amount($total))
                ->currency($this->currency($total));

            $baseFare = $this->http->FindSingleNode("(//td[not(.//td) and {$this->eq($this->t('Fare'))}]/following-sibling::td[normalize-space()][1])[1]");

            if (preg_match('/^(?:' . preg_quote($f->getPrice()->getCurrencyCode(), '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)(?:[ ]*[×*][ ]*1)?$/u', $baseFare, $matches)) {
                $f->price()
                    ->cost($this->amount($matches[1]));
            }

            $tax = $this->http->FindSingleNode("(//td[not(.//td) and {$this->eq($this->t('Taxes & Fees'))}]/following-sibling::td[normalize-space()][1])[1]");

            if (preg_match('/^(?:' . preg_quote($f->getPrice()->getCurrencyCode(), '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)(?:[ ]*[×*][ ]*1)?$/u', $tax, $matches)) {
                $f->price()
                    ->tax($this->amount($matches[1]));
            }
        }
        $xpath = "//*[tr[1][" . $this->eq($this->t("Departure")) . "]]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $xpath = "//td[" . $this->contains($this->t("Departure")) . "]/following-sibling::td";
            $roots = $this->http->XPath->query($xpath);
        }
        $this->logger->debug($xpath);

        if ($roots->length === 0) {
            $this->logger->debug('Segments not found by xpath: ' . $xpath);
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $s = $f->addSegment();

            if (!empty($this->http->FindSingleNode("descendant::*[{$xpathRow}][{$this->starts($this->t('Depart'))} and not({$this->eq($this->t('Depart'))})]", $root))) {
                $names = preg_split('/\s*-\s*/', $this->http->FindSingleNode("descendant::*[{$xpathRow}][not({$this->eq($this->t('Depart'))})][1]", $root));

                if (count($names) === 2) {
                    $s->departure()
                        ->name($names[0]);
                    $s->arrival()
                        ->name($names[1]);
                }

                if (count($names) === 3) {
                    $s->departure()
                        ->name($names[0]);
                    $s->arrival()
                        ->name($names[2]);
                }

                $date = $this->http->FindSingleNode("descendant::*[{$xpathRow}][{$this->starts($this->t('Depart'))} and not({$this->eq($this->t('Depart'))})]", $root, true, "/{$this->opt($this->t('Depart'))}\s*:\s*(.+)/");

                if (preg_match("#^\s*(.+\d{1,2}:\d{1,2}(?:\s*[ap]m)?),\s*(?<airport>.+?)(?<term> T?\w)?\s*$#iu", $date, $m)) {
                    $date = $m[1];

                    if (!empty($m['term'])) {
                        $s->departure()
                            ->terminal(trim($m['term']));
                    }
                    /*$s->departure()
                        ->name(((!empty($seg['DepName'])) ? $seg['DepName'] . ', ' : '') . $m['airport']);*/
                }
                $s->departure()
                    ->date(strtotime($date));

                $date = $this->http->FindSingleNode("descendant::*[{$xpathRow}][{$this->starts($this->t('Arrival'))}]", $root, true, "/{$this->opt($this->t('Arrival'))}\s*:\s*(.+)/");

                if (preg_match("#^\s*(.+\d{1,2}:\d{1,2}(?:\s*[ap]m)?),\s*(?<airport>.+?)(?<term> T?\w)?\s*$#iu", $date, $m)) {
                    $date = $m[1];

                    if (!empty($m['term'])) {
                        $s->arrival()
                            ->terminal(trim($m['term']));
                    }
                    /*$s->arrival()
                        ->name(((!empty($seg['ArrName'])) ? $seg['ArrName'] . ', ' : '') . $m['airport']);*/
                }
                $s->arrival()
                    ->date(strtotime($date));

                $airline = $this->http->FindSingleNode("descendant::*[{$xpathRow}][{$this->starts($this->t('Depart'))} and not({$this->eq($this->t('Depart'))})]/preceding-sibling::*[normalize-space()][1]", $root);

                if (preg_match("#^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\s*\((.+)\s+class\)\s*$#iu", $airline, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                    $s->extra()
                        ->cabin($m[3]);
                }

                if (!empty($s->getDepDate()) && !empty($s->getArrDate()) && !empty($s->getDepName()) && !empty($s->getArrName())) {
                    $s->airline()
                        ->noName()
                        ->noNumber();

                    $s->departure()
                        ->noCode();

                    $s->arrival()
                        ->noCode();
                }
            } else {
                $info = $this->http->FindSingleNode('descendant::tr[1]', $root);

                if (preg_match("#(.*\d{4})\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{1,5})#", $info, $m)) {
                    $date = trim($m[1]);
                    $s->airline()
                        ->name($m[2])
                        ->number($m[3]);
                }
                $info = $this->http->FindSingleNode("(descendant::tr[starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd ')])[1]", $root);

                if (preg_match("#(\d{2}:\d{2})\s+([A-Z]{3})\s+(.+?)(?:\s+T(\w+))?$#", $info, $m)) {
                    if (!empty($date)) {
                        $s->departure()
                            ->date(strtotime($date . ' ' . $m[1]));
                    }
                    $s->departure()
                        ->code($m[2])
                        ->name(trim($m[3]));

                    if (!empty($m[4])) {
                        $s->departure()
                            ->terminal(trim($m[4]));
                    }
                }

                $info = $this->http->FindSingleNode("(descendant::tr[starts-with(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd ')])[2]", $root);

                if (preg_match("#(\d{2}:\d{2})\s+([A-Z]{3})\s+(.+?)(?:\s+T(\w+))?$#", $info, $m)) {
                    if (!empty($date)) {
                        $s->arrival()
                            ->date(strtotime($date . ' ' . $m[1]));
                    }
                    $s->arrival()
                        ->code($m[2])
                        ->name(trim($m[3]));

                    if (!empty($m[4])) {
                        $s->arrival()
                            ->terminal(trim($m[4]));
                    }
                }
            }

            if (empty($s->getDepDate()) && empty($s->getArrDate())) {
                break;
            }
        }

        $xpath = "//tr[normalize-space(.)='Departure' or normalize-space(.)='Return']";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->debug('Segments not found by xpath-2: ' . $xpath);
        }

        foreach ($roots as $root) {
            /** @var AirTripSegment $seg */
            $s = $f->addSegment();

            if (preg_match('/(.+)[ ]*\-[ ]*(.+)/', $this->http->FindSingleNode('following-sibling::tr[1]', $root), $m)) {
                $s->departure()
                    ->name($m[1]);
                $s->arrival()
                    ->name($m[2]);
            }

            if (false !== $dDate = strtotime($this->http->FindSingleNode("following-sibling::tr[starts-with(normalize-space(.), 'Departing')]", $root, true, '/Departing[ ]*\:[ ]*(.+)/'))) {
                $s->departure()
                    ->date($dDate);
            }

            if (false !== $aDate = strtotime($this->http->FindSingleNode("following-sibling::tr[starts-with(normalize-space(.), 'Arriving')]", $root, true, '/Arriving[ ]*\:[ ]*(.+)/'))) {
                $s->arrival()
                    ->date($aDate);
            }

            if (!empty($s->getDepDate()) && !empty($s->getArrDate()) && !empty($s->getDepName()) && !empty($s->getArrName())) {
                $s->airline()
                    ->noNumber()
                    ->noName();

                $s->departure()->noCode();
                $s->arrival()->noCode();
            }
        }

        $this->format = 'html';
    }

    private function parsePdf(Email $email, $body): void
    {
        $this->logger->debug(__FUNCTION__);
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $f = $email->add()->flight();

        $pos = strpos($body, 'Airline PNR');

        if ($pos !== false && preg_match("#Airline PNR[ ]+([A-Z\d]+)\b#", substr($body, $pos), $m)) {
            $conf = $m[1];
        }

        if (empty($conf) && $pos !== false && preg_match("#GDS PNR[ ]+([A-Z\d]+)\b#", substr($body, $pos), $m)) {
            $conf = $m[1];
        }

        if (!empty($conf)) {
            $f->general()
                ->confirmation($conf);
        } elseif (preg_match("#Airline PNR\s*\n {0,10}\S#", substr($body, $pos), $m) && preg_match("#GDS PNR\s*\n {0,10}\S#", substr($body, $pos), $m)) {
            $f->general()
                ->noConfirmation();
        }

        $pos = strpos($body, 'Ctrip Account');

        if ($pos === false) {
            $pos = strpos($body, 'Trip.com Account');
        }

        if ($pos !== false && preg_match("#(?:Ctrip|Trip.com) Account:[ ]+([A-Z\d*]+)\s#", substr($body, $pos), $m)) {
            $f->program()
                ->account($m[1], false);
        }

        $pos = strpos($body, 'Booking No');

        if ($pos !== false && preg_match("#Booking No.\s+([\d]+)\b#", substr($body, $pos), $m)) {
            $this->otaConf[] = $m[1];
        }

        $pos = strpos($body, 'Booking Date');

        if ($pos !== false && preg_match("#Booking Date:\s+(.+)\n#", substr($body, $pos), $m)) {
            $f->general()
                ->date(strtotime($m[1]));
        }

        $pos = strpos($body, 'Payment Summary');

        if ($pos !== false) {
            if (preg_match("#\n\s*Total\s{3,}(.+)\s+#", substr($body, $pos), $m)) {
                $f->price()
                    ->total($this->amount($m[1]))
                    ->currency($this->currency($m[1]));
            }

            if (preg_match("#\n\s*Fare\s{3,}(.+)\s+#", substr($body, $pos), $m)) {
                $f->price()
                    ->cost($this->amount($m[1]));
            }
        }
        $pos = strpos($body, 'Passenger Name');
        $posItin = strpos($body, 'Itinerary', $pos);
        $ticketArray = [];

        if ($pos !== false && $posItin !== false
            && preg_match_all("/^\s*(\w[\w.\- ]+\/[\w.\- ]+.*)$/mu", substr($body, $pos, $posItin - $pos), $passengerMatches)
        ) {
            foreach ($passengerMatches[1] as $value) {
                $pass = array_values(array_filter(explode('   ', trim($value))));
                $f->general()
                    ->traveller(preg_replace(["/ (MR|MS|MISS|MRS)\.?\s*$/", "/(.+?)\s*\/\s*(.+)/"], ["", '$2 $1'], trim($pass[0])));

                if (!empty($pass[1])) {
                    $tickets = explode(',', $pass[1]);

                    foreach ($tickets as $ticket) {
                        if (preg_match("/^{$this->patterns['eTicket']}$/", trim($ticket))) {
                            $ticketArray[] = trim($ticket);
                        }
                    }
                }
            }
        }

        if (!empty($ticketArray)) {
            $f->issued()
                ->tickets(array_unique($ticketArray), false);
        }

        $posEnd = strpos($body, 'Payment Summary', $posItin);

        if ($posItin !== false && $posEnd !== false
            && preg_match_all("/(.+)\n+\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)(?:\s{3,}(.+))?\n+(.+)/", substr($body, $posItin, $posEnd - $posItin), $segMatches)
        ) {
            foreach ($segMatches[0] as $key => $value) {
                $s = $f->addSegment();

                $s->airline()->name($segMatches[2][$key])->number($segMatches[3][$key]);

                if (!empty($segMatches[4][$key])) {
                    $s->extra()->cabin($segMatches[4][$key]);
                }
                $dep = array_filter(explode('   ', $segMatches[1][$key]));

                if (count($dep) == 2) {
                    $s->departure()
                        ->date(strtotime(array_shift($dep)));

                    if (preg_match("#(.+)\(([A-Z]{3})\)\s*T?(.+)?#", array_shift($dep), $mat)) {
                        $s->departure()
                            ->name(trim($mat[1]))
                            ->code($mat[2]);

                        if (!empty($mat[3])) {
                            $s->departure()
                                ->terminal($mat[3]);
                        }
                    }
                }
                $arr = array_filter(explode('   ', $segMatches[5][$key]));

                if (count($arr) == 2) {
                    $s->arrival()
                        ->date(strtotime(array_shift($arr)));

                    if (preg_match("#(.+)\(([A-Z]{3})\)\s*T?(.+)?#", array_shift($arr), $mat)) {
                        $s->arrival()
                            ->name(trim($mat[1]))
                            ->code($mat[2]);

                        if (!empty($mat[3])) {
                            $s->arrival()
                                ->terminal($mat[3]);
                        }
                    }
                }

                if (empty($s->getDepDate())) {
                    $this->logger->debug('Wrong depDate on segment-' . $key . '!');
                    $this->format = 'html';
                    $email->clearItineraries();

                    return;
                }
            }
        } else {
            $this->logger->debug('Segments not found!');
            $this->format = 'html';
            $email->clearItineraries();

            return;
        }

        $this->format = 'pdf';
    }

    private function parsePdf2(Email $email, $body): void
    {
        // merge this method with `ItineraryFlight::parsePdf` (in favor of `ItineraryFlight::parsePdf`)

        $this->logger->debug(__FUNCTION__);

        $f = $email->add()->flight();

        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $pos = strpos($body, 'Airline Booking Reference');

        if ($pos !== false && preg_match("#Airline Booking Reference\s*:\s*([A-Z\d]+)\b#", substr($body, $pos), $m)) {
            $f->general()
                ->confirmation($m[1]);
        } elseif (preg_match("#Airline Booking Reference\s*:\s*\)#", substr($body, $pos), $m)) {
            $f->general()
                ->noConfirmation();
        }

        $pos = strpos($body, 'Booking No');

        if ($pos !== false && preg_match("#Booking No.\s+([\d]+)\b#", substr($body, $pos), $m)) {
            $this->otaConf[] = $m[1];
        }

        $pos = strpos($body, 'Booking On');

        if ($pos !== false && preg_match("#Booking On:\s+(.+)\n#", substr($body, $pos), $m)) {
            $f->general()
                ->date(strtotime($m[1]));
        }
        // get total from body
        $total = $this->http->FindSingleNode("//td[(" . $this->eq($this->t('Total amount')) . ") and not(descendant::td)]/following-sibling::td[normalize-space()][1]");

        if ($total === null) {
            $total = $this->http->FindSingleNode("//td[(" . $this->contains($this->t('Total amount')) . ") and not(descendant::td)]");
        }

        if ($total !== null) {
            $f->price()
                ->total($this->amount($total))
                ->currency($this->currency($total));
        }

        $pos = strpos($body, 'Ticket Number');
        $posItin = strpos($body, 'Flight Details', $pos);
        $ticketArray = [];

        if ($pos !== false && $posItin !== false
            && preg_match_all("/^\s*(\w[\w.\- ]+\/[\w.\- ]+.*)$/mu", substr($body, $pos, $posItin - $pos), $passengerMatches)
        ) {
            foreach ($passengerMatches[1] as $value) {
                $pass = array_values(array_filter(explode('   ', trim($value))));
                $f->general()
                    ->traveller(preg_replace("/(.+?)\s*\/\s*(.+)/", '$2 $1', trim($pass[0])));

                if (!empty($pass[1])) {
                    $tickets = explode(',', $pass[1]);

                    foreach ($tickets as $ticket) {
                        if (preg_match("/^{$this->patterns['eTicket']}$/", trim($ticket))) {
                            $ticketArray[] = trim($ticket);
                        }
                    }
                }
            }
        }

        if (!empty($ticketArray)) {
            $f->issued()
                ->tickets(array_unique($ticketArray), false);
        }

        $posEnd = strpos($body, 'Baggage Allowance', $posItin);
        $i = 0;

        while (($newEnd = strpos($body, 'Baggage Allowance', $posEnd + 1)) !== false) {
            // FE: it-44966296.eml
            $posEnd = $newEnd;
            $i++;

            if ($i > 20) {
                break;
            }
        }

        if ($posItin !== false && $posEnd !== false
            && preg_match_all("#(.+)\n+\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d{1,5})(?:\s{3,}(.+))?\n+(.+)#", substr($body, $posItin, $posEnd - $posItin), $segMatches)
        ) {
            foreach ($segMatches[0] as $key => $value) {
                $s = $f->addSegment();
                $s->airline()
                    ->name($segMatches[2][$key])
                    ->number($segMatches[3][$key]);

                if (!empty($segMatches[4][$key])) {
                    $s->extra()
                        ->cabin($segMatches[4][$key]);
                }
                $dep = array_filter(explode("   ", $segMatches[1][$key]));

                if (count($dep) == 2) {
                    $s->departure()
                        ->date(strtotime(array_shift($dep)));
                    $depNode = array_shift($dep);

                    if (preg_match("#(.+)\(([A-Z]{3})\)\s*T?(.+)?#", $depNode, $mat)) {
                        $s->departure()
                            ->name(trim($mat[1]))
                            ->code($mat[2]);

                        if (!empty($mat[3])) {
                            $s->departure()
                                ->terminal($mat[3]);
                        }
                    } elseif (preg_match("#(.+?)\s*(?:T(\w))?$#", $depNode, $mat)) {
                        $s->departure()
                            ->name(trim($mat[1]))
                            ->noCode();

                        if (isset($mat[2]) && !empty($mat[2])) {
                            $s->departure()
                                ->terminal($mat[2]);
                        }
                    }
                }
                $arr = array_filter(explode("   ", $segMatches[5][$key]));

                if (count($arr) == 2) {
                    $s->arrival()
                        ->date(strtotime(array_shift($arr)));
                    $arrNode = array_shift($arr);

                    if (preg_match("#(.+)\(([A-Z]{3})\)\s*T?(.+)?#", $arrNode, $mat)) {
                        $s->arrival()
                            ->name(trim($mat[1]))
                            ->code($mat[2]);

                        if (!empty($mat[3])) {
                            $s->arrival()->terminal($mat[3]);
                        }
                    } elseif (preg_match("#(.+?)\s*(?:T(\w))?$#", $arrNode, $mat)) {
                        $s->arrival()
                            ->name(trim($mat[1]))
                            ->noCode();

                        if (isset($mat[2]) && !empty($mat[2])) {
                            $s->arrival()
                                ->terminal($mat[2]);
                        }
                    }
                }

                if (empty($s->getDepName())) {
                    $this->format = 'html';
                    $email->clearItineraries();

                    return;
                }
            }
        }

        $this->format = 'pdf2';
    }

    private function getNode($str, $re = null, bool $following = true): ?string
    {
        if ($following) {
            return $this->http->FindSingleNode("//td[" . $this->contains($str) . "]/following-sibling::td[1]", null, true, $re);
        } else {
            return $this->http->FindSingleNode("//td[" . $this->contains($str) . "]", null, true, $re);
        }
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
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

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
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
