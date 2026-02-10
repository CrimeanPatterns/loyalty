<?php

namespace AwardWallet\Engine\centrav\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    public $mailFiles = "centrav/it-20094631.eml, centrav/it-20341080.eml, centrav/it-20358461.eml, centrav/it-25639694.eml";

    private $from = '/[@\.]travservices\.com/i';

    private $detects = [
        'Centrav E-Ticket Confirmation for Record',
        'Centrav Booking Confirmation for Record',
    ];

    private $prov = 'Centrav';

    private $lang = 'en';

    /**
     * @return array|Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($email);
        $email->setType('Flight' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from'])
            && isset($headers['subject']) && false !== stripos($headers['subject'], 'Centrav');
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email): Email
    {
        if ($conf = $this->getNode('Record Locator', '/\:\s*([A-Z\d]{5,9})/')) {
            $email->ota()->confirmation($conf);
        }

        $year = $this->getNode('Departure Date', '/\/(\d{4})/');

        $xpath = "//td[contains(normalize-space(.), 'Depart') and not(.//td)]/ancestor::td[contains(normalize-space(.), 'Arrive')][1]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }
        $airs = [];

        foreach ($roots as $root) {
            if ($rl = $this->getSNode('Airline RL:', $root)) {
                $airs[$rl][] = $root;
            } else {
                $airs[CONFNO_UNKNOWN][] = $root;
            }
        }

        foreach ($airs as $rl => $roots) {
            $f = $email->add()->flight();

            if ($rl == CONFNO_UNKNOWN) {
                $f->general()->noConfirmation();
            } else {
                $f->general()->confirmation($rl);
            }

            if ($st = $this->getNode('Status', '/\:\s*([a-z]+)/i')) {
                $f->general()->status($st);
            }

            $pax = $this->http->FindSingleNode("//div[contains(normalize-space(.), 'Ticket Numbers:') and not(.//td) and not(.//div)]");
            preg_match_all('/(?:PAX\s+)?([\d\-]+)\s+(?:ET\s+)?\-\s+([a-zA-Z\/]+)/', $pax, $m);

            foreach ($m[1] as $i => $ps) {
                $f->addTicketNumber($ps, false);

                if (!in_array($m[2][$i], array_map(function ($el) { return $el[0]; }, $f->getTravellers()))) {
                    $f->addTraveller($m[2][$i]);
                }
            }

            if (empty($pax)) {
                $pax = $this->http->FindSingleNode("//div[normalize-space(.)='Passengers']/following-sibling::div[1]");
                preg_match_all('/([A-Za-z\/\.]+)\s*\*ADT/', $pax, $math);

                foreach ($math[1] as $p) {
                    $f->addTraveller($p);
                }
            }

            foreach ($roots as $root) {
                $s = $f->addSegment();

                $fl = $this->http->FindSingleNode("descendant::td[descendant::img and not(.//td)]/following-sibling::td[1]", $root);

                if (preg_match('/([a-z\s]+)\s+(\d{1,6})/i', $fl, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }

                $re = '/(?<d>\d{1,2})\s*(?<m>[a-z]+)\s*(?<t>\d{1,2}:\d{2}\s*[ap]m)\s*\-\s*(?<name>.+)\s*\((?<code>[A-Z]{3})\)/i';

                $dep = $this->http->FindSingleNode("descendant::tr[contains(normalize-space(.), 'Depart') and not(.//tr)]", $root);

                if (preg_match($re, $dep, $m)) {
                    $s->departure()
                        ->name($m['name'])
                        ->code($m['code'])
                        ->date(strtotime($m['d'] . ' ' . $m['m'] . ' ' . $year . ', ' . $m['t']));
                }

                $arr = $this->http->FindSingleNode("descendant::tr[contains(normalize-space(.), 'Arrive') and not(.//tr)]", $root);

                if (preg_match($re, $arr, $m)) {
                    $s->arrival()
                        ->name($m['name'])
                        ->code($m['code'])
                        ->date(strtotime($m['d'] . ' ' . $m['m'] . ' ' . $year . ', ' . $m['t']));
                }

                if ($bc = $this->getSNode('Class:', $root)) {
                    $s->extra()
                        ->bookingCode($bc);
                }

                if ($aircraft = $this->getSNode('Aircraft:', $root)) {
                    $s->extra()
                        ->aircraft($aircraft);
                }

                $op = $this->getSNode('operated by:', $root);

                if (preg_match('/[a-zA-Z\s]+\s+express\s+\-\s+([a-zA-Z\s]+)/', $op, $m) || preg_match('/([a-zA-Z\s]++)\s+airlines\s+dba\s+[a-zA-Z\s]++/', $op, $m)) {
                    $s->airline()
                        ->operator($m[1])
                        ->wetlease();
                }

                if (preg_match('/([a-z\s]+)\/[a-z\s]+\s*[\-]{1,5}\s*\b((?:[A-Z]\d{1,4}|[A-Z]{2}))\s*(\d+)\b/i', $op, $m)) {
                    $s->setCarrierAirlineName($m[2])
                        ->setCarrierFlightNumber($m[3]);
                    $s->airline()
                        ->operator($m[1])
                        ->wetlease();
                } elseif (preg_match('/[a-z\s]+\s*[\-]{1,5}\s*\b((?:[A-Z]\d{1,4}|[A-Z]{2}))\s*(\d+)\b/i', $op, $m)) {
                    $s->setCarrierAirlineName($m[1])
                        ->setCarrierFlightNumber($m[2]);
                }

                if (($seat = $this->getSNode('Seats:', $root, '/Seats\s*:\s*([A-Z\d,\s]+)/')) && !empty(trim($seat))) {
                    if (($seats = explode(', ', $seat)) && 1 < count($seats)) {
                        $s->extra()
                            ->seats($seats);
                    } else {
                        $s->extra()
                            ->seat($seat);
                    }
                }

                if ($miles = $this->getSNode('Miles:', $root)) {
                    $s->extra()
                        ->miles($miles);
                }

                if ($dur = $this->getSNode('Elapse:', $root)) {
                    $s->extra()
                        ->duration($dur);
                }

                if (empty($s->getDepName()) && count($dep = $this->http->FindNodes("descendant::tr[contains(normalize-space(.), 'Depart') and not(.//tr)]", $root)) > 1
                 && count($arr = $this->http->FindNodes("descendant::tr[contains(normalize-space(.), 'Arrive') and not(.//tr)]", $root)) > 1 && count($dep) == count($arr)) {
                    foreach ($dep as $key => $str) {
                        if ($key == 0) {
                            if (preg_match($re, $dep[$key], $m)) {
                                $s->departure()
                                    ->name($m['name'])
                                    ->code($m['code'])
                                    ->date(strtotime($m['d'] . ' ' . $m['m'] . ' ' . $year . ', ' . $m['t']));
                            }

                            if (preg_match($re, $arr[$key], $m)) {
                                $s->arrival()
                                    ->name($m['name'])
                                    ->code($m['code'])
                                    ->date(strtotime($m['d'] . ' ' . $m['m'] . ' ' . $year . ', ' . $m['t']));
                            }

                            continue;
                        }

                        $s2 = $f->addSegment();

                        if (preg_match($re, $dep[$key], $m)) {
                            $s2->departure()
                                ->name($m['name'])
                                ->code($m['code'])
                                ->date(strtotime($m['d'] . ' ' . $m['m'] . ' ' . $year . ', ' . $m['t']));
                        }

                        if (preg_match($re, $arr[$key], $m)) {
                            $s2->arrival()
                                ->name($m['name'])
                                ->code($m['code'])
                                ->date(strtotime($m['d'] . ' ' . $m['m'] . ' ' . $year . ', ' . $m['t']));
                        }

                        if ($s->getAirlineName() != false) {
                            $s2->airline()->name($s->getAirlineName());
                        }

                        if ($s->getFlightNumber() != false) {
                            $s2->airline()->number($s->getFlightNumber());
                        }

                        if ($s->getBookingCode() != false) {
                            $s2->extra()->bookingCode($s->getBookingCode());
                        }

                        if ($s->getAircraft() != false) {
                            $s2->extra()->aircraft($s->getAircraft());
                        }

                        if ($s->getOperatedBy() != false) {
                            $s2->airline()->operator($s->getOperatedBy());
                        }

                        if ($s->getIsWetlease() == true) {
                            $s2->airline()->wetlease(true);
                        }

                        if ($s->getCarrierAirlineName() != false) {
                            $s2->airline()->carrierName($s->getCarrierAirlineName());
                        }

                        if ($s->getCarrierFlightNumber() != false) {
                            $s2->airline()->carrierNumber($s->getCarrierFlightNumber());
                        }

                        if ($s->getSeats() != false) {
                            $s2->extra()->seats($s->getSeats());
                        }

                        if ($s->getMiles() != false) {
                            $s2->extra()->miles($s->getMiles());
                        }

                        if ($s->getDuration() != false) {
                            $s2->extra()->duration($s->getDuration());
                        }
                    }
                }
            }
        }

        return $email;
    }

    private function getSNode(string $str, \DOMNode $root, ?string $re = null): ?string
    {
        if (empty($re)) {
            $re = "/{$str}\s*(.+)/";
        }

        return $this->http->FindSingleNode("descendant::div[contains(normalize-space(.), '{$str}')][1]", $root, true, $re);
    }

    private function getNode(string $str, ?string $re = null): ?string
    {
        return $this->http->FindSingleNode("//node()[normalize-space(.)='{$str}']/following::text()[normalize-space(.)][1]", null, true, $re);
    }
}
