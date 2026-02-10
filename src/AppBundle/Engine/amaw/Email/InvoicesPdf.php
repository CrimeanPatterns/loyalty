<?php

namespace AwardWallet\Engine\amaw\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class InvoicesPdf extends \TAccountChecker
{
    public $mailFiles = "amaw/it-136280398.eml, amaw/it-672541707.eml, amaw/it-675044266-cancelled.eml, amaw/it-695456410.eml, amaw/it-698366035.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Reservation ID:'   => ['Reservation ID:'],
            'cancelledStatuses' => ['Cancelled', 'Canceled'],

            // CRUISE
            'Disembark' => ['Disembark'],

            // HOTELS
            'guests' => ['Guests', 'Guest'],

            // FLIGHT
            // '' => '',
        ],
    ];

    private $travellers = [];
    private $currencyCode = null;

    private $patterns = [
        'date'       => '\b\d{1,2}\/\d{1,2}\/\d{2,4}\b', // 01/26/25    |    01/26/2025
        'date2'      => '\b\d{1,2} [[:alpha:]]{3,20} \d{2,4}\b', // 26 Jan 2025
        'time'       => '\b\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?|\D|\b)', // 4:19PM    |    2:00 p. m.
        'totalPrice' => '(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)', // C$12,486.00
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@amawaterways.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Amawaterways Res ID') !== false
            || stripos($headers['subject'], 'AmaWaterways Guest Invoices') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $detectProvider = $this->detectEmailFromProvider($parser->getHeader('from')) === true;

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($detectProvider === false && stripos($textPdf, '@amawaterways.com') === false
                && stripos($textPdf, '.amawaterways.com/') === false
                && stripos($textPdf, 'www.amawaterways.com') === false
            ) {
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
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        $guestInvoiceTexts = $agencyInvoiceTexts = [];

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $garbageStart = false;

                if (preg_match("/\n[ ]*{$this->opt($this->t('DETAILED FLIGHT SUMMARY'))}\n/", $textPdf)) {
                    $garbageStart = $this->strposArray($textPdf, $this->t('TRAVEL PROTECTION - CANADIAN RESIDENTS'));

                    if ($garbageStart === false) {
                        $garbageStart = $this->strposArray($textPdf, $this->t('PRE-CRUISE REGISTRATION'));
                    }
                } else {
                    $garbageStart = $this->strposArray($textPdf, $this->t('GROSS BALANCE DUE'));

                    if ($garbageStart === false) {
                        // by cancelled
                        $garbageStart = $this->strposArray($textPdf, $this->t('REFUND DUE'));
                    }
                }

                if ($garbageStart !== false) {
                    $textPdf = substr($textPdf, 0, $garbageStart);
                }

                $h1Text = substr($textPdf, 0, 100);

                if (preg_match("/^[ ]*{$this->opt($this->t('GUEST INVOICE'))}$/m", $h1Text)) {
                    $guestInvoiceTexts[] = $textPdf;
                } elseif (preg_match("/^[ ]*{$this->opt($this->t('AGENCY INVOICE'))}$/m", $h1Text)) {
                    $agencyInvoiceTexts[] = $textPdf;
                }
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('InvoicesPdf' . ucfirst($this->lang));

        $pdfTexts = count($guestInvoiceTexts) > 0 ? $guestInvoiceTexts : $agencyInvoiceTexts;

        foreach ($pdfTexts as $pdfText) {
            $this->parsePdf($email, $pdfText);
        }

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

    private function parsePdf(Email $email, $text): void
    {
        $headerText = $this->re("/(?:{$this->opt($this->t('GUEST INVOICE'))}|{$this->opt($this->t('AGENCY INVOICE'))})\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('ATTN'))}[ ]*:[ ]{2,}{$this->opt($this->t('GUEST NAME'))}/", $text);

        $status = $this->re("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Status'))}[ ]*:[ ]*(.{2,})/m", $headerText);
        $cancelledBookings = $status && preg_match("/^{$this->opt($this->t('cancelledStatuses'))}$/i", $status) ? true : null;
        $bookingDate = strtotime($this->re("/(?:^[ ]*|[ ]{2}){$this->opt($this->t('Booking Date'))}[ ]*:[ ]*({$this->patterns['date']}|{$this->patterns['date2']})/m", $headerText));

        $travellersText = $this->re("/\n([ ]*{$this->opt($this->t('ATTN'))}[ ]*:[ ]{2,}{$this->opt($this->t('GUEST NAME'))}\n+[\s\S]+?)\n+[ ]*{$this->opt($this->t('Agency ID'))}[ ]*:/", $text);
        $tablePos = [0];

        if (preg_match("/(.+[ ]{2,}){$this->opt($this->t('GUEST NAME'))}\n/", $travellersText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]) - 5;
        }
        $table = $this->splitCols($travellersText, $tablePos);

        if (count($table) === 2 && preg_match_all("/^[ ]*\d{1,2}[ ]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/mu", $table[1], $travellerMatches)) {
            $this->travellers = $travellerMatches[1];
        }

        $this->currencyCode = $this->re("/\n[ ]*{$this->opt($this->t('Agency ID'))}[ ]*:[ ]*.{5,}[ ]+{$this->opt($this->t('Currency'))}[ ]*:[ ]*([A-Z]{3})(?:[ ]{2}|\n)/", $text);

        /*
            Price (step 1): column order search
        */

        $priceText = $this->re("/\n([ ]*{$this->opt($this->t('Guest'))}.{2,}{$this->opt($this->t('Total'))}\n[\s\S]+?\n[ ]*{$this->opt($this->t('TOTALS'))}.+)/", $text)
            ?? $this->re("/\n([ ]*{$this->opt($this->t('Charges'))}[ ]+{$this->opt($this->t('Commission'))}.*\n[\s\S]+?\n[ ]*{$this->opt($this->t('TOTALS'))}.+)/", $text)
        ;

        $totalPriceIndex = null;
        $tableFirstRow = $this->re("/^\n*(.{2,})/", $priceText);
        $tablePos = $this->colsPos($tableFirstRow);

        if (count($tablePos) > 1) {
            $offset = ($tablePos[count($tablePos) - 1] - $tablePos[count($tablePos) - 2]) / 2;
            array_walk($tablePos, function (&$item) use ($offset) {
                $item -= $offset;
            });

            $tableHeaders = $this->splitCols($tableFirstRow, $tablePos);

            foreach ($tableHeaders as $i => $hName) {
                if (preg_match("/^\s*(?:{$this->opt($this->t('Total'))}|{$this->opt($this->t('Charges'))})\s*$/", $hName)) {
                    $totalPriceIndex = $i;
                }
            }
        } else {
            $this->logger->debug('Wrong price table!');
        }

        /*
            Price (step 2): parsing target values
        */

        // Price (cruise)

        $tpCruise = null;

        if ($totalPriceIndex !== null && preg_match("/\n([ ]*{$this->opt($this->t('TOTAL VOYAGE CHARGES'))}[ ]{2}.*\d.*)/", $priceText, $m)) {
            $table = $this->splitCols($m[1], $tablePos);

            if (!empty($table[$totalPriceIndex])) {
                $tpCruise = trim($table[$totalPriceIndex]);
            }
        }

        // Price (hotels)

        $tpHotels = null;

        if ($totalPriceIndex !== null && preg_match("/\n([ ]*{$this->opt($this->t('TOTAL LAND PACKAGE & EXTRA NIGHTS'))}[ ]{2}.*\d.*)/", $priceText, $m)) {
            $table = $this->splitCols($m[1], $tablePos);

            if (!empty($table[$totalPriceIndex])) {
                $tpHotels = trim($table[$totalPriceIndex]);
            }
        }

        // Price (flight)

        $tpFlight = null;

        if ($totalPriceIndex !== null && preg_match("/\n([ ]*{$this->opt($this->t('TOTAL AIR CHARGES'))}[ ]{2}.*\d.*)/", $priceText, $m)) {
            $table = $this->splitCols($m[1], $tablePos);

            if (!empty($table[$totalPriceIndex])) {
                $tpFlight = trim($table[$totalPriceIndex]);
            }
        }

        /*
            Parse bookings
        */

        if ($cancelledBookings) {
            $tpCruise = $tpHotels = $tpFlight = null;
        }

        $this->parseCruise($email, $text, $bookingDate, $tpCruise, $cancelledBookings, $status);

        if (preg_match("/ {$this->opt($this->t('HOTEL'))}[ ]+{$this->opt($this->t('IN'))}[ ]+{$this->opt($this->t('OUT'))}/", $text) > 0) {
            $this->logger->debug('Found hotels.');
            $this->parseHotels($email, $text, $bookingDate, $tpHotels, $cancelledBookings, $status);
        }

        if (preg_match("/\n[ ]*{$this->opt($this->t('DETAILED FLIGHT SUMMARY'))}\n/", $text) > 0) {
            $this->logger->debug('Found flight.');
            $this->parseFlight($email, $text, $bookingDate, $tpFlight, $cancelledBookings, $status);
        }
    }

    private function parseCruise(Email $email, $text, $bookingDate, ?string $totalPrice, ?bool $cancelledBookings, ?string $status): void
    {
        if ($cancelledBookings) {
            // $this->logger->debug('Found CANCELLED cruise. Skip!');

            // return; // because for cancelled cruise required confirmation number
        }

        $cruise = $email->add()->cruise();
        $cruise->general()
            ->confirmation($this->re("/\s+{$this->opt($this->t('Reservation ID:'))} *(\d{5,})\n/", $text))
            ->status($status)
            ->date($bookingDate)
            ->travellers($this->travellers, true);

        if ($cancelledBookings) {
            $cruise->general()
                ->cancelled();
        }

        $cruiseName = preg_match("/\n(?:[ ]*{$this->opt($this->t('Agency ID'))}[ ]*:[ ]*.{5,}|[ ]*{$this->opt($this->t('Currency'))}[ ]*:[ ]*[A-Z]{3})+[ ]{2,}(.+\S\n){1,2}\n*[ ]*{$this->opt($this->t('Ship'))}[ ]{2,}{$this->opt($this->t('Embark'))}/", $text, $m)
            ? preg_replace('/\s+/', ' ', trim($m[1])) : null;
        $shipText = $this->re("/\n([ ]*{$this->opt($this->t('Ship'))}[ ]{2,}{$this->opt($this->t('Embark'))}.+\n+.{10,}(?:\n.+){0,2})\n\n/", $text);

        $tablePos = [0];

        if (preg_match("/((.+ ){$this->patterns['date']}.+? ){$this->patterns['date']} /", $shipText, $matches)) {
            $tablePos[] = mb_strlen($matches[2]);
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/((.+ ){$this->opt($this->t('# Nights'))})[ ]{2}/", $shipText, $matches)) {
            $tablePos[] = mb_strlen($matches[2]);
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/(.+ ){$this->opt($this->t('Cabin/Catg'))} /", $shipText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }

        if (preg_match("/(.+ ){$this->opt($this->t('Bedding'))}$/m", $shipText, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($shipText, $tablePos);

        if (count($table) !== 7) {
            $this->logger->debug('Wrong ship table!');

            return;
        }

        $shipName = $this->re("/^\s*{$this->opt($this->t('Ship'))}[ ]*\n+[ ]*(\S[\s\S]*?)\s*$/", $table[0]);
        $cruise->details()->ship(preg_replace('/\s+/', ' ', $shipName));

        $theme = preg_match("/^\s*{$this->opt($this->t('Theme'))}[ ]*\n+[ ]*(\S[\s\S]*?)\s*$/", $table[4], $m) ? preg_replace('/\s+/', ' ', $m[1]) : null;
        $cruise->details()->description($theme ?? $cruiseName, false, true);

        $cabin = $this->re("/^\s*{$this->opt($this->t('Cabin/Catg'))}[ ]*\n+[ ]*(\d+)(?:[ ]{2}|\s*$)/", $table[5]);
        $cruise->details()->room($cabin, false, true);

        $s = $cruise->addSegment();

        if (preg_match("/^\s*{$this->opt($this->t('Embark'))}[ ]*\n+[ ]*(?<date>{$this->patterns['date']})\s+(?<name>\S[\s\S]*?)\s*$/", $table[1], $m)) {
            $s->parseAboard($m['date']);
            $s->setName(preg_replace('/\s+/', ' ', $m['name']));
        }

        $s = $cruise->addSegment();

        if (preg_match("/^\s*{$this->opt($this->t('Disembark'))}[ ]*\n+[ ]*(?<date>{$this->patterns['date']})\s+(?<name>\S[\s\S]*?)\s*$/", $table[2], $m)) {
            $s->parseAshore($m['date']);
            $s->setName(preg_replace('/\s+/', ' ', $m['name']));
        }

        // $cruise->general()->noConfirmation();

        if (preg_match("/^{$this->patterns['totalPrice']}$/", $totalPrice, $matches)) {
            $cruise->price()
                ->currency($this->currencyCode ?? $matches['currency'])
                ->total(PriceHelper::parse($matches['amount'], $this->currencyCode));
        }
    }

    private function parseHotels(Email $email, $text, $bookingDate, ?string $totalPrice, ?bool $cancelledBookings, ?string $status): void
    {
        if (preg_match("/\n(?<head>.+ {$this->opt($this->t('HOTEL'))}[ ]+{$this->opt($this->t('IN'))}[ ]+{$this->opt($this->t('OUT'))}.+)\n+(?<body>[\s\S]+?)\n+(?:[ ]*{$this->opt($this->t('PENALTY'))} .{20,} {$this->opt($this->t('Total'))}\n|[ ]*{$this->opt($this->t('REQUESTED AIR'))}|.{40,} {$this->opt($this->t('Total'))}\n+[ ]*{$this->opt($this->t('VOYAGE CHARGES'))})/", $text, $m)) {
            $hotelsHead = $m['head'];
            $hotelsBody = $m['body'];
        } else {
            $hotelsHead = $hotelsBody = null;
        }

        $hotels = $this->splitText($hotelsBody, "/(.+ {$this->patterns['date']}[ ]+[-–]+[ ]+{$this->patterns['date']}.*)/", true);

        foreach ($hotels as $hText) {
            $h = $email->add()->hotel();
            $h->general()->date($bookingDate);

            if ($cancelledBookings) {
                $h->general()->cancelled();
            }

            $tablePos = [0];

            if (preg_match("/(.{10,}) {$this->opt($this->t('HOTEL'))}[ ]+{$this->opt($this->t('IN'))} /", $hotelsHead, $matches)) {
                $tablePos[1] = mb_strlen($matches[1]);
            }

            if (preg_match("/(.{15,} ){$this->opt($this->t('guests'))} \d/i", $hotelsBody, $matches)) {
                $tablePos[2] = mb_strlen($matches[1]);
            }

            if (preg_match("/(.{20,} ){$this->patterns['date']}[ ]+[-–]+[ ]+{$this->patterns['date']}/", $hotelsBody, $matches)) {
                $tablePos[3] = mb_strlen($matches[1]);
            }

            if (preg_match("/(.{20,} {$this->patterns['date']}[ ]+[-–]+[ ]+{$this->patterns['date']} )/", $hotelsBody, $matches)) {
                $tablePos[4] = mb_strlen($matches[1]);
            }

            $table = $this->splitCols($hText, $tablePos);

            if (count($table) < 4) {
                $this->logger->debug('Wrong hotel table!');

                continue;
            }
            $table = array_map('trim', $table);

            $h->hotel()->name(preg_replace('/\s+/', ' ', $table[1]))->noAddress();

            if (!empty($table[2])) {
                $h->general()->travellers($this->travellers, true);
            }

            if (preg_match("/({$this->patterns['date']})\s+[-–]+\s+({$this->patterns['date']})/", $table[3], $m)) {
                $h->booked()->checkIn2($m[1])->checkOut2($m[2]);
            }

            if (count($table) > 4) {
                $h->general()->status($cancelledBookings ? $status : $table[4]);
            } elseif ($status) {
                $h->general()->status($status);
            }

            $h->general()->noConfirmation();
        }

        if (count($hotels) === 1) {
            if (preg_match("/^{$this->patterns['totalPrice']}$/", $totalPrice, $matches)) {
                $h->price()
                    ->currency($this->currencyCode ?? $matches['currency'])
                    ->total(PriceHelper::parse($matches['amount'], $this->currencyCode));
            }
        }
    }

    private function parseFlight(Email $email, $text, $bookingDate, ?string $totalPrice, ?bool $cancelledBookings, ?string $status): void
    {
        $f = $email->add()->flight();
        $f->general()->status($status)->date($bookingDate)->travellers($this->travellers, true);

        if ($cancelledBookings) {
            $f->general()->cancelled();
        }

        $confNumbers = $segmentsIndexes = [];

        // remove garbage
        $text = preg_replace([
            "/^[ ]*{$this->opt($this->t('RES ID'))}[ ]*:.*/im",
            "/.*\b{$this->opt($this->t('Page'))}\s+\d.*/i",
            "/^[ ]{0,15}[[:upper:]][[:upper:], \']+[[:upper:]]\n{1,2}([ ]{0,15}(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?\d{1,5} )/mu",
        ], [
            '',
            '',
            '$1',
        ], $text);

        if (preg_match("/\n[ ]*{$this->opt($this->t('DETAILED FLIGHT SUMMARY'))}\n+(?<head>(?:.+\n+){1,4}?)(?<body>[ ]{0,15}(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?\d{1,5} [\s\S]+?)(?:\n+[ ]*{$this->opt($this->t('Important Notifications'))}|$)/", $text, $m)) {
            $m = preg_replace("/^ {0,15}Independent Flight Schedule: *$/m", '', $m);
            $flightHead = $m['head'];
            $flightBody = $m['body'];
        } else {
            $flightHead = $flightBody = null;
        }

        $segments = $this->splitText($flightBody, "/^([ ]{0,15}(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?\d{1,5} )/m", true);

        foreach ($segments as $sText) {
            $airlineName = $flightNumber = $operator = null;

            if (preg_match("/^([\s\S]+?)\n+[ ]{0,25}{$this->opt($this->t('Operated by'))}[ ]+(.{2,})/", $sText, $m)) {
                $sText = $m[1];
                $operator = $m[2];
            }

            if (preg_match("/^[ ]{0,15}(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])?(?<number>\d+) /", $sText, $m)) {
                $airlineName = $m['name'] ?? null;
                $flightNumber = $m['number'];
            }

            /*
            $tablePos = [0];

            if (preg_match("/(((.{10,} ){$this->patterns['date2']} ).{10,} ){$this->patterns['date2']}/u", $sText, $matches)) {
                $tablePos[1] = mb_strlen($matches[3]);
                $tablePos[2] = mb_strlen($matches[2]);
                $tablePos[6] = mb_strlen($matches[1]);
            } elseif (preg_match_all("/^((.{10,} ){$this->patterns['date2']} ?)/mu", $sText, $dateMatches) && count($dateMatches[0]) === 2) {
                $dateBeforePos = array_map('mb_strlen', $dateMatches[2]);
                sort($dateBeforePos);
                $tablePos[1] = $dateBeforePos[0];
                $tablePos[6] = $dateBeforePos[1];

                $dateAfterPos = array_map('mb_strlen', $dateMatches[1]);
                sort($dateAfterPos);
                $tablePos[2] = $dateAfterPos[0];
            }

            if (preg_match("/(((.{20,} ){$this->patterns['time']} ).{3,} ){$this->patterns['time']}/u", $sText, $matches)) {
                $tablePos[3] = mb_strlen($matches[3]);
                $tablePos[4] = mb_strlen($matches[2]);
                $tablePos[5] = mb_strlen($matches[1]);
            } elseif (preg_match_all("/^((.{20,} ){$this->patterns['time']})/mu", $sText, $timeMatches) && count($timeMatches[0]) === 2) {
                $timeBeforePos = array_map('mb_strlen', $timeMatches[2]);
                sort($timeBeforePos);
                $tablePos[3] = $timeBeforePos[0];
                $tablePos[5] = $timeBeforePos[1];

                $timeAfterPos = array_map('mb_strlen', $timeMatches[1]);
                sort($timeAfterPos);
                $tablePos[4] = $timeAfterPos[0];
            }

            $table = $this->splitCols($sText, $tablePos);

            $tdFirstParts = preg_split('/[ ]{2,}/', $table[0]);

            if (count($tdFirstParts) === 4) {
                $carrierName = $tdFirstParts[1];
                $confNumbers[] = $tdFirstParts[3];
            } else {
                $carrierName = null;
            }

            */

            $table = $this->createTable($sText, $this->rowColumnPositions($this->inOneRow($flightHead . "\n" . $sText)));
            // $this->logger->debug('$table = '.print_r( $table,true));

            if (count($table) === 9 && preg_match("/^\s*(.+?)\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/i", $table[5], $m)) {
                $newtable = array_merge(array_slice($table, 0, 5), [$m[1], $m[2]], array_slice($table, 6, 3));
                $table = $newtable;
            }

            if (count($table) !== 10) {
                $this->logger->debug('Wrong flight table!');

                continue;
            }
            $table = array_map('trim', $table);

            $carrierName = $table[1];
            $confNumbers[] = $table[3];

            $nameDep = $nameArr = $codeDep = $codeArr = null;

            if (preg_match($pattern = "/^(?<name>.{2,}?)\s*\(\s*(?<code>[A-Z]{3})[\s)]*$/s", $table[5], $m)) {
                $nameDep = preg_replace('/\s+/', ' ', $m['name']);
                $codeDep = $m['code'];
            }

            if (preg_match($pattern, $table[7], $m)) {
                $nameArr = preg_replace('/\s+/', ' ', $m['name']);
                $codeArr = $m['code'];
            }

            $dateDep = $dateArr = null;

            if (preg_match("/^{$this->patterns['date2']}$/u", $table[4])
                && preg_match("/^\s*({$this->patterns['time']})\s*$/u", $table[6], $m)) {
                $dateDep = strtotime($m[1], strtotime($table[4]));
            }

            if (preg_match("/^{$this->patterns['date2']}$/u", $table[9])
                && preg_match("/^({$this->patterns['time']})\s*$/u", $table[8], $m)
            ) {
                $dateArr = strtotime($table[8], strtotime($table[9]));
            }

            if (!$flightNumber || !$dateDep) {
                $this->logger->debug('Requared fields for flight segment not found!');
                $f->addSegment();

                continue;
            }
            $segIndex = strtoupper($airlineName) . $flightNumber . '_' . $dateDep;

            if (in_array($segIndex, $segmentsIndexes)) {
                $this->logger->debug('Found duplicate flight segment!');

                continue;
            }

            $s = $f->addSegment();

            if (empty($airlineName) && !empty($carrierName)) {
                $airlineName = $carrierName;
            }
            $s->airline()
                ->number($flightNumber)
                ->operator($operator, false, true);

            if (empty($airlineName) && !empty($flightNumber)) {
                $s->airline()
                    ->noName();
            } else {
                $s->airline()
                    ->name($airlineName);
            }

            if ($carrierName && $carrierName !== $airlineName) {
                $s->airline()->carrierName($carrierName);
            }

            $s->departure()->name($nameDep)->code($codeDep)->date($dateDep);
            $s->arrival()->name($nameArr)->code($codeArr)->date($dateArr);

            $segmentsIndexes[] = $segIndex;
        }

        $confNumbers = array_unique(array_filter($confNumbers));

        foreach ($confNumbers as $confNo) {
            $f->general()->confirmation($confNo);
        }

        if (empty($confNumbers)) {
            $f->general()
                ->noConfirmation();
        }

        if (preg_match("/^{$this->patterns['totalPrice']}$/", $totalPrice, $matches)) {
            $f->price()
                ->currency($this->currencyCode ?? $matches['currency'])
                ->total(PriceHelper::parse($matches['amount'], $this->currencyCode));
        }
    }

    private function assignLang($text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Reservation ID:']) || empty($phrases['Disembark'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['Reservation ID:']) !== false
                && $this->strposArray($text, $phrases['Disembark']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray($text, $phrases)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (!empty($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function colsPos($table, $delta = 5): array
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
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $delta) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
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

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
}
