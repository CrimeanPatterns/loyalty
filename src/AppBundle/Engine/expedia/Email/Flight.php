<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "expedia/it-29304084.eml, expedia/it-35785915.eml, expedia/it-35949328.eml, expedia/it-36218273.eml, expedia/it-40430162.eml, expedia/it-40532930.eml, expedia/it-40582010.eml, expedia/it-40729595.eml, expedia/it-40822783.eml, expedia/it-41348660.eml, expedia/it-58453371.eml, expedia/it-58969957.eml";

    private $lang = 'en';

    private $from = '/[@\.a-z]+expedia[a-z]*\.com/i';

    private $detects = [
        'en'  => 'You can still add cancellation, medical and baggage protection',
        'en2' => 'we are processing your flight purchase',
        'en3' => 'your flights are booked',
        'en4' => 'your flight is booked',
        'en5' => 'VIEW YOUR ITINERARY',
        'en6' => 'Your reservation is booked and confirmed',
    ];

    private $subjects = [
        'Expedia flight purchase confirmation',
    ];

    private $prov = 'expedia';

    private $date;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (empty($parser->getHTMLBody())) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }
        $this->date = strtotime($parser->getDate());
        $cl = explode('\\', __CLASS__);
        $email->setType(end($cl) . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && !preg_match($this->from, $headers['from'])) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (isset($headers['subject']) && false !== stripos($headers['subject'], $subject)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== strpos($body, $detect)
                || $this->http->XPath->query('//node()[contains(normalize-space(),"' . $detect . '")]')->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public static function getEmailTypesCount()
    {
        return 3; //segments
    }

    private function parseEmail(Email $email): void
    {
        // Travel Agency
        $email->obtainTravelAgency();

        if ($conf = $this->gn('Expedia Itinerary:', '/\#?(\d+)$/')) {
            $email->ota()
                ->confirmation($conf, 'Expedia Itinerary');
        } elseif ($conf = $this->http->FindSingleNode("(//text()[contains(normalize-space(),'Itinerary #')])[1]", null,
            false, "#Itinerary \#\s*(\d+)#")) {
            $email->ota()
                ->confirmation($conf, 'Expedia Itinerary');
        } elseif ($conf = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Need to cancel')]/following::text()[contains(normalize-space(),'Itinerary')]/following::text()[1]", null,
            true, "/#(\d{10,})/")) {
            $email->ota()
                ->confirmation($conf, 'Expedia Itinerary');
        }

//        if ( $accNums = $this->http->FindNodes("//text()[starts-with(normalize-space(.),'Known Traveler Number')]", null, "/Known Traveler Number\s+([\w\-]+)/")) {
//            $email->ota()
//                ->accounts($accNums, false);
//        }
        if ($points = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'You earned ')]", null,
            false, "#You earned\s+(.+)#")) {
            $email->ota()
                ->earnedAwards($points);
        }

        $f = $email->add()->flight();

        if ($conf = $this->gn('Flight Confirmation:', '/\#(.+)/')) {
            $f->general()
                ->confirmation($conf, 'Flight Confirmation');
        } else {
            $f->general()->noConfirmation();
        }

        $ticketNumbers = array_filter($this->http->FindNodes("//text()[{$this->eq('Ticket number:')}][1]/following::text()[normalize-space()][1]",
            null, '/(?:#\s*|^)(\d{3}[- ]*\d{5,}[- ]*\d{1,2})$/'));

        if ($ticketNumbers) {
            $f->issued()->tickets($ticketNumbers, false);
        }

        if ($pax = $this->gn(['Traveler:', 'Travelers:'])) {
            $pax = preg_replace("/\s*\([^()]+\)\s*$/", '', array_filter(array_map("trim", explode(',', $pax))));
            $f->general()->travellers($pax);
        }

        $totalPrice = $this->getTotalCurrency($this->http->FindSingleNode("//text()[normalize-space()='Total']/ancestor::td[1]/following::td[normalize-space()][1]"));

        if ($totalPrice['Total'] !== null) {
            $email->price()
                ->total($totalPrice['Total'])
                ->currency($totalPrice['Currency']);
        }

        $cost = null;
        $costValues = $this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Flight'] and following::tr[normalize-space()][1][{$this->starts('Taxes & fees')}] ]/*[normalize-space()][2]");

        if (count($costValues) === 0) {
            $costValues = $this->http->FindNodes("//*[ *[normalize-space()][2][normalize-space()='Flight'] and *[normalize-space()][3][normalize-space()='Taxes & fees'] ]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()]/descendant-or-self::*[ *[normalize-space()][3] ]/*[normalize-space()][2]");
        }

        if (count($costValues) === 0) {
            $costValues = $this->http->FindNodes("//*[ *[normalize-space()][1][normalize-space()='Flight'] and *[normalize-space()][2][normalize-space()='Taxes & fees'] ]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()]/descendant-or-self::*[ *[normalize-space()][2] ]/*[normalize-space()][1]");
        }

        foreach ($costValues as $cValue) {
            $sum = $this->getTotalCurrency($cValue);

            if ($sum['Total'] !== null && !empty($email->getPrice()) && !empty($email->getPrice()->getCurrencyCode())
                && $sum['Currency'] === $email->getPrice()->getCurrencyCode()
            ) {
                $cost += (float) $sum['Total'];
            } else {
                $cost = null;

                break;
            }
        }

        if ($cost !== null) {
            $email->price()->cost($cost);
        }

        $tax = null;
        $taxValues = $this->http->FindNodes("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][normalize-space()='Taxes & fees'] ]/*[normalize-space()][2]");

        if (count($taxValues) === 0) {
            $taxValues = $this->http->FindNodes("//*[ *[normalize-space()][3][normalize-space()='Taxes & fees'] ]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()]/descendant-or-self::*[ *[normalize-space()][3] ]/*[normalize-space()][3]");
        }

        if (count($taxValues) === 0) {
            $taxValues = $this->http->FindNodes("//*[ *[normalize-space()][2][normalize-space()='Taxes & fees'] ]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()]/descendant-or-self::*[ *[normalize-space()][2] ]/*[normalize-space()][2]");
        }

        foreach ($taxValues as $tValue) {
            $sum = $this->getTotalCurrency($tValue);

            if ($sum['Total'] !== null && !empty($email->getPrice()) && !empty($email->getPrice()->getCurrencyCode())
                && $sum['Currency'] === $email->getPrice()->getCurrencyCode()
            ) {
                $tax += (float) $sum['Total'];
            } else {
                $tax = null;

                break;
            }
        }

        if ($tax !== null) {
            $email->price()->tax($tax);
        }

        $xpath = "//tr[(" . $this->eq([
            'Flight Details',
            'Flight details',
            'Departing flight',
            'Returning flight',
        ]) . ") and not(.//tr)]/following-sibling::"
            . "tr[normalize-space()][not(" . $this->starts("Airline confirmation") . ")][not(" . $this->starts("Travel duration") . ")][" . $this->starts('Leg ') . " or ( not(following-sibling::tr[" . $this->starts('Leg ') . "]) and not(preceding-sibling::tr[" . $this->starts('Leg ') . "]) and position() = 1)]";

        $roots = $this->http->XPath->query($xpath);

        if ($roots->length == 0) {
            // it-58453371.eml
            $this->http->XPath->registerNamespace("php", "http://php.net/xpath");
            $this->http->XPath->registerPHPFunctions('preg_match');
            $regex = '/^\s*\w{3}, \d{1,2} \w{3}\s*$/';
            $xpath = "//tr[td[php:functionString('preg_match', '$regex', text())>0]]";
            $roots = $this->http->XPath->query($xpath);
            $this->logger->debug($xpath);
        }

        if ($roots->length == 0) {
            // it-58969957.eml
            $this->http->XPath->registerNamespace("php", "http://php.net/xpath");
            $this->http->XPath->registerPHPFunctions('preg_match');
            $regex = '/^\s*\w{3}, \w{3} \d{1,2}\s*$/';
            $xpath = "//tr[td[php:functionString('preg_match', '$regex', text())>0]]";
            $roots = $this->http->XPath->query($xpath);
            $this->logger->debug($xpath);
        }

        if ($roots->length > 0) {
            $this->logger->debug("xpath: {$xpath}");
            $this->parseSegments_1($roots, $f);
        } else {
            if ($roots->length == 0) {
                $xpath = "//tr[starts-with(normalize-space(), 'Flight details')]/following::tr[starts-with(normalize-space(), 'Leg ')]/descendant::text()[starts-with(normalize-space(), 'Leg ')]/ancestor::tr[1]";
                $roots = $this->http->XPath->query($xpath);

                if ($roots->length === 0) {
                    $xpath = "//tr[starts-with(normalize-space(), 'Flight details')]/following::tr[1][./descendant::img[contains(@alt,'Flight ')]]";
                    $roots = $this->http->XPath->query($xpath);
                }

                if ($roots->length > 0) {
                    $this->logger->debug("xpath: {$xpath}");
                    $this->parseSegments_1($roots, $f);
                } else {
                    $xpath = "//text()[{$this->starts('Departs')}]/ancestor::tr[1]/following-sibling::tr[1][{$this->starts('Airline confirmation')}]";
                    $roots = $this->http->XPath->query($xpath);
                    $this->logger->debug("xpath: {$xpath}");
                    $this->parseSegments_2($roots, $f);
                }
            }
        }
    }

    private function parseSegments_3(\DOMNodeList $roots, \AwardWallet\Schema\Parser\Common\Flight $f): void
    {
    }

    private function parseSegments_1(\DOMNodeList $roots, \AwardWallet\Schema\Parser\Common\Flight $f): void
    {
        $this->logger->notice(__METHOD__);

        foreach ($roots as $segNum => $root) {
            $rowN = 1;
            $patterns['date'] = '/(?:^\s*|.*\W)([-[:alpha:]]+[.\s]*,\s*(?:[[:alpha:]]+|\d{1,2})\s+(?:[[:alpha:]]+|\d{1,2}))\b/u';
            $dateStr = $this->http->FindSingleNode('.', $root, true, $patterns['date']);

            if (empty($dateStr)) {
                $dateStr = $this->http->FindSingleNode('following::tr[1]', $root, true, $patterns['date']);

                if (!empty($dateStr)) {
                    $rowN = 2;
                }
            }
            $date = $this->normalizeDate($dateStr);

            if (!$date) {
                $this->logger->debug('date for segment did not found');
            }
            $xpathTime = 'starts-with(translate(normalize-space(),"0123456789","dddddddddd"),"d:dd")';

            if ($this->http->XPath->query("following-sibling::tr[normalize-space()][position()<=5][.//img]",
                    $root)->length !== 2
                && $this->http->XPath->query("following-sibling::tr[normalize-space()][position()<=5][ descendant::text()[{$xpathTime}] ]",
                    $root)->length !== 2
            ) {
                $this->logger->debug("segment-{$segNum} parse by info above");

                if ($this->http->XPath->query("//text()[{$this->starts('Layover')}]")->length > 0) {
                    // WTF?
                    $this->logger->notice("it has Layover. can't parse correct. skip segment");

                    if ($f->price()) {
                        $f->removePrice();
                    }

                    return;
                }
                // TODO: look at it-40729595.eml, it-40822783.eml  maybe collect one else segment?

                $pnr = $this->http->FindSingleNode("./preceding::tr[normalize-space()!=''][1][{$this->starts("Airline confirmation")}]",
                    $root);
                $xpathHeader = "./preceding::tr[normalize-space()!=''][{$this->starts("Airline confirmation")}][contains(.,'{$pnr}')][last()]";
                $rootHeader = $this->http->XPath->query($xpathHeader, $root);

                if ($rootHeader->length === 1) {
                    $rootHeader = $rootHeader->item(0);
                    $this->logger->debug("xpathHeader: " . $xpathHeader);
                    $s = $f->addSegment();
                    $this->parseSegment_2($rootHeader, $s, $date);
                }

                continue;
            }

            $s = $f->addSegment();
            // Airline
            $flight = implode("\n",
                $this->http->FindNodes("following-sibling::tr[normalize-space()][position()>={$rowN} and position()<={$rowN}+1 and not(.//img) and not({$this->contains('Check in for')})]//text()[normalize-space()]",
                    $root));

            if (preg_match('/(?:^|\n)(?<al>.+?)(?:\s*,\s*operated by (?<op>.+?))?\s+(?<alCode>[A-Z\d][A-Z]|[A-Z][A-Z\d])?(?<fn>\d{1,5})(?:\n|$)/',
                $flight, $m)) {
                /*
                    British Airways, operated by BA Cityflyer
                    8490
                 */

                /*
                    Web Fare
                    American Airlines
                    118
                    Arrives at JFK, not EWR
                 */
                $s->airline()
                    ->name(empty($m['alCode']) ? $m['al'] : $m['alCode'])
                    ->number($m['fn'])
                    ->operator(empty($m['op']) ? null : $m['op'], false, true);
                $rowN++;
            } elseif (!empty($this->http->FindSingleNode("./following-sibling::tr[normalize-space()][" . ($rowN + 1) . "]",
                $root, true, "#^\s*(\d{1,5})\s*$#"))
            ) {
                $s->airline()
                    ->name($this->http->FindSingleNode("./following-sibling::tr[normalize-space()][{$rowN}]", $root,
                        true, "#(.+?)\s*(,\s*operated by|$)#"))
                    ->operator($this->http->FindSingleNode("./following-sibling::tr[normalize-space()][{$rowN}]", $root,
                        true, "#.+?\s*,\s*operated by (.+)#"), true, true)
                    ->number($this->http->FindSingleNode("./following-sibling::tr[normalize-space()][" . ($rowN + 1) . "]",
                        $root));
                $rowN += 2;
            } else {
                $rowN++;
            }

            $conf = $this->http->FindSingleNode("./preceding-sibling::tr[" . $this->starts("Airline confirmation:") . "]",
                $root, true, "#Airline confirmation:\s*([A-Z\d]{5,7})\b#");

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode("./preceding-sibling::tr[" . $this->eq("Airline confirmation:") . "]/following-sibling::tr[1]",
                    $root, true, "#^\s*([A-Z\d]{5,7})\b#");
            }

            if (!empty($conf)) {
                $s->airline()->confirmation($conf);
            }

            $re = '/(?<time>\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?|))\s*(?<nextday>[+\-]\d+)?\W*\s*(?<name>.+)\s*\((?<code>[A-Z]{3})\b\W*(?<name2>\w.+)?\s*\)(?:\s*Overnight arrives on\s*(?:\s*|.*\W)(\w+, (?:\w+|\d{1,2}) (?:\w+|\d{1,2}))\b|)/u';

            // Departure
            $dep = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][{$rowN}]/descendant::tr[not(.//tr) and normalize-space()][1]",
                $root, true, "#.*\d:\d{2}\D.*#");

            if (empty($dep)) {
                for ($i = 1; $i <= 2; $i++) {
                    $dep = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][" . ($rowN + $i) . "]/descendant::tr[not(.//tr) and normalize-space()][1]",
                        $root, true, "#.*\d:\d{2}\D.*#");

                    if (!empty($dep)) {
                        $rowN += $i;

                        break;
                    }
                }
            }

            if (preg_match($re, $dep, $m)) {
                $s->departure()
                    ->name((!empty($m['name2']) ? $m['name2'] . ', ' : '') . $m['name'])
                    ->code($m['code'])
                    ->date($date ? strtotime($m['time'], $date) : null)
                    ->terminal($this->http->FindSingleNode("following-sibling::tr[normalize-space()][{$rowN}]//text()[{$this->contains("Terminal")}]", $root, true, '/Terminal\s*([A-z\d]+)(?:\s|$)/i'), false, true);
            }

            // Extra
            $s->extra()
                ->duration($this->http->FindSingleNode("./following-sibling::tr[normalize-space()][{$rowN}]//text()[" . $this->contains("flight duration") . "]",
                    $root, false, "/\[?(.+?)\s*flight duration/"));

            $info = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][{$rowN}]//tr[" . $this->contains("Coach") . " and not(.//tr)]",
                $root);

            if (empty($info)) {
                $info = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][{$rowN}]//tr[" . $this->contains("flight duration") . " and not(.//tr)]/following-sibling::tr[normalize-space()][1]",
                    $root);
            }

            if (preg_match('/^\s*(?<aircraft>.+?)\s*\W+\s*(?<cabin>\w+)\s*\/\s*Coach\s*$/u', $info, $m)
                || preg_match('/^\s*(?<cabin>\w+)\s*\/\s*Coach\s+\((?<bc>[A-Z]{1,2})\)(?:\s*\W+\s*(?<aircraft>.+)|$)/u',
                    $info, $m)
                || preg_match('/^\s*(?<cabin>\w+)\s*\((?<bc>[A-Z]{1,2})\)\s*[^\w\s]+\s*(?<aircraft>.+)/u', $info, $m)
            ) {
                // Economy / Coach (K) • Airbus A319
                $s->extra()
                    ->aircraft(empty($m['aircraft']) ? null : $m['aircraft'], false, true)
                    ->cabin($m['cabin'])
                    ->bookingCode(empty($m['bc']) ? null : $m['bc'], false, true);
            }

            $seats = $this->http->FindSingleNode("./following-sibling::tr[normalize-space()][{$rowN}]//tr[" . $this->contains([
                "Seats:",
                "Seat:",
            ]) . " and not(.//tr)]", $root, true, "#Seats?:\s*(\d[,\dA-Z\s]*[A-Z])(?:[ ;\|]+|\s*$)#");

            if (!empty($seats) && preg_match_all("#\b(\d{1,3}[A-Z])\b#", $seats, $m)) {
                $s->extra()->seats($m[1]);
            }

            // Arrival
            $rowN++;

            $arrival = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][{$rowN}]", $root);

            if (preg_match($re, $arrival, $matches)) {
                //$this->logger->debug(var_export($matches, true));
                if (!empty($matches[6])) {
                    $date = $this->normalizeDate($matches[6]);
                }
                $s->arrival()
                    ->name((!empty($matches['name2']) ? $matches['name2'] . ', ' : '') . $matches['name'])
                    ->code($matches['code'])
                    ->date($date ? strtotime($matches['time'], $date) : null)
                    ->terminal(preg_match('/Terminal\s*([A-z\d]+)(?:\s|$)/i', $arrival, $m) ? $m[1] : null, false, true);

//                if (!empty($matches['nextday']) && !empty($s->getArrDate())) {
//                    $s->arrival()->date(strtotime($matches['nextday'] . ' day', $s->getArrDate()));
//                }
            }
        }
    }

    private function parseSegments_2(\DOMNodeList $roots, \AwardWallet\Schema\Parser\Common\Flight $f): void
    {
        $this->logger->notice(__METHOD__);

        foreach ($roots as $root) {
            $s = $f->addSegment();
            $this->parseSegment_2($root, $s);
        }
    }

    private function parseSegment_2(
        \DOMNode $root,
        \AwardWallet\Schema\Parser\Common\FlightSegment $s,
        $checkDate = null
    ): void {
        $num = null;
        $this->logger->debug(__METHOD__);
        $node = $this->http->FindSingleNode(".", $root);

        if (preg_match("#Airline confirmation[: ]+(?<c>[A-Z\d]{5,6})\s+\((?<al>.+?)\)#", $node, $m)) {
            $s->airline()
                ->confirmation($m['c'])
                ->name($m['al']);
            $num = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), '" . $m["al"] . "')]/following::text()[normalize-space()!=''][1]",
                $root, true, "/^\d{3,6}$/");

            if (!empty($num)) {
                $s->airline()->number($num);
            } else {
                $s->airline()->noNumber();
            }
        }
        $node = $this->http->FindSingleNode("./preceding-sibling::tr[1]", $root);
        $i = 2;

        if (empty($node)) {
            $node = $this->http->FindSingleNode("./preceding-sibling::tr[2]", $root);

            if (!empty($node)) {
                $i = 3;
            }
        }

        if (preg_match("#Departs on\s+(.+?)\s*$#", $node, $m)) {
            $s->departure()
                ->date($this->normalizeDate($m[1]));

            if (isset($checkDate) && date("Y-d-m", $checkDate) !== date("Y-d-m", $s->getDepDate())) {
                return;
            }
        }

        $node = $this->http->FindSingleNode("./preceding-sibling::tr[$i]", $root);

        if (preg_match("#(.+?)\s*(?:\(([A-Z]{3})\))? to (.+?)\s*(?:\(([A-Z]{3})\))?$#", $node, $m)) {
            $arrTime = null;

            if (isset($m[2]) && !empty($m[2])) {
                $s->departure()->code($m[2]);
            } else {
                $s->departure()->noCode();
            }
            $s->departure()->name($m[1]);

            if (isset($m[4]) && !empty($m[4])) {
                $s->arrival()->code($m[4]);
            } else {
                $arrCode = null;

                if (preg_match("/\((.+)\)/", $m[3], $arr)) {
                    $arrCode = $this->http->FindSingleNode("//text()[contains(normalize-space(.), '-" . $arr[1] . "')]",
                        null, true, "/\(([A-Z]{3})-" . $arr[1] . "\)/");
                    $arrTime = $this->http->FindSingleNode("//text()[contains(normalize-space(.), '-" . $arr[1] . "')]",
                        null, true, "/^(\d{1,2}:\d{1,2}[apm]{2})/");
                }

                if (!empty($arrCode)) {
                    $s->arrival()->code($arrCode);
                } else {
                    $s->arrival()->noCode();
                }
            }

            if (!empty($arrTime)) {
                $s->arrival()->date(strtotime($arrTime, $checkDate));
            } else {
                $s->arrival()->noDate();
            }
            $s->arrival()->name($m[3]);
            $roots = $this->http->XPath->query("//text()[" . $this->contains($num) . "]/ancestor::table[1]/following::table[1]/descendant::tr[./descendant::img[contains(@alt,'Flight ')]]");

            if ($roots->length === 2) {
                $terminal = $this->http->FindSingleNode("//text()[" . $this->starts('Terminal') . "]", $roots[0], true,
                    "/Terminal\s(.+)/");

                if (!empty($terminal)) {
                    $s->departure()->terminal($terminal);
                }
                $terminal = $this->http->FindSingleNode("//text()[" . $this->starts('Terminal') . "]", $roots[1], true,
                    "/Terminal\s(.+)/");

                if (!empty($terminal)) {
                    $s->arrival()->terminal($terminal);
                }
                $duration = $this->http->FindSingleNode("//text()[" . $this->starts('flight duration') . "]", $roots[0],
                    true, "/^(.+)\sflight duration/");

                if (!empty($duration)) {
                    $s->extra()->duration($duration);
                }

                $seats = $this->http->FindSingleNode("//text()[" . $this->contains([
                    "Seats:",
                    "Seat:",
                ]) . " and not(.//tr)]", $root, true, "#Seats?:\s*(\d[,\dA-Z\s]*[A-Z])(?:[ ;\|]+|\s*$)#");

                if (!empty($seats) && preg_match_all("#\b(\d{1,3}[A-Z])\b#", $seats, $m)) {
                    $s->extra()->seats($m[1]);
                }
            }
        }
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Sat, Jun 29
            '#^(\w+),\s+(\w+)\s+(\d+)$#u',
            //Tue, Jul 2 at 8:45pm
            '#^(\w+),\s+(\w+)\s+(\d+)\s+at\s+(\d+:\d+(?:\s*[ap]m)?+)$#ui',
            //Fri, 6 Mar    |    Wed., 19 Aug.   |   Mon, 12 OctTerminal
            '/^([-[:alpha:]]+)[.\s]*,\s*(\d{1,2})\s+([A-z]{3})(?:\.?|[A-z]+)$/u',
            //Sat, Jun 29Terminal
            '#^(\w+),\s+(\w+)\s+(\d+)(\D+)$#u',
            //Sat., 23 May at 6:50 pm
            '#^(\w+)\.,\s+(\d+)\s+(\w+)[.]?\s+at\s+(\d+:\d+(?:\s*[ap]m)?+)$#ui',
        ];
        $out = [
            '$3 $2 ' . $year,
            '$3 $2 ' . $year . ', $4',
            '$2 $3 ' . $year,
            '$2 $3 ' . $year,
            '$2 $3 ' . $year . ', $4',
        ];
        $outWeek = [
            '$1',
            '$1',
            '$1',
            '$1',
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function gn($s, ?string $re = null): ?string
    {
        return $this->http->FindSingleNode("(//text()[" . $this->eq($s) . "])[1]/following::text()[normalize-space()][1]",
            null, true, $re);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function getTotalCurrency($total)
    {
        $tot = null;
        $cur = null;

        if (preg_match("#^\s*(?<curr>[^\d\s]\D{0,4})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
        || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]\D{0,4})\s*$#", $total, $m)) {
            $tot = PriceHelper::cost($m['amount']);
            $cur = $this->currency($m['curr']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
        $s = str_replace(' ', '', $s);
        $sym = [
            '$C'        => 'CAD',
            'R$'        => 'BRL',
            'C$'        => 'CAD',
            'CA$'       => 'CAD',
            'SG$'       => 'SGD',
            'HK$'       => 'HKD',
            'AU$'       => 'AUD',
            'NT$'       => 'TWD',
            'A$'        => 'AUD',
            '€'         => 'EUR',
            '$'         => 'USD',
            'US$'       => 'USD',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            //			'kr'=>'NOK', NOK or SEK
            'RM'             => 'MYR',
            '฿'              => 'THB',
            'MXN$'           => 'MXN',
            'MX$'            => 'MXN',
            'Euro'           => 'EUR',
            'Euros'          => 'EUR',
            'Real brasileiro'=> 'BRL',
            'JP¥'            => 'JPY',
            '円'              => 'JPY',
            'CN¥'            => 'CNY',
            '₹'              => 'INR',
            '₩'              => 'KRW',
            '₪'              => 'ILS',
            'NZ$'            => 'NZD',
            'AR$'            => 'MGF',
            '₫'              => 'VND',
        ];
//        $sym = [
//            '€'=>'EUR',
//            '$'=>'USD',
//            '£'=>'GBP',
//        ];
        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }
}
