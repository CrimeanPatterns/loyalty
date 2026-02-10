<?php

namespace AwardWallet\Engine\aceh\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsers with similar formats: goldpassport/InvoicePDF

class YourReservationPDF extends \TAccountChecker
{
    public $mailFiles = "aceh/it-207728725.eml";
    public $lang = 'en';
    public $hotels = [
        'www.acehotel.com/brooklyn/'     => 'Ace Hotel Brooklin',
        'www.acehotel.com/kyoto/'        => 'Ace Hotel Kyoto',
        'www.acehotel.com/los-angeles/'  => 'Ace Hotel Los-Angeles',
        'www.acehotel.com/new-orleans/'  => 'Ace Hotel New-Orleans',
        'www.acehotel.com/new-york/'     => 'Ace Hotel New-York',
        'www.acehotel.com/palm-springs/' => 'Ace Hotel Palm-Springs',
        'www.acehotel.com/portland/'     => 'Ace Hotel Portland',
        'www.acehotel.com/seattle/'      => 'Ace Hotel Seattle',
        'www.acehotel.com/sydney/'       => 'Ace Hotel Sydney',
        'www.acehotel.com/toronto/'      => 'Ace Hotel Toronto',
    ];
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'www.acehotel.com') !== false && strpos($text, 'INFORMATION INVOICE') !== false && strpos($text, 'Room No.') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]acehotel\.com$/', $from) > 0;
    }

    public function ParseHotelPDF(Email $email, $text)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->re("/{$this->opt($this->t('Conf. No.'))}[\s\:]+(\d{6,})/", $text));

        if (preg_match("/\n{3,}\s*(?<address>.+)\n\s*Phone:\s*(?<phone>[\d\(\)\-]+)\n\s*(?<site>www\.acehotel\.com\/\D+\/)\n\s*frontdesk/", $text, $m)) {
            $h->hotel()
                ->name($this->hotels[$m['site']])
                ->address($m['address'])
                ->phone($m['phone']);
        }

        $checkIn = $this->re("/{$this->opt($this->t('Arrival'))}[\s\:]+(\d+\S\d+\S\d{2})/", $text);
        $checkOut = $this->re("/{$this->opt($this->t('Departure'))}[\s\:]+(\d+\S\d+\S\d{2})/", $text);

        $h->booked()
            ->checkIn($this->normalizeDate($checkIn))
            ->checkOut($this->normalizeDate($checkOut));

        $roomNumber = $this->re("/{$this->opt($this->t('Room No.'))}[\s\:]+(\d+)/", $text);

        if (!empty($roomNumber)) {
            $room = $h->addRoom();

            $room->setDescription($roomNumber);
        }

        $currency = $this->re("/Charges\s*Credits.+\n\s*([A-Z]{3})/", $text);
        $total = $this->re("/Total\s+([\d\,\.]+)\s+/", $text);

        if (!empty($currency) && $total !== null) {
            $h->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->ParseHotelPDF($email, $text);
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
        $in = [
            "#^(\d+)\S(\d+)\S(\d{2})$#u", //10-15-22
        ];
        $out = [
            "$2.$1.20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
