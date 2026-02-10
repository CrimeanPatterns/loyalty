<?php

namespace AwardWallet\Engine\eurostar\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booked extends \TAccountChecker
{
    public $mailFiles = "eurostar/it-28364821.eml, eurostar/it-40322805.eml, eurostar/it-8115632.eml, eurostar/it-120946259.eml"; // +2 bcdtravel(html)[en,nl]

    public $reFrom = "/[.@]*eurostar\.com/i";
    public $reBody = [
        'nl' => [
            'Zo heb je voldoende tijd voor de ticketpoortjes',
            'Scan de barcode aan het ticketpoortje en presto',
            'Wist je dat je tickets kunt afdrukken',
            'Voor het vertrek moet elke barcode apart worden gescand',
            'ESSENTIËLE INFORMATIE OVER JE REIS',
            'ESSENTI?LE INFORMATIE OVER JE REIS',
        ],
        'fr' => ['Référence de réservation:'],
        'en' => [
            'Ticket gates close 30 minutes before departure',
            'Simple scan your barcode at the ticket gate and you',
            'Eurostar International Limited is an Appointed Representative',
            'Simply scan your barcode at the ticket gate and you\'re off',
            'you can print your tickets or simply show',
            'each barcode must be scanned individually',
            'ESSENTIAL INFORMATION FOR YOUR TRIP',
        ],
    ];
    public $reSubject = [
        'nl' => ['Hier zijn je Eurostar-tickets!'],
        'fr' => ['Voici vos billets d’Eurostar!'],
        'en' => ['Your booking is confirmed!', 'Your Eurostar booking confirmation', 'Your Eurostar Ticket'],
    ];
    public $lang = '';
    public static $dictionary = [
        'nl' => [
            "Booking reference:"=> "Boekingsreferentie:",
            "Total paid"        => "Totaal betaald",
            "Print Tickets"     => ["Naar tickets", "NAAR TICKETS"],
            "Coach"             => "Rijtuig",
            "seat"              => "plaats",
            "Direct"            => ["Direct", "Changes"],
            " to "              => [" naar ", " - "],
            "Going out"         => ["Heengaan", "Terugkomen", "Uitgaand", "Inkomend"],
            // Type 2
            //            "Departs"=>"",
            //            "Arrives"=>"",
            //            " at "=>"",
            //            "Journey Time:"=>"",
        ],
        'fr' => [
            "Booking reference:"=> "Référence de réservation:",
            //			"Total paid"=>"",
            "Print Tickets"=> ["Obtenir mes billets", "OBTENIR MES BILLETS"],
            "Coach"        => "Voiture",
            "seat"         => "place",
            //			"Direct"=>"",
            " to "     => [" à ", " - "],
            "Going out"=> ["Aller", "Retour", "Partir", "Revenir"],
            // Type 2
            //            "Departs"=>"",
            //            "Arrives"=>"",
            //            " at "=>"",
            //            "Journey Time:"=>"",
        ],
        'en' => [
            "Booking reference:"=> ["Booking reference:", "Booking Reference:", "Eurostar reference :"],
            "Total paid"        => ["Total paid", "Total Booking Value:"],
            "Print Tickets"     => ["Print Tickets", "PRINT TICKETS", "Get Tickets", "GET TICKETS"],
            //			"Coach"=>"",
            "seat"     => ["seat", "Seat"],
            "Direct"   => ["Direct", "Changes"],
            " to "     => [" to ", " To "],
            "Going out"=> ["Going out", "Coming back"],
            // Type 2
            //            "Departs"=>"",
            //            "Arrives"=>"",
            //            " at "=>"",
            //            "Journey Time:"=>"",
        ],
    ];

    private $type = '';

    private $date = null;

    private $xpathFragments = [];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $this->assignLang();

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $this->type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'eurostar')] | //img[contains(@src,'eurostar')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from'])
            && (preg_match($this->reFrom, $headers['from']) || strpos($headers["subject"], 'Eurostar') !== false)
            && isset($this->reSubject)
        ) {
            foreach ($this->reSubject as $phrases) {
                foreach ($phrases as $phrase) {
                    if (stripos($headers['subject'], $phrase) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reFrom, $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        if ($year = $this->http->FindSingleNode("//text()[" . $this->contains("All Rights Reserved") . "]", null, true, "#\b(\d{4})\b#")) {
            $this->date = strtotime($year . "-01-01");
        }

        $t = $email->add()->train();

        // General - Confirmation
        $bookingReference = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Booking reference:"))}]", null, true, "/{$this->preg_implode($this->t("Booking reference:"))}\s*([A-Z\d]{5,})$/");

        if (empty($bookingReference)) {
            $bookingReference = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Booking reference:"))}]/following::text()[normalize-space(.)][1]", null, true, '/^[A-Z\d]{5,}$/');
        }

        if (empty($bookingReference)) {
            $bookingReference = $this->http->FindSingleNode("//text()[{$this->eq($this->t('View in browser.'))}]/following::text()[{$this->contains($this->t('Booking reference:'))}][1]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');
        }
        $t->general()
            ->confirmation($bookingReference, trim($this->http->FindSingleNode("//text()[{$this->contains($this->t("Booking reference:"))}]", null, true, "/{$this->preg_implode($this->t("Booking reference:"))}/"), ':'), true);

        // Price
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->contains($this->t("Total paid"))}]/ancestor::*[(self::td or self::th) and ./following-sibling::*][1]/following-sibling::*[normalize-space(.)][1]"));

        if ($tot['Total'] !== null) {
            $t->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }

        $this->xpathFragments['coachSeat'] = $this->contains($this->t("Coach")) . ' and ' . $this->contains($this->t("seat"));

        // General - Passengers
        $passengers = $this->http->FindNodes("//img[contains(@alt, 'Print')]/ancestor::td/preceding-sibling::td[1]");

        if (empty($passengers)) {
            $passengers = $this->http->FindNodes("//a[{$this->contains($this->t("Print Tickets"))}]/ancestor::*[(self::td or self::th) and preceding-sibling::*][1]/preceding-sibling::*[self::td or self::th][1]", null, '/^\n*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])/u');
            $passengers = array_filter($passengers);
        }

        if (empty($passengers)) {
            $passengers = $this->http->FindNodes("//text()[{$this->contains($this->t("Print Tickets"))}]/ancestor::*[(self::td or self::th) and preceding-sibling::*][1]/preceding-sibling::*[self::td or self::th][1]", null, '/^\n*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])/u');
            $passengers = array_filter($passengers);
        }

        if (empty($passengers)) {
            // it-28364821.eml
            $passengerCells = $this->http->XPath->query("//tr[ not(.//tr) and ./*[1][normalize-space(.)] and ./*[2][./descendant::text()[{$this->xpathFragments['coachSeat']}]] ]/*[1]");

            foreach ($passengerCells as $passengerCell) {
                $passengersHtml = $passengerCell->ownerDocument->saveHTML($passengerCell);
                $passengersText = $this->htmlToText($passengersHtml);

                if (preg_match_all("/^ *([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]) *$/mu", $passengersText, $passengerMatches)) {
                    $passengers = array_merge($passengers, $passengerMatches[1]);
                }
            }
        }

        if (empty($passengers)) {
            // it-8115632.eml
            $passengers = $this->http->FindNodes("//text()[{$this->xpathFragments['coachSeat']}]/preceding::text()[normalize-space(.)][1]", null, '/^[[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]]$/u');
            $passengers = array_filter($passengers);
        }

        $t->general()
            ->travellers(array_unique($passengers));

        // Segments
        $xpathTime = "translate(normalize-space(),'0123456789','dddddddddd')='dd:dd'";

        $tSeg = $email->add()->train(); // for getting segments, then deteted
        $xpath = "//text()[{$xpathTime}]/ancestor::tr[{$this->contains($this->t("Direct"))}][1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length > 0) {
            $this->logger->debug('Segments type 1.1: ' . $xpath);
            $this->parseSegments1($t, $tSeg, $segments);
        }

        if ($segments->length === 0) {
            $xpath = "//img[contains(@src, 'arrow')]/ancestor::tr[4]";
            $segments = $this->http->XPath->query($xpath);

            if ($segments->length > 0) {
                $this->logger->debug('Segments type 1.2: ' . $xpath);
                $this->parseSegments1($t, $tSeg, $segments);
            }
        }

        if ($segments->length === 0) {
            $xpath = "//tr[ count(*)=3 and *[1][{$xpathTime}] and *[3][{$xpathTime}] ]/ancestor::tr[2]";
            $segments = $this->http->XPath->query($xpath);

            if ($segments->length > 0) {
                $this->logger->debug('Segments type 1.3: ' . $xpath);
                $this->parseSegments1($t, $tSeg, $segments);
            }
        }

        if ($segments->length === 0) {
            $xpath = "//tr[not(.//tr) and {$this->contains($this->t("Departs"))} and {$this->contains($this->t("Arrives"))}]/ancestor-or-self::tr[ ./preceding-sibling::tr[normalize-space(.)] ][1]";
            $segments = $this->http->XPath->query($xpath);

            if ($segments->length > 0) {
                $this->logger->debug('Segments type 2');
                $this->parseSegments2($t, $tSeg, $segments);
            }
        }

        foreach ($tSeg->getSegments() as $s) {
            $foundSegment = false;

            foreach ($t->getSegments() as $segment) {
                if (serialize(array_diff_key($segment->toArray(), ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                    $segment->extra()->seats(array_merge($segment->getSeats(), $s->getSeats()));
                    $foundSegment = true;

                    break;
                }
            }

            if ($foundSegment === false) {
                $t->addSegment()->fromArray($s->toArray());
            }
        }

        $email->removeItinerary($tSeg);

        return $email;
    }

    private function parseSegments1(Train $t, Train $tSeg, $segments)
    {
        $this->logger->warning(__METHOD__);
        // it-8115632.eml

        $this->type .= '1';

        $numpax = count($t->getTravellers());

        foreach ($segments as $root) {
            $s = $tSeg->addSegment();
            $seg = [];

            $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]", $root));

            if (empty($date)) {
                $date = $this->normalizeDate($this->http->FindSingleNode("preceding::tr[count(th)=2 or count(td)=2][1]/*[name() = 'th' or name() = 'td'][1]",
                    $root));
            }

            $dateDep = $dateArr = $nameDep = $nameArr = null;

            $node = $this->http->FindNodes(".//text()[normalize-space(.)]", $root);

            if (!empty($date) && count($node) === 4) {
                $dateDep = strtotime($node[0], $date);
                $dateArr = strtotime($node[3], $date);
                $s->extra()->duration($node[1]);
            } elseif (!empty($date) && count($node) === 8) {
                $dateDep = strtotime($node[0], $date);
                $dateArr = strtotime($node[1], $date);

                if ($node[5] !== 'Seat') {
                    $s->extra()
                        ->seat($node[5]);
                }

                $t->addTicketNumber(end($node), false);
            } elseif (!empty($date) && count($node) === 10 || count($node) === 12) {
                $dateDep = strtotime($node[0], $date);
                $dateArr = strtotime($node[1], $date);
                $s->extra()
                    ->number($node[3]);
                $t->addTicketNumber($node[9], false);

                $s->extra()
                    ->car($node[5]);
                $s->extra()
                    ->seat($node[7]);

                if (!empty($node[10]) && !empty($node[11])
                    && preg_match("/^{$this->preg_implode($this->t("PNR:"))}/", $node[10])
                    && !in_array($node[11], array_column($t->getConfirmationNumbers(), 0))
                ) {
                    // it-120946259.eml
                    $t->general()
                        ->confirmation($node[11], 'PNR');
                }
            }

            $xpathA = "preceding::text()[normalize-space()][2]/ancestor::tr[position()=1 and {$this->contains($this->t(" to "))}]";
            $xpathB = "preceding::tr[{$this->contains($this->t(" to "))} and .//tr][1]";

            $node = $this->http->FindSingleNode($xpathA . "/descendant::p[normalize-space()][1]", $root)
                ?? $this->http->FindSingleNode($xpathB . "/descendant::p[normalize-space()][1]", $root)
                ?? $this->http->FindSingleNode($xpathA . "/descendant::div[not(.//div[normalize-space()]) and normalize-space()][1]", $root)
                ?? $this->http->FindSingleNode($xpathB . "/descendant::div[not(.//div[normalize-space()]) and normalize-space()][1]", $root)
            ;

            if (preg_match("#(.*?){$this->preg_implode($this->t(" to "))}(.+)#", $node, $m)) {
                $nameDep = $m[1];
                $nameArr = $m[2];

                if (empty($s->getNumber())) {
                    $s->extra()->noNumber();
                }
            }

            $coach = [];
            $nodes = $this->http->FindNodes("./following::text()[{$this->xpathFragments['coachSeat']}][position()<={$numpax}]", $root);

            foreach ($nodes as $value) {
                if (preg_match("#{$this->preg_implode($this->t("Coach"))}\s+(\d+)\s*,?\s*{$this->preg_implode($this->t("seat"))}\s+(\d+)#i", $value, $m)) {
                    $s->extra()
                        ->seat($m[2]);
                    $coach[] = $m[1];
                }
            }

            $coach = array_unique($coach);

            if (count($coach) > 1 && empty($seg['Type'])) {
                $s->extra()
                    ->car(implode(', ', $coach));
            } elseif (count($coach) === 1 && empty($seg['Type'])) {
                $s->extra()
                    ->car(array_shift($coach));
            }

            $class = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)][3]/following::img[1]/@alt", $root);

            if (empty($class)) {
                $class = $this->http->FindSingleNode("preceding::*[self::th or self::td][{$this->contains($this->t("Going out"))}][1]/following-sibling::*[self::th or self::td][1]", $root);
            }

            if (empty($class)) {
                $class = $this->http->FindSingleNode("(./preceding::*[self::th or self::td][{$this->contains($this->t("Going out"))} and count(descendant::*[self::th or self::td])=0]/following::*[self::th or self::td and count(descendant::*[self::th or self::td])=0])[1]/descendant::img[contains(@src,'/img/')]/@src", $root, true, "#img\/(.+?)\-logo\.png#i");
            }

            if ($class && stripos($class, 'remove') === false) {
                $s->extra()
                    ->cabin($class);
            }

            $s->departure()->date($dateDep)->name($nameDep)->geoTip('Europe');

            if ($dateDep === $dateArr) {
                $tSeg->removeSegment($s);

                continue;
            } else {
                $s->arrival()->date($dateArr)->name($nameArr)->geoTip('Europe');
            }
        }
    }

    private function parseSegments2(Train $t, Train $tSeg, $segments)
    {
        // it-28364821.eml

        $this->logger->warning(__METHOD__);

        $this->type .= '2';

        foreach ($segments as $root) {
            $s = $t->addSegment();

            // FlightNumber
            $flight = $this->http->FindSingleNode('./preceding-sibling::tr[normalize-space(.)][2]/descendant::td[not(.//td) and normalize-space(.)][1]', $root);

            if (preg_match('/\b(?<airline>[A-Z]+)\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
                $s->extra()
                    ->service($matches['airline'])
                    ->number($matches['flightNumber']);
            }

            // Cabin
            $class = $this->http->FindSingleNode('./preceding-sibling::tr[normalize-space(.)][2]/descendant::td[not(.//td) and normalize-space(.)][2]', $root, true, '/^[\w\s]+$/u');

            if ($class) {
                $s->extra()
                    ->cabin($class);
            }

            // DepName
            // ArrName
            $route = $this->http->FindSingleNode('./preceding-sibling::tr[normalize-space(.)][1]', $root);

            if (preg_match("#(.*?)\s*{$this->preg_implode($this->t(" to "))}\s*(.+)#", $route, $m)) {
                $s->departure()
                    ->name($m[1]);
                $s->arrival()
                    ->name($m[2]);
            }

            $patterns['time'] = '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon|\s*午[前後])?'; // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon    |    3:10 午後

            // DepDate
            // ArrDate
            $pattern = "#"
                . "{$this->preg_implode($this->t("Departs"))}[:\s]+(?<dateDep>.+?)\s*{$this->preg_implode($this->t(" at "))}\s*(?<timeDep>{$patterns['time']})" // Departs : Thur 27 Dec at 15:31
                . "\s+"
                . "{$this->preg_implode($this->t("Arrives"))}[:\s]*{$this->preg_implode($this->t(" at "))}\s*(?<timeArr>{$patterns['time']})" // Arrives : at 18:47
                . "#";
            $dateTime = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t("Departs"))} and {$this->contains($this->t("Arrives"))}]", $root);

            if (empty($dateTime)) {
                $dateTime = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t("Departs"))}]/ancestor-or-self::*[{$this->contains($this->t("Arrives"))}][1]", $root);
            }

            if (preg_match($pattern, $dateTime, $m)) {
                $dateDep = $this->normalizeDate($m['dateDep']);

                if ($dateDep) {
                    $s->departure()
                        ->date(strtotime($m['timeDep'], $dateDep));
                    $s->arrival()
                        ->date(strtotime($m['timeArr'], $dateDep));
                }
            }

            // Duration
            $duration = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t("Journey Time:"))}]", $root, true, "/{$this->preg_implode($this->t("Journey Time:"))}\s*(\d.+)/");

            if (empty($duration)) {
                $duration = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t("Journey Time:"))}]/following::text()[normalize-space()][1]", $root, true, "/^\s*(\d.+)/");
            }

            if ($duration) {
                $s->extra()
                    ->duration($duration);
            }

            // Type
            // Seats
            $coach = [];

            $seatCells = $this->http->XPath->query("./following-sibling::tr[normalize-space(.)][1]/*[2]", $root);

            foreach ($seatCells as $seatCell) {
                $seatsHtml = $seatCell->ownerDocument->saveHTML($seatCell);
                $seatsText = $this->htmlToText($seatsHtml);

                if (preg_match_all("/^ *{$this->preg_implode($this->t("Coach"))}\s*(\d+)[,\s]+{$this->preg_implode($this->t("seat"))}\s*(\d+) *$/m", $seatsText, $seatMatches)) {
                    // Coach 008, Seat 78
                    $coach = array_merge($coach, $seatMatches[1]);
                    $s->extra()
                        ->seats($seatMatches[2]);
                }
            }

            $coach = array_unique($coach);

            if (count($coach) > 1) {
                $s->extra()
                    ->car(implode(', ', $coach));
            } elseif (count($coach) === 1) {
                $s->extra()
                    ->car(array_shift($coach));
            }
        }
    }

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        $in = [
            "#^(\d+)/(\d+)/(\d+)$#",
            "#^(?<week>[^\d\W]{2,})[,.\s]+(\d{1,2})\s+([^\d\W]{3,})$#u", // ma, 16 juli    |    Thur 27 Dec    |    dim., 18 novembre
        ];
        $out = [
            "$2/$1/$3",
            "$2 $3 %Y%",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short february
        if (strpos($str, "29 February") !== false && date('m/d', strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignLang(): bool
    {
        $body = $this->http->Response['body'];

        if (stripos($body, 'Eurostar') === false) {
            return false;
        }

        foreach ($this->reBody as $lang => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false
                    || $this->http->XPath->query('//node()[contains(normalize-space(),"' . $re . '")]')->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $node = str_replace("€", "EUR", $node);

        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['c']) ? $m['c'] : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
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
}
