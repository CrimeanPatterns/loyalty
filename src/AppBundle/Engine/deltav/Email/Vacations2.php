<?php

namespace AwardWallet\Engine\deltav\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Vacations2 extends \TAccountChecker
{
    public $mailFiles = "deltav/it-11217535.eml, deltav/it-17274490.eml, deltav/it-102178405.eml";

    private $status = '';
    private $dateBooled = '';
    private $passengers = [];
    private $accountNumbers = [];

    private $otaConf = [];
    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $statusVariants = "(not confirmed|confirmed)";
        $this->status = $this->http->FindSingleNode("//text()[normalize-space()='Reservation Status:']/following::text()[normalize-space()][1]", null, true, "/^{$statusVariants}$/i")
            ?? $this->http->FindSingleNode("//text()[contains(normalize-space(),'This itinerary is')]", null, true, "/This itinerary is\s+({$statusVariants})(?:\W|$)/i")
        ;

        $this->dateBooled = $this->http->FindSingleNode("//text()[normalize-space()='Date Booked:']/following::text()[normalize-space()][1]", null, true, "/^\d+\/\d+\/\d+$/");

        $passengers = $this->http->FindNodes("//tr[contains(., 'Travelers') and not(.//tr)]/following-sibling::tr[starts-with(normalize-space(.), '#')]/td[1]");
        array_walk($passengers, function ($val) {
            if (preg_match('/\#(\d+)\s+(.+)/i', $val, $m)) {
                $this->passengers[$m[1]] = $m[2];
            }
        });

        if (count($this->passengers) == 0) {
            $pax = $this->http->FindNodes("//tr[contains(., 'Traveler') and not(.//tr)]/following-sibling::tr[starts-with(normalize-space(.), '#')]/td[2]");

            foreach ($pax as $i => $p) {
                $this->passengers[$i + 1] = $p;
            }
        }

        $this->accountNumbers = array_values(array_filter($this->http->FindNodes("//tr[contains(., 'Traveler') and not(.//tr)]/following-sibling::tr[starts-with(normalize-space(.), '#')]/td[last()]", null, "#^\s*([\dA-Z]{5,})\b#")));
        $total = $this->http->FindSingleNode("//td[contains(., 'Payment Total')]/following-sibling::td[1]");

        if ($total === null) {
            $total = $this->http->FindSingleNode("//td[contains(., 'Discounted Package Price')]/following-sibling::td[1]");
        }

        if ($total === null) {
            $total = $this->http->FindSingleNode("//td[contains(., 'Package Price')]/following-sibling::td[1]");
        }
        $currency = '';
        $tot = 0;

        if (preg_match('/^(\D?)\s*(\d[,.\'\d]*)$/', $total, $m)) {
            // $3,708.89
            $email->price()
                ->currency($m[1] === '$' ? 'USD' : $m[1])
                ->total($this->normalizeAmount($m[2]));
        }

        $this->parseAir($email);

        $carRows = $this->http->XPath->query("//text()[starts-with(normalize-space(),'Car') or starts-with(normalize-space(),'Ground Transportation')]/ancestor::tr[1][starts-with(normalize-space(),'Date')]/ancestor::table[1]/descendant::tr[position()>1 and count(*[normalize-space()])>1 and (string-length(*[1])>5 or *[normalize-space()][3])]");

        if ($carRows->length > 0) {
            $this->parseCar($email, $carRows);
        }

        $hotelRows = $this->http->XPath->query("//text()[starts-with(normalize-space(),'Hotel')]/ancestor::tr[1][starts-with(normalize-space(),'Date')]/ancestor::table[1]/descendant::tr[position()>1 and descendant::text()[normalize-space()][1][not(normalize-space()='Date')] and count(*[normalize-space()])>1 and (string-length(*[1])>5 or *[normalize-space()][3])]");

        if ($hotelRows->length > 0) {
            $this->parseHotel($email, $hotelRows);
        }

        if (!empty($this->otaConf) && count($otaConf = array_filter(array_unique($this->otaConf))) == 1) {
            $email->ota()->confirmation($otaConf[0]);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && (stripos($headers['from'], 'DeltaVacations@mltvacations.com') !== false || stripos($headers['from'], '@deltavacations.com') !== false);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Thank you for choosing Delta Vacations') !== false
            || $this->http->XPath->query("//img[@alt='Delta Vacations' or @alt = 'Image removed by sender. Delta Vacations']")->length > 0
            || stripos($parser->getHTMLBody(), 'itinerary@deltavacations.com') !== false
            || $this->http->XPath->query("//img[contains(@src, 'deltavacations')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]delta\.com/", $from) > 0;
    }

    private function parseAir(Email $email): void
    {
        $rls = $this->http->XPath->query("//text()[starts-with(normalize-space(.),'Flight')]/ancestor::tr[1][starts-with(normalize-space(.),'Date')]/ancestor::table[1]/preceding-sibling::table[normalize-space(.)!=''][1]");

        foreach ($rls as $rl) {
            $f = $email->add()->flight();

            $this->otaConf[] = $this->http->FindSingleNode("//*[text()[contains(., 'Booking Number:')]]", null, true, "/Booking Number:\s*(\d+)/");

            if ($this->dateBooled) {
                $f->general()->date2($this->dateBooled);
            }

            if ($this->status) {
                $f->general()->status($this->status);
            }

            if (preg_match('/PNR\s+([A-Z\d]{6,7})/', $rl->nodeValue, $m)) {
                $f->general()
                    ->confirmation($m[1]);
            } elseif ($this->http->XPath->query("//*[contains(normalize-space(),'Traveler information is not available until the booking is completed')]")->length > 0) {
                $f->general()->noConfirmation();
            }
            $roots = $this->http->XPath->query("following-sibling::table[normalize-space(.)!=''][1]/descendant::tr[position()>1 and count(./td)>2]", $rl);

            foreach ($roots as $root) {
                $seg = [];
                $s = $f->addSegment();

                if (!preg_match("/(\d{1,2}-\w{3}-\d{1,4})/", $this->getNode($root), $date)) {
                    continue;
                }

                $nodeHtml = $this->http->FindHTMLByXpath('td[string-length(normalize-space())>1][2]', null, $root);
                $node = $this->htmlToText($nodeHtml);

                if (preg_match('/\s*(.+?)\s*\(([A-Z]{3})\)\s*to\s*(.+?)\s*\(([A-Z]{3})\)/u', $node, $m)) {
                    $s->departure()
                        ->name($m[1])
                        ->code($m[2]);

                    $s->arrival()
                        ->name($m[3])
                        ->code($m[4]);
                }

                // DL3422 - Operated by ENDEAVOR AIR DBA DELTA CONNECT
                // 10:19 PM - 11:51 PM
                if (preg_match('/(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<FlightNumber>\d{2,4})(?: - Operated by (?<Operator>.+?))?' .
                    '\s+(?<DepTime>\d+:\d+ (?:[AP]\.?M\.?|Noon))(?:\s+(?<DepDay>\+\d day))?' .
                    '\s*-\s*(?<ArrTime>\d+:\d+ (?:[AP]\.?M\.?|Noon))(?:\s+(?<ArrDay>\+\d day))?/i', $node, $m)) {
                    $s->airline()
                        ->name($m['AirlineName'])
                        ->number($m['FlightNumber']);

                    if (!empty($m['Operator'])) {
                        $s->airline()
                            ->operator($m['Operator']);
                    }

                    $depDate = strtotime($date[1] . ", " . str_replace('.', '', $m['DepTime']));
                    $arrDate = strtotime($date[1] . ", " . str_replace('.', '', $m['ArrTime']));

                    if (!empty($m['DepDay'])) {
                        $depDate = strtotime($m['DepDay'], $depDate);
                    }

                    if (!empty($m['ArrDay'])) {
                        $arrDate = strtotime($m['ArrDay'], $arrDate);
                    }

                    $s->departure()
                        ->date($depDate);
                    $s->arrival()
                        ->date($arrDate);
                }

                if (preg_match('#\s*Aircraft:\s+(.+)\s+([A-Z]{1,2})\s*/\s*(.+? Cabin)#', $node, $m)) {
                    $s->extra()
                        ->aircraft($m[1])
                        ->bookingCode($m[2])
                        ->cabin($m[3]);
                }

                if (preg_match('/non[ \-]*stop/i', $node, $m)) {
                    $s->extra()
                        ->stops('0');
                } elseif (preg_match('/Stops:\s*(\d{1,3})(?:\D|$)/', $node, $m)) {
                    $s->extra()
                        ->stops($m[1]);
                }

                if (empty($seg['Operator']) && preg_match("/^[ ]*Operated by[- ]+(.+?)[ ]*(?:--|$)/m", $node, $m)) {
                    // Operated by ENDEAVOR AIR DBA DELTA CONNECT    |    Operated by AIR FRANCE -- AF567
                    $s->airline()
                        ->operator($m[1]);
                }
            }

            if (count($this->accountNumbers) > 0) {
                $f->setAccountNumbers($this->accountNumbers, false);
            }

            if (count($this->passengers) > 0) {
                $f->general()->travellers($this->passengers);
            }
        }
    }

    private function parseHotel(Email $email, \DOMNodeList $roots): void
    {
        foreach ($roots as $root) {
            $h = $email->add()->hotel();

            $this->otaConf[] = $this->http->FindSingleNode("//*[text()[contains(., 'Booking Number:')]]", null, true, "/Booking Number:\s*(\d+)/");

            if ($this->status) {
                $h->general()->status($this->status);
            }

            if ($this->dateBooled) {
                $h->general()->date2($this->dateBooled);
            }

            $confirmation = $this->http->FindSingleNode('//td[contains(., "Hotel Confirmation") and not(.//td)]', null, true, '/Hotel Confirmation\s*\#\s*:\s*(\d+)$/');

            if (!empty($confirmation)) {
                $h->general()
                    ->confirmation($confirmation);
            } else {
                $h->general()->noConfirmation();
            }

            $node = $this->getNode($root, 2);

            if (preg_match('/Hotel\s+\d{1,3}\s*(.+?)(?:\s*Number of nights|\-\s*Price|\n|$)/i', $node, $m)) {
                $h->hotel()
                    ->name(trim($m[1], '-'))
                    ->noAddress();
            }

            $checkinDate = $this->getNode($root);
            $h->booked()
                ->checkIn(strtotime($checkinDate));

            if (preg_match("#Number of nights: (\d+)#i", $node, $m)) {
                $h->booked()
                    ->checkOut(strtotime("+ {$m[1]} days", $h->getCheckInDate()));
            }

            if (count($this->passengers) > 0) {
                $h->general()->travellers($this->passengers);
            }

            $guests = $this->http->FindSingleNode("self::*[ preceding-sibling::tr[normalize-space()][1][not(.//tr) and contains(normalize-space(),'Travelers') and count(td[string-length(normalize-space())>2])=3] ]/td[string-length(normalize-space())>2][last()]", $root);

            if (preg_match('/\b(\d{1,3}) adult/', $guests, $m)) {
                $h->booked()
                    ->guests($m[1]);
            }

            if (preg_match('/\d{1,2} adults, child age 13, child age 15/', $guests)) {
                $h->booked()
                    ->kids(2);
            }
        }
    }

    private function parseCar(Email $email, \DOMNodeList $roots): void
    {
        foreach ($roots as $root) {
            $r = $email->add()->rental();

            $orderPax = $this->http->FindSingleNode("./descendant::td[3]", $root, true, "/^(\d)$/");

            if (!empty($orderPax)) {
                $traveler = $this->http->FindSingleNode("//tr[contains(normalize-space(), 'Traveler Name')]/following::tr/descendant::td[normalize-space()='#{$orderPax}']/ancestor::tr[1]/descendant::td[2]");

                if (!empty($traveler)) {
                    $r->general()
                        ->traveller($traveler);
                }
            }

            $confirmation = $this->http->FindSingleNode("//text()[contains(., 'Booking Number')]/following-sibling::*[normalize-space(.)!=''][1]");

            if (empty($confirmation)) {
                $confirmation = $this->http->FindSingleNode("//text()[contains(., 'Car Confirmation')]", null, true, "#[\#:\s]*([A-Z\d]+)\s*$#");
            }

            if (!empty($confirmation)) {
                $r->general()
                    ->confirmation($confirmation);
            }

            if (empty($confirmation) && $this->http->XPath->query("//*[contains(normalize-space(),'Traveler information is not available until the booking is completed')]")->length > 0) {
                $r->general()
                    ->noConfirmation();
            }

            $total = $this->http->FindSingleNode("//tr[contains(normalize-space(.), 'Price in USD') and count(td[string-length(normalize-space(.))>2])=3]/following-sibling::tr[1]/td[string-length(normalize-space(.))>2][last()]");

            if (preg_match('/(\D+)[ ]*([\d.]+)/', $total, $m)) {
                $r->price()
                    ->currency(str_replace('$', 'USD', $m[1]))
                    ->total($m[2]);
            }

            $info = implode("\n", $this->http->FindNodes("./td[2]//text()", $root));

            if (preg_match("#(?<company>.+?) - (?<type>.+?) \((?<model>.+?)\)?\s+Pick up (?<puDate>.+?) at (?<puLocation>.+)\s+Drop off (?<doDate>.+?) at (?<doLocation>.+)#", $info, $m)) {
                $r->pickup()
                    ->date(strtotime($m['puDate']))
                    ->location($m['puLocation']);
                $r->dropoff()
                    ->date(strtotime($m['doDate']))
                    ->location($m['doLocation']);

                $r->setCompany($m['company']);
                $r->car()
                    ->type($m['type'])
                    ->model($m['model']);
            } elseif (preg_match('/(?<company>.+?) (?<type>.+?-size.+) \((?<model>.+?)\)?\s+Pick up (?<puDate>.+?) at (?<puTime>\d{1,2}:\d{2} [AP]M) at (?<puLocation>.+)\s+Drop off (?<doDate>.+?) at (?<doTime>\d{1,2}:\d{2} [AP]M) at (?<doLocation>.+)/', $info, $m)) {
                $r->pickup()
                    ->date(strtotime($m['puDate'] . ', ' . $m['puTime']))
                    ->location($m['puLocation']);

                $r->dropoff()
                    ->date(strtotime($m['doDate'] . ', ' . $m['doTime']))
                    ->location($m['doLocation']);

                $r->setCompany($m['company']);

                $r->car()
                    ->type($m['type'])
                    ->model($m['model']);
            }
            // Alamo Rent A Car Premium elite SUV 5-Door/Automatic/Air (BMW X3 or similar)
            // Pick up Sat 6-Jun-20 at 3:17 PM at ORLANDO INTL ARPT
            // Drop off Fri 12-Jun-20 at 5:45 PM at ORLANDO INTL ARPT
            elseif (preg_match("#(?<company>.+?) (?:Premium elite) (?<type>.+?) \((?<model>.+?)\)?\s+Pick up (?<puDate>.+?[AP]M) at (?<puLocation>.+)\s+Drop off (?<doDate>.+?[AP]M) at (?<doLocation>.+)#", $info, $m)) {
                $r->pickup()
                    ->date(strtotime(str_replace(' at ', ', ', $m['puDate'])))
                    ->location($m['puLocation']);

                $r->dropoff()
                    ->date(strtotime(str_replace(' at ', ', ', $m['doDate'])))
                    ->location($m['doLocation']);

                $r->setCompany($m['company']);

                $r->car()
                    ->type($m['type'])
                    ->model($m['model']);
            }

            $pNum = $this->getNode($root, 'last()');

            if (isset($this->passengers[$pNum])) {
                $r->general()
                    ->travellers($this->passengers[$pNum]);
            }
        }
    }

    private function getNode(\DOMNode $root, $td = 1)
    {
        return $this->http->FindSingleNode('descendant::td[string-length(normalize-space(.))>1][' . $td . ']', $root);
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
}
