<?php

namespace AwardWallet\Engine\cleartrip\Email;

use AwardWallet\Schema\Parser\Email\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "cleartrip/it-6443568.eml, cleartrip/it-8134728.eml";

    public $emailSubject;
    private $pdfDetectors = [
        'en' => ['Cleartrip', 'Use your Trip ID'],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // associated with parser AirTicketPDF
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->pdfDetectors as $reBody) {
                if (stripos($textPdf, $reBody[0]) !== false && stripos($textPdf, $reBody[1]) !== false) {
                    return false;
                }
            }
        }

        $this->emailSubject = $parser->getSubject();

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

//    public function ParsePlanEmail(\PlancakeEmailParser $parser)
//    {
//        // associated with parser AirTicketPDF
//        $pdfs = $parser->searchAttachmentByName('.*pdf');
//
//        foreach ($pdfs as $pdf) {
//            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
//
//            foreach ($this->pdfDetectors as $reBody) {
//                if (stripos($textPdf, $reBody[0]) !== false && stripos($textPdf, $reBody[1]) !== false) {
//                    return false;
//                }
//            }
//        }
//
//        return $this->parseEmail();
//    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Cleartrip Booking') !== false
            || stripos($from, '@cleartrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'reply@cleartrip.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // associated with parser AirTicketPDF
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->pdfDetectors as $reBody) {
                if (stripos($textPdf, $reBody[0]) !== false && stripos($textPdf, $reBody[1]) !== false) {
                    return false;
                }
            }
        }

        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Cleartrip Private")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.cleartrip.com") or contains(@href,".cleartrip.com/")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"Your trip ID is")]')->length > 0) {
            return true;
        }

        return false;
    }

    protected function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    private function parseEmail(Email $email)
    {
        // Travel Agency
        $email->obtainTravelAgency();

        $conf = $this->http->FindSingleNode("//text()[contains(.,'Your trip ID is')]/following-sibling::a[1]/strong");

        if (empty($conf) && preg_match("/Trip ID\s*(\d{8,})(?: |$)/", $this->emailSubject, $m)) {
            $conf = $m[1];
        }
        $email->ota()
            ->confirmation($conf);

        $f = $email->add()->flight();

        // General
        $confs = [];
        $travellers = [];
        $travellerRows = $this->http->XPath->query('//tr[contains(.,"Travellers")or contains(.,"Flight changed for")][not(.//tr)]/following-sibling::tr/descendant::tr[count(./td)=2]/td[2]');

        if ($travellerRows->length == 0) {
            $travellerRows = $this->http->XPath->query('//tr[*[normalize-space()][2][normalize-space()= "Passengers revoked"]]/following-sibling::tr[count(*[normalize-space()])=3]/td[normalize-space()][3]');
        }

        foreach ($travellerRows as $travellerRow) {
            $travellers[] = $this->http->FindSingleNode('./descendant::text()[normalize-space(.)][1]', $travellerRow);
            $confs[] = $this->http->FindSingleNode('./descendant::text()[normalize-space(.)][position()>1][starts-with(normalize-space(.),"PNR")]/following::node()[normalize-space(.)][1]', $travellerRow, true, '/^([A-Z\d]{5,})$/');
        }

        $confs = array_unique(array_filter($confs));
        $travellers = array_unique(array_filter($travellers));

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        if (empty($confs)) {
            $f->general()
                ->noConfirmation();
        }

        $f->general()
            ->travellers($travellers, true);

        if ($this->http->XPath->query("//node()[{$this->contains(['been successfully cancelled', 'The trip is cancelled for the following passengers'])}]")->length > 0) {
            $f->general()
                ->status('Cancelled')
                ->cancelled();
        }
        // Segments
        $xpath = "//img[contains(@src,'cleartrip.com/images/air_logos')or contains(@src,'cltpstatic.com/images/air_logos')]/ancestor::tr[1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return false;
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode('./descendant::td[2]', $root);

            if (preg_match('/\b([A-Z\d]{2})\s*(\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $flightDetails = $this->http->FindSingleNode('./descendant::td[not(starts-with(normalize-space(), "PNR"))][3]', $root);

            if (empty($flightDetails)) {
                $flightDetails = $this->http->FindSingleNode('./following::tr[normalize-space(.)][1]/td[normalize-space(.)][1]', $root);
            }

            $this->logger->debug('$flightDetails = ' . print_r($flightDetails, true));

            if (preg_match('/(.+)\s+\D\s+(.+)\s*\w{3},\s+(\d{1,2} \w+ \d{4})\s+(\d{1,2}:\d{2})\s+\D\s+(\d{1,2}:\d{2})\s*(\d{1,2}\w(?:[ ]*\d{1,2}\w)?), (\d{1,3})[ ]*\w+/u', $flightDetails, $m)) {
                $date = $m[3];

                // Depparture
                $s->departure()
                    ->noCode()
                    ->name($m[1])
                    ->date(strtotime($date . ', ' . $m[4]));

                // Arrival
                $s->arrival()
                    ->noCode()
                    ->name($m[2])
                    ->date(strtotime($date . ', ' . $m[5]));

                // Extra
                $s->extra()
                    ->duration($m[6])
                    ->stops($m[7]);
            } elseif (preg_match('/(.+)\s+\W\s+(.+)\s*\w{3},\s+(\w+ \d{1,2} \d{4})\b\s*(Fare type|$)/u', $flightDetails, $m)) {
                // Bangalore → Chennai Fri, Apr 07 2023 Fare type: STANDARD
                // Depparture
                $s->departure()
                    ->noCode()
                    ->name($m[1])
                    ->noDate()
                    ->day(strtotime($m[3]));

                // Arrival
                $s->arrival()
                    ->noCode()
                    ->name($m[2])
                    ->noDate();
            }
        }

        // Price
        $paymentTotal = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Total charge")]/following::text()[normalize-space(.)][1]');

        if (preg_match('/^(\D+)[ ]+([,.\d]+)$/', $paymentTotal, $matches)) {
            $currency = $matches[1];
            $totalCharge = $this->normalizePrice($matches[2]);
            $baseFareValue = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Base Fare")]/following::text()[normalize-space(.)][1]');

            if (preg_match('/^' . preg_replace('/([.$*)(])/', '\\\\$1', $currency) . '[ ]+(\d[,.\d]*)/', $baseFareValue, $m)) {
                $baseFare = $this->normalizePrice($m[1]);
            }
            $currency = str_replace("Rs.", "INR", $currency);
            $feeNodes = $this->http->XPath->query("//td[normalize-space() = 'Base Fare']/ancestor::tr[1]/following-sibling::*");
            $discount = 0.0;

            foreach ($feeNodes as $fn) {
                $name = $this->http->FindSingleNode("*[normalize-space()][1]", $fn);
                $amount = $this->normalizePrice($this->http->FindSingleNode("*[normalize-space()][2]", $fn, true, "/^\s*\D*(\d[,.\d ]*)\D*\s*$/"));

                if (preg_match("/\s*\(\s*-\s*\)\s*$/u", $name)) {
                    $discount += $amount;

                    continue;
                }
                $f->price()
                    ->fee($name, $amount);
            }

            if (!empty($discount)) {
                $f->price()
                    ->discount($discount);
            }

            $f->price()
                ->total($totalCharge)
                ->currency($currency)
            ;

            if ($baseFare) {
                $f->price()
                    ->cost($baseFare);
            }
        }

        return $email;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }
}
