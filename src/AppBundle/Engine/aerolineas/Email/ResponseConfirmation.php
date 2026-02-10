<?php

namespace AwardWallet\Engine\aerolineas\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ResponseConfirmation extends \TAccountChecker
{
    public $mailFiles = "aerolineas/it-4019940.eml, aerolineas/it-4019942.eml";

    public static $dictionary = [
        "en" => [
        ],
    ];
    protected $lang = 'en';

    protected $pdf;

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'FlyingBlue@airfrance-klm.com') !== false
            || isset($headers['subject']) && preg_match('/Flying\s+Blue\s*:\s*(Respuesta\s+a\s+su\s+pedido|Acuse\s+de\s+recibo\s+de\s+su\s+pedido)/i', $headers['subject']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return preg_match('/El\s+equipo(\s+de|)\s+Flying\s+Blue/i', $parser->getBody());
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'FlyingBlue@airfrance-klm.com') !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                $this->pdf = clone $this->http;
                $this->pdf->SetEmailBody($html);

                if (($this->pdf->FindSingleNode('(//*[starts-with(normalize-space(.),"Booking")]/following-sibling::*[normalize-space(.)!=""])[1]')) !== null) {
                    $this->ParsePdf($email, $this->pdf);

                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    protected function ParsePdf(Email $email, $pdf)
    {
        $f = $email->add()->flight();

        $paxs = [];
        $passengers = $pdf->XPath->query('//text()[starts-with(normalize-space(.),"E-Ticket")]/following::text()[starts-with(normalize-space(.),"Ticket")]/preceding-sibling::text()[normalize-space(.)!=""][1]');

        foreach ($passengers as $p) {
            $paxs[] = str_replace('/', ' ', $pdf->FindSingleNode('.', $p));
        }

        $f->general()
            ->travellers($paxs)
            ->confirmation($this->pdf->FindSingleNode('(//*[starts-with(normalize-space(.),"Booking")]/following-sibling::*[normalize-space(.)!=""])[1]'));

        $rows = $pdf->XPath->query('//*[normalize-space(.)="Flight Information" and ./following::text()[normalize-space(.)="Airline"][1]]');

        foreach ($rows as $row) {
            $s = $f->addSegment();

            $flight = $pdf->FindSingleNode('./following::text()[normalize-space(.)="Flight"][1]/following::text()[normalize-space(.)!=""][1]', $row);

            if (preg_match('/([A-Z]+)([\d]+)/', $flight, $matches)) {
                $s->airline()
                    ->name($matches[1])
                    ->number($matches[2]);
            }

            $s->departure()
                ->noCode()
                ->name($pdf->FindSingleNode('./following::text()[normalize-space(.)="Origin"][1]/following::text()[normalize-space(.)!=""][1]', $row));

            $s->arrival()
                ->noCode()
                ->name($pdf->FindSingleNode('./following::text()[normalize-space(.)="Destination"][1]/following::text()[normalize-space(.)!=""][1]', $row));

            $date = $pdf->FindSingleNode('./preceding::text()[normalize-space(.)="Travel Details"][1]/following::text()[normalize-space(.)!=""][1]', $row, true, '/[A-z]+\s+(\d+\s+[A-z]+\s+\d+)/');
            $timeDep = $pdf->FindSingleNode('./following::text()[normalize-space(.)="Departing"][1]/following::text()[normalize-space(.)!=""][1]', $row, true, '/[:\d]{4,5}/');
            $timeArr = $pdf->FindSingleNode('./following::text()[normalize-space(.)="Arriving"][1]/following::text()[normalize-space(.)!=""][1]', $row, true, '/[:\d]{4,5}/');

            if ($date && $timeDep && $timeArr) {
                $timeDep = preg_replace('/(\d{2})(\d{2})/', '$1:$2', $timeDep);
                $timeArr = preg_replace('/(\d{2})(\d{2})/', '$1:$2', $timeArr);
                $s->departure()
                    ->date(strtotime($date . ', ' . $timeDep));

                $s->arrival()
                    ->date(strtotime($date . ', ' . $timeArr));
            }

            $depTerminal = $pdf->FindSingleNode('./following::text()[normalize-space(.)="Departure Terminal"][1]/following::text()[normalize-space(.)!=""][1]', $row, true, '/(Terminal[\w\s\d]+)/i');

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $arrTerminal = $pdf->FindSingleNode('./following::text()[normalize-space(.)="Arrival Terminal"][1]/following::text()[normalize-space(.)!=""][1]', $row, true, '/(Terminal[\w\s\d]+)/i');

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $duration = $pdf->FindSingleNode('./following::text()[normalize-space(.)="Estimated Time"][1]/following::text()[normalize-space(.)!=""][1]', $row, true, '/((:?\d+\s*[Hrs]+\s+|)\d+\s*[Mins]+)/i');

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $aircraft = $pdf->FindSingleNode('./following::text()[normalize-space(.)="Aircraft"][1]/following::text()[normalize-space(.)!=""][1]', $row);

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            $meal = $pdf->FindSingleNode('./following::text()[normalize-space(.)="Meal Service"][1]/following::text()[normalize-space(.)!=""][1]', $row);

            if (!empty($meal)) {
                $s->extra()
                    ->meal($meal);
            }

            $stops = $pdf->FindSingleNode('./following::text()[normalize-space(.)="Number of Stops"][1]/following::text()[normalize-space(.)!=""][1]', $row);

            if (strtolower($stops) === 'non-stop') {
                $s->extra()
                    ->stops('0');
            }

            $cabin = $pdf->FindSingleNode('./following::text()[normalize-space(.)="Class"][1]/following::text()[normalize-space(.)!=""][1]', $row);

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }
        }
    }
}
