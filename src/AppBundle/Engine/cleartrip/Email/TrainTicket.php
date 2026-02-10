<?php

namespace AwardWallet\Engine\cleartrip\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Email\Email;

class TrainTicket extends \TAccountChecker
{
    public $mailFiles = "cleartrip/it-13184807.eml, cleartrip/it-13184813.eml, cleartrip/it-4327703.eml, cleartrip/it-4371662.eml, cleartrip/it-5453266.eml, cleartrip/it-8310793.eml";

    public $reBody = [
        'en' => ['Scheduled', 'Departure'],
    ];

    public $reBody2 = [
        'en' => ['Cleartrip', 'Electronic Reservation Slip'],
    ];
    public $reBodyHtml = [
        'en'  => ['Your Cleartrip Trip ID is', 'one-way train ticket is'],
        'en2' => ['Your trip ID is', 'flight is confirmed'],
    ];
    /** @var \HttpBrowser */
    public $pdf;

    public $lang = '';

    public static $dict = [
        'en' => [
            //Pdf
            'PNR'             => 'PNR',
            'Passenger'       => 'passengerdetails',
            'Journey details' => 'journeydetails',
            'Total cost'      => 'total',
            'Train Number'    => 'trainno',
            //Html
        ],
    ];

    private $pdfText = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                    $this->pdf = clone $this->http;
                    $this->pdf->SetEmailBody($html);
                }
                $this->pdfText = \PDF::convertToText($parser->getAttachmentBody($pdf));
            }
        }
        $a = explode('\\', __CLASS__);
        $class = end($a);

        $type = 'Pdf';

        if (!$this->parseEmailPdf($email) && count($email->getItineraries()) === 0) {
            $type = 'Html';
            $this->parseEmailHTML($email);
        }

        $email->setType($class . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach ($this->reBody2 as $lang => $reBody) {
                if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                    return true;
                }
            }
        }
        $body = $this->http->Response['body'];

        if (isset($this->reBodyHtml)) {
            foreach ($this->reBodyHtml as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'reply@cleartrip.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Cleartrip Booking') !== false
            || stripos($from, '@cleartrip.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmailPdf(Email $email)
    {
        if (empty($this->pdf)) {
            return false;
        }

        $body = $this->pdf->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    break;
                }
            }
        }

        $NBSP = chr(194) . chr(160);

        $details = $this->pdf->FindNodes("//b[contains(translate(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),' " . $NBSP . "',''), '" . $this->t('Journey details') . "')]/../following-sibling::p/descendant::text()[not(./ancestor::b)]");
        $detailsHeader = $this->pdf->FindNodes("//b[contains(translate(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),' " . $NBSP . "',''), '" . $this->t('Journey details') . "')]/../following-sibling::p/descendant::text()[./ancestor::b]");

        if (!count($details)) {
            return false;
        }

        if (count($details) < 10) {
            return false;
        }

        $email->ota()->confirmation(trim($details[1]), trim($detailsHeader[1]), true);
        $r = $email->add()->train();

        $pnrTexts = $this->pdf->FindNodes('//p[contains(.,"PNR:")]/descendant::text()[normalize-space(.)!=""]');
        $pnrValue = implode(' ', $pnrTexts);

        if (preg_match('/\(PNR:[ ]*(.+)\)/', $pnrValue, $m)) {
            $r->general()->confirmation($m[1], 'PNR');
        } else {
            $r->general()->noConfirmation();
        }

        $passengers = $this->pdf->FindNodes("//b[contains(translate(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),' " . $NBSP . "',''), '" . $this->t('Passenger') . "')]/../following-sibling::p/descendant::text()[not(./ancestor::b)]");
        $num = 1;
        $seats = [];

        foreach ($passengers as $i => $lines) {
            if ($lines == $num) {
                $r->general()->traveller($passengers[$i + 1]);

                if (strpos($passengers[$i + 3], 'Female') !== false) {
                    if (preg_match("#(.+)\/(.+)\/(.+)#", $passengers[$i + 4], $m)) {
                        $seats[] = trim($m[1]) . '/' . trim($m[2]);
                    }
                } else {
                    if (preg_match("#(.+)\/(.+)\/(.+)#", $passengers[$i + 5], $m)) {
                        $seats[] = trim($m[1]) . '/' . trim($m[2]);
                    }
                }
                $num++;
            }

            if ($lines === '-') {
                break;
            }
        }

        $s = $r->addSegment();

        if (!empty($seats[0])) {
            $s->setSeats($seats);
        }

        $paymentTotal = $this->pdf->FindSingleNode("//b[contains(translate(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),' " . $NBSP . "',''), '" . $this->t('Total cost') . "')]/../following-sibling::p[1]");
        $tot = $this->getTotalCurrency($paymentTotal);

        if (!empty($tot['Total'])) {
            $r->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }
        $baseFareValue = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Base Fare")]/following::text()[normalize-space(.)!=""][1]');
        $tot = $this->getTotalCurrency($baseFareValue);

        if (!empty($tot['Total'])) {
            $r->price()
                ->cost($tot['Total']);
        }

        $m = explode("/", $details[2]);

        $s->extra()
            ->number(trim($m[0]))
            ->type(trim($m[1], '# '));

        $s->departure()
            ->name($this->http->FindPreg("#(.+?)\s*(?:\(|$)#", false, $details[8]))
            ->code($this->http->FindPreg("#\(([A-Z]{2,})\)#", false, $details[8]));
        $s->arrival()
            ->name($this->http->FindPreg("#(.+?)\s*(?:\(|$)#", false, $details[9]))
            ->code($this->http->FindPreg("#\(([A-Z]{2,})\)#", false, $details[9]));

        $details[5] = str_replace(',', '', $details[5]);

        if (preg_match("#([0-2]?[0-9]+\:[0-5][0-9]\s*[PA]?[M]?)#", trim($details[10]), $m)) {
            $s->departure()->date(strtotime($details[5] . ', ' . $m[1]));
        }

        if (preg_match("#([0-2]?[0-9]+\:[0-5][0-9]\s*[PA]?[M]?)#", trim($details[11]), $m)) {
            $s->arrival()->date(strtotime($details[5] . ', ' . $m[1]));
        }

        if ($s->getArrDate() < $s->getDepDate()) {
            $s->arrival()
                ->date(strtotime("+1 day", $s->getArrDate()));
        }
        $r->general()->date(strtotime(str_replace(',', '', $details[16]) . ', ' . $details[17]));

        return true;
    }

    private function parseEmailHtml(Email $email)
    {
        $body = $this->http->Response['body'];

        if (isset($this->reBodyHtml)) {
            foreach ($this->reBodyHtml as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    break;
                }
            }
        }

        $descr = 'Cleartrip Trip ID';
        $descr2 = 'Your trip ID';
        $rl = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'{$descr}') or contains(normalize-space(.), '{$descr2}')]/following::text()[normalize-space(.)!=''][1]");

        $email->ota()->confirmation($rl, $descr, true);

        if (false !== stripos($body, 'flight is confirmed')) {
            $r = $email->add()->flight();
        } else {
            $r = $email->add()->train();
        }

        if (!empty($this->pdfText)) {
            $text = $this->cutText('TRAVELLERS', 'ABOUT THIS TRIP', $this->pdfText);
            preg_match_all('/\b([\d\-]+)\b/', $text, $m);
            $r->setTicketNumbers($m[1], false);
        }

        $descr = 'Your PNR no';
        $descr2 = 'PNR:';
        $rl = $this->http->FindSingleNode("(//text()[contains(normalize-space(.),'{$descr}') or contains(normalize-space(.), '{$descr2}')]/following::text()[normalize-space(.)!=''][1])[1]");

        if (preg_match("#^\s*([A-Z\d]{5,})\s*$#", $rl, $m)) {
            $r->general()->confirmation($m[1], 'PNR');
        } else {
            $r->general()->noConfirmation();
        }
        $status = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'one-way train ticket is')]", null, true, "#one-way train ticket is\s+(\w+)#");

        if (!empty($status)) {
            $r->general()->status($status);
        }

        $passengers = $this->http->FindNodes("//text()[normalize-space(.)='Travellers']/ancestor::tr[1]/following-sibling::tr[1]/descendant::tr[not(.//tr)]/td[not(.//img)][1]/descendant::text()[normalize-space(.)][1]");

        $r->general()->travellers(preg_replace('/\s*PNR\s*:\s*[A-Z\d]+/', '', $passengers));

        $paymentTotal = $this->http->FindSingleNode("//text()[normalize-space(.)='Total charge']/following::text()[normalize-space(.)!=''][1]");
        $tot = $this->getTotalCurrency($paymentTotal);

        if (!empty($tot['Total'])) {
            $r->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }
        $baseFareValue = $this->http->FindSingleNode("//text()[normalize-space(.)='Base Fare']/following::text()[normalize-space(.)!=''][1]");
        $tot = $this->getTotalCurrency($baseFareValue);

        if (!empty($tot['Total'])) {
            $r->price()
                ->cost($tot['Total']);
        }
        $fees = ['Railway Charges', 'Cleartrip Service Fee', 'Processing Fee'];

        foreach ($fees as $fee) {
            $this->http->FindSingleNode("//text()[normalize-space(.)='{$fee}']/following::text()[normalize-space(.)!=''][1]");
            $tot = $this->getTotalCurrency($baseFareValue);

            if (!empty($tot['Total'])) {
                $r->price()
                    ->fee($fee, $tot['Total']);
            }
        }

        $seats = $this->http->FindNodes("//text()[normalize-space(.)='Travellers']/ancestor::tr[1]/following-sibling::tr[1]/descendant::tr[not(.//tr)]/td[1]/following-sibling::td[last()]");
        $xpath = "//img[contains(@src,'images/air_logos') or contains(@src,'images/logos/rail')]/ancestor::tr[./following-sibling::tr][1]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->notice("Segments did not found by xpath: {$xpath}");
        }

        foreach ($roots as $root) {
            $s = $r->addSegment();

            if ($r instanceof Train) {
                $s->extra()
                    ->number($this->http->FindSingleNode("//text()[normalize-space(.)='Travellers']/preceding::text()[normalize-space(.)!=''][1]/ancestor::tr[1]/preceding::tr[1]/descendant::text()[normalize-space(.)!=''][2]", null, true, "#^\d+$#"))
                    ->type($this->http->FindSingleNode("//text()[normalize-space(.)='Travellers']/preceding::text()[normalize-space(.)!=''][1]/ancestor::tr[1]/preceding::tr[1]/descendant::text()[normalize-space(.)!=''][1]"));

                if (!empty($seats[0])) {
                    $s->setSeats($seats);
                }
            } elseif ($r instanceof Flight) {
                if (preg_match('/(.+)\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/', $this->http->FindSingleNode('descendant::td[not(.//td)][2]', $root), $m)) {
                    $s->airline()
                        ->name($m[2])
                        ->number($m[3])
                        ->operator($m[1]);
                }
                $s->departure()
                    ->noCode();
                $s->arrival()
                    ->noCode();
            }
            $date = strtotime($this->http->FindSingleNode('following-sibling::tr[1]/descendant::td[not(.//td)][1]/descendant::text()[normalize-space(.)][2]', $root));
            $node = $this->http->FindSingleNode('following-sibling::tr[1]/descendant::td[not(.//td)][1]/descendant::text()[normalize-space(.)][1]', $root);

            if (preg_match("#(.+)\s*→\s*(.+)#u", $node, $m)) {
                $s->departure()
                    ->name($m[1]);
                $s->arrival()
                    ->name($m[2]);
            } elseif ($r instanceof Train) {
                $node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Your') and contains(normalize-space(.),'one-way train ticket')]");

                if (preg_match("#Your (.+?)-(.+) one-way train ticket#", $node, $m)) {
                    $s->departure()
                        ->name($m[1]);
                    $s->arrival()
                        ->name($m[2]);
                }
            }
            $node = $this->http->FindSingleNode('following-sibling::tr[1]/descendant::td[not(.//td)][2]/descendant::text()[normalize-space(.)][1]', $root);

            if (preg_match("#^(\d+:\d+)\s*[^\d\s:]+\s*(\d+:\d+)$#", $node, $m)) {
                $s->departure()
                    ->date(strtotime($m[1], $date));
                $s->arrival()
                    ->date(strtotime($m[2], $date));

                if ($s->getArrDate() < $s->getDepDate()) {
                    $s->arrival()
                        ->date(strtotime("+1 day", $s->getArrDate()));
                }
            }
            $s->extra()
                ->duration($this->http->FindSingleNode('following-sibling::tr[1]/descendant::td[not(.//td)][2]/descendant::text()[normalize-space(.)][2]', $root));
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $cuttedText = strstr(strstr($text, $start), $end, true);

            return substr($cuttedText, 0);
        }

        return null;
    }

    //	protected function normalizePrice($string = '') {
    //		if ( empty($string) ) return $string;
    //		$string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
    //		$string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
    //		$string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00
    //		return $string;
    //	}
//
    private function getTotalCurrency($node)
    {
        $node = str_replace("Rs.", "INR", $node);
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);			// 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);	// 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);	// 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);	// 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
