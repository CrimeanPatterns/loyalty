<?php

namespace AwardWallet\Engine\alitalia\Email;

class TicketReceiptHtml2014Pl extends TicketReceiptHtml2014Fr
{
    public $mailFiles = "alitalia/it-4182505.eml";

    protected $pattern = [
        'recordLocator' => 'Twojej rezerwacji:',
        // Passangers
        'firstName'     => 'Imię:',
        'lastName'      => 'Nazwisko:',
        'ticketNumbers' => 'Nr biletu:',
        // Price list
        'totalCharge' => 'Cena całkowita',
        'tax'         => 'Opłaty lotniskowe',
        // Segments
        'code'          => '(Z|Do):',
        'traveledMiles' => 'Odległość:',
        'duration'      => 'Długość lotu:\s*(\d+\s*godz\.\s*\d+\s*min\.)',
        'aircraft'      => 'Samolot:(.+?)Lotnisko',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'confirmation@alitalia.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Podsumowanie transakcj') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Dziękujemy za dokonanie zakupu. Oto numer Twojej rezerwacji:') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alitalia.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['pl'];
    }

    protected function monthPlToEn($string)
    {
        if (preg_match('/[[:alpha:]]+/u', $string, $match)) {
            $monthName = mb_strtolower($match[0]);
        }

        $pl = ['stycz', 'lut', 'mar', 'kwie', 'maj', 'czerw', 'lip', 'sierp', 'wrze', 'październik', 'listopad', 'grud'];
        $en = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        $monthNameEn = null;

        foreach ($pl as $key => $value) {
            if ($this->startsWith($monthName, $value)) {
                $monthNameEn = $en[$key];

                break;
            }
        }

        return str_replace($monthName, $monthNameEn, $string);
    }

    protected function parseDate($date)
    {
        return strtotime($this->monthPlToEn($date . ' ' . $this->dateYear));
    }
}
