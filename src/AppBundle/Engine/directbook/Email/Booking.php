<?php

namespace AwardWallet\Engine\directbook\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "";

    public $lang;
    public static $dictionary = [
        'en' => [
            //            'Thank you, your reservation at' => '',
            //            'has been confirmed.' => '',
            'Check-in'            => 'Check-in',
            'Check-out'           => 'Check-out',
            'Address and contact' => 'Address and contact',
            //            'Accommodation' => '',
            //            'adult' => '',
            //            'child' => '',
            'Booking reference number:' => ['Booking reference number:', 'Booking Reference Number:'],
            'Guest details'             => ['Guest details', 'Guest Details'],
            'Charges'                   => 'Charges',
            //            'Fees' => '',
            'Total'                                => 'Total',
            'Cancellation'                         => ['Cancellation', 'CANCELLATION POLICY', 'Cancellation Policy'],
            'Terms, conditions and Privacy Policy' => ['Terms, conditions and Privacy Policy', 'Terms and conditions', 'Terms and Conditions'],
        ],
        'ko' => [
            'Thank you, your reservation at' => '감사합니다.',
            'has been confirmed.'            => '예약이 확인되었습니다.',
            'Check-in'                       => '체크인',
            'Check-out'                      => '체크아웃',
            'Address and contact'            => '주소 및 연락처',
            'Accommodation'                  => '숙소 정보',
            'Booking reference number:'      => '예약 참조 번호:',
            // 'Rooms'                              => '',
            'adult'                                => '성인',
            'child'                                => '어린이',
            'Guest details'                        => '투숙객 정보',
            'Booked on'                            => '예약 날짜',
            'Charges'                              => '요금',
            'Fees'                                 => '수수료',
            'Total'                                => '총액',
            'Cancellation'                         => '취소',
            'Terms, conditions and Privacy Policy' => '이용 약관',
        ],
        'fr' => [
            'Thank you, your reservation at' => "Merci, votre réservation est confirmée pour l'établissement suivant :",
            'has been confirmed.'            => '', // not error
            'Check-in'                       => 'Arrivée',
            'Check-out'                      => 'Départ',
            'Address and contact'            => 'Adresse et coordonnées',
            'Accommodation'                  => 'Hébergement',
            'Booking reference number:'      => 'Référence de réservation :',
            // 'Rooms'                              => '',
            'adult'                          => 'adulte',
            // 'child' => '',
            'Guest details'                        => 'Informations sur le client',
            'Booked on'                            => 'Réservation effectuée le',
            'Charges'                              => 'Frais',
            'Fees'                                 => 'Frais',
            'Total'                                => 'Total',
            'Cancellation'                         => 'Deposit / Cancellation Policy:',
            'Terms, conditions and Privacy Policy' => 'Minimum Stay Policy:',
        ],
        'pt' => [
            'Thank you, your reservation at' => "Obrigado! A sua reserva no",
            'has been confirmed.'            => 'foi confirmada.',
            'Check-in'                       => 'Check-in',
            'Check-out'                      => 'Check-out',
            'Address and contact'            => 'Morada e contacto',
            'Accommodation'                  => 'Alojamento',
            'Booking reference number:'      => 'Número de referência da reserva:',
            // 'Rooms'                              => '',
            'adult'                          => 'adult',
            // 'child' => '',
            'Guest details'                        => 'Dados do hóspede',
            'Booked on'                            => 'Reservado em',
            'Charges'                              => 'Taxas',
            'Fees'                                 => 'Taxas',
            'Total'                                => 'Total',
            'Cancellation'                         => 'Cancelamento',
            'Terms, conditions and Privacy Policy' => 'Termos, condições e política de privacidade',
        ],
        'es' => [
            'Thank you, your reservation at' => "¡Gracias! Su reserva en",
            'has been confirmed.'            => 'está confirmada.',
            'Check-in'                       => 'Entrada',
            'Check-out'                      => 'Salida',
            'Address and contact'            => 'Dirección y contacto',
            'Accommodation'                  => 'Alojamiento',
            'Booking reference number:'      => 'Referencia de la reserva:',
            // 'Rooms'                              => '',
            'adult'                          => 'adult',
            // 'child' => '',
            'Guest details'                        => 'Datos del huésped',
            'Booked on'                            => 'Reservado el',
            'Charges'                              => 'Cargos',
            'Fees'                                 => 'Importe',
            'Total'                                => 'Total',
            'Cancellation'                         => 'Cancelación',
            'Terms, conditions and Privacy Policy' => 'Términos, condiciones y política de privacidad',
        ],
    ];

    private $detectFrom = ["donotreply@book-directonline.com", 'donotreply@app.thebookingbutton.com',
        'donotreply@reservation.easybooking-asia.com', 'donotreply@bookings.skytouchhos.com', 'donotreply@direct-book.com', ];
    private $detectSubject = [
        // en
        'Online Booking For', //Online Booking For Jordan Banchieri (BB23100114747299) Checking In: 13 Jan 2024
        'Booking Confirmation: Please Review',
        // fr
        'Réservation en ligne pour',
        // Confirmação de Reserva
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.](?:book-directonline|thebookingbutton|easybooking-asia|direct-book|skytouchhos)\.com\b/i", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->containsText($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach (self::$dictionary as $dict) {
            if (
                !empty($dict['Check-in']) && !empty($dict['Check-out'])
                && !empty($dict['Address and contact']) && !empty($dict['Guest details']) && !empty($dict['Charges'])
                && $this->http->XPath->query("//*[count(*[normalize-space()]) = 2][*[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($dict['Check-in'])}] and *[normalize-space()][2]/descendant::text()[normalize-space()][1][{$this->eq($dict['Check-out'])}]]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Address and contact'])}]/following::text()[{$this->eq($dict['Guest details'])}]/following::text()[{$this->eq($dict['Charges'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Address and contact"]) && !empty($dict["Guest details"])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Address and contact'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Guest details'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference number:'))}]/following::text()[normalize-space()][1]", null, true, "/^\s*([A-Z\d\-]{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Guest details'))}]/following::text()[normalize-space()][1]"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booked on'))}]/following::text()[normalize-space()][1]")))
        ;

        $countHr = $this->http->XPath->query("//text()[{$this->eq($this->t('Cancellation'))}]/following::hr")->length;
        $cancellation = [];

        if ($countHr > 0) {
            $cancellation = $this->http->FindNodes("//text()[{$this->eq($this->t('Cancellation'))}]/following::text()[normalize-space()][count(following::hr) = {$countHr}]");

            if (count($cancellation) > 20) {
                $cancellation = [];
            }
        }

        if (strlen(implode(" ", $cancellation)) > 2000) {
            $countHr = $this->http->XPath->query("(//text()[{$this->eq($this->t('Cancellation'))}])[last()]/following::hr")->length;
            $cancellation = [];

            if ($countHr > 0) {
                $cancellation = $this->http->FindNodes("(//text()[{$this->eq($this->t('Cancellation'))}])[last()]/following::text()[normalize-space()][count(following::hr) = {$countHr}]");

                if (count($cancellation) > 20) {
                    $cancellation = [];
                }
            }
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindNodes("//text()[{$this->eq($this->t('Cancellation'))}][last()]/following::text()[normalize-space()][following::text()[normalize-space()][{$this->eq($this->t('Terms, conditions and Privacy Policy'))}]]");
        }

        $cancellation = implode("\n", $cancellation);

        if (!empty($cancellation) && mb_strlen($cancellation) < 2000) {
            $h->general()
                ->cancellation($cancellation, true, true);
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->contains($this->t('Thank you, your reservation at'))}][{$this->contains($this->t('has been confirmed.'))}]",
                null, true, "/^\s*{$this->opt($this->t('Thank you, your reservation at'))}\s*(.+?)\s*{$this->opt($this->t('has been confirmed.'))}\s*$/"))
        ;
        $addressText = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Address and contact'))}]/following::text()[normalize-space()][1]/ancestor::*[not(.//text()[{$this->eq($this->t('Address and contact'))}])][last()]//text()[normalize-space()]"));

        if (preg_match("/^\s*(?<address>.+(?:\n.+)?)\n(?:Tel\:|Free Call)? *(?<phone>[\d \+\-\(\)\.]{5,})\n\s*\S+@\S+\s*(?:$|\n)/", $addressText, $m)) {
            $h->hotel()
                ->address(preg_replace(['/\s*,\s*/', '/\s+/'], [', ', ' '], $m['address']))
                ->phone($m['phone'])
            ;
        } elseif (preg_match("/^\s*(?<address>.+(?:\n.+)?)\n\s*\S+@\S+\s*(?:$|\n)/", $addressText, $m)) {
            $h->hotel()
                ->address(preg_replace(['/\s*,\s*/', '/\s+/'], [', ', ' '], $m['address']))
            ;
        }

        // Booked
        $checkXpath = "//*[count(*[normalize-space()]) = 2][*[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Check-in'))}] and *[normalize-space()][2]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Check-out'))}]]";
        $h->booked()
            ->checkIn($this->normalizeDate(implode(' ', $this->http->FindNodes($checkXpath . "/*[normalize-space()][1]/descendant::text()[normalize-space()][position() > 1]"))))
            ->checkOut($this->normalizeDate(implode(' ', $this->http->FindNodes($checkXpath . "/*[normalize-space()][2]/descendant::text()[normalize-space()][position() > 1]"))))
        ;

        $adults = 0;
        $kids = 0;

        // Rooms
        $roomTypes = [];
        $roomsNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Booking reference number:'))}]/following::text()[normalize-space()][2]/ancestor::*[not(.//text()[{$this->eq($this->t('Accommodation'))}])][last()]//img/ancestor::*[count(.//img) = 1][last()]");

        foreach ($roomsNodes as $rRoot) {
            $roomsText = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $rRoot));
            $roomRe = "/^(?:{$this->opt($this->t('Rooms'))}\n\s*)?(?<desc>(?<type>.+?)(?: *\\/ *(?<rateType>.+))?)\n(?<guests>.*{$this->opt($this->t('adult'))}.*(?:\s*,\s*.*{$this->opt($this->t('child'))}.*)?)\n.+\s*/iu";

            if (preg_match($roomRe, $roomsText, $m)) {
                $h->addRoom()
                    ->setType($m['type'])
                    ->setRateType($m['rateType'], true, true)
                ;
                $roomTypes[] = $m['desc'];

                if (preg_match("/^\s*(\d+)\s*{$this->opt($this->t('adult'))}/iu", $m['guests'], $mat)
                    || (in_array($this->lang, ['ko']) && preg_match("/^\s*{$this->opt($this->t('adult'))}\s*(\d+)/iu", $m['guests'], $mat))
                ) {
                    $adults += $mat[1];
                }

                if (preg_match("/,\s*(\d+)\s*{$this->opt($this->t('child'))}/iu", $m['guests'], $mat)
                    || (in_array($this->lang, ['ko']) && preg_match("/,\s*{$this->opt($this->t('child'))}\s*(\d+)/iu", $m['guests'], $mat))
                ) {
                    $kids += $mat[1];
                }
            }
        }

        if ($roomsNodes->length === 0) {
            $roomTypes = [];
            $roomsNodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Booking reference number:'))}]/following::text()[normalize-space()][position() < 3][{$this->starts($this->t('Room:'))}]/ancestor::*[not(.//text()[{$this->eq($this->t('Accommodation'))}])]//text()[{$this->starts($this->t('Room:'))}]/ancestor::tr[1]");

            foreach ($roomsNodes as $rRoot) {
                $roomsText = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $rRoot));
                $roomRe = "/^(?:{$this->opt($this->t('Rooms'))}\n\s*)?(?<desc>(?<type>.+?)(?: *\\/ *(?<rateType>.+))?)\n(?<guests>.*{$this->opt($this->t('adult'))}.*(?:\s*,\s*.*{$this->opt($this->t('child'))}.*)?)\n.+\s*/iu";

                if (preg_match($roomRe, $roomsText, $m)) {
                    $h->addRoom()
                        ->setType($m['type'])
                        ->setRateType($m['rateType'], true, true);
                    $roomTypes[] = $m['desc'];

                    if (preg_match("/^\s*(\d+)\s*{$this->opt($this->t('adult'))}/iu", $m['guests'], $mat)
                        || (in_array($this->lang, ['ko']) && preg_match("/^\s*{$this->opt($this->t('adult'))}\s*(\d+)/iu", $m['guests'], $mat))
                    ) {
                        $adults += $mat[1];
                    }

                    if (preg_match("/,\s*(\d+)\s*{$this->opt($this->t('child'))}/iu", $m['guests'], $mat)
                        || (in_array($this->lang, ['ko']) && preg_match("/,\s*{$this->opt($this->t('child'))}\s*(\d+)/iu", $m['guests'], $mat))
                    ) {
                        $kids += $mat[1];
                    }
                }
            }
        }

        if (empty($h->getRooms())) {
            $h->addRoom()->setType(null);
        }

        $h->booked()
            ->guests($adults)
            ->kids($kids);

        // Price
        $totalText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Address and contact'))}]/following::tr[{$this->eq($this->t('Total'))}]/following-sibling::tr[normalize-space()][1]");

        if (empty($totalText)) {
            $totalText = $this->http->FindSingleNode("//tr[*[1][{$this->eq($this->t('Total:'))}]]/*[2]");
        }

        $total = $this->getTotal($totalText);
        $h->price()
            ->total($total['amount'])
            ->currency($total['currency']);

        $priceXpath = "//table[not(.//table)][preceding::text()[{$this->eq($this->t('Charges'))}]][following::text()[{$this->eq($this->t('Total'))}]]";

        $nights = 0;

        if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
            $nights = date_diff(
                date_create('@' . strtotime('00:00', $h->getCheckOutDate())),
                date_create('@' . strtotime('00:00', $h->getCheckInDate()))
            )->format('%a');
        }

        if (!empty($roomTypes) && !empty($nights)) {
            $ratesText = implode("\n", $this->http->FindNodes($priceXpath . "[contains(., '{$roomTypes[0]}')]//text()[normalize-space()]"));

            $ratesParts = $this->split("/\n({$this->opt($roomTypes)}\n)/", "\n\n" . $ratesText);

            if (count($ratesParts) == count($roomTypes)) {
                foreach ($ratesParts as $i => $rText) {
                    $rates = [];

                    if (preg_match("/^\s*{$this->opt($roomTypes[$i])}\s+([\S\s]+)$/", $rText, $m)) {
                        $rows = explode("\n", trim($m[1]));

                        if (count($rows) !== $nights * 2) {
                            break;
                        }

                        for ($j = 0; $j < count($rows); $j += 2) {
                            if (preg_match("/\b20\d{2}\b/", $rows[$j])) {
                                $rates[] = $rows[$j + 1];
                            }
                        }
                    }

                    $h->getRooms()[$i]->setRates($rates);
                }
            }
        }
        $feeNodes = $this->http->XPath->query($priceXpath . "[descendant::text()[normalize-space()][1][{$this->eq($this->t('Fees'))}]]//tr[normalize-space()][not({$this->eq($this->t('Fees'))})]");

        foreach ($feeNodes as $fRoot) {
            $name = $this->http->FindSingleNode("./*[normalize-space()][1]", $fRoot);
            $value = $this->getTotal($this->http->FindSingleNode("./*[normalize-space()][2]", $fRoot))['amount'];

            $h->price()
                ->fee($name, $value);
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            // 03 Feb 2024 from 1:00pm (13:00)
            // 26 oct. 2023 à partir de 12:00pm (12:00)
            '/^\s*(\d{1,2})\s+([[:alpha:]]+)[.]?\s+(\d{4})\s+(?:from|à partir de|a partir de|desde las|[[:alpha:] ]+)\s+(\d{1,2}:\d{2}(\s*[ap]m)?)?(?:\s*\(.+\))?\s*$/ui',
            // 07 Out 2023
            '/^\s*(\d{1,2})\s+([[:alpha:]]+)[.]?\s+(\d{4})\s*$/ui',
            // 31 7월 2023
            '/^\s*(\d{1,2})\s+(\d{1,2})월\s+(\d{4})\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$1 $2 $3',
            '$3-$2-$1',
        ];

        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('date replace = ' . print_r( $date, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }
//        if (
//            preg_match("/Free cancellation before (?<day>\d+)\-(?<month>\D+)\-(?<year>\d{4}) (?<time>\d+:\d+)\./i", $cancellationText, $m)
//        ) {
//            $h->booked()
//                ->deadline($this->normalizeDate($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time']));
//        }
//
//        if (
//            preg_match("/Free cancellation before  (?<date>.+{6,40}), (?<time>\d+:\d+)\./i", $cancellationText, $m)
//        ) {
//            $h->booked()
//                ->deadline2($this->normalizeDate($m['date'] . ', ' . $m['time']));
//        }
//
//        if (
//            preg_match("/This reservation is non-refundable/i", $cancellationText, $m)
//        ) {
//            $h->booked()
//                ->nonRefundable();
//        }
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $m['amount'] = PriceHelper::parse($m['amount'], $m['currency']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }

    private function currency($s)
    {
        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        $currency = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Prices are in'))}]",
            null, true, "/{$this->opt($this->t('Prices are in'))}\s+([A-Z]{3})\s*$/");

        if (!empty($currency)) {
            return $currency;
        }

        $sym = [
            'Rs.'=> 'INR',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
