<?php

namespace AwardWallet\Engine\bvisit\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "bvisit/it-322443387.eml, bvisit/it-405083782.eml, bvisit/it-406764613.eml, bvisit/it-407875012.eml";
    public $subjects = [
        // en
        'Reservation confirmation - ',
        // pt
        'Confirmação de reserva - ',
        // sv
        'Bokningsbekräftelse - ',
        // it
        'Conferma della prenotazione - ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            // 'Booking' => '',
            // 'Thank you for choosing' => '',
            // 'Your reservation number is' => '',
            // 'Guests:' => '',
            // 'adult' => '',
            // 'child' => '',
            'Responsible for booking' => 'Responsible for booking',
            // 'Name:' => '',

            'Payment overview' => 'Payment overview',
            // 'Subtotal:' => '',
            // 'VAT:' => '',
            // 'Friends of' => '', // discount
            // 'Total:' => '',

            'Accommodation' => 'Accommodation',
            'Room %'        => ['Room %', 'Accommodation %', 'Cabin %'],
            // 'Arrival date:' => '',
            // 'Check-out:' => '',
            // 'Rate plan:' => '',
            'Cancellation' => ['Cancellation', 'Cancellation term - non-refundable&non-changeable'],
            // 'Address:' => '',
            // 'Phone:' => '',
        ],
        "pt" => [
            'Booking'                    => 'Reserva',
            'Thank you for choosing'     => 'Obrigado por escolher o',
            'Your reservation number is' => 'O seu número de reserva é',
            'Guests:'                    => 'Hóspedes:',
            'adult'                      => 'adult',
            'child'                      => 'crianças',
            'Responsible for booking'    => 'Responsável pela reserva',
            'Name:'                      => 'Nome:',

            'Payment overview' => 'Visão geral de pagamento',
            'Subtotal:'        => 'Subtotal:',
            'VAT:'             => 'IVA:',
            // 'Friends of' => '', // discount
            'Total:' => 'Total:',

            'Accommodation'  => 'Alojamento',
            'Room %'         => 'Quarto %',
            'Arrival date:'  => 'Entrada:',
            'Check-out:'     => 'Saída:',
            'No. of nights:' => 'Nº de noites:',
            'Rate plan:'     => 'Plano de preço:',
            'Cancellation'   => 'Cancelamento Gratuito',
            'Address:'       => 'Morada:',
            'Phone:'         => 'Telefone:',
        ],
        "sv" => [
            'Booking'                    => 'Bokningen',
            'Thank you for choosing'     => 'Tack för din bokning hos oss på',
            'Your reservation number is' => 'Ditt bokningsnummer är',
            'Guests:'                    => 'Gäster:',
            'adult'                      => ['Vuxen', 'vuxna'],
            'child'                      => 'barn',
            'Responsible for booking'    => 'Ansvarig för bokningen',
            'Name:'                      => 'Namn:',

            'Payment overview' => 'Betalningsöversikt',
            'Subtotal:'        => 'Delsumma:',
            'VAT:'             => 'Moms:',
            // 'Friends of' => '', // discount
            'Total:' => 'Totalt:',

            'Accommodation'  => 'Boende',
            'Room %'         => ['Rum %', 'Boende %'],
            'Arrival date:'  => 'Incheckning:',
            'Check-out:'     => 'Utcheckning:',
            'No. of nights:' => 'Antal nätter:',
            'Rate plan:'     => ['Prisavtal:', 'Paket:'],
            'Cancellation'   => 'Avbokningsvillkor - kan avbokas 2 dagar innan ankomst',
            'Address:'       => 'Adress:',
            'Phone:'         => 'Telefon:',
        ],
        "it" => [
            'Booking'                    => 'La tua prenotazione è stata',
            'Thank you for choosing'     => 'Grazie per aver scelto',
            'Your reservation number is' => ' Il numero della tua prenotazione è',
            'Guests:'                    => 'Ospiti:',
            'adult'                      => 'adult',
            'child'                      => 'bambin',

            'Responsible for booking' => 'Responsabile della prenotazione',
            'Name:'                   => 'Nome:',

            'Payment overview' => 'Panoramica del pagamento.',
            'Subtotal:'        => 'Importo:',
            'VAT:'             => 'IVA:',
            // 'Friends of' => '', // discount
            'Total:' => 'Totale:',

            'Accommodation'  => 'Sistemazione',
            'Room %'         => 'Camera %',
            'Arrival date:'  => 'Check-in:',
            'Check-out:'     => 'Check out:',
            'No. of nights:' => 'N. notti:',
            'Rate plan:'     => 'Codice Tariffa:',
            'Cancellation'   => 'Cancellazione Non Rimborsabile',
            'Address:'       => 'Indirizzo:',
            'Phone:'         => 'Telefono:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@bookvisit.com') !== false) {
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
        if ($this->http->XPath->query("//img[contains(@src, 'bookvisit.com')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Responsible for booking'])
                && !empty($dict['Payment overview'])
                && !empty($dict['Accommodation'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Responsible for booking'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Payment overview'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Accommodation'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]bookvisit\.com$/', $from) > 0;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('Your reservation number is'))}]/ancestor::*[1]",
                null, true, "/{$this->opt($this->t('Your reservation number is'))}\s*([A-Z\d]{5,})/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Responsible for booking'))}]/following::text()[{$this->eq($this->t('Name:'))}]/ancestor::tr[1]/descendant::td[2]"), true);

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation'))}]/following::text()[normalize-space()][1]");

        if (!empty($cancellation)) {
            $h->general()
                ->cancellation($cancellation);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking'))}][contains(., '!')]", null, true, "/{$this->opt($this->t('Booking'))}\s+(\D+)\!/");

        if (!empty($status)) {
            $h->general()
                ->status($status);
        }

        $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Thank you for choosing'))}]", null, true, "/{$this->opt($this->t('Thank you for choosing'))}\s*(.+)\.\s+/");

        $h->hotel()
            ->name($hotelName)
            ->address($this->http->FindSingleNode("//text()[{$this->starts($this->t('Accommodation'))}]/following::text()[{$this->eq($this->t('Address:'))}][1]/following::text()[normalize-space()][1]"));

        $phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Accommodation'))}]/following::text()[{$this->eq($this->t('Phone:'))}]/following::text()[normalize-space()][1]");

        if (!empty($phone)) {
            $h->hotel()
                ->phone(str_replace(['– '], '', $phone));
        }

        $inDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Accommodation'))}]/following::text()[{$this->eq($this->t('Arrival date:'))}][1]/following::text()[normalize-space()][1]");

        if (preg_match("/^(.+,)\s*(?:[^\d\s]+(?: [^\d\s]+)?\s+)?(\d+:\d+(?:\s*[AP]M)?)\s*(?:\-.+)?$/i", $inDate, $m)) {
            $inDate = $m[1] . $m[2];
        }

        $outDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Accommodation'))}]/following::text()[{$this->starts($this->t('Check-out:'))}][1]/following::text()[normalize-space()][1]");

        if (preg_match("/^(.+,)\s*(?:[^\d\s]+(?: [^\d\s]+)?\s+)?(?:\d+:\d+(?:\s*[AP]M)?\s*\-\s*)?(\d+:\d+(?:\s*[AP]M)?)\s*$/i", $outDate, $m)) {
            $outDate = $m[1] . $m[2];
        }

        $h->booked()
            ->checkIn($this->normalizeDate($inDate))
            ->checkOut($this->normalizeDate($outDate))
            ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Responsible for booking'))}]/preceding::text()[{$this->eq($this->t('Guests:'))}]/following::text()[normalize-space()][1]",
                null, true, "/(\d+)\s*{$this->opt($this->t('adult'))}/i"));

        $kids = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Responsible for booking'))}]/preceding::text()[{$this->eq($this->t('Guests:'))}]/following::text()[normalize-space()][1]",
            null, true, "/(\d+)\s*{$this->opt($this->t('child'))}/i");

        if (!empty($kids)) {
            $h->booked()
                ->kids($kids);
        }

        $roomNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Accommodation'))}]/following::text()[{$this->starts($this->t('Room %'), 'translate(normalize-space(),"0123456789","%%%%%%%%%%")')}][contains(., ':')]");

        if ($roomNodes->length > 0) {
            $h->booked()
                ->rooms($roomNodes->length);
        }

        foreach ($roomNodes as $roomRoot) {
            $type = $this->http->FindSingleNode("./following::text()[normalize-space()][1][not(contains(normalize-space(), ':'))]", $roomRoot);
            $rateType = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::text()[{$this->eq($this->t('Rate plan:'))}][1]/following::text()[normalize-space()][1]", $roomRoot);

            if (!empty($type) || !empty($rateType)) {
                $room = $h->addRoom();

                if (!empty($type)) {
                    $room->setType($type);
                }

                if (!empty($rateType)) {
                    $room->setRateType($rateType);
                }

                $ratesXpath = "./ancestor::tr[1]/following::tr[1]/descendant::text()[{$this->eq($this->t('Rate plan:'))}][1]/following::text()[normalize-space()][1]/ancestor::tr[1]/following::table[1]/descendant::tr/td[normalize-space()][2][string-length()>3]/ancestor::tr[1]";

                foreach ($this->http->XPath->query($ratesXpath, $roomRoot) as $rateRoot) {
                    $rates[] = implode(' ', $this->http->FindNodes(".//text()[normalize-space()][not(ancestor::*[contains(@style, 'line-through')])]", $rateRoot));
                }
                // $rates = $this->http->FindNodes("./ancestor::tr[1]/following::tr[1]/descendant::text()[{$this->eq($this->t('Rate plan:'))}][1]/following::text()[normalize-space()][1]/ancestor::tr[1]/following::table[1]/descendant::tr/td[normalize-space()][2][string-length()>3]/ancestor::tr[1]", $roomRoot);

                $night = $this->http->FindSingleNode("./ancestor::tr[1]/following::tr[1]/descendant::text()[{$this->eq($this->t('No. of nights:'))}][1]/following::text()[normalize-space()][1]",
                    $roomRoot, true, "/^\s*(\d+)\b/");

                if (count($rates) == $night) {
                    $rates = preg_replace("/^.+:\s*/", '', $rates);
                    $room->setRates($rates);
                } elseif (!empty($rates)) {
                    $rate = implode(', ', $rates);
                    $room->setRate($rate);
                }
            }
        }

        $priceText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment overview'))}]/ancestor::td[1]/descendant::text()[{$this->eq($this->t('Total:'))}]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][last()]");

        if (preg_match("/^(?<total>[\d\s\.\,]+)\s*(?<currency>[A-Z]{3}$)/u", $priceText, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment overview'))}]/ancestor::td[1]/descendant::text()[{$this->eq($this->t('Subtotal:'))}]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][last()]", null, true, "/([\d\.\,\s]+)/");

            if (!empty($cost)) {
                $h->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $tax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment overview'))}]/ancestor::td[1]/descendant::text()[{$this->eq($this->t('VAT:'))}]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][last()]", null, true, "/([\d\.\,\s]+)/");

            if (!empty($tax)) {
                $h->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }

            $discount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Payment overview'))}]/ancestor::td[1]/descendant::text()[{$this->starts($this->t('Friends of'))}]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()][last()]", null, true, "/\-\s*([\d\.\,\s]+)/");

            if (!empty($discount)) {
                $h->price()
                    ->discount(PriceHelper::parse($discount, $m['currency']));
            }

            $this->detectDeadLine($h);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Responsible for booking'])
                && !empty($dict['Payment overview'])
                && !empty($dict['Accommodation'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Responsible for booking'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Payment overview'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Accommodation'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }
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

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
            // en
            preg_match("/Booking is non-refundable and non-changeable/", $cancellationText)
            //it
            || preg_match("/Questa prenotazione non può essere cancellata e l'intero importo sarà/", $cancellationText)
        ) {
            $h->booked()
                ->nonRefundable();
        }

        if (
            // en
            preg_match("/Free cancellation until (?<hours>\d+) hours? before arrival\./u", $cancellationText, $m)
            // pt
            || preg_match("/Se cancelado ou alterado até (?<hours>\d+) horas antes da data de chegada não será cobrada qualquer penalidade\./u", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative($m['hours'] . ' hours');
        }
    }

    private function starts($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) {
            return "starts-with({$text}, \"{$s}\")";
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

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // Saturday 7 January 2023, after 15:00
            // lördag 21 oktober 2023,
            "/^\s*\D*\s+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*,\s*(?:[^\d\s]+(?: [^\d\s]+)?\s+)?(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu",
            "/^\s*\D*\s+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*,\s*$/iu",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        // if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
        //     $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
        //     $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        // } elseif (preg_match("/\b\d{4}\b/", $str)) {
        //     $str = strtotime($str);
        // } else {
        //     $str = null;
        // }

        return strtotime($str);
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) use ($text) {
            return $text . "=\"{$s}\"";
        }, $field)) . ')';
    }
}
