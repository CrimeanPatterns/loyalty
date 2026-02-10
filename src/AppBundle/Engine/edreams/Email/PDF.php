<?php

namespace AwardWallet\Engine\edreams\Email;

use AwardWallet\Schema\Parser\Email\Email;

class PDF extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "edreams/it-12113989.eml, edreams/it-4028320.eml, edreams/it-4028321.eml, edreams/it-4031293.eml";
    public $reBody = [
        'fr' => ['Passager', 'Date de facturation'],
        'pt' => ['Passageiro', 'Data da Factura'],
        'es' => ['Pasajero', 'Fecha de Factura'],
        'en' => ['Passenger(s)', 'Printed Date'],
    ];
    public $pdf;
    public $lang = '';
    public static $dict = [
        'fr' => [
            'Record locator'  => 'Réservation N°:',
            'Passenger'       => 'Passager',
            'Date of booking' => 'Date de facturation',
            'Ticket'          => 'Tax. Amount',
            'Total cost'      => 'Total des prestations',
            'Dep Name'        => 'Itinéraire',
            'Dep Date'        => 'Date',
            'From'            => 'des',
        ],
        'pt' => [
            'Record locator'  => 'Reserva nº:',
            'Passenger'       => 'Passageiro',
            'Date of booking' => 'Data da Factura',
            'Ticket'          => 'Tax. Amount',
            'Total cost'      => 'Total dos Serviços',
            'Dep Name'        => 'Rota',
            'Dep Date'        => 'Data de emissão',
            'From'            => 'dos',
            'Flight'          => 'Vôo',
            'Class'           => 'Classe',
            'Depart'          => 'Saída',
            'Return'          => 'Chegada',
        ],
        'es' => [
            'Record locator'  => 'Número de Factura',
            'Passenger'       => 'Pasajero',
            'Date of booking' => 'Fecha de Factura',
            'Total cost'      => 'Total servicio',
            'Depart'          => 'Hora de Salida',
            'Flight'          => 'Vuelo',
            'Return'          => 'Llegada a',
            'Class'           => 'Clase',
            'Ticket'          => 'Tax. Amount',
        ],
        'en' => [
            'Record locator' => 'Booking No',
            //'Passenger' => '',
            'Date of booking' => 'Receipt Date',
            'Total cost'      => 'Total for Services',
            //'Depart' => '',
            //'Flight' => '',
            'Return' => 'Arrive',
            'Class'  => 'Class',
            //'Ticket' => '',
            'Total' => 'Fare',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                    $this->pdf = clone $this->http;
                    $this->pdf->SetBody($html);
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }
        $body = $this->pdf->Response['body'];

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                } else {
                    $this->lang = 'en';
                }
            }
        }
        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], "noreply@edreams.com") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "noreply@edreams.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();
        $confNumber = $this->pdf->FindSingleNode("//*[contains(text(), '" . $this->t('Record locator') . "')]/following-sibling::text()[normalize-space(.)!=''][1]", null, true, "#(?:(\d+)|([\d\w])*)#");

        if (empty($confNumber)) {
            $confNumber = $this->pdf->FindSingleNode("//*[contains(text(), '" . $this->t('Record locator') . "')]/following-sibling::*[normalize-space(.)!=''][2]");
        }
        $f->general()
            ->confirmation($confNumber)
            ->travellers($this->pdf->FindNodes("//*[contains(text(), '" . $this->t('Passenger') . "')]/following-sibling::text()[normalize-space(.)!=''][1]"), true)
            ->date(strtotime($this->normalizeDate($this->pdf->FindSingleNode("//*[contains(text(), '" . $this->t('Date of booking') . "')][normalize-space(.)!=''][1]"))));

        $currency = $this->pdf->FindSingleNode("(//*[starts-with(text(), 'Total') and not(contains(., '" . $this->t('From') . "'))]/following-sibling::text()[1])[1]", null, true, "#\((\w{3})\)#");

        if (empty($currency)) {
            $currency = $this->pdf->FindSingleNode("//text()[contains(., 'Fare')]/following::text()[normalize-space()][1]", null, true, "#^([A-Z]{3})$#");
        }
        $f->price()
            ->total(cost($this->pdf->FindSingleNode("(//*[contains(normalize-space(.), '" . $this->t('Total cost') . "')]/following-sibling::text()[normalize-space(.)!=''][1])[1]")))
            ->currency($currency);

        $ticket = $this->pdf->FindSingleNode("(//*[starts-with(text(), 'Total') and not(contains(., '" . $this->t('From') . "'))]/following-sibling::text()[1])[1]", null, true, "#(\d+)\.#");

        if (!empty($ticket)) {
            $f->issued()
                ->ticket($ticket, false);
        }

        $xpath = "//text()[contains(., '" . $this->t('Flight') . "')]";
        $roots = $this->pdf->XPath->query($xpath);

        if ($roots->length === 0) {
            $xpath = "//*[contains(text(), '" . $this->t('Ticket') . "')]";
            $roots = $this->pdf->XPath->query($xpath);
        }

        if ($roots->length === 0) {
            $this->pdf->Log("roots not found {$xpath}", LOG_LEVEL_NORMAL);
        }

        foreach ($roots as $root) {
            $s = $f->addSegment();

//            FLIGHT INFO
            $infoAir = $this->pdf->FindSingleNode(".", $root);

            if (preg_match("#" . $this->t('Flight') . ":\s*(\w{2})\s*(\d+)#", $infoAir, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            } else {
                $s->airline()
                    ->noName()
                    ->noNumber();
            }

            $cabin = $this->pdf->FindSingleNode("following-sibling::text()[contains(., '" . $this->t('Class') . "')][1]", $root, true, "#" . $this->t('Class') . ":\s(\w+)#");

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $seat = $this->pdf->FindSingleNode("following-sibling::text()[contains(., '" . $this->t('Seats') . "')][1]", $root, true, "#" . $this->t('Seats') . ":\s([A-Z\d]+)#");

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }

//            DATE
            $date = $this->normalizeDate($this->pdf->FindSingleNode("preceding-sibling::*[normalize-space(.)!=''][1]", $root, true, "#.*\d.*#"));

            if (empty($date)) {
                $date = $this->normalizeDate($this->pdf->FindSingleNode("preceding::text()[contains(., '" . $this->t('Dep Date') . "')][1]", $root, true, "#.*\d.*#"));
            }

//            DEP INFO
            $dep = $this->setRegExp($this->pdf->FindSingleNode("following-sibling::text()[contains(., '" . $this->t('Depart') . "')][1]", $root));

            if (count($dep) >= 3) {
                $s->departure()
                    ->date((!empty($dep['Date'])) ? strtotime($dep['Date'] . ' ' . $dep['Time']) : strtotime($date . ' ' . $dep['Time']))
                    ->name($dep['Name']);

                if (isset($dep['Terminal'])) {
                    $s->departure()
                        ->terminal($dep['Terminal']);
                }
            }

//            ARR INFO
            $arr = $this->setRegExp($this->pdf->FindSingleNode("following-sibling::text()[contains(., '" . $this->t('Return') . "')][1]", $root));

            if (count($arr) >= 3) {
                $s->arrival()
                    ->date((!empty($arr['Date'])) ? strtotime($this->normalizeDate($arr['Date']) . ' ' . $arr['Time']) : strtotime($this->normalizeDate($date) . ' ' . $dep['Time']))
                    ->name($arr['Name']);

                if (isset($arr['Terminal']) && !empty($arr['Terminal'])) {
                    $s->arrival()
                        ->terminal($arr['Terminal']);
                }
            }

            if (empty($s->getDepName()) && empty($s->getArrName())) {
                $infoAir = $this->pdf->FindSingleNode("preceding::text()[contains(., '" . $this->t('Dep Name') . "')][1]", $root, true, "#" . $this->t('Dep Name') . ":\s+(.+)#");

                if (preg_match("#(\w+) - (\w+)#", $infoAir, $m)) {
                    $s->departure()->name($m[1]);
                    $s->arrival()->name($m[2]);
                }
            }

//            DATE
            if (!empty($date) && empty($s->getDepDate()) && empty($s->getArrDate()) && preg_match("#\d+[\.]*\d+[\.]*\d+#", $date) !== false) {
                $s->departure()->date(strtotime($date));
                $s->arrival()->date(strtotime($date));
            }

            if (empty($date) && empty($s->getDepDate()) && empty($s->getArrDate())) {
                $s->departure()->noDate();
                $s->arrival()->noDate();
            }

//            CODES
            $code = $this->pdf->FindSingleNode("preceding::text()[4]", $root);

            if (preg_match("#(\w{3})\s*-\s*.+\s*-\s*(\w{3})\s+.+#", $code, $math)) {
                $s->departure()->code($math[1]);
                $s->arrival()->code($math[2]);
            } else {
                $s->departure()->noCode();
                $s->arrival()->noCode();
            }
        }

        return true;
    }

    private function setRegExp($str)
    {
        if (preg_match("#:\s+(?<name>(?:\w+|[\w\s]+))\s+(?:at)\s+(?<time>\d{2}:\d{2})[.]*\s*[Terminal]*[:]*\s*(?<terminal>[\d]*)#", $str, $m) //Depart: Taipei taoyuan Intl Apt at 16:30. Terminal: 2.
            || preg_match("#:\s+(?<name>(?:\w+|[\w\s]+))\s+(?<date>[\d\w\-\.]*)\s*(?:às|a las|at)\s+(?<time>\d{2}:\d{2})[.]*\s*(?<terminal>[Terminal]*[:]*\s*[\d]*)#", $str, $m)
        ) {
            if (isset($m['date'])) {
                return [
                    'Name'     => $m['name'],
                    'Date'     => $m['date'],
                    'Time'     => $m['time'],
                    'Terminal' => $m['terminal'],
                ];
            } else {
                return [
                    'Name'     => $m['name'],
                    'Time'     => $m['time'],
                    'Terminal' => $m['terminal'],
                ];
            }
        }

        return [];
    }

    private function normalizeDate($date)
    {
        //$this->logger->error($date);
        $in = [
            '#[\S\s]*(\d{2})[\.\/]*(\d{2})[\.\/]*(\d{2})#',
            '#^[\D\s\:]*\s*(\d+)\-(\w+)\-(\d+)$#', //Receipt Date: 26-Feb-13
        ];
        $out = [
            "$2/$1/$3",
            "$1 $2 20$3",
        ];

        return preg_replace($in, $out, $date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
