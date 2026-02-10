<?php

namespace AwardWallet\Engine\despegar\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingHotel extends \TAccountChecker
{
    public $mailFiles = "despegar/it-1842700.eml"; // +2 bcdtravel(html)[es]
    public $lang = 'es';
    public static $dictionary = [
        'es' => [],
    ];

    private $langDetectors = [
        'es' => ['Titular de la reserva'],
    ];

    private $textSubject;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
//        $this->assignLang();
        $this->textSubject = $parser->getSubject();
        $this->parseEmail($email);

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@despegar.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match('/\w+@despegar\.com/i', $headers['from'])) {
            return true;
        }

        if (stripos($headers['subject'], 'Despegar.com') !== false && stripos($headers['subject'], 'Hotel') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//a[contains(@href,"//www.despegar.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//node()[contains(.,"Despegar.com") or contains(.,"despegar.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
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

        if (preg_match("#Número\s*:\s*([A-Z\d]{5,})#", $this->textSubject, $m)) {
            $email->ota()->confirmation($m[1]);
        }

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq(["Código de ingreso al hotel:", "Código de ingreso al alojamiento:"]) . "]/following::*[normalize-space()][1]"))
        ;
        $cancellationPolicy = $this->http->FindSingleNode('//text()[starts-with(normalize-space(.),"Política de cancelación:")]', null, true, '/^[^:]+:\s*(.+[.!])$/');

        if (empty($cancellationPolicy)) {
            $cancellationPolicy = $this->http->FindSingleNode('//tr[not(.//tr) and normalize-space()=\'Políticas de cancelación\']/following-sibling::tr[normalize-space()][1]');
        }

        if (!empty($cancellationPolicy)) {
            $h->general()
                ->cancellation($cancellationPolicy);
        }

        // Hotel
        $xpath = "//img[contains(@src, 'phone-grey.')]/ancestor::tr[1]/ancestor::*[1]";

        if (empty($this->http->FindSingleNode($xpath))) {
            $xpath = "//text()[normalize-space() = 'Detalles de la reserva']/preceding::text()[normalize-space()][1]/ancestor::tr[position()<3][./preceding::tr[.//tr][1][.//img]]/ancestor::*[1][count(tr[normalize-space()])>1]";
        }

        if (!empty($this->http->FindSingleNode($xpath))) {
            $h->hotel()
                ->name($this->http->FindSingleNode($xpath . "/tr[normalize-space()][1]"))
                ->address($this->http->FindSingleNode($xpath . "/tr[normalize-space()][2]"))
            ;
            $phones = $this->http->FindNodes($xpath . "/tr[normalize-space()][3][.//img[contains(@src, 'phone-grey.')]]//text()[normalize-space()]");

            foreach ($phones as $phone) {
                $h->hotel()
                    ->phone($phone);
            }
        }

        // Booked

        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq("Entrada:") . "]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq("Salida:") . "]/following::text()[normalize-space()][1]")))
            ->rooms($this->http->FindSingleNode("//text()[" . $this->eq("Reserva:") . "]/following::text()[normalize-space()][1]", null, true, "#\W(\d{1,2})\s*habitación#u"))
        ;

        $rooms = $this->http->FindNodes("//text()[translate(normalize-space(), '0123456789', 'dddddddddd') = 'Habitación d:']/ancestor::*[./following-sibling::*[normalize-space()]][1]/following-sibling::*[normalize-space()][1]");
        $adults = 0;
        $kids = 0;

        foreach ($rooms as $room) {
            $r = $h->addRoom();

            if (preg_match("#\W(\d{1,2})\s?adults#", $room, $m)) {
                $adults += $m[1];
            }
            // check word "menor"
            if (preg_match("#\W(\d{1,2})\s?menor#", $room, $m)) {
                $kids += $m[1];
            }

            if (preg_match("#^([^;]+);\s*([^,]+),#", $room, $m)) {
                $r->setType($m[1]);
                $h->general()->traveller($m[2], true);
            }
            $r->setRate($this->http->FindSingleNode("//text()[" . $this->eq("Precio por noche:") . "]/following-sibling::text()[normalize-space()][1]"), true, true);
        }

        if (!empty($adults)) {
            $h->booked()->guests($adults, true, true);
        }

        if (!empty($kids)) {
            $h->booked()->kids($kids, true, true);
        }

        $this->detectDeadLine($h, $cancellationPolicy);

        /*
         * not example for this part
        $xpathFragment2 = '//tr[normalize-space(.)="Habitación"]';

        if ( empty($it['Rooms']) )
            $it['Rooms'] = $this->http->FindSingleNode($xpathFragment2 . '/following::text()[starts-with(normalize-space(.),"Habitación ")][1]', null, true, '/(\d+)$/');

        $holder = $this->http->FindSingleNode($xpathFragment2 . '/following::text()[normalize-space(.)="Titular:"]/following::text()[normalize-space(.)][1]');
        if ($holder)
            $it['GuestNames'] = [$holder];

        $it['RoomType'] = $this->http->FindSingleNode($xpathFragment2 . '/following::text()[normalize-space(.)="Tipo:"]/following::text()[normalize-space(.)][1]');

        $it['Guests'] = $this->http->FindSingleNode($xpathFragment2 . '/following::text()[normalize-space(.)="Huéspedes:"]/following::text()[normalize-space(.)][1]', null, true, '/(\d+)\s*adultos/ui');

        $descriptionParts = [];
        $regime = $this->http->FindSingleNode($xpathFragment2 . '/following::text()[normalize-space(.)="Régimen:"]/following::text()[normalize-space(.)][1]');
        if ($regime)
            $descriptionParts[] = 'Régimen: ' . $regime;
        $beds = $this->http->FindSingleNode($xpathFragment2 . '/following::text()[normalize-space(.)="Camas:"]/following::text()[normalize-space(.)][1]');
        if ($beds)
            $descriptionParts[] = 'Camas: ' . $beds;
        if (count($descriptionParts))
            $it['RoomTypeDescription'] = implode('. ', $descriptionParts);
        */

        // Price
        $xpathPrice = '//tr[' . $this->eq(["Cargos", "Pago"]) . ']';

        $payment = $this->http->FindSingleNode($xpathPrice . '/following::text()[' . $this->starts(['TOTAL:', 'Total:']) . '][1]', null, true, '/^[^:]+:\s*(.+)/');

        if ($payment = explode('/', $payment)) {
            $payment = array_pop($payment);
        }

        if (preg_match('/^([^\d]+)\s*(\d[,.\d]*)\s*\*?\s*$/', $payment, $matches)) {
            $h->price()
                ->currency($this->currency($matches[1]))
                ->total($this->normalizePrice($matches[2]));
            $taxes = $this->http->FindSingleNode($xpathPrice . '/following::text()[' . $this->eq('Impuestos y tasas:') . '][1]/following::text()[normalize-space()][1]');

            if (empty($taxes)) {
                $taxes = $this->http->FindSingleNode($xpathPrice . '/following::text()[' . $this->starts('Impuestos y tasas:') . '][1]', null, true, "#:\s*(.+)#");
            }

            if (preg_match('#(?:^|/)\s*' . preg_quote($matches[1]) . '\s*(\d[,.\d]*)\s*$#u', $taxes, $m)) {
                $h->price()->tax($this->normalizePrice($m[1]));
            }
        }

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, ?string $cancellationText)
    {
        if (empty($cancellationText)) {
            return false;
        }

        if (
            preg_match("#Puedes cancelar o realizar cambios sin cargo hasta el (?<date>\d{2}/\d{2}/\d{4}) a las (?<time>\d{1,2}:\d{2})\.#ui", $cancellationText, $m)
        ) {
            $h->booked()->deadline(str_replace('/', '.', $m['date']) . ', ' . $m['time']);

            return true;
        }

        if (
            preg_match("#Cancelaciones con un mínimo de (?<day>\d+) días antes del check-in, NO se cobrarán cargos.#ui", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['day'] . ' day');

            return true;
        }

        if (
            preg_match("#La tarifa seleccionada no permite realizar cambios o cancelaciones\.#ui", $cancellationText)
        ) {
            $h->booked()->nonRefundable();

            return true;
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field, string $node = '', $separator = 'or'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(" {$separator} ", array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug("Date: {$date}");
        $in = [
            "#^\s*\w+,\s+(\d+)\s+(\w+)\s+(\d{4})\s+-\s+(\d{1,2}+:\d{2})\s+\w+\s*$#u", //Sábado, 25 Feb 2017 - 14:00 hs
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function getNode($str)
    {
        return $this->http->FindSingleNode("//*[contains(normalize-space(text()), '{$str}')]/following::text()[normalize-space(.)][1]");
    }

    private function re($re, $str, $c = 1)
    {
        if (preg_match($re, $str, $m)) {
            if (isset($m[$c])) {
                return $m[$c];
            }
        }

        return null;
    }

    private function res($re, $str, $c = 1)
    {
        if (preg_match_all($re, $str, $m)) {
            if (isset($m[$c])) {
                return $m[$c];
            }
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            'ARS $' => 'ARS $',
            'COP $' => 'COP $',
            'U$S'   => 'USD',
            'US$'   => 'USD',
            'MXN$'  => 'MXN',
            '€'     => 'EUR',
            '£'     => 'GBP',
            '₹'     => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return $s;
    }
}
