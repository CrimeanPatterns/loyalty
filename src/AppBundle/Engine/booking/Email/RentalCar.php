<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Schema\Parser\Email\Email;

class RentalCar extends \TAccountChecker
{
    public $mailFiles = "booking/it-50167501.eml, booking/it-56733820.eml";

    private static $detectors = [
        'fr' => ['Au moment de la prise en charge, vous aurez besoin de:', 'Au moment de la prise en charge, vous aurez besoin de :'],
        'en' => ["When you pick your car up, you'll need:"],
        'pt' => ["Ao retirar seu carro, você precisa apresentar:"],
    ];

    private static $dictionary = [
        'fr' => [
            'Confirmation:'         => ['Confirmation:', 'Confirmation :'],
            'Your car'              => 'Votre voiture',
            'Supplied by'           => 'Fournie par',
            'Picking up your car'   => ['Prise en charge du véhicule'],
            'Date and time'         => ['Date et heure'],
            'Location'       => 'Emplacement',
            'Phone:'                => ['Téléphone:', 'Téléphone :'],
            //            'If you require assistance call:' => '',
            'Rental company' => 'Société de location',
            'Dropping off your car' => 'Restitution du véhicule',
            'Drop-off instructions' => 'Instructions de restitution',
            'Driver details'        => 'Coordonnées du conducteur',
            'Name'                  => 'Nom',
            'Price breakdown'       => 'Détail du tarif',
            'Car Hire Charge'       => 'Prix de la location',
            //            'Total' => '',
            'feeNames'       => ['Assurance tous risques'],
        ],
        'en' => [
            'Picking up your car' => ['Picking up your car'],
            'Date and time'       => ['Date and time'],
        ],
        'pt' => [
            'Confirmation:'         => ['Confirmação:'],
            'Your car'              => 'Seu carro',
            'Supplied by'           => 'Fornecido por',
            'Picking up your car'   => ['Retirando seu carro'],
            'Date and time'         => ['Data e horário'],
            'Location'              => 'Localização',
            'Phone:'                => ['Telefone:'],
            //            'If you require assistance call:' => '', // in Pick-up instructions
            'Rental company'        => 'Locadora',
            'Dropping off your car' => 'Devolvendo seu carro',
            'Drop-off instructions' => 'Instruções para devolução',
            'Driver details'        => 'Dados do condutor',
            'Name'                  => 'Nome',
            'Price breakdown'       => 'Detalhes do preço',
            'Car Hire Charge'       => 'Preço do aluguel',
            'Total' => 'Total',
//            'feeNames'       => [''],
        ],
    ];

    private $from = "@cars.booking.com";

    private $body = "Booking.com";

    private $subject = [
        'fr' => 'Merci ! Votre réservation ',
        'en' => 'Thanks! Your car rental with ',
    ];

    private $lang;

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (stripos($text, $this->body) === false) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $email->setType('RentalCar' . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    private function parseEmail(Email $email)
    {
        if (!$this->detectBody()) {
            return false;
        }

        $r = $email->add()->rental();

        $xpathCell = '(self::td or self::th)';
        $xPathPickUp = "//*[" . $this->starts($this->t('Picking up your car')) . "]/ancestor::tr[1]/following-sibling::tr";
        $xPathDropOff = "//*[" . $this->starts($this->t('Dropping off your car')) . "]/ancestor::tr[1]/following-sibling::tr";
        $xPathPrice = "//*[" . $this->starts($this->t('Price breakdown')) . "]/ancestor::tr[1]/following-sibling::tr";
        $xPathCar = "//*[" . $this->starts($this->t("Your car")) . "]";

        $confNo = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Confirmation:')) . "]/following-sibling::span[1]", null, true, '/(\d+)/');

        if (!empty($confNo)) {
            $confirmationTitle = $this->http->FindSingleNode("//*[{$this->starts($this->t('Confirmation:'))} and following-sibling::span]", null, true, '/^(.+?)[\s:]*$/');
            $r->general()->confirmation($confNo, $confirmationTitle);
        }

        $traveller = $this->http->FindSingleNode("//*[" . $this->starts($this->t('Driver details')) . "]/ancestor::tr[1]/following-sibling::tr/descendant::th[" . $this->starts($this->t("Name")) . "]/following-sibling::th[1]");

        if (!empty($traveller)) {
            $r->general()->traveller($traveller, true);
        }

        $total = $this->http->FindSingleNode($xPathPrice . "/descendant::th[" . $this->starts($this->t("Total")) . "]/following-sibling::th[1]");

        if (preg_match('/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d ]*)$/', $total, $m)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+)$/', $total, $m)
        ) {
            // US$123.64    |    189,05€
            $m['currency'] = trim($m['currency']);

            $r->price()
                ->currency($this->normalizeCurrency($m['currency']))
                ->total($this->normalizeAmount($m['amount']));


            $cost = $this->http->FindSingleNode($xPathPrice . "/descendant::th[{$this->starts($this->t("Car Hire Charge"))}]/following-sibling::th[1]");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $cost, $matches)
                || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . ')?$/', $cost, $matches)
            ) {
                $r->price()->cost($this->normalizeAmount($matches['amount']));
            }

            $feeRows = $this->http->XPath->query($xPathPrice . "/descendant::th[ {$this->contains($this->t("feeNames"))} and following-sibling::th[1] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('following-sibling::th[1]', $feeRow);

                if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $matches)
                    || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($m['currency'], '/') . ')?$/', $feeCharge, $matches)
                ) {
                    $feeName = $this->http->FindSingleNode('.', $feeRow);
                    $r->price()->fee($feeName, $this->normalizeAmount($matches['amount']));
                }
            }
        }

        //PickUp
        $pickupDate = $this->http->FindSingleNode($xPathPickUp . "/descendant::th[" . $this->starts($this->t("Date and time")) . "]/following-sibling::th[1]");

        if (!empty($pickupDate)) {
            $r->pickup()->date(strtotime($pickupDate));
        }

        $pickupLocation = $this->http->FindSingleNode($xPathPickUp . "/descendant::th[" . $this->starts($this->t("Location")) . "]/following-sibling::th[1]");

        if (!empty($pickupLocation)) {
            $r->pickup()->location($pickupLocation);
        }

        $patterns['phone'] = '[+(\d][-. \d)(]{5,}[\d)]'; // +377 (93) 15 48 52    |    713.680.2992

        $pickupPhone = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("If you require assistance call:")) . "]", null, true, '/' . $this->opt($this->t('If you require assistance call:')) . '(.+)/');

        if (empty($pickupPhone)) {
            $pickupPhone = $this->http->FindSingleNode($xPathPickUp . "/descendant::*[{$xpathCell} and {$this->starts($this->t('Rental company'))}]/following-sibling::*[normalize-space()]/descendant::tr[{$this->starts($this->t('Phone:'))}]", null, true, "/{$this->opt($this->t('Phone:'))}\s*({$patterns['phone']})$/");
        }
        $r->pickup()->phone($pickupPhone, false, true);

        //DropOff
        $dropoffDate = $this->http->FindSingleNode($xPathDropOff . "/descendant::th[" . $this->starts($this->t("Date and time")) . "]/following-sibling::th[1]");

        if (!empty($dropoffDate)) {
            $r->dropoff()->date(strtotime($dropoffDate));
        }

        $dropoffLocation = $this->http->FindSingleNode($xPathDropOff . "/descendant::th[" . $this->starts($this->t("Location")) . "]/following-sibling::th[1]");

        if (!empty($dropoffLocation)) {
            $r->dropoff()->location($dropoffLocation);
        }

        $dropoffPhone = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Drop-off instructions")) . "]", null, true, '/' . $this->opt($this->t('If you require assistance call:')) . '(.+)/');

        if (empty($dropoffPhone)) {
            $dropoffPhone = $this->http->FindSingleNode($xPathDropOff . "/descendant::*[{$xpathCell} and {$this->starts($this->t('Rental company'))}]/following-sibling::*[normalize-space()]/descendant::tr[{$this->starts($this->t('Phone:'))}]", null, true, "/{$this->opt($this->t('Phone:'))}\s*({$patterns['phone']})$/");
        }
        $r->dropoff()->phone($dropoffPhone, false, true);

        //Car
        $img = $this->http->FindSingleNode($xPathCar . "//img[contains(@src,'car_images')]/@src");

        if (!empty($img)) {
            $r->car()->image($img);
        }

        $model = $this->http->FindSingleNode($xPathCar . "/ancestor::td/following::th[2]/descendant::tr[1]");

        if (!empty($model)) {
            $r->car()->model($model);
        }

        $company = $this->http->FindSingleNode($xPathCar . "/ancestor::td/following::th[2]/descendant::tr[{$this->starts($this->t('Supplied by'))}][1]", null, true, "/{$this->opt($this->t('Supplied by'))}\s*(.{2,})$/");

        if (!empty($company)) {
            $r->extra()->company($company);
        }

        return $email;
    }

    private function detectBody()
    {
        foreach (self::$detectors as $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words['Picking up your car'], $words['Date and time'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Picking up your car'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Date and time'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'USD' => ['US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
