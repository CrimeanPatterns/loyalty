<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PDF extends \TAccountChecker
{
    public $mailFiles = "bcd/it-11483338.eml, bcd/it-11483405.eml, bcd/it-11483444.eml";
    public static $dictionary = [
        'en' => [
            // Flight
            //            "Itinerary" => "",
            //            "Flight duration" => "",
            //            "Cabin class" => "",
            //            "Booking class" => "",
            //            "Meal on board" => "",
            //            "Aircraft" => "",
            //            "Seat" => "",
            //            "Reservation number" => "",
            //            "Ticketnr:" => "",
            // Rental
            //            "Gesellschaft" => "",
            //            "Fahrzeuggruppe" => "",
            //            "Reservierungsnr." => "",
            //            "Telefon" => "",
            //            "Öffnungszeiten" => "",
            //            "Kategorie" => "",

            //            "Gesamtpreis lt. Buchung:" => "",
        ],
        'de' => [
            // Flight
            "Itinerary"       => "Reiseplan",
            "Flight duration" => "Flugdauer",
            "Cabin class"     => "Klasse",
            "Booking class"   => "Buchungsklasse",
            //            "Meal on board" => "",
            //            "Aircraft" => "",
            "Seat"               => "Sitzplatz",
            "Reservation number" => "Buchungsnr.",
            //            "Ticketnr:" => "",
            // Rental
            "Gesellschaft"     => "Gesellschaft",
            "Fahrzeuggruppe"   => "Fahrzeuggruppe",
            "Reservierungsnr." => "Reservierungsnr.",
            "Telefon"          => "Telefon",
            "Öffnungszeiten"   => "Öffnungszeiten",
            "Kategorie"        => "Kategorie",

            "Gesamtpreis lt. Buchung:" => "Gesamtpreis lt. Buchung:",
        ],
    ];

    /** @var \HttpBrowser */
    private $pdf;

    private $from = '/[@.]bcdtravel\.com/';

    private $detects = [
        'en' => 'The details of your booked journey follow. Please take the cancellation conditions and possible remarks of services',
        'de' => 'Nachfolgend finden Sie die Details zu Ihrer gebuchten Reise.',
    ];

    private $prov = 'BCD Travel';

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $pdfBody = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);

            foreach ($this->detects as $lang => $detect) {
                if (false !== stripos($pdfBody, $detect)) {
                    $this->lang = $lang;
                    $this->pdf = clone $this->http;
                    $this->pdf->SetEmailBody($pdfBody);
                    $this->parseEmail($email);
                }
            }
        }

        $path = explode('\\', __CLASS__);
        $email->setType(end($path) . ucfirst($this->lang));
    }

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdfs[0]) && 0 < count($pdfs)) {
            $pdfBody = \PDF::convertToHtml($parser->getAttachmentBody($pdfs[0]), \PDF::MODE_COMPLEX);

            foreach ($this->detects as $lang => $detect) {
                if (false !== stripos($pdfBody, $detect)) {
                    $this->lang = $lang;
                    $this->pdf = clone $this->http;
                    $this->pdf->SetEmailBody($pdfBody);
                    $this->http->SetEmailBody($pdfBody);
                }
            }
        }

        if (!($this->pdf instanceof \HttpBrowser)) {
            return null;
        }
        $class = explode('\\', __CLASS__);

        return [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() ?? $parser->getPlainBody();
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdfs[0]) && 0 < count($pdfs)) {
            $body .= \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));
        }

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

    private function parseEmail(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->pdf->FindSingleNode("(//p[" . $this->contains($this->t("Itinerary")) . "])[1]", null, true, '#' . $this->preg_implode($this->t("Itinerary")) . '\s*:\s*([A-Z\d]{5,11})#'));

        // Price
        $total = $this->pdf->FindSingleNode("//p[" . $this->contains($this->t("Gesamtpreis lt. Buchung:")) . "]", null, true, "#:\s*(.+)#");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $email->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        $passenger = $this->pdf->FindSingleNode("(//p[" . $this->contains($this->t("Itinerary")) . "])[1]/following-sibling::p[1]/text()[normalize-space(.)][1]");

        // FLIGHTS
        $xpath = "//p[" . $this->contains($this->t("Cabin class")) . " or " . $this->contains($this->t("Booking class")) . "]";
        $this->logger->debug('Flight xpath: ' . $xpath);
        $roots = $this->pdf->XPath->query($xpath);

        if ($roots->length > 0) {
            $f = $email->add()->flight();

            // General
            $confirmation = $this->pdf->FindSingleNode("//p[" . $this->contains($this->t("Flight duration")) . " and not(./following::*[position() < 20]/*[" . $this->contains($this->t("Flight duration")) . "])]/following::*[position() < 25][" . $this->contains($this->t("Reservation number")) . "]/following-sibling::p[1]", null, true, "#^\s*\w{5,}(?:$|\W)#u");
            $f->general()
                ->confirmation($confirmation)
                ->traveller($passenger)
            ;

            // Issued
            $ticket = trim($this->pdf->FindSingleNode("//p[" . $this->contains($this->t("Flight duration")) . " and not(./following::*[position() < 20]/*[" . $this->contains($this->t("Flight duration")) . "])]/following::*[position() < 25][" . $this->contains($this->t("Ticketnr:")) . "]", null, true, "#" . $this->preg_implode($this->t("Ticketnr:")) . "\s*(\d{3} *\d{10})\D#u"));

            if (!empty($ticket)) {
                $f->issued()->ticket($ticket, false);
            }

            $durations = $this->pdf->FindNodes("//p[" . $this->contains($this->t("Flight duration")) . "]/following-sibling::p[1]");

            if (count($durations) !== $roots->length) {
                $durations = [];
            }

            // Segments
            foreach ($roots as $root) {
                $s = $f->addSegment();

                if (preg_match('#' . $this->preg_implode($this->t('Cabin class')) . ':\s*(\w+),\s*' . $this->preg_implode($this->t('Booking class')) . '\s+([A-Z]{1,2})\b#isu', $root->nodeValue, $m)) {
                    $s->extra()
                        ->cabin($m[1])
                        ->bookingCode($m[2])
                    ;
                }

                $cnt1 = $this->pdf->XPath->query("preceding-sibling::p[contains(., ',') and contains(translate(., '0123456789', 'dddddddddd'), 'dddd')][2]/preceding::p", $root)->length;
                $cnt2 = $this->pdf->XPath->query('self::p/preceding::p', $root)->length;

                if ($cnt = $cnt2 - $cnt1) {
                    $info = implode(' ', $this->pdf->FindNodes("preceding-sibling::p[position() <= {$cnt}]", $root));
                    // Mon, 19.06.2017 07:35 Paris | CDG (Charles De Gaulle Terminal 2F) Air France AF1114 Mon, 19.06.2017 08:50 Zurich | ZRH (Zurich-Kloten)
                    $re = '/\w+, (?<DDate>\d{1,2}\.\d{1,2}\.\d{2,4} \d{1,2}:\d{2})\s+(?<DName>.+)\s*\|\s*(?<DCode>[A-Z]{3})\s+\(.+?(?:Terminal\s+(?<DTerm>[A-Z\d]{1,4}))?\)\s+.+\s+(?<AirName>[A-Z\d]{2})\s*(?<FNum>\d+)\s+\w+, (?<ADate>\d{1,2}\.\d{1,2}\.\d{2,4} \d{1,2}:\d{2})\s+(?<AName>.+)\s*\|\s*(?<ACode>[A-Z]{3})\s+\(.+?(?:Terminal\s+(?<ATerm>[A-Z\d]{1,4}))?\)/iu';

                    if (preg_match($re, $info, $m)) {
                        // Airline
                        $s->airline()
                            ->name($m['AirName'])
                            ->number($m['FNum'])
                        ;

                        // Departure
                        $s->departure()
                            ->date(strtotime($m['DDate']))
                            ->name(trim($m['DName']))
                            ->code($m['DCode'])
                            ->terminal(!empty($m['DTerm']) ? $m['DTerm'] : null, true, true)
                        ;

                        // Arrival
                        $s->arrival()
                            ->date(strtotime($m['ADate']))
                            ->name(trim($m['AName']))
                            ->code($m['ACode'])
                            ->terminal(!empty($m['ATerm']) ? $m['ATerm'] : null, true, true)
                        ;
                    }
                }

                $s->extra()
                    ->meal($this->pdf->FindSingleNode("(following-sibling::p[" . $this->contains($this->t("Meal on board")) . "]/following-sibling::p[1])[1]", $root), true, true)
                    ->aircraft($this->pdf->FindSingleNode("(following-sibling::p[" . $this->contains($this->t("Aircraft")) . "]/following-sibling::p[1])[1]", $root), true, true)
                ;

                if (count($durations) > 0) {
                    $s->extra()->duration(array_shift($durations));
                }
//                    $seg['Duration'] = array_shift($durations);
//                $meal = $this->pdf->FindSingleNode("(following-sibling::p[".$this->contains($this->t("Meal on board"))."]/following-sibling::p[1])[1]", $root);
//                if (!empty($meal)) {
//                }
//                    $seg['Meal'] = $meal;
//
//                if ( $aircraft = $this->pdf->FindSingleNode("(following-sibling::p[".$this->contains($this->t("Aircraft"))."]/following-sibling::p[1])[1]", $root) )
//                    $seg['Aircraft'] = $aircraft;
//
//                if ( 0 < count($durations) )
//                    $seg['Duration'] = array_shift($durations);
//
//                if ( $seat = $this->pdf->FindSingleNode("(following-sibling::p[".$this->contains($this->t("Seat"))."]/following-sibling::p[1])[1]", $root, true, '/([A-Z\d]{1,4})/') )
//                    $seg['Seats'][] = $seat;
            }
        }

        // RENTAL
        $xpath = "//p[" . $this->starts($this->t("Gesellschaft")) . "]";
        $this->logger->debug('Rental xpath: ' . $xpath);
        $roots = $this->pdf->XPath->query($xpath);

        if ($roots->length > 0) {
            $r = $email->add()->rental();

            // General
            $confirmation = $this->pdf->FindSingleNode("//p[" . $this->contains($this->t("Fahrzeuggruppe")) . "]/following::*[position() < 50][" . $this->contains($this->t("Reservierungsnr.")) . "]/following-sibling::p[1]", null, true, "#^\s*(.+?)\(#");
            $r->general()
                ->confirmation($confirmation)
                ->traveller($passenger)
            ;

            if ($roots->length == 2) {
                // Mo, 06.07.2020 18:00 Stuttgart Flughafen, Flughafenstr. 43/MWZ 70629 Stuttgart (Deutschland) Telefon: +49-8966060060 Öffnungszeiten: 07:00-23:59
                $re = '/\w+, (?<date>\d{1,2}\.\d{1,2}\.\d{2,4} \d{1,2}:\d{2})\s+(?<name>.+)\s+' . $this->preg_implode($this->t('Telefon')) . ':\s*(?<tel>[\d +\-\(\).]{5,})\s+' . $this->preg_implode($this->t('Öffnungszeiten')) . ':\s*(?<hours>.+)/iu';
                // Pick Up
                $cnt1 = $this->pdf->XPath->query("preceding-sibling::p[contains(., ',') and contains(translate(., '0123456789', 'dddddddddd'), 'dddd')][1]/preceding::p", $roots->item(0))->length;
                $cnt2 = $this->pdf->XPath->query('self::p/preceding::p', $roots->item(0))->length;

                if ($cnt = $cnt2 - $cnt1) {
                    $info = implode(' ', $this->pdf->FindNodes("preceding-sibling::p[position() <= {$cnt}]", $roots->item(0)));

                    if (preg_match($re, $info, $m)) {
                        $r->pickup()
                            ->date(strtotime($m['date']))
                            ->location($m['name'])
                            ->phone($m['tel'])
                            ->openingHours($m['hours'])
                        ;
                    }
                }

                // Gropp Off
                $cnt1 = $this->pdf->XPath->query("preceding-sibling::p[contains(., ',') and contains(translate(., '0123456789', 'dddddddddd'), 'dddd')][1]/preceding::p", $roots->item(1))->length;
                $cnt2 = $this->pdf->XPath->query('self::p/preceding::p', $roots->item(1))->length;

                if ($cnt = $cnt2 - $cnt1) {
                    $info = implode(' ', $this->pdf->FindNodes("preceding-sibling::p[position() <= {$cnt}]", $roots->item(1)));

                    if (preg_match($re, $info, $m)) {
                        $r->dropoff()
                            ->date(strtotime($m['date']))
                            ->location($m['name'])
                            ->phone($m['tel'])
                            ->openingHours($m['hours'])
                        ;
                    }
                }

                if (preg_match('#' . $this->preg_implode($this->t('Gesellschaft')) . '\s*(.+)\s*' . $this->preg_implode($this->t('Kategorie')) . '\s+(.+)#isu', $roots->item(0)->nodeValue, $m)) {
                    $r->extra()->company($m[1]);
                    $r->car()->type($m[2]);
                }
            } else {
                $this->logger->debug('not done for 2 or more car rental');
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function eq($field)
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            '#^\s*(\d{1,2})\s+([^\d\s]+)\s+(\d{4})\s*$#u', //10 Feb 2019
        ];
        $out = [
            '$1 $2 $3',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", trim($price))));

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
}
