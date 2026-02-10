<?php

namespace AwardWallet\Engine\delta\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: it's better remake parsing, without \PDF::convertToHtml
class PDF extends \TAccountChecker
{
    public $mailFiles = "delta/it-17528408.eml, delta/it-22330889.eml, delta/it-35075275.eml, delta/it-117362773.eml";

    public $lang = 'en';

    public static $dict = [
        'en' => [
            'FLIGHT'             => ['FLIGHT', 'Flight'],
            'SEAT:'              => ['SEAT:', 'SEATS:'],
            'travellerStopWords' => ['NUMBER', 'PASSENGER', 'NAME', 'FLIGHT', 'SEATS', 'EXTRAS', 'SPECIAL', 'SERVICE', 'REQUESTS', 'Print My Trips', '.delta.'],
        ],
    ];

    private $detects = [
        'FLIGHT CONFIRMATION #',
    ];

    private $from = '/[@e\.]*delta[.]com/';

    /** @var \HttpBrowser */
    private $pdf;

    private $textPdf = '';

//    private function normalizeDate($text)
//    {
//        if ( empty($text) )
//            return '';
//        $in = [
//            // Sat 24 Aug 2019 - 06:30 PM
//            '/^(?:[[:alpha:]]{2,}\s*)?(\d{1,2}\s+[[:alpha:]]{3,}\s+\d{2,4})[-\s]+(\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)$/u',
//        ];
//        $out = [
//            '$1, $2',
//        ];
//        return preg_replace($in, $out, $text);
//    }

    /**
     * @return array|Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (0 < count($pdfs)) {
            $pdf = \PDF::convertToHtml($parser->getAttachmentBody($pdfs[0]));
            $NBSP = chr(194) . chr(160);
            $pdf = str_replace($NBSP, ' ', html_entity_decode($pdf));

            foreach ($this->detects as $detect) {
                if (false !== stripos($pdf, $detect)) {
                    $this->pdf = clone $this->http;
                    $this->pdf->SetEmailBody($pdf);
                    $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));
                    $textPdf = str_replace('&#160;', ' ', $textPdf);
                    $this->textPdf = $textPdf;
                    $this->parseEmail($email);

                    break;
                }
            }
        }
        $ns = explode('\\', __CLASS__);
        $class = end($ns);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (0 < count($pdfs)) {
            $pdf = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));
            $pdf = str_replace('&#160;', ' ', $pdf);

            foreach ($this->detects as $detect) {
                if (false !== stripos($pdf, $detect)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email): void
    {
        $patterns = [
            'confNumber'    => '[A-Z\d]{5,}', // 11986371476    |    M5GPQK
            'travellerName' => '(?:[A-Z][-.\'A-Z]* )+', // MR. HAO-LI HUANG
        ];

        // remove page headers & footers
        $nodesToStip = $this->pdf->XPath->query("//p[{$this->starts('http')} and {$this->contains('delta.com/')}] | //p[{$this->eq('Print My Trips')}]");

        foreach ($nodesToStip as $nodeToStip) {
            $nodeToStip->parentNode->removeChild($nodeToStip);
        }

        // smart sorting paragraphs
        $pages = $this->pdf->XPath->query("//div[starts-with(@id,'page')]");

        foreach ($pages as $page) {
            $pageContent = [];
            $paragraphs = $this->pdf->XPath->query("p", $page);

            foreach ($paragraphs as $p) {
                $pTexts = $this->pdf->FindNodes("descendant::text()", $p);
                $pTexts = array_map(function ($item) {
                    return '<span>' . htmlspecialchars($item) . '</span>';
                }, $pTexts);

                $pageContent[preg_replace('/.*top[ ]*:[ ]*(\d+)px[ ]*;[ ]*left[ ]*:[ ]*(\d+)px.*/i', '$1.$2', $this->pdf->FindSingleNode('@style', $p))]
                    = '<p>' . implode("\n", $pTexts) . '</p>';
            }
            ksort($pageContent, SORT_NATURAL);

            $html = '<div id="' . $this->pdf->FindSingleNode('@id', $page) . '" style="' . $this->pdf->FindSingleNode('@style', $page) . '">'
                . implode("\n", $pageContent)
                . '</div>';
            $htmlFragment = $this->pdf->DOM->createDocumentFragment();

            if ($htmlFragment->appendXML($html)) {
                $page->parentNode->replaceChild($htmlFragment, $page);
            }
        }
//        var_dump( $this->pdf->DOM->saveHTML() );

        $dvConfirmationTitle = $this->pdf->FindSingleNode("//p[{$this->starts($this->t('DELTA VACATIONS CONFIRMATION #'))}]");
        $dvConfirmation = $this->pdf->FindSingleNode("//p[{$this->starts($this->t('DELTA VACATIONS CONFIRMATION #'))}]/following::p[normalize-space()][1]", null, true, "/^\s*({$patterns['confNumber']})\s*$/");

        if (empty($dvConfirmation)) {
            $dvConfirmation = $this->pdf->FindSingleNode("//p[{$this->starts($this->t('DELTA VACATIONS CONFIRMATION #'))}]/preceding::p[normalize-space()][1]", null, true, "/^\s*({$patterns['confNumber']})\s*$/");
        }

        if (empty($dvConfirmation) && preg_match("/({$this->opt($this->t('DELTA VACATIONS CONFIRMATION #'))})\s*:*\s*({$patterns['confNumber']})$/m", $this->textPdf, $matches)) {
            $dvConfirmationTitle = $matches[1];
            $dvConfirmation = $matches[2];
        }

        ////////////
        // FLIGHT //
        ////////////

        $f = $email->add()->flight();

        // confirmationNumbers
        $confirmationTitle = $this->pdf->FindSingleNode("//p[{$this->starts($this->t('FLIGHT CONFIRMATION #'))}]");
        $confirmation = $this->pdf->FindSingleNode("//p[{$this->starts($this->t('FLIGHT CONFIRMATION #'))}]/following::p[not(contains(normalize-space(),'number'))][1]", null, true, "/^\s*({$patterns['confNumber']})\s*$/");

        if (empty($confirmation)) {
            $confirmation = $this->pdf->FindSingleNode("//p[{$this->starts($this->t('FLIGHT CONFIRMATION #'))}]/preceding::p[not(contains(normalize-space(),'number'))][1]", null, true, "/^\s*({$patterns['confNumber']})\s*$/");
        }

        if (empty($confirmation) && preg_match("/({$this->opt($this->t('FLIGHT CONFIRMATION #'))})\s*:*\s*({$patterns['confNumber']})$/m", $this->textPdf, $matches)) {
            $confirmationTitle = $matches[1];
            $confirmation = $matches[2];
        }
        $f->general()->confirmation($confirmation, preg_replace('/\s*:+\s*$/', '', $confirmationTitle));

        if (!empty($dvConfirmation)) {
            $f->addConfirmationNumber($dvConfirmation, preg_replace('/\s*:+\s*$/', '', $dvConfirmationTitle));
        }

        // travellers
        $psngText = $this->cutText('PASSENGER INFORMATION', 'Complete Delta Air Lines Baggage Information', $this->textPdf);
        $psngText = preg_replace("/^.*\b{$this->opt($this->t('travellerStopWords'))}\b.*\n*/im", '', $psngText); // remove garbage rows
        $psngText = preg_replace("/^[ ]{4,}[A-Z]{3} (?:[>►]+|to)? [A-Z]{3}\b.*\n*/m", '', $psngText); // remove empty rows

        if (preg_match_all('/^[ ]*(?:\d{1,3}[ ]{2,})?(' . $patterns['travellerName'] . ')(?:[ ]{2,}.*$|$)\n([ ]{0,40}' . $patterns['travellerName'] . ')?(?:[ ]{2,}.*$|$)/m', $psngText, $m)) {
            foreach ($m[1] as $key => $value) {
                $travellers[] = trim($value) . ((!empty($m[2][$key])) ? ' ' . trim($m[2][$key]) : '');
            }
            $travellers = array_values(array_filter(array_unique($travellers)));
            $f->general()
                ->travellers($travellers);
        }

        // accountNumbers
        if (preg_match_all('/SkyMiles[ ]*#[ ]*([A-Z\d]*\d{6,}[A-Z\d]*|[*]{2,}[A-Z\d]+)(?:[ ]{2,}|$)/im', $psngText, $accountMatches)) {
            // SkyMiles # 2678335338    |    SkyMiles # ******9645
            foreach ($accountMatches[1] as $account) {
                $f->program()->account($account, preg_match("/^[*]{2,}[A-Z\d]+$/", $account) > 0);
            }
        }

        // ticketNumbers
        if (preg_match_all('/eTicket[ ]*#[ ]*(\d[-\d]*\d{7,}[-\d]*)(?:[ ]{2,}|$)/im', $psngText, $m)) { // eTicket # 0067162815761
            $f->setTicketNumbers($m[1], false);
        }

        $xpath = "//text()[starts-with(normalize-space(.),'DEPART:')]";
        $segments = $this->pdf->XPath->query($xpath);

        if (0 === $segments->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }
        $textSegments = $this->splitter("#\n([ ]*(?:FLIGHT|Flight) (?:[A-Z\d][A-Z]|[A-Z][A-Z\d]) \d+[ ]*)#", $this->textPdf);

        foreach ($segments as $segNum=> $root) {
            $s = $f->addSegment();

            // depDate
            // arrDate
            $date = 0;
            $dateTexts = $this->pdf->FindNodes('preceding::p[position()<20]', $root, '/(\w+, \d{1,2} \w+ \d{2,4})/');
            $dateValues = array_values(array_filter(array_reverse($dateTexts)));

            if (!empty($dateValues[0])) {
                $date = strtotime($dateValues[0]);
            }

            $depTime = $this->pdf->FindSingleNode('following::text()[normalize-space()][1]', $root, true, "/^\s*(\d{1,2}:\d{2} [ap]m)\s*$/i")
                ?? $this->pdf->FindSingleNode('.', $root, true, "/{$this->opt($this->t('DEPART:'))}\s*(\d{1,2}:\d{2} [ap]m)/i")
            ;

            $arrTimeUp = $this->pdf->FindSingleNode("preceding::text()[normalize-space()][position()<8][{$this->eq($this->t('ARRIVE:'))}]/following::text()[normalize-space()][1]", $root, true, "/^\s*(\d{1,2}:\d{2} [ap]m)\s*$/i")
                ?? $this->pdf->FindSingleNode("preceding::text()[normalize-space()][position()<8][{$this->starts($this->t('ARRIVE:'))}]", $root, true, "/{$this->opt($this->t('ARRIVE:'))}\s*(\d{1,2}:\d{2} [ap]m)/i")
            ;
            $arrTimeDown = $this->pdf->FindSingleNode("following::text()[normalize-space()][position()<8][{$this->eq($this->t('ARRIVE:'))}]/following::text()[normalize-space()][1]", $root, true, "/^\s*(\d{1,2}:\d{2} [ap]m)\s*$/i")
                ?? $this->pdf->FindSingleNode("following::text()[normalize-space()][position()<8][{$this->starts($this->t('ARRIVE:'))}]", $root, true, "/{$this->opt($this->t('ARRIVE:'))}\s*(\d{1,2}:\d{2} [ap]m)/i")
            ;
            $arrTime = ($arrTimeUp || $arrTimeDown) && ($arrTimeUp === null || $arrTimeDown === null) ? ($arrTimeUp ?? $arrTimeDown) : null;

            if (!$depTime && !$arrTime
                && $this->pdf->XPath->query("following::text()[normalize-space()][1][{$this->eq($this->t('ARRIVE:'))}]", $root)->length > 0
            ) {
                $timeUpTexts = $this->pdf->FindNodes('preceding::p[position()<3]', $root, '/(\d{1,2}:\d{2} [ap]m)/i');
                $timeUpValues = array_values(array_filter($timeUpTexts));

                if (count($timeUpValues) === 2) {
                    $depTime = $timeUpValues[0];
                    $arrTime = $timeUpValues[1];
                }
            }

            if ($date && $depTime) {
                $s->departure()->date(strtotime($depTime, $date));
            }

            if ($date && $arrTime) {
                $s->arrival()->date(strtotime($arrTime, $date));
            }

            // depCode
            // arrCode
            $airportCodesTexts = $this->pdf->FindNodes('preceding::p[position()<15]', $root);

            if (preg_match('/^([A-Z]{3})\b[>►\s]*(?:to)?[>►\s]*\b([A-Z]{3})$/m', implode("\n", $airportCodesTexts), $matches)) {
                $s->departure()->code($matches[1]);
                $s->arrival()->code($matches[2]);
                // try to find terminals from textPdf
                $pos = [];

                if (isset($textSegments[$segNum])
                    && (
                        preg_match("#\n[ ]*{$matches[1]}[ ]+>[ ]+{$matches[2]}[ ]*\n#", $textSegments[$segNum])
                        || preg_match("#\n[ ]*{$matches[1]}[ ]+{$matches[2]}(?:[ ]+►)?[ ]*\n#", $textSegments[$segNum])
                    )
                ) {
                    $table = $this->re("#\n([ ]*DEPART[^\n]*[ ]{3,}ARRIVE.*)#s", $textSegments[$segNum]);
                    $pos[] = 0;
                    $pos[] = mb_strlen($this->re("#([ ]*DEPART[^\n]*[ ]{3,})ARRIVE.*#", $table));
                    $last = array_map('mb_strlen', array_filter([
                        $this->re("/\n(.*[ ]{3,})Aircraft:/", $textSegments[$segNum]),
                        $this->re("/\n(.*[ ]{3,})Flight Time:/", $textSegments[$segNum]),
                        $this->re("/\n(.* )Miles Flown:/", $textSegments[$segNum]),
                    ]));

                    if (count($last)) {
                        sort($last);
                        $pos[] = array_shift($last);
                        $table = $this->splitCols($table, $pos);
                        $depTerm = $this->re("#TERMINAL (.+)#", $table[0]);

                        if (!empty($depTerm)) {
                            $s->departure()->terminal($depTerm);
                        }
                        $arrTerm = $this->re("#TERMINAL (.+)#", $table[1]);

                        if (!empty($arrTerm)) {
                            $s->arrival()->terminal($arrTerm);
                        }
                        $meal = trim(preg_replace("#\s+#", ' ',
                            $this->re("#MEAL SERVICES\s*:\s*(.+?)\s+(?:On Time|In-Flight services)#s", $table[1])));

                        if (!empty($meal)) {
                            $s->extra()->meal($meal);
                        }
                    }
                }
            }

            $flightTexts = $this->pdf->FindNodes("preceding::p[position()<24][{$this->starts($this->t('FLIGHT'))}]", $root);

            if (preg_match('/' . $this->opt($this->t('FLIGHT')) . '[ ]+([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)/', implode("\n", array_reverse($flightTexts)), $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $operated = $this->pdf->FindSingleNode("preceding::p[position()<18][{$this->starts($this->t('Operated by:'))}]", $root, true, "/{$this->opt($this->t('Operated by:'))}\s*([^()]+)/");

            if ($operated) {
                $s->airline()
                    ->operator($operated);
            }

            // seats
            if ($this->pdf->XPath->query("preceding::p[position()<10][{$this->starts($this->t('SEAT:'))}]", $root)->length > 0) {
                $seatTexts = [];
                $seatRows = $this->pdf->FindNodes("preceding::p[position()<10]", $root);

                foreach (array_reverse($seatRows) as $row) {
                    if (preg_match("/^(?:{$this->opt($this->t('SEAT:'))}\s*)?(\d[,A-Z\d ]*[A-Z])$/", $row, $m)) {
                        $seatTexts[] = $m[1];
                    }

                    if (preg_match("/^{$this->opt($this->t('SEAT:'))}/", $row)) {
                        break;
                    }
                }
                $seatText = implode(', ', array_reverse($seatTexts));
                $seats = preg_split('/\s*,\s*/', $seatText);

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
            }

            $patterns['cabin'] = '(([A-Z\s]+)\s*\S*\s*\(\s*([A-Z]{1,2})\s*\))'; // DELTA COMFORT+® ( W )

            // cabin
            // bookingCode
            $cabinUp = $this->pdf->FindSingleNode("preceding::text()[normalize-space()][position()<5][contains(normalize-space(),'(')]", $root, true, "/^{$patterns['cabin']}$/");
            $cabinDown = $this->pdf->FindSingleNode("following::text()[normalize-space()][position()<10][contains(normalize-space(),'(')]", $root, true, "/^{$patterns['cabin']}$/");

            if (($cabinUp === null || $cabinDown === null)
                && preg_match("/^{$patterns['cabin']}$/", $cabinUp ?? $cabinDown, $m)
            ) {
                $s->extra()->cabin($m[2])->bookingCode($m[3]);
            }

            // aircraft
            $aircraftUp = $this->pdf->FindSingleNode("preceding::text()[normalize-space()][position()<14][{$this->eq($this->t('Aircraft:'))}]/following::text()[normalize-space()][1]", $root, true, "/^[^:]{2,}$/")
                ?? $this->pdf->FindSingleNode("preceding::text()[normalize-space()][position()<14][{$this->starts($this->t('Aircraft:'))}]", $root, true, "/^{$this->opt($this->t('Aircraft:'))}\s*([^:]{2,})$/")
            ;
            $aircraftDown = $this->pdf->FindSingleNode("following::text()[normalize-space()][position()<14][{$this->eq($this->t('Aircraft:'))}]/following::text()[normalize-space()][1]", $root, true, "/^[^:]{2,}$/")
                ?? $this->pdf->FindSingleNode("following::text()[normalize-space()][position()<14][{$this->starts($this->t('Aircraft:'))}]", $root, true, "/^{$this->opt($this->t('Aircraft:'))}\s*([^:]{2,})$/")
            ;

            if ($aircraftUp === null || $aircraftDown === null) {
                $s->extra()->aircraft($aircraftUp ?? $aircraftDown, true);
            }

            // duration
            $flightTimeUp = $this->pdf->FindSingleNode("preceding::text()[normalize-space()][position()<15][{$this->eq($this->t('Flight Time:'))}]/following::text()[normalize-space()][1]", $root, true, "/^\s*(\d.+)/")
                ?? $this->pdf->FindSingleNode("preceding::text()[normalize-space()][position()<15][{$this->starts($this->t('Flight Time:'))}]", $root, true, "/^{$this->opt($this->t('Flight Time:'))}\s*(\d.+)/")
            ;
            $flightTimeDown = $this->pdf->FindSingleNode("following::text()[normalize-space()][position()<15][{$this->eq($this->t('Flight Time:'))}]/following::text()[normalize-space()][1]", $root, true, "/^\s*(\d.+)/")
                ?? $this->pdf->FindSingleNode("following::text()[normalize-space()][position()<15][{$this->starts($this->t('Flight Time:'))}]", $root, true, "/^{$this->opt($this->t('Flight Time:'))}\s*(\d.+)/")
            ;

            if ($flightTimeUp === null || $flightTimeDown === null) {
                $s->extra()->duration($flightTimeUp ?? $flightTimeDown, true);
            }

            // miles
            $milesUp = $this->pdf->FindSingleNode("preceding::text()[normalize-space()][position()<16][{$this->eq($this->t('Miles Flown:'))}]/following::text()[normalize-space()][1]", $root, true, "/^\s*(\d[,.\d]*)\s*$/")
                ?? $this->pdf->FindSingleNode("preceding::text()[normalize-space()][position()<16][{$this->starts($this->t('Miles Flown:'))}]", $root, true, "/^{$this->opt($this->t('Miles Flown:'))}\s*(\d[,.\d]*)\s*$/")
            ;
            $milesDown = $this->pdf->FindSingleNode("following::text()[normalize-space()][position()<16][{$this->eq($this->t('Miles Flown:'))}]/following::text()[normalize-space()][1]", $root, true, "/^\s*(\d[,.\d]*)\s*$/")
                ?? $this->pdf->FindSingleNode("following::text()[normalize-space()][position()<16][{$this->starts($this->t('Miles Flown:'))}]", $root, true, "/^{$this->opt($this->t('Miles Flown:'))}\s*(\d[,.\d]*)\s*$/")
            ;

            if ($milesUp === null || $milesDown === null) {
                $s->extra()->miles($milesUp ?? $milesDown, true);
            }
        }

        //////////
        // CARS //
        //////////
        // TODO: https://redmine.awardwallet.com/issues/16992#note-45
//        $cars = $this->pdf->XPath->query("//p[{$this->eq($this->t('PICK UP'))}]/following::p[normalize-space()][1][{$this->eq($this->t('DROP OFF'))}]");
//        if ($cars->length === 0)
//            $cars = $this->pdf->XPath->query("//p[{$this->eq($this->t('DROP OFF'))}]/following::p[normalize-space()][1][{$this->eq($this->t('PICK UP'))}]");
//        foreach ($cars as $root) {
//            $rental = $email->add()->rental();
//
//            $confNumber = $this->pdf->FindSingleNode("preceding::text()[normalize-space()][position()<14][{$this->eq($this->t('RENTAL CAR'))}]/following::text()[normalize-space()][1]", $root, true, "/^\s*({$patterns['confNumber']})\s*$/");
//            $rental->general()->confirmation($confNumber);
//
//            // required locations
//
//            $date1 = strtotime( $this->normalizeDate($this->pdf->FindSingleNode("following::text()[normalize-space()][1]", $root)) );
//            $date2 = strtotime( $this->normalizeDate($this->pdf->FindSingleNode("following::text()[normalize-space()][2]", $root)) );
//            if ($date1 && $date2) {
//                if ($date1 < $date2) {
//                    $rental->pickup()->date($date1);
//                    $rental->dropoff()->date($date2);
//                } else {
//                    $rental->pickup()->date($date2);
//                    $rental->dropoff()->date($date1);
//                }
//            }
//        }
    }

    private function getNode(\DOMNode $root, $s, bool $revert = false, ?string $re = null, string $contains = '[normalize-space(.)]'): ?string
    {
        if (!$revert) {
            $anchorForSegment = $this->pdf->XPath->query("following::text()[{$this->starts(['Baggage & Service Fees', 'In-Flight services and amenities'])}][1]/preceding::text()[normalize-space(.)]", $root)->length;

            return $this->pdf->FindSingleNode("following::text()[normalize-space(.)][position()<{$anchorForSegment}][{$this->starts($s)}][1]/following::text(){$contains}[1]", $root, true, $re);
        } else {
            return $this->pdf->FindSingleNode("preceding::p[position()<15][{$this->starts($s)}][1]", $root, true, $re);
        }
    }

    private function cutText(string $start, $end, string $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return false;
        }

        if (is_array($end)) {
            $begin = stristr($text, $start);

            foreach ($end as $e) {
                if (stristr($begin, $e, true) !== false) {
                    return stristr($begin, $e, true);
                }
            }
        }

        return stristr(stristr($text, $start), $end, true);
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
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

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function splitCols($text, $pos = false)
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
