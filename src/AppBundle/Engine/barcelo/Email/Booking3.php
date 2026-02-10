<?php

namespace AwardWallet\Engine\barcelo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking3 extends \TAccountChecker
{
    public $mailFiles = "barcelo/it-620664581.eml, barcelo/it-621170772.eml, barcelo/it-623612369.eml";
    public $subjects = [
        'here is your booking confirmation for the',
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ["Your Booking"],
        "fr" => ["Votre réservation"],
        "es" => ["Tu Reserva"],
    ];

    public static $dictionary = [
        "en" => [
            //'Your Booking' => '',
            //'Customer details' => '',

            'Booking reference:' => ['Booking reference:', 'Locator:'],
            //'Customer details' => '',
            //'Name:' => '',
            'hotelStart' => 'Thank you for booking with us and choosing',
            'hotelEnd'   => 'for your next stay',
            //'Room' => '',
            //'Your Booking' => '',
            //'Check-in after' => '',
            //'Check-out before' => '',
            //'adults' => '',
            //'children' => '',
            'Total room amount' => ['Total room amount', 'Total amount (taxes included)*'],
        ],

        "fr" => [
            'Booking reference:'                => ['Référence:'],
            'hotelStart'                        => 'Nous vous remercions pour votre réservation et pour avoir choisi',
            'hotelEnd'                          => 'pour votre prochain séjour',
            'Customer details'                  => 'Coordonnées du client',
            'Name:'                             => 'Nom:',
            'Room'                              => 'Chambre',
            'Your Booking'                      => 'Votre réservation',
            'Check-in after'                    => 'Arrivée après',
            'Check-out before'                  => 'Départ avant',
            'adults'                            => 'adultes',
            'children'                          => 'enfants',
            'Total room amount'                 => 'Montant total (taxes incluses)*',
            'Cancellation and no-show policies' => 'Politiques d’annulation et de non-présentation',
        ],

        "es" => [
            'Booking reference:'                => ['Localizador:'],
            'hotelStart'                        => 'Muchas gracias por reservar con nosotros y elegir',
            'hotelEnd'                          => 'para su próxima estancia',
            'Customer details'                  => 'Datos del cliente',
            'Name:'                             => 'Nombre:',
            'Room'                              => 'Habitación',
            'Your Booking'                      => 'Tu Reserva',
            'Check-in after'                    => 'Entrada después de las',
            'Check-out before'                  => 'Salida antes de las',
            'adults'                            => 'adultos',
            'children'                          => 'niños',
            'Total room amount'                 => ['Importe total (Impuestos Incluidos)*', 'Importe total habitación'],
            'Cancellation and no-show policies' => 'Políticas de cancelación y No Show',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@barcelo.com') !== false) {
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
        $this->assignLang();

        return $this->http->XPath->query("//text()[contains(normalize-space(), 'Barceló Hotel Group')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your Booking'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Customer details'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]barcelo\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference:'))}]/following::text()[normalize-space()][1]"))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Customer details'))}]/ancestor::table[1]/following::table[1]/descendant::text()[{$this->starts($this->t('Name:'))}]", null, "/{$this->opt($this->t('Name:'))}\s*(.+)/"));

        $hotelName = trim($this->http->FindSingleNode("//text()[{$this->starts($this->t('hotelStart'))}]", null, true, "/{$this->opt($this->t('hotelStart'))}(.+){$this->opt($this->t('hotelEnd'))}/"));
        $h->hotel()
            ->name($hotelName);

        $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('hotelStart'))}]/following::text()[{$this->eq($this->t('Cancellation and no-show policies'))}][1]/following::text()[normalize-space()][1]");

        if (!empty($hotelName) && !empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $addressText = trim(implode("\n", $this->http->FindNodes("//text()[{$this->eq($hotelName)}]/ancestor::table[1]/descendant::text()[normalize-space()]")));

        if (preg_match("/{$hotelName}\n(?<address>(?:.+\n){1,4}).*@.+\n(?<phone>[+\d\-]{10,})/", $addressText, $m)) {
            $h->hotel()
                ->address(str_replace("\n", ", ", $m['address']))
                ->phone($m['phone']);
        }

        $checkInOutText = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Room'))}]/preceding::text()[{$this->eq($this->t('Your Booking'))}][1]/following::img[1]/ancestor::tr[1]"));

        if (count($checkInOutText) > 1) {
            $this->logger->debug('This is probably a new format!!!');

            return $email;
        }

        if (isset($checkInOutText[0]) && preg_match("/(?<inDate>(?:\w+\,?\s*)?\d+\s*\D*\s*\d{4})\s*\-\s*(?<outDate>(?:\w+\,?\s*)?\d+\s*\D*\s*\d{4}).*{$this->opt($this->t('Check-in after'))}\s*(?<inTime>[\d\:]+).*{$this->opt($this->t('Check-out before'))}\s*(?<outTime>[\d\:]+)\s+/u", $checkInOutText[0], $m)
        || isset($checkInOutText[0]) && preg_match("/(?<inDate>(?:\w+\,?\s*)?\w+\s*\d+\,?\s*\d{4})\s*\-\s*(?<outDate>(?:\w+\,?\s*)?\w+\s*\d+\,?\s*\d{4}).*{$this->opt($this->t('Check-in after'))}\s*(?<inTime>[\d\:]+).*{$this->opt($this->t('Check-out before'))}\s*(?<outTime>[\d\:]+)\s+/u", $checkInOutText[0], $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m['inDate'] . ' ' . $m['inTime']))
                ->checkOut($this->normalizeDate($m['outDate'] . ' ' . $m['outTime']));
        }

        $rooms = $this->http->FindNodes("//text()[{$this->eq($this->t('Room'))}]/ancestor::tr[1]/following::tr[1]/descendant::text()[string-length()>2][1]");
        $adults = $this->http->FindNodes("//text()[{$this->eq($this->t('Room'))}]/ancestor::tr[1]/following::tr[1]/descendant::text()[{$this->contains($this->t('adults'))}][1]", null, "/(\d+)\s+{$this->opt($this->t('adults'))}/");
        $kids = $this->http->FindNodes("//text()[{$this->eq($this->t('Room'))}]/ancestor::tr[1]/following::tr[1]/descendant::text()[{$this->contains($this->t('children'))}][1]", null, "/(\d+)\s+{$this->opt($this->t('children'))}/");

        $h->booked()
            ->rooms(count($rooms))
            ->guests(array_sum($adults))
            ->kids(array_sum($kids));

        foreach ($rooms as $roomDescription) {
            $h->addRoom()->setDescription($roomDescription);
        }

        $currency = $this->http->FindNodes("//text()[{$this->eq($this->t('Total room amount'))}]/ancestor::tr[1]/descendant::td[2]", null, "/^([A-Z]{3})\s*[\d\.\,]+/");

        if (count($currency) > 0) {
            $currency = array_unique(array_filter($currency));
        }

        $totalArray = $this->http->FindNodes("//text()[{$this->eq($this->t('Total room amount'))}]/ancestor::tr[1]/descendant::td[2]", null, "/^[A-Z]{3}\s*([\d\.\,]+)/");

        if (count($totalArray) > 0 && !empty($currency[0])) {
            foreach ($totalArray as $key => $val) {
                $totalArray[$key] = PriceHelper::parse($val, $currency[0]);
            }
        }

        $total = array_sum($totalArray);

        if (!empty($total) && !empty($currency[0])) {
            $h->price()
                ->total(PriceHelper::parse($total, $currency[0]))
                ->currency($currency[0]);
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseHotel($email);

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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/when making your booking, your stay does not permit cancellation/", $cancellationText, $m)
        || preg_match("/when making your reservation, your stay does not permit cancellation/", $cancellationText, $m)
        || preg_match("/N’oubliez pas, au moment de faire votre réservation, que ce séjour ne permet pas les annulations/", $cancellationText, $m)) {
            $h->booked()
                ->nonRefundable();
        }

        if (preg_match("/You can change or cancel your booking free of charge up until (\d+\s*days?) before your arrival at the hotel/", $cancellationText, $m)
        || preg_match("/You can make any change or cancellation to your reservation up to (\d+\s*days?) before your arrival/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m[1]);
        }

        if (preg_match("/puede hacer cualquier cambio o cancelación sin coste en su reserva hasta el día de su llegada \(hasta las (\d+)\.(\d+) horas\)/", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative('0 days', $m[1] . ':' . $m[2]);
        }
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^(?:\w+\s+)?(\d+\s*\w+\s*\d{4})\s+([\d\:]+)$#u", //dimanche 18 février 2024 15:00
            "#^(?:\D*\s+)?(\d+)\s*de\s*(\w+)\s*de\s*(\d{4})\s+([\d\:]+)$#u", //sábado, 13 de enero de 2024 15:00
        ];
        $out = [
            "$1, $2",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        //$this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
