<?php

namespace AwardWallet\Engine\copaair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation2 extends \TAccountChecker
{
    public $mailFiles = "copaair/it-152357585-pt.eml, copaair/it-167796455.eml, copaair/it-186255100.eml, copaair/it-186891332.eml, copaair/it-636815446.eml, copaair/it-88293378.eml, copaair/it-88515978.eml";
    public $subjects = [
        // en
        'Reservation Confirmation',
        'Your new itinerary for reservation',
        // pt
        'Confirmação de Reserva',
        'Seu novo itinerário foi confirmado para a reserva',
        // es
        'Confirmación de Reserva',
        'Tu nuevo itinerario para la reserva',
    ];

    public $lang = '';
    public $subject;

    public $detectLang = [
        'en' => ['Itinerary details', 'New itinerary'],
        'pt' => ['Detalhes do itinerário', 'Novo itinerário'],
        'es' => ['Detalles de itinerario', 'Nuevo itinerario'],
    ];

    public static $dictionary = [
        "en" => [
            'Itinerary details' => ['Itinerary details', 'New itinerary'],
            'New itinerary'     => ['New itinerary'],
            'Pay now'           => ['Pay now', 'Manage your booking'],
            'Reservation total' => ['Reservation total', 'Total'],
            'Member Member'     => ['Member Member', 'Member'],
        ],
        "pt" => [
            //detects
            'Itinerary details' => ['Detalhes do itinerário', 'Novo itinerário'],
            'New itinerary'     => 'Novo itinerário',
            'Pay now'           => 'Gira sua reserva',

            'Reservation Code:'              => ['Código de Reserva:', 'Código de reserva:'],
            'Passengers in this reservation' => 'Passageiros nesta reserva',
            'Seats'                          => 'Assentos',
            'Operated by'                    => 'Operado por',
            'Fare Family'                    => 'Familia tarifária',
            //'Member Member' => '',
            'Reservation total' => 'Total',
            'Subtotal'          => 'Subtotal',
            'Taxes and fees'    => 'Taxas e impostos',
            /*'' => '',
            '' => '',*/
        ],

        "es" => [
            //detects
            'Itinerary details' => ['Detalles de itinerario', 'Nuevo itinerario'],
            'New itinerary'     => 'Nuevo itinerario',
            'Pay now'           => ['Maneja tu reserva', 'Pagar ahora'],

            'Reservation Code:'              => ['Código de Reserva:', 'Reservation code:'],
            'Passengers in this reservation' => 'Pasajeros en esta reserva',
            'Seats'                          => 'Asientos',
            'Operated by'                    => 'Operado por',
            'Fare Family'                    => 'Familia Tarifaria',
            'Member Member'                  => 'Presidential Member',
            'Reservation total'              => 'Total',
            'Subtotal'                       => 'Subtotal',
            'Taxes and fees'                 => 'Tasas e impuestos',
            /*'' => '',
            '' => '',
            '' => '',*/
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@cns.copaair.com') !== false) {
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
        if ($this->http->XPath->query("//img[contains(@src, '.copaair.com')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Itinerary details']) && $this->http->XPath->query("//text()[{$this->contains($dict['Itinerary details'])}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]cns\.copaair\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email): void
    {
        $patterns = [
            'time' => '\d{1,2}[:：] ?\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];

        $newItinerary = false;

        if (!empty($this->http->FindSingleNode("//text()[{$this->eq($this->t("New itinerary"))}]"))) {
            $newItinerary = true;
        }

        $f = $email->add()->flight();

        if (!empty($this->subject)) {
            $f->general()
                ->status($this->subject);
        }

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Itinerary details'))}]/preceding::text()[{$this->eq($this->t('Reservation Code:'))}][1]/following::text()[normalize-space()][1]"));

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers in this reservation'))}]/ancestor::tr[1]/following-sibling::tr/descendant::td/descendant::p", null, "/^[A-Z\s]+$/"));

        if (count($travellers) == 0) {
            $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers in this reservation'))}]/ancestor::tr[1]/following-sibling::tr/descendant::tr/descendant::text()[normalize-space()][1][not(contains(normalize-space(), '.'))]", null, "/^[A-Z\s]+$/"));
        }

        if ($newItinerary !== true) {
            $f->general()
                ->travellers($travellers, true);
        }

        $xpath = "//text()[{$this->eq($this->t('Itinerary details'))}]/ancestor::tr[1]/following-sibling::tr";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        $seatsAll = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers in this reservation'))}]/ancestor::tr[1]/following-sibling::tr/descendant::text()[{$this->eq($this->t('Seats'))}]/ancestor::th[1]", null, "/{$this->opt($this->t('Seats'))}\s*(\d{1,2}[A-Z]{1}.*)/"));

        foreach ($nodes as $i => $root) {
            $s = $f->addSegment();

            $airlineName = $this->http->FindSingleNode("./descendant::tr[2]/descendant::td[normalize-space()][2]", $root, true, "/^([A-Z\d]{2})\s/");
            $flightNumber = $this->http->FindSingleNode("./descendant::tr[2]/descendant::td[normalize-space()][2]", $root, true, "/\s(\d{2,4})$/");

            if (!empty($airlineName) && !empty($flightNumber)) {
                $s->airline()
                    ->name($airlineName)
                    ->number($flightNumber);
            }

            $operated = $this->http->FindSingleNode("./descendant::text()[" . $this->contains($this->t("Operated by")) . "]/following::text()[normalize-space()][1]", $root);

            if (!empty($operated)) {
                $s->airline()
                    ->operator($operated);
            }

            $cabin = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Fare Family'))}]/following::text()[normalize-space()][1]", $root, true, "/^(\D+)\(/");

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $bookingCode = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Fare Family'))}]/following::text()[normalize-space()][1]", $root, true, "/\(([A-Z]{1})\)/");

            if (!empty($bookingCode)) {
                $s->extra()
                    ->bookingCode($bookingCode);
            }

            $duration = $this->http->FindSingleNode("./descendant::text()[contains(normalize-space(), 'hr')][1]", $root, true, "/\((.+)\)/");

            if (empty($duration)) {
                $durations = array_filter($this->http->FindNodes("./descendant::text()[contains(normalize-space(), 'h') and contains(normalize-space(), 'm')][1]", $root, "/^\s*\(?\s*(\d{1,5}h ?\d{1,2}m)\s*\)?\s*$/"));

                if (count($durations) === 1) {
                    $duration = array_shift($durations);
                }
            }

            if (!empty($duration)) {
                $s->extra()
                    ->duration($duration);
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("descendant::tr[2]/descendant::td[1]", $root, true, '/^.*\d.*$/'));
            $depInfo = implode(' ', $this->http->FindNodes("descendant::tr[3]/descendant::td[string-length()>3][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<time>{$patterns['time']})\s*(?<airport>\S.{3,})$/", $depInfo, $m)) {
                $s->departure()
                    ->date(strtotime(str_replace(' ', '', $m['time']), $date))
                    ->name($m['airport'])
                    ->noCode();
            }

            $arrInfo = implode(' ', $this->http->FindNodes("descendant::tr[3]/descendant::td[contains(normalize-space(),':')][2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<time>{$patterns['time']})\s*(?<airport>\S.{3,})$/", $arrInfo, $m)) {
                $s->arrival()
                    ->date(strtotime(str_replace(' ', '', $m['time']), $date))
                    ->name($m['airport'])
                    ->noCode();
            }

            $seats = [];

            if (isset($seatsAll[0])) {
                foreach ($seatsAll as $seatsText) {
                    $seatArray = explode(',', $seatsText);

                    if (isset($seatArray[$i])) {
                        $seats[] = $seatArray[$i];
                    }
                }
            }

            if (count($seats) > 0) {
                $s->setSeats($seats);
            }

            if (empty($s->getAirlineName()) && empty($s->getFlightNumber()) && empty($s->getDepName()) && empty($s->getArrName())) {
                $f->removeSegment($s);
            }
        }

        $accounts = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers in this reservation'))}]/ancestor::tr[1]/following-sibling::tr/descendant::text()[{$this->eq($this->t('Member Member'))}]/following::text()[normalize-space()][1]", null, "/[#]\s*([A-Z\d]+)/");

        if (count($accounts) > 0) {
            $f->setAccountNumbers(array_unique($accounts), false);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t('Reservation total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)$/', $totalPrice, $matches)) {
            // 2445.56 USD    |    5.285,40 BRL
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);

            $cost = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t('Subtotal'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $cost, $m)) {
                $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $tax = $this->http->FindSingleNode("//tr[ *[normalize-space()][1][{$this->eq($this->t('Taxes and fees'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $tax, $m)) {
                $f->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Itinerary details']) && $this->http->XPath->query("//text()[{$this->contains($dict['Itinerary details'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        if (preg_match("/Your reservation\s*(is processing)/u", $parser->getSubject(), $m)) {
            $this->subject = $m[1];
        }

        $this->ParseFlight($email);

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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $reBody) {
            foreach ($reBody as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function normalizeDate(?string $str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // Mon, 31 May, 2021
            "/^[-[:alpha:]]+\s*,\s*(\d{1,2})\s*([[:alpha:]]+)\s*,\s*(\d{4})$/u",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
