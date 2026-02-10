<?php

// test hook 7

namespace AwardWallet\Engine\aa\Email;

// TODO: for safety, to be removed
require_once __DIR__ . '/../functions.php';

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class API extends \TAccountCheckerAa
{
    public $mailFiles = "aa/it-11220842.eml, aa/it-139630566.eml, aa/it-155714862.eml, aa/it-27819803.eml, aa/it-322295426.eml, aa/it-33727905.eml, aa/it-388778395.eml, aa/it-54063072.eml, aa/it-59336894.eml, aa/it-61297162.eml, aa/it-6597000.eml, aa/it-65993350.eml, aa/it-6630026.eml, aa/it-6652063.eml, aa/it-6653404.eml, aa/it-6692819.eml, aa/it-6692820.eml, aa/it-6702502.eml, aa/it-6827073.eml, aa/it-8030190.eml";

    private $lang = 'en';

    private $fromEmails = [
        "/[@\.]aa\.com\b/i",
        "/[@\.]aa\.globalnotifications\.com\b/i",
        "/[@\.]aadvantageeshopping\.com\b/i",
        "/[@\.]checkin\.email\.aa\.com\b/i",
        "/[@\.]notify\.email\.aa\.com\b/i",
        "/aacarhotel@aa\.com\b/i",
        "/AmericanAirlinesVacations@aavacations\.com\b/",
    ];

    private $subjects = [
        "/AA\.com Itinerary/i",
        "/AA Car\/Hotel Confirmation and Itinerary/i",
        "/E\-Ticket Confirmation\-[A-Z\d]{6}/",
        //		"/Your trip confirmation\-[A-Z\d]{6}/",
        "/AA FLIGHT NUMBER CHANGE/",
        "/AA Schedule Change-[A-Z\d]{6}/",
        "/American Airlines Car Reservation Summary/",
        "/American Airlines AAdvantage Reservation/",
        "/American Airlines Boarding Pass\(es\)/",
        "/AAdvantage eSummary/",
        "/American Airlines Vacations Reservation/",
        "/American Airlines check-in reminder/",
        "/Thank you for booking with American Airlines through Orbitz/",
        //		"/\d+\/\d+\/\d+\s+trip details/",
        "/American Airlines mobile boarding pass/",
        "/American Airlines flight \d+ to .+? \([A-Z\d]{5,}\)/",
        "/AA\.com Change Reservation Confirmation/",
    ];

    private $bodyText = [
        "Welcome to American Airlines! To make your trip more pleasant",
        "Thank you for choosing American Airlines",
        "This email has been sent on behalf of American Airlines",
        "Thank you for redeeming your miles with AAdvantage",
        "Kiitos, että valitsit American Airlinesin",
        "Baggage charges for your itinerary will be governed by American Airlines",
        "Gracias por elegir American Airlines",
        "Merci d'avoir choisi American Airlines",
        "Wir bedanken uns für Ihre Buchung bei American Airlines",
        //        "This message contains confidential and proprietary information of American Airlines",
        "need your record locator to find your trip at the kiosk and when you call Reservations",
        "Thank you for booking your flight with American Airlines",
        "Código de reserva de American Airlines",
        "Thank you for using American Airlines for Business",
        "Grazie per aver scelto American Airlines, membro dell'alleanza",
        "Danke, dass Sie sich für American Airlines entschieden haben, ein Mitglied der",
        "Bedankt dat u hebt gekozen voor American Airlines",
        "Please return to aa.com for your future travel needs",
        "Thank you for modifying your travel arrangements on AA.com.",
        "Free entertainment with the American app",
        //        "American Airlines flight"
        //		'This appointment works with calendar applications that support an iCal format',
        //		"Retrieve your boarding pass:"
    ];

    private $date;
    // TODO: many XPath-expressions need to narrow the range of operation
    private $bodyXPath = [
        "//a[contains(@href, 'www.aa.com/myAccount/myAccountAccess.do')]",
        "//a[contains(@href, 'www.aa.com/reservation/reservationsHomeAccess.do')]",
        "//a[contains(@href, 'www.aa.com/reservation/emailHoldConfirmation.do')]",
        "//a[contains(@href, 'www.aa.com/reservation/view/find-your-trip')]",
        "//a[contains(@href, 'www.aa.com/contactAA/viewContactAAAccess.do')]",
        "//a[contains(@href, 'pdp.link.aa.com') and contains(., 'AAdvantage')]",
        "//a[contains(@href, 'www.aa.com/reservation/emailcheckin.do') and contains(text(), 'Manage')]",
        "//a[contains(@href, 'aavacations.com/service/booking')]",
        "//a[contains(@href, 'www.aa.com/intl/fr/informationVoyage/onlineCheckin.jsp')]",
        "//a[contains(@href, 'pdp.link.aa.com/r/') and contains(., 'Reservations')]",
        "//a[contains(@href, 'www.aa.com/reservation/emailcheckin.do?source=') and contains(., 'View your trip')]",
        "//a[contains(@href,'American_Airlines_Guest_Travel@aa.com') and contains(text(),'American_Airlines_Guest_Travel@aa.com')]",
        "//a[contains(@href, 'www.aa.com/reservation/emailHoldConfirmation.do?firstName=') and contains(., 'View your trip')]",
        "//a[contains(@href, 'http://pdp.link.aa.com/r/') and ./img]",
        "//a[contains(@href, 'www.aa.com/homePage.do') and ./img]",
        "//a[contains(@href, 'https://www.aa.com/homePage.do')]",
        "//node()[contains(., 'www.aa.com/checkin')]",
        "//a[contains(@href, 'https://www.aa.com/checkin/viewMobileBoardingPass?')]",
        //"//img[contains(@src, 'www.aa.com/content')]",
        "//a[contains(@href, 'l.info.ms.aa.com/rts/go2.aspx')]",
        "//img[contains(@alt, 'Thanks for choosing American Airlines')]",
        "//a[contains(@href, '//www.aa.com/reservation/flightCheckInViewReservationsAccess.do') and contains(normalize-space(.),'Flight Check-in')]",
        "//a[contains(@href, 'www.aa.com_checkin_viewMobileBoardingPass')]",
        "//a[contains(@href, 'www.aa.com_reservation_view_find')]",
        "//a[contains(@href, '.safelinks.protection.outlook.com/?url=') and contains(@href, 'www.aa.com') and contains(@href, 'checkin') and contains(@href, 'viewMobileBoardingPass')]",
        "//img[contains(@src,'business.aa.com')]",
        "//a[contains(., 'Manage Your Trip') and (contains(@href, 'www.aa.com/reservation/view/find-your-trip') or contains(@href, 'www.aa.com_reservation_view_find-2Dyour-2Dtrip'))]",
    ];

    private $xpath = [
        'time' => '(starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))',
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]',
    ];

    public function detectEmailFromProvider($from)
    {
        foreach ($this->fromEmails as $email) {
            if (preg_match($email, $from)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from'])) {
            foreach ($this->fromEmails as $email) {
                if (preg_match($email, $headers['from'])) {
                    return true;
                }
            }
        }

        if (isset($headers['subject'])) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->bodyXPath as $xpath) {
            if ($this->http->XPath->query($xpath)->length > 0) {
                return true;
            }
        }

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        foreach ($this->bodyText as $text) {
            if (stripos($body, $text) !== false) {
                return true;
            }
        }

        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $pdfBody = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($pdfBody, 'AAdvantage') !== false || (strpos($pdfBody, 'www.aa.com') !== false && strpos($pdfBody, 'American Airlines') !== false)) {
                return true;
            }
        }

        return false;
    }

    public function ParseETicket()
    {
        $result = ["Kind" => "T"];

        $result["RecordLocator"] = $this->http->FindSingleNode("//*[contains(normalize-space(text()),'Record Locator')]/following::td[1]", null, true, '/^([A-Z\d]{5,7})$/');

        if (!isset($result['RecordLocator'])) {
            $result['RecordLocator'] = $this->http->FindSingleNode('//td[contains(.,"Record Locator") and not(.//td)]/following-sibling::td[1]', null, true, '/^([A-Z\d]{5,7})$/');
        }

        if (!isset($result["RecordLocator"])) {
            $confs = array_filter($this->http->FindNodes("//td[(contains(.,'Record Locator:') or contains(.,'Record locator:')) and not(.//td)]", null, "/Record [Ll]ocator:\s*([A-Z\d]{5,7})$/"), 'strlen');
            $result["RecordLocator"] = array_shift($confs);
        }

        $result["Passengers"] = array_map('trim', $this->http->FindNodes("//img[contains(@src,'icon_passenger')]/ancestor::td[1]", null, '/[\w\s]+/'));

        if (count($result["Passengers"]) == 0) {
            $result["Passengers"] = $this->http->FindNodes("//tr[td[.//strong[contains(.,'PASSENGER')] and not(.//td)]]/following-sibling::tr/td[1]");
        }

        if (count($result["Passengers"]) == 0) {
            $result["Passengers"] = $this->http->FindNodes("//tr[td[contains(.,'Passenger') and not(.//td)] and td[contains(.,'Ticket #') and not(.//td)]]/following-sibling::tr[normalize-space(.)!='']/td[2]");
        }

        if (count($result["Passengers"]) == 0) {
            $result['Passengers'] = $this->http->FindNodes("//text()[starts-with(.,'Ticket')]/ancestor::td[2]/preceding-sibling::td[1]");
        }

        if (count($result["Passengers"]) == 0) {
            $result['Passengers'] = $this->http->FindNodes('//td[count(./descendant::text()[string-length(normalize-space(.))>1])=2]/descendant::*[starts-with(normalize-space(.),"Traveling on this trip")]/following::text()[string-length(normalize-space(.))>1][1]');
        }

        if (count($result["Passengers"]) == 0) {
            $result['Passengers'] =
                $this->http->FindNodes('//text()[starts-with(normalize-space(.),"Hello ") and contains(.,"!")]', null,
                    "#Hello\s+(\w+\s+\w+.*)\!#u");
        }

        if (!empty($result['Passengers'])) {
            $result['Passengers'] = preg_replace("#^\s*Chd (.+?) Chd\s*$#", '$1', $result['Passengers']);
            $result['Passengers'] = array_unique($result['Passengers']);
        }

        $resDate = $this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Ticket Issued')]", null, false, "#Ticket Issued[\s:]+(.+)#"));

        if ($resDate) {
            $result['ReservationDate'] = $resDate;
            $this->date = $resDate;
        }
        $tickets = $this->http->FindNodes("//text()[normalize-space()='Ticket #']/ancestor::tr[1][contains(.,'Passenger')]/following-sibling::tr/td[normalize-space()][2]", null, "#^\d+$#");

        if (count($tickets)) {
            $result["TicketNumbers"] = array_unique($tickets);
        }
        $acc = array_values(array_filter(array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(),'FF#:')]",
            null, "/FF#:\s*(.+)/"))));

        if (count($acc)) {
            $result['AccountNumbers'] = $acc;
        }
        $total = $this->http->FindSingleNode("//img[contains(@src, 'icon_passenger')]/ancestor::table[1]/following::table[1]/descendant::td[normalize-space()!=''][count(.//td)=0][last()]");

        if ($total) {
            $result['TotalCharge'] = $this->amount($total);
            $result['Currency'] = $this->currency($total);
        }

        $xpath = "//text()[normalize-space()='Flight #']/ancestor::tr[1][contains(.,'Carrier')]/following-sibling::tr[normalize-space()][ ./td[1][.//img] and ./td[2][.//img]  and count(./td)>3]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $seg = [];
            $cntPax = count($result["Passengers"]);
            $seats = $this->http->FindNodes(".//following-sibling::tr[normalize-space()!=''][count(./td)>3][position()<={$cntPax}]/td[normalize-space()!=''][2][contains(.,'Seat')]", $root, "#Seat[:\s*](\d+[A-z])$#");

            if (count($seats)) {
                $seg['Seats'] = $seats;
            }
            $cabin = implode('|', array_unique($this->http->FindNodes(".//following-sibling::tr[normalize-space()!=''][position()<={$cntPax}]/td[normalize-space()!=''][2][contains(.,'Seat')]/following-sibling::td[normalize-space()!=''][1]", $root)));

            if (!empty($cabin)) {
                $seg['Cabin'] = $cabin;
            }
            $meal = implode('|', array_unique($this->http->FindNodes(".//following-sibling::tr[normalize-space()!=''][position()<={$cntPax}]/td[normalize-space()!=''][2][contains(.,'Seat')]/following-sibling::td[normalize-space()!=''][3]", $root)));

            if (!empty($meal)) {
                $seg['Meal'] = $meal;
            }

            $node = $this->http->FindSingleNode("./td[normalize-space()!=''][1]", $root);

            if ($node == 'American') {
                $seg['AirlineName'] = 'AA';
            } else {
                $seg['AirlineName'] = $node;
            }
            $seg['FlightNumber'] = $this->http->FindSingleNode("./td[normalize-space()!=''][2]", $root, false,
                "#^\d+$#");

            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            $nodes = $this->http->FindNodes("./td[normalize-space()!=''][3]/descendant::text()[normalize-space()]", $root);

            if (count($nodes) == 3) {
                $seg['DepName'] = $nodes[0];
                $date = $this->normalizeDate($nodes[1]);
                $seg['DepDate'] = strtotime($nodes[2], $date);
            }

            $nodes = $this->http->FindNodes("./td[normalize-space()!=''][4]/descendant::text()[normalize-space()]", $root);

            if (count($nodes) == 2) {
                $seg['ArrName'] = $nodes[0];

                if (isset($date)) {
                    $seg['ArrDate'] = strtotime($nodes[1], $date);
                }
            } elseif (count($nodes) == 3) {
                $seg['ArrName'] = $nodes[0];
                $date = $this->normalizeDate($nodes[1]);
                $seg['ArrDate'] = strtotime($nodes[2], $date);
            }

            $result['TripSegments'][] = $seg;
        }

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    public function ParseETicketPt()
    {
        $result = ["Kind" => "T"];
        $result["RecordLocator"] = $this->http->FindSingleNode("//*[contains(normalize-space(text()), 'Data de emiss')]/following::td[1]", null, true, "/^[A-Z\d]{5,7}$/");

        if (!isset($result["RecordLocator"])) {
            $confs = array_filter($this->http->FindNodes("//td[contains(., 'Data de emiss') and not(.//td)]", null, "/Data de emissão\s*([A-Z\d]{5,7})$/"), 'strlen');
            $result["RecordLocator"] = array_shift($confs);
        }
        $names = $this->http->FindNodes("//img[contains(@src, 'icon_passenger')]/ancestor::td[1]");
        $tickets = $this->http->FindNodes("//img[contains(@src, 'icon_passenger')]/ancestor::td[1]/following-sibling::td[1]", null, '/^\d{3}[- ]*\d{5,}[- ]*\d{1,2}$/');

        if (empty($names)) {
            $rows = $this->http->XPath->query('//tr[.//td[contains(., "Passageiro")] and not(.//tr)]/following-sibling::tr[normalize-space(.) != ""]');
            $names = [];
            $tickets = [];

            foreach ($rows as $row) {
                if ($ticket = $this->http->FindSingleNode('td[3]', $row, true, '/^\d{5,}$/')) {
                    $tickets[] = $ticket;
                }
            }
        }
        $result["Passengers"] = array_map(function ($s) {
            return preg_replace("/ - .+$/", "", $s);
        }, $names);

        foreach ($result['Passengers'] as $i => $p) {
            $result['Passengers'][$i] = trim(str_replace([html_entity_decode("&#194;")], ' ', $p));
        }

        $resDate = $this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Bilhete emitido')]", null, true, "/Bilhete emitido[\s:]+(.+)/"), 'pt');

        if ($resDate) {
            $result['ReservationDate'] = $resDate;
            $this->date = $resDate;
        }

        if (count($tickets)) {
            $result["TicketNumbers"] = array_unique($tickets);
        }
        $acc = array_values(array_filter(array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(),'FF#:')]",
            null, "/FF#:\s*(.+)/"))));

        if (count($acc)) {
            $result['AccountNumbers'] = $acc;
        }
        $total = $this->http->FindSingleNode("//img[contains(@src, 'icon_passenger')]/ancestor::table[1]/following::table[1]/descendant::td[normalize-space()!=''][count(.//td)=0][last()]");

        $currencyArr = array_filter($this->http->FindNodes("(//img[contains(@src, 'icon_passenger')])[1]/preceding::td[not(.//td)][position()<10][starts-with(normalize-space(),'Tarifa')]", null, "/Tarifa(?: [[:alpha:]]+\.?)?-([A-Z]{3})/"));

        if (!empty($currencyArr)) {
            $currency = array_pop($currencyArr);
        }

        if ($total) {
            $result['TotalCharge'] = $this->amount($total);
            $result['Currency'] = (!empty($currency)) ? $currency : $this->currency($total);
        }

        $xpath = "//tr[ *[normalize-space()='Transportadora'] and *[normalize-space()='Chegando'] ]/following-sibling::tr[normalize-space()][ *[1]//img and *[2]//img and *[5] ]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $seg = [];
            $cntPax = count($result["Passengers"]);
            $seats = $this->http->FindNodes(".//following-sibling::tr[normalize-space()!=''][count(./td)>3][position()<={$cntPax}]/td[normalize-space()!=''][2][contains(.,'Seat')]", $root, "#Seat[:\s*](\d+[A-z])$#");

            if (count($seats)) {
                $seg['Seats'] = $seats;
            }
            $cabin = implode('|', array_unique($this->http->FindNodes(".//following-sibling::tr[normalize-space()!=''][position()<={$cntPax}]/td[normalize-space()!=''][2][contains(.,'Seat')]/following-sibling::td[normalize-space()!=''][1]", $root)));

            if (empty($cabin)) {
                $cabin = implode('|',
                    array_unique($this->http->FindNodes(".//following-sibling::tr[normalize-space()!=''][position()<={$cntPax}]/td[normalize-space()!=''][4][contains(.,'FF#:')]/preceding-sibling::td[normalize-space()!=''][1]",
                        $root)));
            }

            if (!empty($cabin)) {
                $seg['Cabin'] = $cabin;
            }
            $meal = implode('|', array_unique($this->http->FindNodes(".//following-sibling::tr[normalize-space()!=''][position()<={$cntPax}]/td[normalize-space()!=''][2][contains(.,'Seat')]/following-sibling::td[normalize-space()!=''][3]", $root)));

            if (empty($meal)) {
                $meal = implode('|', array_unique($this->http->FindNodes(".//following-sibling::tr[normalize-space()!=''][position()<={$cntPax}]/td[normalize-space()!=''][4][contains(.,'FF#:')]/following-sibling::td[normalize-space()!=''][1]", $root)));
            }

            if (!empty($meal)) {
                $seg['Meal'] = $meal;
            }

            $node = $this->http->FindSingleNode("./td[normalize-space()!=''][1]", $root);

            if ($node == 'American') {
                $seg['AirlineName'] = 'AA';
            } else {
                $seg['AirlineName'] = $node;
            }
            $seg['FlightNumber'] = $this->http->FindSingleNode("./td[normalize-space()!=''][2]", $root, false,
                "#^\d+$#");

            $operatedBy = $this->http->FindSingleNode("./following::td[normalize-space()!=''][1]", $root, false,
                "#OPERATED BY (.+)#");

            if (!empty($operatedBy)) {
                $seg['Operator'] = $operatedBy;
            }

            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            $nodes = $this->http->FindNodes("./td[normalize-space()!=''][3]/descendant::text()", $root);

            if (count($nodes) == 3) {
                $seg['DepName'] = $nodes[0];
                $date = $this->normalizeDate(str_replace(['', 'Ã'], ['', 'Á'], $nodes[1]), 'pt');
                $seg['DepDate'] = strtotime($nodes[2], $date);
            }

            $nodes = $this->http->FindNodes("./td[normalize-space()!=''][4]/descendant::text()", $root);

            if (count($nodes) == 2) {
                $seg['ArrName'] = $nodes[0];

                if (isset($date)) {
                    $seg['ArrDate'] = strtotime($nodes[1], $date);
                }
            } elseif (count($nodes) == 3) {
                $seg['ArrName'] = $nodes[0];
                $date = $this->normalizeDate(str_replace(['', 'Ã'], ['', 'Á'], $nodes[1]), 'pt');
                $seg['ArrDate'] = strtotime($nodes[2], $date);
            }
            $result['TripSegments'][] = $seg;
        }

        $result["Passengers"] = array_unique($result["Passengers"]);

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    public function ParseETicketFr()
    {
        $result = ["Kind" => "T"];
        $result["RecordLocator"] = $this->http->FindSingleNode("(//text()[contains(., 'rence de dossier:')]/following-sibling::*[normalize-space()])[1]", null, true, "/^[A-Z\d]{5,7}$/");

        if (empty($result["RecordLocator"])) {
            $result["RecordLocator"] = $this->http->FindSingleNode("(//*[contains(normalize-space(text()), 'Référence de dossier')])[1]/following::td[1]", null, true, "/^[A-Z\d]{5,7}$/");
        }

        if (empty($result["RecordLocator"])) {
            $confs = array_filter($this->http->FindNodes("//td[contains(., 'Référence de dossier') and not(.//td)]", null, "/Référence de dossier\S*\s*([A-Z\d]{5,7})$/"), 'strlen');
            $result["RecordLocator"] = array_shift($confs);
        }

        if (empty($result["RecordLocator"])) {
            $result['RecordLocator'] = $this->http->FindSingleNode('//td[contains(., "Référence de dossier") and not(.//td)]', null, true, '/Référence de dossier:\s*([A-Z\d]{5,7})/', 1);
        }

        $result["Passengers"] = array_unique(array_map(function ($s) {
            return preg_replace("/ - .+$/", "", $s);
        }, $this->http->FindNodes("//img[contains(@src, 'icon_passenger')]/ancestor::td[1]")));

        if (empty($result['Passengers'])) {
            $result['Passengers'] = $this->http->FindNodes('//tr[td[contains(., "PASSAGER") and not(.//td)]]/following-sibling::tr/td[1]');
        }

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    public function ParseETicketDe()
    {
        $result = ["Kind" => "T"];
        $result["RecordLocator"] = $this->http->FindSingleNode("(//*[contains(normalize-space(text()), 'Buchungsreferenz')])[1]/following::td[1]", null, true, "/^[A-Z\d]{5,7}$/");

        if (!isset($result["RecordLocator"])) {
            $confs = array_filter($this->http->FindNodes("//td[contains(., 'Buchungsreferenz') and not(.//td)]", null, "/Buchungsreferenz\S*\s*([A-Z\d]{5,7})$/"), 'strlen');
            $result["RecordLocator"] = array_shift($confs);
        }

        if (!isset($result["RecordLocator"])) {
            $result['RecordLocator'] = $this->http->FindSingleNode('//td[contains(., "Buchungsreferenz") and not(.//td)]', null, true, '/Buchungsreferenz:\s*([A-Z\d]{5,7})/', 1);
        }

        $result["Passengers"] = array_unique(array_map(function ($s) {
            return preg_replace("/ - .+$/", "", $s);
        }, $this->http->FindNodes("//img[contains(@src, 'icon_passenger')]/ancestor::td[1]")));

        if (empty($result['Passengers'])) {
            $result['Passengers'] = $this->http->FindNodes('//tr[td[contains(., "PASSAGIERNAMEN") and not(.//td)]]/following-sibling::tr/td[1]');
        }

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    public function ParseETicketEs()
    {
        $result = ["Kind" => "T"];
        $result["RecordLocator"] = $this->http->FindSingleNode("(//*[contains(normalize-space(text()), 'digo de reservaci')])[1]/following::td[1]", null, true, "/^[A-Z\d]{5,7}$/");

        if (!isset($result["RecordLocator"])) {
            $confs = array_filter($this->http->FindNodes("//td[contains(., 'digo de reservaci') and not(.//td)]", null, "/digo de reservaci\S*\s*([A-Z\d]{5,7})$/"), 'strlen');
            $result["RecordLocator"] = array_shift($confs);
        }

        if (!isset($result["RecordLocator"])) {
            $result['RecordLocator'] = $this->http->FindSingleNode('//td[contains(., "Localizador de la Reserva") and not(.//td)]', null, true, '/Localizador de la Reserva:\s*([A-Z\d]{5,7})/', 1);
        }

        $result["Passengers"] = array_map(function ($s) {
            return preg_replace("/ - .+$/", "", $s);
        }, $this->http->FindNodes("//img[contains(@src, 'icon_passenger')]/ancestor::td[1]"));

        if (!empty($result["Passengers"])) {
            $result["TicketNumbers"] = array_unique($this->http->FindNodes("//img[contains(@src, 'icon_passenger')]/ancestor::td[1]/following-sibling::td[1]", null, '/^\d{5,}$/'));
        }

        if (empty($result['Passengers'])) {
            $result['Passengers'] = $this->http->FindNodes('//tr[td[(contains(., "PASAJERO") or contains(., "Pasajero")) and not(.//td)]]/following-sibling::tr/td[1][normalize-space()]');
            $result['TicketNumbers'] = $this->http->FindNodes('//tr[td[(contains(., "PASAJERO") or contains(., "Pasajero")) and not(.//td)]]/following-sibling::tr/td[2][normalize-space()]');
        }

        if (empty($result['Passengers'])) {
            $result['Passengers'] = $this->http->FindNodes('//tr[td[(normalize-space() = "PASAJERO" or normalize-space() = "Pasajero") and not(.//td)]]/following-sibling::tr[normalize-space()][./td[1][.//img and not(.//*[normalize-space()])]]/td[2]');
            $result['TicketNumbers'] = $this->http->FindNodes('//tr[td[(normalize-space() = "PASAJERO" or normalize-space() = "Pasajero") and not(.//td)]]/following-sibling::tr[normalize-space()][./td[1][.//img and not(.//*[normalize-space()])]]/td[3]');
        }

        foreach ($result['Passengers'] as $i => $p) {
            $result['Passengers'][$i] = trim(str_replace([html_entity_decode("&#194;")], ' ', $p));
        }

        $resDate = $this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Billete emitido')]", null, false, "#Billete emitido[\s:]+(.+)#"), 'es');

        if ($resDate) {
            $result['ReservationDate'] = $resDate;
            $this->date = $resDate;
        }

        $acc = array_values(array_filter(array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(),'FF №:')]",
            null, "/FF №:\s*(.+)/"))));

        if (count($acc)) {
            $result['AccountNumbers'] = $acc;
        }
        $total = $this->http->FindSingleNode("//img[contains(@src, 'icon_passenger')]/ancestor::table[1]/following::table[1]/descendant::td[normalize-space()!=''][count(.//td)=0][last()]");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//tr[td[(normalize-space() = 'PASAJERO' or normalize-space() = 'Pasajero') and not(.//td)]]/ancestor::table[1]/following::table[1]/descendant::td[normalize-space()!=''][count(.//td)=0][last()]");
        }

        $currencyArr = array_filter($this->http->FindNodes("(//img[contains(@src, 'icon_passenger')])[1]/preceding::td[not(.//td)][position()<10][starts-with(normalize-space(),'Tarifa')]", null, "/Tarifa(?: [[:alpha:]]+\.?)?-([A-Z]{3})/"));

        if (empty($currencyArr)) {
            $currencyArr = array_filter($this->http->FindNodes("//tr[td[(normalize-space() = 'PASAJERO' or normalize-space() = 'Pasajero') and not(.//td)]]/following::td[not(.//td)][position()<10][starts-with(normalize-space(),'Tarifa')]",
                null, "/Tarifa(?: [[:alpha:]]+\.?)?-([A-Z]{3})/"));
        }

        if (!empty($currencyArr)) {
            $currency = array_pop($currencyArr);
        }

        if ($total) {
            $result['TotalCharge'] = $this->amount($total);
            $result['Currency'] = (!empty($currency)) ? $currency : $this->currency($total);
        }

        $xpath = "//text()[normalize-space()='Vuelo №' or normalize-space() = 'Vuelo â']/ancestor::tr[1][contains(.,'transportadora')]/following-sibling::tr[normalize-space()][ ./td[1][.//img] and ./td[2][.//img] and *[5] ]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $seg = [];
            $cntPax = count($result["Passengers"]);
            $seats = $this->http->FindNodes(".//following-sibling::tr[normalize-space()!=''][count(./td)>3][position()<={$cntPax}]/td[normalize-space()!=''][2][contains(.,'Seat')]", $root, "#Seat[:\s*](\d+[A-z])$#");

            if (count($seats)) {
                $seg['Seats'] = $seats;
            }
            $cabin = implode('|', array_unique($this->http->FindNodes(".//following-sibling::tr[normalize-space()!=''][position()<={$cntPax}]/td[normalize-space()!=''][2][contains(.,'Seat')]/following-sibling::td[normalize-space()!=''][1]", $root)));

            if (!empty($cabin)) {
                $seg['Cabin'] = $cabin;
            }

            $meal = implode('|', array_unique($this->http->FindNodes(".//following-sibling::tr[normalize-space()!=''][position()<={$cntPax}]/td[normalize-space()!=''][2][contains(.,'Seat')]/following-sibling::td[normalize-space()!=''][3]", $root)));

            if (!empty($meal) && strlen($meal) > 2) {
                $seg['Meal'] = $meal;
            }

            $node = $this->http->FindSingleNode("./td[normalize-space()!=''][1]", $root);

            if ($node == 'American') {
                $seg['AirlineName'] = 'AA';
            } else {
                $seg['AirlineName'] = $node;
            }
            $seg['FlightNumber'] = $this->http->FindSingleNode("./td[normalize-space()!=''][2]", $root, false,
                "#^\d+$#");

            $operatedBy = $this->http->FindSingleNode("./following::td[normalize-space()!=''][1]", $root, false,
                "#OPERATED BY (.+)#");

            if (!empty($operatedBy)) {
                $seg['Operator'] = $operatedBy;
            }

            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            $nodes = $this->http->FindNodes("./td[normalize-space()!=''][3]/descendant::text()[normalize-space()]", $root);

            if (count($nodes) == 3) {
                $seg['DepName'] = $nodes[0];
                $nodes[1] = str_replace('Ì', '', $nodes[1]);
                $nodes[1] = str_replace('É', 'E', $nodes[1]);
                $date = $this->normalizeDate(str_replace('Á', 'Á', $nodes[1]), 'es');
                $seg['DepDate'] = strtotime($nodes[2], $date);
            }

            $nodes = $this->http->FindNodes("./td[normalize-space()!=''][4]/descendant::text()[normalize-space()]", $root);

            if (count($nodes) == 2) {
                $seg['ArrName'] = $nodes[0];

                if (isset($date)) {
                    $seg['ArrDate'] = strtotime($nodes[1], $date);
                }
            } elseif (count($nodes) == 3) {
                $seg['ArrName'] = $nodes[0];
                $nodes[1] = str_replace('Ì', '', $nodes[1]);
                $nodes[1] = str_replace('É', 'E', $nodes[1]);
                $date = $this->normalizeDate(str_replace('Á', 'Á', $nodes[1]), 'es');
                $seg['ArrDate'] = strtotime($nodes[2], $date);
            }
            $result['TripSegments'][] = $seg;
        }

        $result["Passengers"] = array_unique($result["Passengers"]);

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    public function ParseAaItinerary()
    {
        $result = ["Kind" => "T"];

        $result["RecordLocator"] = $this->http->FindSingleNode("(//*[name()='b' or name()='strong']/descendant::text()[contains(normalize-space(.),'Record Locator:')]/following::font[normalize-space(.)][1])[1]", null, true, '/^([A-Z\d]{5,7})$/');

        if (empty($result["RecordLocator"])) {
            $rl = $this->http->FindNodes("//*[contains(text(),'Record Locator:')]/parent::*/parent::*", null, "/Record Locator: ([A-Z\d]{5,7})/");
            $rls = array_filter($rl);
            $result["RecordLocator"] = array_shift($rls);
        }

        $result["Passengers"] = $this->http->FindNodes('//table/descendant::tr[normalize-space(.)="PASSENGER" and not(.//tr)][1]/following-sibling::tr[string-length(normalize-space(.))>1]');

        if (empty($result["Passengers"])) {
            $result["Passengers"] = $this->http->FindNodes("//text()[normalize-space(.)='Passengers']/following::text()[string-length(normalize-space(.))>2][1]");
        }

        if (empty($result["Passengers"])) {
            $result["Passengers"] = $this->http->FindNodes("//*[contains(text(),'Class')]/ancestor::thead/following-sibling::tbody/tr/td[1] | //*[contains(text(),'Class')]/ancestor::thead/following-sibling::tr/td[1]");
        }

        if (!empty($result["RecordLocator"]) && empty($this->http->FindSingleNode("(//table)[1]"))) {
            if (preg_match("#\s{2,}Passenger[ ]+Class.*\s+((\S.+\n){1,10}?)\n\n#", $this->http->Response['body'], $mat) && preg_match_all("#^\s*(.+?)(?:\s{2,}|\n)#m", $mat[1], $m)) {
                $result["Passengers"] = $m[1];
            }
        }

        if (!empty($result['Passengers'])) {
            $result['Passengers'] = array_unique($result['Passengers']);
        }

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    public function ParseAaItineraryNl()
    {
        $result = ["Kind" => "T"];

        $result["RecordLocator"] = $this->http->FindSingleNode("(//*[name()='b' or name()='strong']/descendant::text()[contains(normalize-space(.),'Record-locator:')]/following::font[normalize-space(.)][1])[1]", null, true, '/^([A-Z\d]{5,7})$/');

        if (empty($result["RecordLocator"])) {
            $rl = $this->http->FindNodes("//*[contains(text(),'Record-locator:')]/parent::*/parent::*", null, "/Record-locator: ([A-Z\d]{5,7})/");
            $rls = array_filter($rl);
            $result["RecordLocator"] = array_shift($rls);
        }

        $result["Passengers"] = $this->http->FindNodes('//table/descendant::tr[normalize-space(.)="PASSAGIER" and not(.//tr)][1]/following-sibling::tr[string-length(normalize-space(.))>1]');

        if (!empty($result['Passengers'])) {
            $result['Passengers'] = array_unique($result['Passengers']);
        }

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    public function ParseAaItineraryPt()
    {
        $result = ["Kind" => "T"];
        $result["RecordLocator"] = $this->http->FindSingleNode("(//b | //strong)/font[contains(text(), 'AA Código de Reserva:')]/following-sibling::font");

        if (empty($result["RecordLocator"])) {
            $result["RecordLocator"] = $this->http->FindSingleNode("//*[contains(text(), 'AA Código de Reserva:')]/parent::*/parent::*", null, true, "/AA Código de Reserva: ([A-Z\d]{5,7})/");
        }

        if (empty($result["RecordLocator"])) {
            $result["RecordLocator"] = $this->http->FindSingleNode("//*[contains(text(), 'Código de reserva da American Airlines:')]/parent::*/parent::*", null, true, "/Código de reserva da American Airlines: ([A-Z\d]{5,7})/");
        }
        $result["Passengers"] = array_unique($this->http->FindNodes("//*[contains(text(), 'Classe')]/ancestor::thead/following-sibling::tbody/tr/td[1] | //*[contains(text(), 'Classe de Cabine')]/ancestor::thead/following-sibling::tr/td[1]"));

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    public function ParseAaItineraryIt()
    {
        $result = ["Kind" => "T"];
        $result["RecordLocator"] = $this->http->FindSingleNode("((//b | //strong)/font[contains(text(), 'Codice prenotazione:')]/following-sibling::font)[1]");

        if (empty($result["RecordLocator"])) {
            $result["RecordLocator"] = $this->http->FindSingleNode("(//*[contains(text(), 'Codice prenotazione:')]/parent::*/parent::*)[1]", null, true, "/Codice prenotazione: ([A-Z\d]{5,7})/");
        }
        $result["Passengers"] = $this->http->FindNodes('//table/descendant::tr[normalize-space(.)="PASSEGGERO" and not(.//tr)][1]/following-sibling::tr[string-length(normalize-space(.))>1]', null, "#^\s*(?:MR |MS |MRS |DR )?(.+)#");

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    public function ParseAaItineraryDe()
    {
        $result = ["Kind" => "T"];
        $result["RecordLocator"] = $this->http->FindSingleNode("((//b | //strong)/font[contains(text(), 'Buchungsreferenz:')]/following-sibling::font)[1]");

        if (empty($result["RecordLocator"])) {
            $result["RecordLocator"] = $this->http->FindSingleNode("(//*[contains(text(), 'Buchungsreferenz:')]/parent::*/parent::*)[1]", null, true, "/Buchungsreferenz: ([A-Z\d]{5,7})/");
        }
        $result["Passengers"] = $this->http->FindNodes('//table/descendant::tr[normalize-space(.)="PASSAGIERNAMEN" and not(.//tr)][1]/following-sibling::tr[string-length(normalize-space(.))>1]', null, "#^\s*(?:MR |MS |MRS |DR )?(.+)#");

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    public function ParseSchedEmail()
    {
        $result = ["Kind" => "T"];

        $result["RecordLocator"] = $this->http->FindSingleNode("//tr[contains(.,'Record Locator:') and not(.//tr)]", null, true, "/Record Locator:\s*([A-Z\d]{5,7})/");

        $xpathFragment1 = './td[position()=3 and starts-with(normalize-space(.),"SEAT")]';
        $xpathFragment2 = '//tr[./td[position()=1 and normalize-space(.)="Name:"] and ' . $xpathFragment1 . ']';
        $passengers = $this->http->FindNodes($xpathFragment2 . '/td[2] | ' . $xpathFragment2 . '/following-sibling::tr[' . $xpathFragment1 . ']/td[2]');
        $passengerValues = array_values(array_filter($passengers));

        if (!empty($passengerValues[0])) {
            $result["Passengers"] = $passengerValues;
        }

        if (empty($result["Passengers"])) {
            $people = $this->http->FindNodes("//tr[contains(.,'Record Locator:') and not(.//tr)]/preceding-sibling::tr");

            if (count($people) === 1) {
                $result["Passengers"] = $people;
            }
        }

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    public function ParseAARental()
    {
        $result = ['Kind' => 'L'];

        if ($this->http->XPath->query("//text()[normalize-space()='Canceled Reservation Details']")->length > 0) {
            $result['Cancelled'] = true;
            $result['Status'] = 'Cancelled';
        }

        $result['Number'] = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"Booking Number") or contains(normalize-space(.),"Booking number")]/following::text()[normalize-space(.)!=""][1]', null, true, '/^([A-Z\d]{5,})$/');
        $result['RentalCompany'] = $this->http->FindSingleNode('//text()[contains(.,"Your Selected Car") or contains(.,"Your selected car")]/ancestor::td[1]//img[contains(@src,"logo-")]/@alt', null, true, '/^(.+) logo$/i');
        $partNames = [
            'Pickup'  => 'Pick It Up',
            'Dropoff' => 'Drop It Off',
        ];

        if ($this->http->XPath->query('//text()[contains(.,"' . $partNames['Pickup'] . '") or contains(.,"' . $partNames['Dropoff'] . '")]/ancestor::td[1]')->length === 0) {
            $partNames = [
                'Pickup'  => 'Pick it up',
                'Dropoff' => 'Drop it off',
            ];
        }

        foreach ($partNames as $prefix => $text) {
            $this->logger->notice('//text()[contains(., "' . $text . '")]/ancestor::td[1]');
            $part = $this->http->XPath->query('//text()[contains(., "' . $text . '")]/ancestor::td[1]');

            if (0 === $part->length) {
                continue;
            } else {
                $part = $part->item(0);
            }

            if ($date = $this->http->FindSingleNode('./descendant::img[contains(@src,"icon-clock-email")]/ancestor::tr[1]', $part)) {
                $result[$prefix . 'Datetime'] = strtotime(str_replace('@', '', $date));
                //it-155714862.eml
                if (empty($result[$prefix . 'Datetime']) && $this->http->XPath->query("//text()[normalize-space()='Pick it up']")->length > 0) {
                    $this->lang = 'pt';
                    $result[$prefix . 'Datetime'] = $this->normalizeDate(str_replace('@', '', $date), $this->lang);
                }
            } elseif ($date = $this->http->FindSingleNode('./descendant::text()[starts-with(normalize-space(), "' . $text . '")]/following::text()[contains(normalize-space(), "@")][1]', $part)) {
                $result[$prefix . 'Datetime'] = strtotime(str_replace('@', '', $date));
            }
            $result[$prefix . 'Location'] = $this->http->FindSingleNode('./descendant::img[contains(@src,"icon-location-email")]/ancestor::tr[1]', $part);
            $strings = array_filter($this->http->FindNodes('.//text()[normalize-space(.)!=""]', $part));
            $text = implode(', ', $strings);

            if (($pos = strpos($text, 'Hours:')) !== false) {
                if (preg_match('/Hours:[,\s]* (.+)/', $text, $m)) {
                    $result[$prefix . 'Hours'] = trim($m[1]);
                }
                $text = substr($text, 0, $pos);
            }

            if (($pos = strpos($text, 'Phone:')) !== false) {
                if (preg_match('/Phone:[,\s]* ([-\d]+)/', $text, $m)) {
                    $result[$prefix . 'Phone'] = $m[1];
                }
                $text = substr($text, 0, $pos);
            }

            if (($pos = stripos($text, 'Location Details')) !== false) {
                if (preg_match('/Location Details[,\s]* (.+)/i', $text, $m)) {
                    $result[$prefix . 'Location'] .= ', ' . trim($m[1], ', ');
                }
            }
        }
        $carInfo = array_values(array_filter($this->http->FindNodes('//*[contains(text(),"Your Selected Car") or contains(text(),"Your selected car")]/parent::*//text()')));

        if (count($carInfo) > 2 && ($carInfo[0] === 'Your Selected Car' || $carInfo[0] === 'Your selected car')) {
            $result['CarType'] = $carInfo[1];
            $result['CarModel'] = $carInfo[2];
        } else {
            $strings = $this->http->FindNodes('//*[contains(text(),"Your Selected Car") or contains(text(),"Your selected car")]/ancestor::td[1]//text()[string-length(normalize-space(.))>1]');
            $text = implode("\n", $strings);

            if (preg_match("#Your selected car\n(.+)\n(.+)\nAdd-ons#i", $text, $carInfo)) {
                $result['CarType'] = $carInfo[1];
                $result['CarModel'] = $carInfo[2];
            }
        }
        $total = $this->http->FindSingleNode('//td[contains(.,"Total due at rental counter") and not(.//td)]/following-sibling::td[1]');

        if ($total && preg_match('/([.\d]+) ([A-Z]{1,3})/', $total, $m)) {
            $result['TotalCharge'] = $m[1];
            $result['Currency'] = $m[2];
        }
        $row = $this->http->XPath->query('//tr[contains(normalize-space(.),"Estimated taxes and fees") and not(.//tr)]');

        if ($row->length === 1) {
            $row = $row->item(0);
            $result['TotalTaxAmount'] = $this->http->FindSingleNode('./td[2]', $row, true, '/[.\d]+/');
            $result['BaseFare'] = $this->http->FindSingleNode('./preceding-sibling::tr[contains(., \'USD/day\')]/td[2]', $row, true, '/[.\d]+/');
        }
        $result['RenterName'] = $this->http->FindSingleNode('//td[(contains(.,"Driver\'s Name:") or contains(.,"Driver\'s name:")) and not(.//td)]/following-sibling::td[1]');

        return [
            'Itineraries' => [$result],
        ];
    }

    public function ParseAAdvantage()
    {
        $its = [];
        $total = null;

        $travelerInfo = array_filter($this->http->FindNodes('//*[contains(text(), "Lead Traveler")]/parent::*//text()'));

        if ('Lead Traveler' === array_shift($travelerInfo)) {
            $name = array_shift($travelerInfo);
        }

        if ($bookingDate = $this->http->FindSingleNode('//text()[contains(., "Booking Date")]', null, true, '/Booking Date ([\d\/]+)/')) {
            $bookingDate = strtotime($bookingDate);

            if ($bookingDate === false || $bookingDate < strtotime('1990-01-01')) {
                unset($bookingDate);
            }
        }

        $rentalRoots = $this->http->XPath->query('//tr[contains(., "Car") and not(.//tr) and following-sibling::tr[contains(., "Confirmation:")]]/parent::*');
        /* @var \DOMNode $root */
        foreach ($rentalRoots as $root) {
            $it = ['Kind' => 'L'];
            $it['Number'] = $this->http->FindSingleNode('tr[contains(., "Confirmation:")]', $root, true, '/Confirmation: ([A-Z\d]+)/');

            if (isset($bookingDate)) {
                $it['ReservationDate'] = $bookingDate;
            }
            $it['RentalCompany'] = $this->http->FindSingleNode('.//img[contains(@src, "logo")]/@alt', $root);
            $it['CarType'] = $this->http->FindSingleNode('.//tr[contains(., "Car type:") and not(.//tr)]', $root, true, '/Car type: (.+)/');

            if ($datetime = $this->http->FindSingleNode('.//td[contains(., "Pickup:") and not(.//td)]/following-sibling::td', $root)) {
                $it['PickupDatetime'] = strtotime($datetime);
            }

            if ($datetime = $this->http->FindSingleNode('.//td[contains(., "Drop-Off:") and not(.//td)]/following-sibling::td', $root)) {
                $it['DropoffDatetime'] = strtotime($datetime);
            }
            $it['PickupLocation'] = $this->http->FindSingleNode('.//td[contains(., "Pickup location:") and not(.//td)]/following-sibling::td', $root);
            $it['DropoffLocation'] = $this->http->FindSingleNode('.//td[contains(., "Drop-Off location:") and not(.//td)]/following-sibling::td', $root);

            if (null === $it['DropoffLocation']) {
                $it['DropoffLocation'] = $it['PickupLocation'];
            }

            if (!empty($name)) {
                $it['RenterName'] = $name;
            }
            $its[] = $it;
        }

        $hotelRoots = $this->http->XPath->query('//tr[contains(., "Room") and not(.//tr) and following-sibling::tr[contains(., "Confirmation:")]]/parent::*');
        /* @var \DOMNode $root */
        foreach ($hotelRoots as $root) {
            $it = ['Kind' => 'R'];
            $it['ConfirmationNumber'] = $this->http->FindSingleNode('tr[contains(., "Confirmation:")]', $root, true, '/Confirmation: ([A-Z\d]+)/');

            if (isset($bookingDate)) {
                $it['ReservationDate'] = $bookingDate;
            }

            if ($datetime = $this->http->FindSingleNode('.//td[contains(., "Check-In:") and not(.//td)]/following-sibling::td', $root)) {
                $it['CheckInDate'] = strtotime($datetime);
            }

            if ($datetime = $this->http->FindSingleNode('.//td[contains(., "Check-Out:") and not(.//td)]/following-sibling::td', $root)) {
                $it['CheckOutDate'] = strtotime($datetime);
            }
            $it['Guests'] = $this->http->FindSingleNode('.//td[contains(., "Occupants:") and not(.//td)]/following-sibling::td', $root, true, '/(\d+) Adults?/');
            $rows = $this->http->XPath->query('//tr[.//img[contains(@src, "star")] and not(.//tr)]/parent::*');

            if (1 === $rows->length) {
                $rows = $rows->item(0);
                $it['HotelName'] = $this->http->FindSingleNode('tr[1]', $rows);
                $it['Address'] = $this->http->FindSingleNode('tr[.//img[contains(@src, "star")]]/following-sibling::tr[1]', $rows);
                $it['RoomType'] = $this->http->FindSingleNode('tr[contains(., "Room description")]', $rows, true, '/Room description:? (.+)/');
                $it['RoomTypeDescription'] = $this->http->FindSingleNode('tr[contains(., "Room type:")]', $rows, true, '/Room type: (.+)/');
            }

            if (!empty($name)) {
                $it['GuestNames'] = [$name];
            }
            $its[] = $it;
        }

        $spend = $this->http->FindSingleNode('//td[contains(., "Payments Received") and not(.//td)]/following-sibling::td[1]', null, true, '/([\d\,]+) AAdvantage Miles/');

        if ($spend) {
            if (count($its) > 1) {
                $total = ['SpentAwards' => str_replace(',', '', $spend)];
            } else {
                $its[0]['SpentAwards'] = str_replace(',', '', $spend);
            }
        }

        return [
            'Itineraries' => $its,
            'TotalCharge' => $total,
        ];
    }

    public function ParseTripDetailsEs()
    {
        $result = ["Kind" => "T"];
        $result["RecordLocator"] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'Código de reserva de American Airline')]/following::text()[string-length(normalize-space(.))>2][1]", null, true, "#[A-Z\d]{5,}#");
        $result['Passengers'] = array_unique($this->http->FindNodes("//text()[contains(normalize-space(.),'Passenger')]/ancestor::table[1]//td[1]"));

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $fullEmail = $parser->getHTMLBody() ? $parser->getHTMLBody() : $parser->getPlainBody();
        $NBSP = chr(194) . chr(160);
        $fullEmail = str_replace($NBSP, ' ', html_entity_decode($fullEmail));
        $this->http->SetBody($fullEmail);
        $emailType = $this->getEmailType($parser);
        $this->logger->debug('Email type: ' . $emailType);

        switch ($emailType) {
            case 'eTicket':
                $result = $this->ParseETicket();

                break;

            case 'eTicketPt':
                $result = $this->ParseETicketPt();

                break;

            case 'eTicketEs':
                $result = $this->ParseETicketEs();

                break;

            case 'eTicketFr':
                $result = $this->ParseETicketFr();

                break;

            case 'eTicketDe':
                $result = $this->ParseETicketDe();

                break;

            case 'tripDetailsEs':
                $result = $this->ParseTripDetailsEs();

                break;

            case 'AaItinerary':
                $result = $this->ParseAaItinerary();

                break;

            case 'AaItineraryNl':
                $result = $this->ParseAaItineraryNl();

                break;

            case 'AaItineraryPt':
                $result = $this->ParseAaItineraryPt();

                break;

            case 'AaItineraryIt':
                $result = $this->ParseAaItineraryIt();

                break;

            case 'AaItineraryDe':
                $result = $this->ParseAaItineraryDe();

                break;

            case 'Sched':
                $result = $this->ParseSchedEmail();

                break;

            case 'AARental':
                $result = $this->ParseAARental();

                break;

            case 'AAdvantage':
                $result = $this->ParseAAdvantage();

                break;

            case 'TripConfirmation2017':
                $result = $this->TripConfirmation2017();

                break;

            case 'AAGuestTravel':
                $result = $this->AAGuestTravel();

                break;

            case 'ChangeToFlight':
                $result = $this->ChangeToFlight();

                break;

            case 'CheckIn':
                $result = $this->CheckIn();

                break;

            case 'PlainItinerary':
                $result = $this->PlainItinerary();

                break;

            case 'BoardingPassPDF':
                $result = $this->BoardingPassPDF($parser);

                break;

            case 'TravelInformationPdf':
                $result = $this->TravelInformationPdf($parser);

                break;

            case 'MobileBoardingPassUrl':
                $result = $this->MobileBoardingPassUrl();

                break;

            case 'TripConfirmation2017Pdf':
                $result = $this->TripConfirmation2017Pdf($parser);

                break;

            case 'PlanTravelPdf':
                $result = $this->PlanTravelPdf($parser);

                break;

            case 'PrintTripAndReceiptPdf':
                $result = $this->PrintTripAndReceiptPdf($parser);

                break;

            case 'BellflightPlain':
                $result = $this->BellflightPlain();

                break;

            case 'Business':
                $result = $this->Business($parser);

                break;

            case 'Receipt':
                $result = $this->Receipt();

                break;

            case 'ChangeReservation':
                $result = $this->ChangeReservation();

                break;

            default:
                $this->logger->alert('Undefined email type!');

                return [];
        }

        return [
            'emailType'  => $emailType,
            'parsedData' => $result,
        ];
    }

    public function getEmailType(\PlancakeEmailParser $parser)
    {
        if (preg_match("/American Airlines flight \d+ to .+? \([A-Z\d]{5,}\)/", $parser->getSubject())
            && $this->http->XPath->query("//text()[starts-with(normalize-space(.),'American Airlines flight')]")->length > 0
            && $this->http->XPath->query("//a[normalize-space(.)='Check in online']")->length > 0
            && !empty($this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Confirmation code:')]", null, false, "#:\s+([A-Z\d]{5,})#"))
        ) {
            return 'BellflightPlain';
        }

        if ($this->http->XPath->query("//a[contains(@href, 'https://www.aa.com/checkin/viewMobileBoardingPass?')
                or contains(@href, '2F%2Fwww.aa.com%2Fcheckin%2FviewMobileBoardingPass')
                or contains(@href, '2f%2fwww.aa.com%2fcheckin%2fviewMobileBoardingPass')
                or contains(@href, '__www.aa.com_checkin_viewMobileBoardingPass')
                ]")->length > 0
        || !empty($this->http->FindPreg("#(https:\/\/www.aa.com\/reservation/mbp.do|https://www.aa.com/checkin/viewMobileBoardingPass)#"))) {
            return 'MobileBoardingPassUrl';
        }// this detect must be previous  detect 'PlainItinerary' and 'Business'

        if ($this->http->XPath->query('//text()[contains(normalize-space(), "Thank you for modifying your travel arrangements on AA.com.")]')->length > 0) {
            // it-59336894.eml, 'before AaItinerary'

            $body = preg_replace("#\xE2\x80\x8B#", "", $this->http->Response["body"]);
            $this->http->SetEmailBody($body);

            return 'ChangeReservation';
        }

        if ($this->http->XPath->query("//*[contains(text(),'Your Itinerary') or contains(normalize-space(.),'itinerary and receipt')] | //img[@alt='receipt']")->length > 0
                && $this->http->XPath->query('//text()[contains(.,"Record Locator:")]')->length > 0) {
            return 'AaItinerary';
        }

        if ($this->http->XPath->query("//*[contains(normalize-space(.),'uw reisschema en betalingsbewijs')] | //img[@alt='receipt']")->length > 0
            && $this->http->XPath->query('//text()[contains(.,"Record-locator:")]')->length > 0) {
            return 'AaItineraryNl';
        }

        if ($this->http->FindSingleNode("(//*[contains(text(),'Seu Itinerário')])[1]")) {
            return 'AaItineraryPt';
        }

        if ($this->http->XPath->query("//*[contains(text(),'il tuo itinerario e la ricevuta')] | //img[@alt='receipt']")->length > 0
                && $this->http->XPath->query('//text()[contains(.,"Codice prenotazione:")]')->length > 0) {
            return 'AaItineraryIt';
        }

        if ($this->http->XPath->query("//*[contains(text(),'Ihre Flugroute und Ihren Beleg')] | //img[@alt='receipt']")->length > 0
                && $this->http->XPath->query('//text()[contains(.,"Buchungsreferenz:")]')->length > 0) {
            return 'AaItineraryDe';
        }

        if ($this->http->XPath->query('//text()[contains(normalize-space(.),"Your trip confirmation and receipt")] | //text()[contains(normalize-space(.),"Your trip summary")] | //text()[contains(normalize-space(.),"Here\'s the American Airlines trip you booked")] | //text()[contains(normalize-space(.),"Seu voo foi alterado")] | //text()[contains(normalize-space(.),"Su vuelo ha cambiado")]')->length > 0) {
            return 'TripConfirmation2017';
        }

        if (strpos($this->http->Response['body'], 'Your trip confirmation and receipt') !== false) {
            return 'TripConfirmation2017';
        }

        if ($this->http->FindSingleNode("(//tr/td[2][normalize-space(.) = 'Compañía transportadora'][./following-sibling::td[4][normalize-space() = 'Código de tarifa']])[1]")
        || $this->http->XPath->query("//text()[contains(normalize-space(), 'Salida')]/ancestor::td[1]/following::td[1][contains(normalize-space(), 'Llegada')]")->length > 0) {
            return 'eTicketEs';
        }

        if ($this->http->XPath->query('//tr[./td[contains(.,"Passenger") and not(.//td)] and ./td[(contains(.,"TICKET #") or contains(.,"Ticket #")) and not(.//td)]]')->length > 0
                || $this->http->XPath->query('//td[starts-with(normalize-space(.),"Traveling on this trip")]')->length > 0
                || $this->http->XPath->query("//node()[contains(., 'Please print and retain this document for use throughout your trip')]")->length > 0) {
            return 'eTicket';
        }

        if (
            $this->http->XPath->query("//text()[{$this->contains(['sobre restrições de bagagem de mão para viagens internacionais com a American Airlines', 'sobre restriÃ§Ãµes de bagagem de mÃ£o para viagens internacionais com a American Airlines', 'Obrigado por escolher a American Airlines / American Eagle, membros da Aliança'])}]")->length > 0
            || $this->http->XPath->query("//tr[*[normalize-space()][1][normalize-space()='Transportadora'] and *[normalize-space()][2][normalize-space()='Voo №' or normalize-space()='Voo â'] and *[normalize-space()][3][normalize-space()='Partindo']]")->length > 0
        ) {
            return 'eTicketPt';
        }

        if ($this->http->FindSingleNode("(//text()[contains(normalize-space(.),'Gracias por elegir American Airlines')])[1]")) {
            return 'eTicketEs';
        }

        if ($this->http->FindSingleNode('(//text()[contains(normalize-space(.),"Merci d\'avoir choisi American Airlines")])[1]')) {
            return 'eTicketFr';
        }

        if ($this->http->FindSingleNode('(//text()[contains(normalize-space(.),"Wir bedanken uns für Ihre Buchung bei American Airlines")])[1]')) {
            return 'eTicketDe';
        }

        if ($this->http->FindSingleNode("//text()[contains(normalize-space(.),'Passenger')]") && $this->http->FindSingleNode("//text()[contains(.,'Código de reserva de American Airlines')]")) {
            return 'tripDetailsEs';
        }

        if ($this->http->XPath->query("//strong[contains(text(),'schedule change')] | //b[contains(text(),'schedule change')]")->length > 0) {
            return 'Sched';
        }

        if ($this->http->XPath->query('//text()[contains(.,"Driver Information") or contains(.,"Driver information")]')->length > 0) {
            return 'AARental';
        }

        if (strpos($this->http->Response['body'], 'Thank you for redeeming your miles with AAdvantage') !== false) {
            return 'AAdvantage';
        }

        if ($this->http->XPath->query('//a[contains(@href,"American_Airlines_Guest_Travel@aa.com") and contains(text(),"American_Airlines_Guest_Travel@aa.com")]')->length > 0) {
            return 'AAGuestTravel';
        }

        if ($this->http->XPath->query('//text()[starts-with(normalize-space(.),"We made a change to your flight, and this is your updated itinerary") or starts-with(normalize-space(.),"We made these changes to your flight.") or contains(normalize-space(.),"Here is a summary of your trip.")]')->length > 0) {
            return 'ChangeToFlight';
        }

        if ($this->http->XPath->query('//text()[normalize-space(.)="Your travel receipt"]/following::text()[string-length(normalize-space())>3][1][starts-with(normalize-space(),"Record locator:")]/following::text()[normalize-space(.)="Your trip receipt"]')->length > 0) {
            return 'Receipt';
        }

        if ($this->http->XPath->query('//img[contains(@src, "/marketingOneOff/PDP/desktopCTA.png")]')->length > 0
            || $this->http->XPath->query('//img[contains(@src, "/wpm/997/Images/B6-CheckIn.jpg")]')->length > 0
            || stripos($parser->getSubject(), 'Check in for your flight') !== false
            || stripos($parser->getSubject(), 'Your complimentary upgrade is confirmed') !== false
        ) {
            // it-8030190.eml, it-322295426.eml
            return 'CheckIn';
        }

        if ($this->http->XPath->query("//node()[contains(., 'www.aa.com/checkin')]")->length > 0) {
            return 'PlainItinerary';
        }

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(.),'You must print all attached boarding pass')]")->length > 0) {
            $pdf = $parser->searchAttachmentByName('[A-Z\d]+\.pdf');

            if (isset($pdf[0])) {
                return 'BoardingPassPDF';
            }// this detect must be previous  detect 'Business'
        }

        if ($this->http->XPath->query("//text()[contains(normalize-space(),'Thank you for using American Airlines for Business')]")->length > 0
            || $this->http->XPath->query("//a[normalize-space() =  'my trips' and contains(@href, 'business.aa.com')]")->length > 0
        ) {
            return 'Business';
        }
        $body = preg_replace("#\xE2\x80\x8B#", "", $this->http->Response["body"]);
        $this->http->SetEmailBody($body);

        if ($this->http->XPath->query("//text()[contains(normalize-space(),'Thank you for using American Airlines for Business')]")->length > 0
            || $this->http->XPath->query("//a[normalize-space() =  'my trips' and contains(@href, 'business.aa.com')]")->length > 0
        ) {
            return 'Business';
        }

        $pdf = $parser->searchAttachmentByName('.*-.*\.pdf');

        if (count($pdf) > 0) {
            $pdfBody = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (strpos($pdfBody, 'AAdvantage') !== false && strpos($pdfBody, 'Travel Information') !== false) {
                return 'TravelInformationPdf';
            }
        }

        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $pdfBody = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($pdfBody, 'American Airlines') !== false && strpos($pdfBody, 'Your trip confirmation and receipt') !== false) {
                return 'TripConfirmation2017Pdf';
            }

            if (strpos($pdfBody, 'AAdvantage') !== false && strpos($pdfBody, 'Plan Travel') !== false && strpos($pdfBody, 'Helpful links') !== false) {
                return 'PlanTravelPdf';
            }

            if (strpos($pdfBody, 'AA RECORD LOCATOR') !== false && strpos($pdfBody, 'Receipt') !== false) {
                return 'PrintTripAndReceiptPdf';
            }
        }

        $pdf = $parser->searchAttachmentByName('.*[A-Z\d]{5,}\.pdf');

        if (count($pdf) > 0) {
            $pdfBody = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (strpos($pdfBody, 'AA') !== false) {
                return 'BoardingPassPDF';
            }
        }

        return 'Undefined';
    }

    protected function TripConfirmation2017()
    {
        // check parser ConfirmOrChangeFlight
        $NBSP = chr(194) . chr(160);
        $this->http->SetEmailBody(str_replace($NBSP, ' ', html_entity_decode($this->http->Response['body'])));

        $it = ['Kind' => 'T'];
        $it['RecordLocator'] = $this->http->FindSingleNode("descendant::tr[{$this->starts(['Record locator:', 'Record Locator:'])}][1]", null, true, '/:\s*([A-Z\d]{5,6})$/');

        if (!empty($it['RecordLocator'])) {
            $lang = 'en';
        } else {
            $it['RecordLocator'] = $this->http->FindSingleNode('//*[' . $this->contains(['Código de reserva:'], 'text()') . ']', null, true, '#:\s*([A-Z\d]{5,6})#');

            if (empty($it['RecordLocator'])) {
                $it['RecordLocator'] = $this->http->FindSingleNode('//text()[' . $this->eq(['Código de reserva:']) . ']/following::text()[string-length(normalize-space())>1][1]', null, true, '#^\s*([A-Z\d]{5,6})\s*$#');
            }

            if (!empty($it['RecordLocator'])) {
                $lang = 'pt';
            } else {
                $it['RecordLocator'] = $this->http->FindSingleNode('//*[' . $this->contains(['Código de reservación:'], 'text()') . ']', null, true, '#:\s*([A-Z\d]{5,6})#');

                if (empty($it['RecordLocator'])) {
                    $it['RecordLocator'] = $this->http->FindSingleNode('//text()[' . $this->eq(['Código de reservación:']) . ']/following::text()[string-length(normalize-space())>1][1]', null, true, '#^\s*([A-Z\d]{5,6})\s*$#');
                }

                if (!empty($it['RecordLocator'])) {
                    $lang = 'es';
                } else {
                    $it['RecordLocator'] = CONFNO_UNKNOWN;
                }
            }
        }
        $it['Passengers'] = $this->http->FindNodes('//*[' . $this->contains(['Ticket #', 'Nº do bilhete', 'N° del boleto'], 'text()') . ']/ancestor-or-self::td[./preceding-sibling::td][1]/preceding-sibling::td[not(contains(.,\'AAdvantage #\'))][1]');

        if (empty($it['Passengers'])) {
            $this->logger->debug('Passengers 0');
            $it['Passengers'] = $this->http->FindNodes('//*[' . $this->contains(['Nº do bilhete', 'N° del boleto'], 'text()') . ']/ancestor-or-self::*[contains(.,"AAdvantage ")][1]/preceding::td[normalize-space()][1]');
        }

        if (empty($it['Passengers'])) {
            $this->logger->warning('Passengers 1');
            $it['Passengers'] = array_filter($this->http->FindNodes("//*[{$this->contains(['Ticket #', 'Nº do bilhete', 'N° del boleto'], 'text()')}]/ancestor-or-self::*[preceding-sibling::*[not(contains(.,'AAdvantage #'))]][1]/preceding-sibling::*[1]", null, "/^{$this->patterns['travellerName']}$/"));
        }

        if (empty($it['Passengers'])) {
            $this->logger->warning('Passengers 2');
            $it['Passengers'] = array_filter($this->http->FindNodes("//tr[{$this->starts(['Ticket #', 'Nº do bilhete', 'N° del boleto'])}]/ancestor::table[1]/preceding-sibling::*[normalize-space()][1]/descendant::tr[normalize-space()][1]", null, "/^{$this->patterns['travellerName']}$/"));
        }

        if (empty($it['Passengers'])) {
            $this->logger->warning('Passengers 3');
            $it['Passengers'] = $this->http->FindNodes('//*[' . $this->contains(['Ticket #', 'Nº do bilhete', 'N° del boleto'], 'text()') . ']/ancestor-or-self::td[2]/preceding-sibling::td[1]');
        }

        if (empty($it['Passengers'])) {
            $this->logger->debug('Passengers 4');
            $it['Passengers'] = $this->http->FindNodes('//*[' . $this->contains(['Nº do bilhete', 'N° del boleto'], 'text()') . ']/ancestor-or-self::td[3]/preceding-sibling::td[1]');
        }

        if (empty($it['Passengers'])) {
            $this->logger->debug('Passengers 5');
            $it['Passengers'] = $this->http->FindSingleNode('//text()[contains(normalize-space(.),"Hello ") and contains(.,"!")]', null, true, "#Hello\s+(.+)!#");
        }

        $it['AccountNumbers'] = $this->http->FindNodes('//text()[contains(normalize-space(),"AAdvantage #")]/ancestor::*[not(self::span)][1]', null, "/AAdvantage\s#[:\s]*([\dA-Z]+(?:\s*[A-Z\d]+)?)/");
        $it['TicketNumbers'] = $this->http->FindNodes("//text()[{$this->contains(['Ticket #', 'Nº do bilhete', 'N° del boleto:', 'N° del boleto'])}]/ancestor::*[not(self::span)][1]", null,
            "#(?:Ticket\s\#|Nº do bilhetes?|N° del boleto:s?|N° del boleto)[:\s]*([\d]+)#");
        $totals = $this->http->FindNodes('//text()[' . $this->eq(['TICKET TOTAL', 'TOTAL']) . ']/ancestor::tr[1]');
        $sum = 0.0;

        foreach ($totals as $total) {
            if (preg_match("#(?:TICKET\s+)?TOTAL\s*(.+)#", $total, $m)) {
                $sum += $this->amount($m[1]);
                $cur = $this->currency($m[1]);
            }
        }

        if (!empty($sum) && !empty($cur)) {
            $it['TotalCharge'] = $sum;
            $it['Currency'] = $cur;
        }

        $changedRule = "[not(preceding::text()[" . $this->eq(['ORIGINAL FLIGHT', 'VUELO ORIGINAL', 'VOO ORIGINAL']) . "])]";
        $xpath = "//img[contains(@src, 'Icon-Arrow.png')]/ancestor::td[./following-sibling::td and count(./../td)=2][1]/parent::*" . $changedRule;
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length == 0) {
            $xpath = "//img[contains(@src, 'Icon-Arrow.png')]/ancestor::*[normalize-space()][count(./../*[normalize-space()])=2][1]/parent::*" . $changedRule;
            $segments = $this->http->XPath->query($xpath);
        }

        if ($segments->length == 0) {
            $xpath = "//text()[{$this->contains(['Seats', 'Assento', 'Asiento'])}]/ancestor::td[./preceding-sibling::td[contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')]][1]/parent::*" . $changedRule;
            $segments = $this->http->XPath->query($xpath);
        }
        $this->logger->debug($xpath);

        if ($segments->length > 0) {
            foreach ($segments as $root) {
                $seg = [];
                $date = $this->http->FindSingleNode("./preceding::text()[normalize-space()][string-length(normalize-space(.))>5][1][contains(translate(normalize-space(.),'0123456789','dddddddddd'),'dddd')]", $root);

                if (isset($lang) && ($lang == 'pt' || $lang == 'es') && preg_match("#(\d+) de ([^\d\s]+) de (\d{4})#u", $date, $m)) {
                    if ($en = MonthTranslate::translate($m[2], $lang)) {
                        $date = $m[1] . ' ' . $en . ' ' . $m[3];
                    }
                }
                $date = strtotime($date);

                if (!$date && isset($datePrev)) {
                    $date = $datePrev;
                } else {
                    $datePrev = $date;
                }

                if (!$codes = $this->http->FindSingleNode(".//img[contains(@src, 'Icon-Arrow.png')]/ancestor::tr[1]/ancestor::*[1]/tr[1]", $root)) {
                    $codes = $this->http->FindSingleNode(".//text()[contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')][1]/ancestor::tr[1]/ancestor::*[1]/tr[1]", $root);
                }

                if (preg_match("#^\s*([A-Z]{3}).*([A-Z]{3})\s*$#", $codes, $m)) {
                    $seg['DepCode'] = $m[1];
                    $seg['ArrCode'] = $m[2];
                } else {
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }

                if (!$times = $this->http->FindSingleNode(".//img[contains(@src, 'Icon-Arrow.png')]/ancestor::tr[1]/ancestor::*[1]/tr[contains(translate(normalize-space(.),'0123456789','dddddddddd'),'d:dd')][1]", $root)) {
                    $times = $this->http->FindSingleNode(".//text()[contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')][1]/ancestor::tr[1]/ancestor::*[1]/tr[contains(translate(normalize-space(.),'0123456789','dddddddddd'),'d:dd')][1]", $root);
                }
                $times = str_replace([' N', ' M'], ['PM', 'AM'], $times);

                if (preg_match("#(\d+:\d+(?:\s*[APMN]{1,2})?)\s+(\d+:\d+(?:\s*[APMN]{1,2})?)#", $times, $m)) { //12:00 N
                    $seg['DepDate'] = strtotime($m[1], $date);
                    $seg['ArrDate'] = strtotime($m[2], $date);

                    if (($seg['ArrDate'] - $seg['DepDate']) < 0) {
                        $seg['ArrDate'] = strtotime('+1 day', strtotime($m[2], $date));
                    }
                } elseif (preg_match("#^(\d+:\d+(?:\s*[APMN]{1,2})?)$#", $times, $m)) {
                    $seg['DepDate'] = strtotime($m[1], $date);
                    $seg['ArrDate'] = MISSING_DATE;
                }
                $seg['DepName'] = $this->http->FindSingleNode("(./*[1]//tr[contains(.,':')]/following::tr[normalize-space()][1][count(.//text()[string-length(normalize-space())>2]) =2]//text()[string-length(normalize-space())>2])[1]", $root);
                $seg['ArrName'] = $this->http->FindSingleNode("(./*[1]//tr[contains(.,':')]/following::tr[normalize-space()][1][count(.//text()[string-length(normalize-space())>2]) =2]//text()[string-length(normalize-space())>2])[2]", $root);

                $flightInfo = implode("\n", $this->http->FindNodes("./*[1]/descendant::tr[1]/ancestor::*[1]/tr", $root));

                if (
                    preg_match("/{$this->patterns['time']}[\s\S]+?\n[ ]*(\S.*?\S)[ ]*(\d{1,5})\b[ ]*(.+?(?:$| • |\n))?/", $flightInfo, $m)
                    || preg_match("/{$this->patterns['time']}[\s\S]+?\n[ ]*(\S.*?\S)[ ]*(\d{1,5})\s*Operated by/i", $flightInfo, $m)
                ) {
                    /*
                        1:25 PM 3:10 PM
                        Montreal New York
                        B6 1461 Embraer ERJ-140
                    */
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];

                    if (!empty($m[3]) && !preg_match("#(?:Operated by|Flight arrives)#i", $m[3])) {
                        $seg['Aircraft'] = trim($m[3], " \t\n\r\0\x0B•");
                    }
                } elseif (stripos($flightInfo, 'American Airlines') == false) {
                    $flightInfo = implode("\n", $this->http->FindNodes("./*[1]/descendant::tr[1]/ancestor::*[1]/tr[1]/following::text()[contains(normalize-space(), 'American Airlines')][1]", $root));

                    if (preg_match('/^\s*([^\d\s]\D*[^\d\s])\s*(\d{2,4})$/', $flightInfo, $m)) {
                        $seg['AirlineName'] = trim($m[1]);
                        $seg['FlightNumber'] = $m[2];
                    }
                }

                if (preg_match("#Operated by[ ]*(.+?)(?:/|$|\n|\.)#i", $flightInfo, $m)) {
                    $seg['Operator'] = trim($m[1]);
                }

                $Seats = $this->http->FindSingleNode(".//*[contains(text(), 'Seat')]/following::*[normalize-space()][1]", $root, true, "#.*\d{1,3}[A-Z].*#");

                if (empty($Seats)) {
                    $seatsText = $this->http->FindSingleNode(".//*[contains(text(), 'Seat')]/following::*[normalize-space()][1]", $root);

                    if (!preg_match("/\-\-/us", $seatsText)) {
                        $Seats = $this->http->FindSingleNode("./following::text()[contains(normalize-space(),'Seat')][1]/following::text()[normalize-space()][1]", $root, true, "#.*\d{1,3}[A-Z].*#");
                    }
                }

                if (!empty($Seats)) {
                    $Seats = explode(",", $Seats);
                    $seg['Seats'] = array_filter(array_map(function ($v) {
                        if (preg_match("#\b(\d{1,3}[A-Z])\b#", $v, $m)) {
                            return $m[1];
                        }

                        return [];
                    }, $Seats));
                } else {
                    $seg['Seats'] = array_filter($this->http->FindNodes(".//*[contains(text(), 'Assento') or contains(text(), 'Asiento')]", $root, "#(?:Assento|Asiento)\s+(\d{1,3}[A-Z])\b#"));

                    if (empty($seg['Seats'])) {
                        $seg['Seats'] = array_filter($this->http->FindNodes(".//text()[normalize-space() = 'Assento' or normalize-space() = 'Asiento']/following::text()[normalize-space()][1]", $root, "#^\s*(\d{1,3}[A-Z])\b#"));
                    }

                    if (empty($seg['Seats'])) {
                        $seg['Seats'] = array_filter($this->http->FindNodes("./ancestor::table[1]/following-sibling::table[1][contains(.,'Seat')]/descendant::text()[normalize-space()='Seat:']/following::text()[normalize-space()!=''][1]", $root, "#^\s*(\d{1,3}[A-Z])$#"));
                    }
                }
                $class = $this->http->FindSingleNode(".//*[contains(text(), 'Class') or contains(text(), 'Classe') or contains(text(), 'Clase:')]", $root, true, "#:\s*(\S.*)#");

                if (empty($class)) {
                    $class = $this->http->FindSingleNode(".//*[contains(text(), 'Class') or contains(text(), 'Classe') or contains(text(), 'Clase:')]/ancestor::td[1]", $root, true, "#(?:Class|Classe|Clase)\s*:\s*(\S.*)#");
                }

                if (empty($class)) {
                    $class = $this->http->FindSingleNode(".//*[contains(text(), 'Class') or contains(text(), 'Classe') or contains(text(), 'Clase:')]/following::*[1]", $root);
                }

                if (empty($class)) {
                    $class = $this->http->FindSingleNode("./ancestor::table[1]/following-sibling::table[1][contains(.,'Seat')]/descendant::text()[normalize-space()='Class:']//ancestor::tr[not(normalize-space()='Class:')][1]", $root, false, "/Class:\s*(.+)/");
                }

                if (preg_match("#^(.+)\s*\(([A-Z]{1,2})\)#", $class, $m)) {
                    $seg['Cabin'] = trim($m[1]);
                    $seg['BookingClass'] = $m[2];
                } elseif (preg_match("#^\(([A-Z]{1,2})\)#", $class, $m)) {
                    $seg['BookingClass'] = $m[1];
                }
                $seg['Meal'] = $this->http->FindSingleNode(".//*[contains(text(), 'Meal') or contains(text(), 'Refeições') or contains(text(), 'Comidas')]", $root, true, "#:\s*(\S.*)#");

                if (empty($seg['Meal'])) {
                    $seg['Meal'] = $this->http->FindSingleNode(".//*[contains(text(), 'Meal') or contains(text(), 'Refeições') or contains(text(), 'Comidas')]/following::*[1]", $root);
                }

                if (empty($seg['Meal'])) {
                    $seg['Meal'] = $this->http->FindSingleNode("./ancestor::table[1]/following-sibling::table[1][contains(.,'Seat')]/descendant::text()[normalize-space()='Meals:']//ancestor::tr[not(normalize-space()='Meals:')][1]", $root, false, "/Meals:\s*(.+)/");
                }
                $it['TripSegments'][] = $seg;
            }
        } else {
            $xpath = "//img[contains(@src, 'grey_arrow.png')]/ancestor::table[contains(.,':')][1]";
            $segments = $this->http->XPath->query($xpath);

            foreach ($segments as $root) {
                $seg = [];
                $date = strtotime($this->http->FindSingleNode("./preceding::text()[string-length(normalize-space(.))>1][1]", $root));
                $codes = $this->http->FindSingleNode(".//img[contains(@src, 'grey_arrow.png')]/ancestor::tr[1]", $root);

                if (preg_match("#^\s*([A-Z]{3}).*([A-Z]{3})\s*$#", $codes, $m)) {
                    $seg['DepCode'] = $m[1];
                    $seg['ArrCode'] = $m[2];
                } else {
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                }
                $times = $this->http->FindSingleNode(".//img[contains(@src, 'grey_arrow.png')]/ancestor::tr[1]/following-sibling::tr[1]", $root);
                $times = str_replace([' N', ' M'], ['PM', 'AM'], $times);

                if (preg_match("#(\d+:\d+(?:\s*[APMN]{1,2}))\s+(\d+:\d+(?:\s*[APMN]{1,2}))#", $times, $m)) { //12:00 N
                    $seg['DepDate'] = strtotime($m[1], $date);
                    $seg['ArrDate'] = strtotime($m[2], $date);
                }
                $seg['DepName'] = $this->http->FindSingleNode(".//img[contains(@src, 'grey_arrow.png')]/ancestor::tr[1]/following-sibling::tr[2]//td[string-length(normalize-space(.))>1][1]", $root);
                $seg['ArrName'] = $this->http->FindSingleNode(".//img[contains(@src, 'grey_arrow.png')]/ancestor::tr[1]/following-sibling::tr[2]//td[string-length(normalize-space(.))>1][last()]", $root);

                $flight = $this->http->FindSingleNode("./following::text()[string-length(normalize-space(.))>1][1]", $root);

                if (preg_match('/^(.{2,}?)\s+(\d{1,5})\b/', $flight, $m)) {
                    $seg['AirlineName'] = trim($m[1]);
                    $seg['FlightNumber'] = $m[2];
                }
                $it['TripSegments'][] = $seg;
            }
        }

        return ['Itineraries' => [$it]];
    }

    protected function AAGuestTravel()
    {
        $result = ["Kind" => "T"];
        $result["RecordLocator"] = $this->http->FindSingleNode('//text()[contains(normalize-space(.), "Record locator")]/ancestor::td[1]/following-sibling::td[1]');

        $result["Passengers"] = array_map('trim', $this->http->FindNodes('//text()[contains(normalize-space(.), "Travelers")]/ancestor::tr[1]/following-sibling::tr[1]/descendant::table//tr[normalize-space(.)]', null, '/\d+\.?\s*([\w\s]+)/'));

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    protected function ChangeToFlight()
    {
        $result = ["Kind" => "T"];
        $result["RecordLocator"] = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Record locator")]/following::text()[normalize-space(.)][1]', null, true, "#([A-Z\d]+)#");

        if (empty($result["RecordLocator"])) {
            $result["RecordLocator"] = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Record locator")]/following::text()[normalize-space(.)][2]', null, true, "#([A-Z\d]+)#");
        }
        $result["Passengers"] = array_map('trim', $this->http->FindNodes("//text()[starts-with(normalize-space(.),'Frequent flyer') or normalize-space(.)='Join AAdvantage »']/ancestor::table[1]//tr[count(descendant::tr)=0 and string-length(normalize-space(.))>3]/td[1][not(contains(.,'#') or contains(.,'»') or (contains(., 'Earn miles')))]"));

        if (empty($result['Passengers'])) {
            $result["Passengers"] = $this->http->FindNodes("//tr[(starts-with(normalize-space(.),'AAdvantage #') or starts-with(normalize-space(.),'Join AAdvantage »')) and ./following-sibling::tr[contains (normalize-space(),'Ticket #')]]/preceding::text()[normalize-space()!='' and not(contains(., 'Earn miles'))][1]/ancestor::td[1]", null, "#^\s*[\w\-]+\s*[\w\-]+\s*$#u");
        }

        if (empty($result['Passengers'])) {
            $result['Passengers'][] = $this->http->FindSingleNode("//td[starts-with(normalize-space(.), 'Hello')]", null, true, '/Hello\s+(.+)/');
        }
        $result['AccountNumbers'] = $this->http->FindNodes('//text()[contains(normalize-space(.),"AAdvantage #")]/ancestor::*[not(self::span)][1]', null, "#AAdvantage\s\#\s*([\dA-Z]+(?:\s*[A-Z\d]+)?)#");
        $result['TicketNumbers'] = $this->http->FindNodes('//text()[' . $this->contains(['Ticket #']) . ']/ancestor::*[not(self::span)][1]', null, "#(?:Ticket\s\#)\s*([\d]+)#");

        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";
        $xpath = "//text()[normalize-space()='UPDATED FLIGHT']/following::table[1][contains(.,':')]/descendant::text()[{$ruleTime}]/ancestor::table[2]";
        $segments = $this->http->XPath->query($xpath);
        $prevDate = null;

        foreach ($segments as $root) {
            $seg = [];
            $date = strtotime($this->http->FindSingleNode("./descendant::text()[string-length(normalize-space(.))>1][1]",
                $root, true, "/.*\d.*/"));

            if (empty($date)) {
                $date = strtotime($this->http->FindSingleNode("./preceding::text()[string-length(normalize-space(.))>1][1]",
                    $root, true, "/.*\d.*/"));
            }

            if (empty($date) && !empty($prevDate)) {
                $date = $prevDate;
            } else {
                $prevDate = $date;
            }

            $codes = $this->http->FindSingleNode(".//text()[{$ruleTime}][1]/ancestor::tr[1]/ancestor::*[1]/tr[1]",
                $root);

            if (preg_match("#^\s*([A-Z]{3}).*([A-Z]{3})\s*$#", $codes, $m)) {
                $seg['DepCode'] = $m[1];
                $seg['ArrCode'] = $m[2];
            } else {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }
            $times = $this->http->FindSingleNode(".//text()[{$ruleTime}][1]/ancestor::tr[1]", $root);
            $times = str_replace([' N', ' M'], ['PM', 'AM'], $times);

            if (preg_match("#(\d+:\d+(?:\s*[APMN]{1,2}))\s+(\d+:\d+(?:\s*[APMN]{1,2}))#", $times, $m)) { //12:00 N
                $seg['DepDate'] = strtotime($m[1], $date);
                $seg['ArrDate'] = strtotime($m[2], $date);
            }
            $seg['DepName'] = $this->http->FindSingleNode(".//text()[{$ruleTime}][1]/ancestor::tr[1]/following-sibling::tr[1]//td[string-length(normalize-space(.))>1][1]",
                $root);
            $seg['ArrName'] = $this->http->FindSingleNode(".//text()[{$ruleTime}][1]/ancestor::tr[1]/following-sibling::tr[1]//td[string-length(normalize-space(.))>1][last()]",
                $root);

            $flight = $this->http->FindSingleNode(".//text()[{$ruleTime}][1]/ancestor::tr[1]/following-sibling::tr[2]",
                $root);

            if (preg_match("#^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s+(\d{1,5})\s*(.*)#", $flight, $m)) {
                $seg['AirlineName'] = trim($m[1]);
                $seg['FlightNumber'] = $m[2];

                if (isset($m[3]) && !empty($m[3])) {
                    $seg['Aircraft'] = $m[3];
                }
            }
            $seats = array_filter($this->http->FindNodes(".//text()[contains(.,'Seat')]/ancestor::tr[1]", $root,
                "#Seat[:\s*](\d+[A-z])\b#"));

            if (!empty($seats)) {
                $seg['Seats'] = $seats;
            }
            $cabin = $this->http->FindSingleNode(".//text()[contains(.,'Class')]/ancestor::tr[1]", $root, false,
                "#Class[:\s*](.+)#");

            if (preg_match("#(.+?)(?:\s*\(([A-Z]{1,2})\)|$)#", $cabin, $m)) {
                $seg['Cabin'] = $m[1];

                if (isset($m[2]) && !empty($m[2])) {
                    $seg['BookingClass'] = $m[2];
                }
            }
            $result['TripSegments'][] = $seg;
        }

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    protected function PlainItinerary()
    {
        $result = ['Kind' => 'T'];
        $text = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.), "Record Locator:")]');

        if (preg_match("#Record Locator:\s+\b([A-Z\d]{5,8})\b#", $text, $m)) {
            $result["RecordLocator"] = $m[1];
        }

        if (preg_match_all('/Traveler Information:\s+([A-Z\s]+)/', $text, $travellerMatches)) {
            $result["Passengers"] = array_map("trim", $travellerMatches[1]);
        }

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    protected function BoardingPassPDF(\PlancakeEmailParser $parser)
    {
        $result = [];
        $pdfs = $parser->searchAttachmentByName('.*[A-Z\d]+\.pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $it = ['Kind' => 'T'];

            if (preg_match("#Record Locator:\s+\b([A-Z\d]{5,8})\b#", $text, $m)) {
                $it["RecordLocator"] = $m[1];
            }

            if (preg_match('/Seat.+\n(.+) \/ (.+)/', $text, $m)) {
                $it["Passengers"][] = trim($m[2]) . ' ' . trim($m[1]);
            } elseif (preg_match('/Record Locator:\s+\b[A-Z\d]{5,8}\b\n(.+) \/ (.+)[ ]{2,}/', $text, $m)) {
                $it["Passengers"][] = trim($m[2]) . ' ' . trim($m[1]);
            }
            $result[] = $it;
        }

        return ["Properties" => [], "Itineraries" => $result];
    }

    protected function TravelInformationPdf(\PlancakeEmailParser $parser)
    {
        $result = [];
        $pdfs = $parser->searchAttachmentByName('.*-.*\.pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $it = ['Kind' => 'T'];

            if (preg_match("#Reservation Number:\s+\b([A-Z\d]{5,8})\b#", $text, $m)) {
                $it["RecordLocator"] = $m[1];
            }

            if (preg_match_all('#\s+(.+)#', $text, $m)) {
                $it["Passengers"] = $m[1];
            }
            $result[] = $it;
        }

        return ["Properties" => [], "Itineraries" => $result];
    }

    protected function MobileBoardingPassUrl()
    {
        $result = ['Kind' => 'T'];
        $text = $this->http->FindSingleNode("//a[contains(@href, 'https://www.aa.com/checkin/viewMobileBoardingPass?')]");

        if (empty($text)) {
            $text = $this->http->FindPreg("#https://www.aa.com/checkin/viewMobileBoardingPass?.+#");
        }

        if (empty($text) && !empty($this->http->FindSingleNode("//a[contains(@href, 'https://urldefense.proofpoint.com')]"))) {
            $text = $this->http->FindSingleNode("//a[contains(@href, '__www.aa.com_checkin_viewMobileBoardingPass')]");
            $text = str_replace(['_', '-3F', '-3D', '-25', '-26'], ['/', '?', '=', '%', '&'], $text);
        }

        if (empty($text) && !empty($url = $this->http->FindSingleNode("//a[contains(@href, 'safelinks.protection.outlook.com/?url=') and contains(@href,'viewMobileBoardingPass')]", null, false, "#\/\?url=([^&]+)#"))) {
            $text = $url;
            $text = str_replace(['%2F', '%3F', '%3D', '%25', '%26'], ['/', '?', '=', '%', '&'], $text);
        }

        if (!empty($text)) {
            if (preg_match("#recordLocator=\b([A-Z\d]{5,8})\b(?:\&|$)#", $text, $m)) {
                $result["RecordLocator"] = $m[1];
            }

            if (preg_match("#\bfirstName\b=(.+?)(?:\&|$)#", $text, $m)) {
                $firstName = str_replace('%20', ' ', $m[1]);
            }

            if (preg_match("#\blastName\b=(.+?)(?:\&|$)#", $text, $m)) {
                $lastName = str_replace('%20', ' ', $m[1]);
            }

            if (isset($firstName, $lastName)) {
                $result["Passengers"][] = $firstName . ' ' . $lastName;
            }
        } else {
            $text = $this->http->FindPreg("#https:\/\/www.aa.com\/reservation/mbp.do.+#");

            if (preg_match("#\brL\b=\b([A-Z\d]{5,8})\b(?:\&|$)#", $text, $m)) {
                $result["RecordLocator"] = $m[1];
            }

            if (preg_match("#\bfN\b=(.+?)(?:\&|$)#", $text, $m)) {
                $firstName = str_replace('%20', ' ', $m[1]);
            }

            if (preg_match("#\blN\b=(.+?)(?:\&|$)#", $text, $m)) {
                $lastName = str_replace('%20', ' ', $m[1]);
            }

            if (isset($firstName, $lastName)) {
                $result["Passengers"][] = $firstName . ' ' . $lastName;
            }
        }

        return ["Properties" => [], "Itineraries" => [$result]];
    }

    protected function TripConfirmation2017Pdf(\PlancakeEmailParser $parser)
    {
        $result = [];
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'American Airlines') == false || strpos($text, 'Your trip confirmation and receipt') == false) {
                continue;
            }
            $it = ['Kind' => 'T'];

            if (preg_match("#Record locator:\s+([A-Z\d]{5,8})\s+#", $text, $m)) {
                $it["RecordLocator"] = $m[1];
            }

            if (preg_match_all('#\n\s*([A-Za-z]{2,15}(?:[ ]+[A-Za-z]{2,15})+)\s+Ticket#', $text, $m)) {
                $it["Passengers"] = $m[1];
            }
            $result[] = $it;
        }

        return ["Properties" => [], "Itineraries" => $result];
    }

    protected function PlanTravelPdf(\PlancakeEmailParser $parser)
    {
        $result = [];
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'AAdvantage') === false || strpos($text, 'Plan Travel') === false || strpos($text, 'Helpful links') === false) {
                continue;
            }
            $it = ['Kind' => 'T'];

            if (preg_match("#Record locator:\s+([A-Z\d]{5,8})\s+#", $text, $m)) {
                $it["RecordLocator"] = $m[1];
            }

            if (preg_match_all('#\n\s*([A-Za-z]{2,15}(?:[ ]+[A-Za-z]{2,15})+)\s+Join the AAdvantage#', $text, $m)) {
                $it["Passengers"] = $m[1];
            }
            $result[] = $it;
        }

        return ["Properties" => [], "Itineraries" => $result];
    }

    protected function PrintTripAndReceiptPdf(\PlancakeEmailParser $parser)
    {
        // this method is ready to be moved to a single parser

        $result = [];
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'AA RECORD LOCATOR') === false || strpos($text, 'Receipt') === false) {
                continue;
            }
            $it = ['Kind' => 'T'];

            if (preg_match("#AA RECORD LOCATOR:\s+([A-Z\d]{5,8})\s+#", $text, $m)) {
                $it["RecordLocator"] = $m[1];
            }

            if (preg_match('#PASSENGER[ ]+TICKET NUMBER.+\n\s*([\s\S]+?)Payment Type#', $text, $m)) {
                if (preg_match_all('#^\s*(\S[A-Za-z, ]{5,}?)([ ]{2,}\d+|$)#m', $m[1], $travellerMatches)) {
                    $it["Passengers"] = array_map(function ($v) {return preg_replace("#^\s*(.+?)\s*,\s*(.+?)\s*$#", "$2 $1", $v); }, $travellerMatches[1]);
                }
            }

            /* Trip table */

            $tripText = $this->re("/\n{2}((?:.+\n+){2,4}[ ]*AA Record Locator[ ]{2,}Reservation Name[\s\S]+?)\n+Receipt\n/", $text);

            $tablePos = [0];

            if (preg_match("/^(.{100,}[ ]{2})Total Paid[ ]*[:]+$/m", $tripText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            } elseif (preg_match("/^(.{80,}[ ]{2})Status[ ]*[:]+$/m", $tripText, $matches)) {
                $tablePos[] = mb_strlen($matches[1]);
            }

            $table = $this->splitCols($tripText, $tablePos);

            if (count($table) !== 2) {
                $this->logger->debug('Wrong trip table!');
                $result[] = $it;

                continue;
            }

            $it['TripSegments'] = [];
            //$segments = $this->splitText($table[0], "/^([ ]*Flight[ ]+Depart[ ]+Arrive\n)/m", true);

            //it-388778395.eml
            $segments = $this->splitText($table[0], "/\n\n^(.+\([A-Z]{3}\)[ ]{10,}.+\s*\([A-Z]{3}\)\n)/mu", true);
            $head = $this->re("/^([ ]*Flight[ ]+Depart[ ]+Arrive\n)/m", $table[0]);

            foreach ($segments as $sText) {
                $sText = $head . $sText;

                $seg = [];

                $tablePos = [0];

                if (preg_match('/^((.+ )' . implode('[ ]+)', ['Depart', 'Arrive']) . '/', $sText, $matches)) {
                    unset($matches[0]);

                    foreach (array_reverse($matches) as $textHeaders) {
                        $tablePos[] = mb_strlen($textHeaders);
                    }
                }

                $segTable = $this->splitCols($sText, $tablePos);

                if (count($segTable) !== 3) {
                    $this->logger->debug('Wrong flight segment table!');
                    $result[] = $it;

                    continue 2;
                }

                if (preg_match("/^\s*Flight\n+[ ]*(?<name>.{2,})\n+[ ]*(?<number>\d+)(?:\n|$)/", $segTable[0], $m)) {
                    $seg['AirlineName'] = $m['name'];
                    $seg['FlightNumber'] = $m['number'];
                }

                if (preg_match("/\n[ ]*Operated by[ ]+(.{2,})/", $segTable[0], $m)) {
                    $seg['Operator'] = $m[1];
                }

                $pattern = "[ ]*(?<airport>\S[\s\S]+?)\s*\([ ]*(?<code>[A-Z]{3})[ ]*\)\n+(?<dateTime>.+\d.+:.+)(?:\n|$)";

                if (preg_match("/^\s*Depart\n+{$pattern}/", $segTable[1], $m)) {
                    $seg['DepName'] = preg_replace('/\s+/', ' ', $m['airport']);
                    $seg['DepCode'] = $m['code'];
                    $seg['DepDate'] = strtotime($m['dateTime']);
                }

                if (preg_match("/^[ ]*Travel Time[ ]*[:]+[ ]*(\d.*[hm])$/im", $segTable[1], $m)) {
                    $seg['Duration'] = $m[1];
                }

                if (preg_match("/^[ ]*Class[ ]*[:]+[ ]*(.{2,})$/im", $segTable[1], $m)) {
                    $seg['Cabin'] = $m[1];
                }

                if (preg_match("/^[ ]*Seat[ ]*[:]+[ ]*(\d[\dA-Z,\s]*[A-Z])$/m", $segTable[1], $m)) {
                    $seg['Seats'] = preg_split("/\s*[,]+\s*/", $m[1]);
                }

                if (preg_match("/^\s*Arrive\n+{$pattern}/", $segTable[2], $m)) {
                    $seg['ArrName'] = preg_replace('/\s+/', ' ', $m['airport']);
                    $seg['ArrCode'] = $m['code'];
                    $seg['ArrDate'] = strtotime($m['dateTime']);
                }

                if (preg_match("/^[ ]*Booking Code[ ]*[:]+[ ]*([A-Z]{1,2})$/m", $segTable[2], $m)) {
                    $seg['BookingClass'] = $m[1];
                }

                if (preg_match("/^[ ]*Aircraft[ ]*[:]+[ ]*(.{2,})$/m", $segTable[2], $m)) {
                    $seg['Aircraft'] = $m[1];
                }

                $it['TripSegments'][] = $seg;
            }

            $totalPrice = $this->re("/^[ ]*Total Paid[ ]*[:]+\n+(.*\d.*)$/m", $table[1]);

            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*?)[ ]*(?<currencyCode>[A-Z]{3})$/', $totalPrice, $matches)) {
                // $12,680.74 USD
                $currency = empty($matches['currencyCode']) ? $matches['currency'] : $matches['currencyCode'];
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $it['TotalCharge'] = PriceHelper::parse($matches['amount'], $currencyCode);
                $it['Currency'] = $currency;
            }

            /* Receipt table */

            $receiptText = $this->re("/\nReceipt\n+([\s\S]+?)\n+[ ]+Payment Type[ ]*:/", $text);

            if (!preg_match("/^(?<thead>.{2,})\n+(?<tbody>[\s\S]{2,})$/", $receiptText, $receiptMatches)) {
                $this->logger->debug('Wrong receipt table!');
                $result[] = $it;

                continue;
            }

            $tablePos = [0];

            if (preg_match('/^(((.+ )' . implode('[ ]+)', ['TICKET NUMBER', 'FREQUENT FLYER NUMBER', 'FARE']) . '/', $receiptMatches['thead'], $matches)) {
                unset($matches[0]);

                foreach (array_reverse($matches) as $textHeaders) {
                    $tablePos[] = mb_strlen($textHeaders);
                }
            }

            if (count($tablePos) < 2) {
                $this->logger->debug('Wrong receipt table headers!');
                $result[] = $it;

                continue;
            }

            $passengers = $tickets = $ffNumbers = [];
            $passengerRows = preg_split("/\n{2,}/", $receiptMatches['tbody']);

            foreach ($passengerRows as $pRow) {
                $table = $this->splitCols($pRow, $tablePos);
                // $passengers[] = preg_replace('/\s+/', ' ', trim($table[0]));
                if (!empty($table[1])) {
                    $tickets[] = $table[1];
                }

                if (!empty($table[2])) {
                    $ffNumbers[] = $this->re("/^\s*([A-Z\d]{6,})\s*$/", $table[2]);
                }
            }

            if (count($passengers) > 0) {
                $it['Passengers'] = $passengers;
            }

            if (count($tickets) > 0) {
                $it['TicketNumbers'] = $tickets;
            }

            $ffNumbers = array_filter($ffNumbers);

            if (count($ffNumbers) > 0) {
                $it['AccountNumbers'] = $ffNumbers;
            }

            $result[] = $it;
        }

        return ["Properties" => [], "Itineraries" => $result];
    }

    protected function BellflightPlain()
    {
        $result = [];
        $it = ['Kind' => 'T'];
        $it["RecordLocator"] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Confirmation code:')]",
            null, false, "#:\s+([A-Z\d]{5,})#");
        $it["Passengers"] = explode(',',
            $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Passengers:')]", null, false,
                "#:\s+(.+)#"));
        $result[] = $it;

        return ["Properties" => [], "Itineraries" => $result];
    }

    protected function ChangeReservation()
    {
        $result = [];
        $it = ['Kind' => 'T'];
        $it["RecordLocator"] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Record Locator:')]",
            null, true, "#Record Locator:\s*([A-Z\d]{5,7})\s+#");

        $traveller = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Dear')]", null, true,
            "#Dear (.+?)[\s\,\.\!]$#");

        if (!empty($traveller) && !preg_match("#Customer#i", $traveller)) {
            // if $traveller contains 'Customer' go to aa/ChangeReservationJunk
            $it["Passengers"][] = $traveller;
        }
        $result[] = $it;

        return ["Properties" => [], "Itineraries" => $result];
    }

    private function normalizeDate($date, $lang = 'en')
    {
        $year = date('Y', $this->date);
        $in = [
            //SEG 01 de ABR
            '/^([[:alpha:]]+)\s+(\d+)\s+de\s+([[:alpha:]]+)$/u',
            //WED 21DEC
            '#^([[:alpha:]]+)\s+(\d+)\s*(\w+)$#u',
            //Mar 12, 2019
            '#^(\w+)\s+(\d+),\s*(\d{4})$#u',
            //Terça-feira Setembro 14, 2021  11:00 AM
            '#^[\w\-]+\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*([\d\:]+\s*A?P?M)$#u',
        ];
        $out = [
            '$2 $3 ' . $year,
            '$2 $3 ' . $year,
            '$2 $1 $3',
            '$2 $1 $3, $4',
        ];
        $outWeek = [
            '$1',
            '$1',
            '',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date), $lang);
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date), $this->lang));
        }

        return $str;
    }

    private function dateStringToEnglish($date, $lang = 'en')
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function CheckIn(): array
    {
        $patterns = [
            'time' => '\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.
        ];

        $result = ['Kind' => 'T'];

        $passengers = [];
        $traveler = $this->http->FindSingleNode("//text()[{$this->starts('Hello ')}]", null, true, "/^Hello\s+({$this->patterns['travellerName']})[,.!]+$/m");

        if ($traveler && !preg_match('/^Traveler$/i', $traveler)) {
            $passengers[] = $traveler;
        }

        $ffNumber = $this->http->FindSingleNode("//text()[{$this->eq('AAdvantage #:')}]/following::text()[normalize-space(.)][1]", null, true, '/^[- A-Z\d\/]{5,}$/');

        if ($ffNumber) {
            $result['AccountNumbers'] = [$ffNumber];
        }

        $result['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->eq('Record locator:')}]/following::text()[normalize-space()][1]", null, true, "/^[A-Z\d]{5,}$/")
            ?? $this->http->FindSingleNode("//text()[{$this->starts('Record locator:')}]", null, true, "/Record locator:\s*([A-Z\d]{5,})$/")
        ;

        $seats = $cabins = [];

        $seg = [];

        $xpathCodes = "//text()[ {$this->eq('to')} and preceding::text()[normalize-space()][1][string-length(normalize-space())=3] and following::text()[normalize-space()][1][string-length(normalize-space())=3] ]";

        $seg['DepCode'] = $this->http->FindSingleNode($xpathCodes . '/preceding::text()[normalize-space()][1]', null, true, '/^[A-Z]{3}$/');
        $seg['ArrCode'] = $this->http->FindSingleNode($xpathCodes . '/following::text()[normalize-space()][1]', null, true, '/^[A-Z]{3}$/');

        $date = strtotime($this->http->FindSingleNode($xpathCodes . '/following::text()[normalize-space()][2]', null, true, "/^[-[:alpha:]]+[,\s]+[[:alpha:]]+[,\s]+\d{1,2}[,\s]+\d{4}$/u"));

        $xpathTimes = "//tr[ *[1][string-length(normalize-space())>2] and *[2][descendant::img] ]";
        $xpathTimes2 = "//tr[ *[normalize-space()][1][{$this->xpath['time']}] and *[normalize-space()][2][{$this->xpath['time']}] ]"; // it-322295426.eml

        $timeDep = $this->http->FindSingleNode($xpathTimes . "/*[1]", null, true, "/^{$patterns['time']}/")
            ?? $this->http->FindSingleNode($xpathTimes2 . "/*[normalize-space()][1]", null, true, "/^{$patterns['time']}/")
        ;

        if ($date && $timeDep) {
            $seg['DepDate'] = strtotime($timeDep, $date);
        }

        $timeArr = $this->http->FindSingleNode($xpathTimes . "/ancestor::td[1]/following-sibling::*[string-length(normalize-space())>2][1]", null, true, "/^{$patterns['time']}/")
            ?? $this->http->FindSingleNode($xpathTimes2 . "/*[normalize-space()][2]", null, true, "/^{$patterns['time']}/")
        ;

        if ($date && $timeArr) {
            $seg['ArrDate'] = strtotime($timeArr, $date);
        }

        $flightTexts = array_filter($this->http->FindNodes($xpathTimes . "/ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()]", null, $pattern = '/^((?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+))/'));

        if (count($flightTexts) === 0) {
            $flightTexts = array_filter($this->http->FindNodes($xpathTimes2 . "/ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()]", null, $pattern));
        }

        if (count($flightTexts) === 1) {
            $flight = array_shift($flightTexts);

            if (preg_match($pattern, $flight, $m)) {
                $seg['AirlineName'] = $m['airline'];
                $seg['FlightNumber'] = $m['flightNumber'];
            }

            $operator = $this->http->FindSingleNode($xpathTimes2 . "/ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[{$this->starts($flight)}]/following-sibling::tr[normalize-space()][1]", null, true, "/Operated by\s+(.{2,}?)(?:\s+DBA\s+|$)/i");

            if ($operator) {
                $seg['Operator'] = $operator;
            }
        }

        $segRightTexts = $this->http->FindNodes($xpathTimes2 . "/ancestor::*[count(div)=2][1]/div[2]/descendant::tr[not(.//tr)]");

        foreach ($segRightTexts as $srText) {
            if (preg_match("/^(?<seat>\d+[A-Z])\s*\(\s*(?<cabin>[^)(]{2,})\s*\)\s*-\s*(?<traveller>{$this->patterns['travellerName']})$/", $srText, $m)) {
                // 3D (Business) - MELISSA ROSA
                $seats[] = $m['seat'];
                $cabins[] = $m['cabin'];
                $passengers[] = $m['traveller'];
            }
        }

        if (count($seats) > 0) {
            $seg['Seats'] = $seats;
        }

        if (count(array_unique($cabins)) === 1) {
            $seg['Cabin'] = $cabins[0];
        }

        $result['TripSegments'][] = $seg;

        if (count($passengers) > 0) {
            $result['Passengers'] = $passengers;
        }

        return ['Properties' => [], 'Itineraries' => [$result]];
    }

    private function Business(\PlancakeEmailParser $parser)
    {
        $result = ["Kind" => "T"];
        $result["RecordLocator"] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Airline Locator')]", null, true, "#Airline Locator[ :]+([A-Z\d]{5,})#");
        $result["Passengers"] = $this->http->FindNodes("//text()[normalize-space()='Traveler']/ancestor::tr[1][contains(.,'Type')]/following-sibling::tr/td[1][normalize-space()!='']");

        $accounts = array_filter($this->http->FindNodes("//tr[td[3][normalize-space()='Frequent Flyer #']]/following-sibling::tr[normalize-space()]/td[3]",
            null, "/^\s*AA-([\dA-Z]{5,})\s*$/"));

        if (!empty($accounts)) {
            $result["AccountNumbers"] = $accounts;
        }
        $tickets = array_filter($this->http->FindNodes("//tr[td[4][normalize-space()='Ticket #']]/following-sibling::tr[normalize-space()]/td[4]",
            null, "/^\s*([\d\- ]{13,})\s*$/"));

        if (!empty($tickets)) {
            $result["TicketNumbers"] = $tickets;
        }

        // Price
        $points = $this->http->FindSingleNode("//text()[" . $this->contains(['Total points for award', 'Total Points for Award']) . "]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");

        if (!empty($points)) {
            $result["SpentAwards"] = preg_replace('/k$/', '000', str_replace('., ', '', trim($points)));
        }
        $cost = $this->http->FindSingleNode("//text()[" . $this->contains(['Total Base Cost of all Travel', 'Total base cost of all travel']) . "]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");

        if (!empty($cost) && preg_match("/^\s*\\$\s*(\d[\d,]*\.\d{2})\s*$/", $cost, $m)) {
            $result["BaseFare"] = $m[1];
            $result["Currency"] = 'USD';
        }
        $tax = $this->http->FindSingleNode("//td[" . $this->eq(['Total_Taxes_&_Fees', 'Total. taxes .& . fees:', 'Total. taxes.& .fees:']) . "]/following-sibling::td[normalize-space()!=''][1]");

        if (!empty($tax) && preg_match("/^\s*\\$\s*(\d[\d,]*\.\d{2})\s*$/", $tax, $m)) {
            $result["Tax"] = $m[1];
            $result["Currency"] = 'USD';
        }

        $total = $this->http->FindSingleNode("//text()[" . $this->contains(['Total:']) . "]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");

        if (!empty($total) && preg_match("/^\s*\\$\s*(\d[\d,]*\.\d{2})\s*(?:[^\w\.\,]*and.*)?$/", $total, $m)) {
            $result["TotalCharge"] = $m[1];
            $result["Currency"] = 'USD';
        }

        if (stripos($parser->getHeader('from'), '.aa.com') !== false) {
            $date = strtotime($parser->getDate());
        } else {
            $dateStr = $this->http->FindSingleNode("//text()[" . $this->eq("Sent:") . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*\w+,\s*(\w+ \d{1,2}, \s*\d{4})\s*[\d:]+.*\s*$/");

            if ($dateStr && strtotime($dateStr)) {
                $date = strtotime($dateStr);
            }
        }

        if (empty($date)) {
            return ["Itineraries" => [$result]];
        }

        $seats = [];
        $seatsRows = $this->http->XPath->query("//tr[td[2][normalize-space()='Seat']]/following-sibling::tr[normalize-space()][count(.//td[normalize-space()]) > 2]/td[2]");

        foreach ($seatsRows as $sRoot) {
            $s = $this->http->FindNodes(".//text()[normalize-space()]", $sRoot);

            if (empty($seats)) {
                $seats[] = $s;
            } elseif (count($s) == count($seats[0])) {
                $seats[] = $s;
            } else {
                $seats = [];

                break;
            }
        }
        $xpath = "//img/following::td[not(.//td)][normalize-space()][2][contains(translate(normalize-space(.), '1234567890', 'dddddddddd'), 'd:dd')]/ancestor::*[contains(., '#')][not(.//img)][1]";
        $nodes = $this->http->XPath->query($xpath);

        if (!empty($seats) && $nodes->length == count($seats[0])) {
            foreach ($seats[0] as $i => $v) {
                $seatsNew[$i] = array_column($seats, $i);
            }
            $seats = $seatsNew;
        } else {
            $seats = [];
        }

        foreach ($nodes as $i => $root) {
            $segment = [];
            $segDate = null;
            $segDateStr = $this->http->FindSingleNode("preceding::td[not(.//td)][normalize-space()][1]", $root);

            if (!empty($segDateStr)) {
                $segDate = EmailDateHelper::parseDateRelative($segDateStr, $date);
            }

            $text = implode("\n", $this->http->FindNodes(".//td[not(.//td)][normalize-space()]", $root));
            $regexp = "/^\s*(?<dTime>\d{1,2}:\d{2}[ap])\s*-\s*(?<aTime>\d{1,2}:\d{2}[ap])\s*\n\s*(?<stop>.*stop.*)\s*\n\s*(?-i)(?<dCode>[A-Z]{3})\s*-\s*(?<aCode>[A-Z]{3})(?i)\s*\n\s*(?<duration>.*\d.*)\s*\n\s*(?<al>.+) *\# *(?<fn>\d{1,5})(?<cabin>\s*\n\s*.*)?\s*$/i";

            if (preg_match($regexp, $text, $m)) {
                // Airline
                $segment["AirlineName"] = trim($m['al']);
                $segment["FlightNumber"] = $m['fn'];

                // Departure
                $segment["DepDate"] = !empty($segDate) ? strtotime($m['dTime'] . 'm', $segDate) : null;
                $segment["DepCode"] = $m['dCode'];

                // Arrival
                $segment["ArrDate"] = !empty($segDate) ? strtotime($m['aTime'] . 'm', $segDate) : null;
                $segment["ArrCode"] = $m['aCode'];

                // Extra
                $segment["Duration"] = $m['duration'];

                if (!empty(trim($m['cabin']))) {
                    $segment["Cabin"] = trim($m['cabin']);
                }

                if (preg_match("/non[ \-]*stop/i", $m['stop'])) {
                    $segment["Stops"] = 0;
                } elseif (preg_match("/^\s*(\d+)\s**stop/i", $m['stop'], $mat)) {
                    $segment["Stops"] = $mat[1];
                }

                if (isset($seats[$i])) {
                    $seat = array_filter($seats[$i],
                        function ($v) {
                            if (preg_match("/^\s*(\d{1,3}[A-Z])\s*$/", $v)) {
                                return true;
                            }

                            return false;
                        });

                    if (!empty($seat)) {
                        $segment['Seats'] = $seat;
                    }
                }
            }

            $result["TripSegments"][] = $segment;
        }

        return ["Itineraries" => [$result]];
    }

    private function Receipt()
    {
        $result = ["Kind" => "T"];
        $result["RecordLocator"] = $this->http->FindSingleNode("//text()[normalize-space()='Record locator:']/following::text()[normalize-space()!=''][1]", null, true, "#^([A-Z\d]{5,})#");
        $result["Passengers"] = $this->http->FindNodes("//text()[starts-with(normalize-space(),\"DOCUMENT NUMBER\")]/preceding::text()[string-length(normalize-space(.))>3][1]/ancestor::*[1][self::span]/ancestor::tr[1][count(.//td)=1]");

        return ["Itineraries" => [$result]];
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
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
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
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

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(normalize-space(' . $text . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }
}
