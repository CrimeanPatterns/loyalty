<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "bcd/it-336777809.eml";
    public $subjects = [
        'Confirmacion - Pasajero:',
    ];

    public $lang = 'es';
    public $pdfNamePattern = ".*pdf";
    public $subject;

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@bcdtravel.com.co') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'bcdtravelonline.travel') !== false
                && strpos($text, 'Fecha') !== false
                && strpos($text, 'Record sistema de reservaciones') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]bcdtravel\.com\.co$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation(str_replace(' ', '', $this->re("/Reserva\:\s*([A-Z\d\s\-]+)\n/", $text)))
            ->date(strtotime(str_replace('/', '.', $this->re("/Fecha de Solicitud\:\s*([\d\/]+)/u", $text))))
            ->traveller($this->re("/Confirmacion - Pasajero:\s*(\D+)\s+\-\s*[A-Z]+/u", $this->subject));

        if (preg_match_all("/^(\d{13})\s*\-/mu", $text, $m)) {
            $f->setTicketNumbers($m[1], true);
        }

        $year = $this->re("#\d+\/\d+\/(\d{4})#", $text);
        $codes = $this->re("/Confirmacion - Pasajero:\s*\D+\s+\-\s*([A-Z]+)/u", $this->subject);

        $flightText = $this->re("/^(\s*Fecha\s*Origen.+)Tarifa Neta/ms", $text);

        $xpath = "/\s+(?<bookingCode>[A-Z])\s*\-\s*(?<cabin1>\w+)*(?:\n*.*){1,2}\n^\s*(?<date>\w+\s*\d+).*[ ]{3,}(?<airlineName>[\S]\D*)\s+(?<depTime>[\d\:]+\s*A?P?M)\s+(?<arrTime>[\d\:]+\s*A?P?M)\s+(?<flightNumber>\d{1,4})\s*(?<cabin2>\w+)*\s(?<confirmation>[A-Z\d]{6})/mu";

        if (!empty($flightText)) {
            if (preg_match_all("/(\s+[A-Z]\s*\-\s*(?:\D+)?\n^\s*\w+\s*\d+.*[ ]{3,}[\S]\D*\s+[\d\:]+\s*A?P?M\s+[\d\:]+\s*A?P?M\s+\d+\s*(?:\D+)?\s[A-Z\d]{6})/um", $flightText, $match)) {
                foreach ($match[1] as $key => $flight) {
                    if (preg_match($xpath, $flight, $m)) {
                        $s = $f->addSegment();

                        $s->airline()
                            ->name($m['airlineName'])
                            ->number($m['flightNumber']);

                        $s->setConfirmation($m['confirmation']);

                        $s->departure()
                            ->date($this->normalizeDate($m['date'] . ' ' . $year . ', ' . $m['depTime']));

                        $s->arrival()
                            ->date($this->normalizeDate($m['date'] . ' ' . $year . ', ' . $m['arrTime']));

                        $s->extra()
                            ->bookingCode($m['bookingCode']);

                        if (isset($m['cabin1']) && !empty($m['cabin1'])) {
                            $s->extra()
                                ->cabin($m['cabin1']);
                        }

                        if (isset($m['cabin2']) && !empty($m['cabin2'])) {
                            $s->extra()
                                ->cabin($m['cabin2']);
                        }

                        if (preg_match("/(?:[A-Z]{3}){{$key}}(?<depCode>[A-Z]{3})(?<arrCode>[A-Z]{3})/", $codes, $m)) {
                            $s->departure()
                                ->code($m['depCode']);
                            $s->arrival()
                                ->code($m['arrCode']);
                        }
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        $this->subject = $parser->getSubject();

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->ParseFlightPDF($email, $text);
        }

        if (preg_match("/Tarifa Neta.*Total\n.*\n*.*\s(?<currency>[A-Z]{3})\s*(?<total>[\d\.\,]+)/u", $text, $m)) {
            $email->price()
                ->total(PriceHelper::parse($m['total']))
                ->currency($m['currency']);
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        //$this->http->log('$str = '.print_r( $str,true));

        $in = [
            "#^(\w+)\s*(\d+)\s*(\d{4})\,\s*([\d\:]+\s*A?p?M)$#ui", //ABRIL 11 2023, 8:21AM
        ];

        $out = [
            '$2 $1 $3, $4',
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
