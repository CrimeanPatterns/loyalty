<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryReceipt2 extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-187652603.eml, aeroplan/it-319075170.eml";
    public $subjects = [
        'Air Canada - ',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Itinerary-Receipt'                                        => 'Itinerary-Receipt',
            'Please print/retain this page for your financial records' => 'Please print/retain this page for your financial records',
            // 'Booking Date' => '',
            // 'Passengers' => '',
            'Booking Information' => 'Booking Information',
            // 'Booking Reference' => '',
            // 'Stops:' => '',
            // 'Duration' => '',
            // 'Aircraft:' => '',
            // 'Cabin:' => '',
            // 'Meals:' => '',

            // 'Passenger Information' => '',
            // 'Ticket Number:' => '',
            // 'Frequent Flyer Pgm:' => '',
            // 'Seat Selection:' => '',

            // 'Sub Total' => '',
            // 'Taxes, Fees and Charges' => '',
            // 'Number Of Passengers' => '',
            // 'Grand Total' => '',
        ],
        "fr" => [
            'Itinerary-Receipt'                                        => 'Itinéraire-reçu',
            'Please print/retain this page for your financial records' => 'Veuillez imprimer/garder en mémoire cette page pour vos documents financiers',
            'Booking Date'                                             => 'Date de Réservation',
            'Passengers'                                               => 'Passagers',
            'Booking Information'                                      => 'Détails de la réservation',
            'Booking Reference'                                        => 'Numéro de réservation',
            'Stops:'                                                   => 'Arrêts:',
            'Duration'                                                 => 'Durée',
            'Aircraft:'                                                => 'Appareil:',
            'Cabin:'                                                   => 'Cabine:',
            'Meals:'                                                   => 'Repas:',

            'Passenger Information' => 'Passagers',
            'Ticket Number:'        => 'Numéro de billet:',
            'Frequent Flyer Pgm:'   => 'Programme de fidélisation:',
            'Seat Selection:'       => 'Place sélectionnée:',

            'Sub Total'               => 'Sous Total',
            'Taxes, Fees and Charges' => 'Tarif aérien total et taxes ',
            'Number Of Passengers'    => 'Nombre de passagers',
            'Grand Total'             => 'Grand Total ',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@info.aircanada.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'www.aircanada.com')]")->length > 0) {
            foreach (self::$dictionary as $dict) {
                if (!empty($dict['Itinerary-Receipt']) && !empty($dict['Booking Information']) && !empty($dict['Please print/retain this page for your financial records'])
                    && $this->http->XPath->query("//text()[{$this->contains($dict['Itinerary-Receipt'])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($dict['Booking Information'])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($dict['Please print/retain this page for your financial records'])}]")->length > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info\.aircanada\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking Reference'))}\s*([A-Z\d]{6})/"))
            ->date(strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Date'))}]/following::text()[normalize-space()][1]")))
            ->travellers($this->http->FindNodes("(//text()[{$this->starts($this->t('Passengers'))}])[1]/following::text()[normalize-space()][1]/ancestor::td[1]/descendant::text()[normalize-space()]"));

        $f->setTicketNumbers($this->http->FindNodes("//text()[{$this->starts($this->t('Passenger Information'))}]/following::text()[{$this->contains($this->t('Ticket Number:'))}]/following::text()[normalize-space()][1]"), false);

        $accounts = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Passenger Information'))}]/following::text()[{$this->contains($this->t('Frequent Flyer Pgm:'))}]/following::text()[normalize-space()][1]", null, "/^\s*(\d+)/"));

        if (!empty($accounts)) {
            $f->setAccountNumbers($accounts, false);
        }

        $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Grand Total'))}]/following::text()[normalize-space()][1]", null, true, "/\b(\d[\d\.,]+)/");
        $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Grand Total'))}]", null, true, "/\(([A-Z]{3})\)/u");

        $passengerNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Grand Total'))}]/preceding::text()[{$this->eq($this->t('Number Of Passengers'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d+)\s*$/");

        if (!empty($total) && !empty($currency) && !empty($passengerNumber)) {
            $f->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);

            $cost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Sub Total'))}]/following::text()[normalize-space()][1]", null, true, "/([\d\.,]+)/");
            $f->price()
                ->cost($passengerNumber * PriceHelper::parse($cost, $currency));

            $feeNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Taxes, Fees and Charges'))}]/ancestor::tr[1]/following-sibling::tr");

            foreach ($feeNodes as $feeRoot) {
                $name = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $feeRoot);
                $sum = $this->http->FindSingleNode("./descendant::text()[normalize-space()][2]", $feeRoot);

                if (preg_match("/Total airfare and taxes/", $name)) {
                    break;
                }

                if (!empty($name) && !empty($sum)) {
                    $f->price()
                        ->fee($name, $passengerNumber * PriceHelper::parse($sum, $currency));
                }
            }
        }

        $seatText = implode("\n", $this->http->FindNodes("//text()[{$this->starts($this->t('Seat Selection:'))}]/ancestor::td[{$this->starts($this->t('Seat Selection:'))}][last()]//text()[normalize-space()]"));

        $nodes = $this->http->XPath->query("//img[contains(@src, 'carrierlogos')][not(preceding::text()[{$this->contains($this->t('Ticket Number:'))}])]/ancestor::tr[1]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("./descendant::td[1]/descendant::text()[normalize-space()][1]", $root, true, "/^([A-Z\d]{2})\d{2,4}/"))
                ->number($this->http->FindSingleNode("./descendant::td[1]/descendant::text()[normalize-space()][1]", $root, true, "/^[A-Z\d]{2}(\d{2,4})/"));

            $depText = implode("\n", $this->http->FindNodes("./descendant::td[2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/\((?<depCode>[A-Z]{3})\)\n(?<depDate>.+)\n(?<depTime>[\d\:]+)$/", $depText, $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['depDate'] . ', ' . $m['depTime']));
            }

            $arrText = implode("\n", $this->http->FindNodes("./descendant::td[6]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/\((?<arrCode>[A-Z]{3})\)\n(?<arrDate>.+)\n(?<arrTime>[\d\:]+)$/u", $arrText, $m)) {
                $s->arrival()
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']));
            }

            $detailsText = implode("\n", $this->http->FindNodes("./descendant::text()[{$this->contains($this->t('Aircraft:'))}]/ancestor::td[{$this->contains($this->t('Duration'))}][1]/descendant::text()[normalize-space()]", $root));
            $stops = $this->re("/{$this->opt($this->t('Stops:'))}\s*(\d+)/s", $detailsText);

            if ($stops !== null) {
                $s->extra()
                    ->stops($stops);
            }

            $aircraft = $this->re("/{$this->opt($this->t('Aircraft:'))}\s*(.+){$this->opt($this->t('Cabin:'))}/s", $detailsText);

            if ($aircraft !== null) {
                $s->extra()
                    ->aircraft($aircraft);
            }

            if (preg_match("/{$this->opt($this->t('Cabin:'))}\s*(?<cabin>\D+)\s+\((?<bookingCode>[A-Z])\)/", $detailsText, $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bookingCode']);
            }

            $meal = $this->re("/{$this->opt($this->t('Meals:'))}\s*(.+)/s", $detailsText);
            $meal = preg_replace("/^\s*NA\s*$/", '', $meal);

            if (!empty($meal)) {
                $s->extra()
                    ->meal($meal);
            }

            if (!empty($seatText)
                && !empty($s->getAirlineName()) && !empty($s->getFlightNumber()) && !empty($s->getDepCode()) && !empty($s->getArrCode())
                && preg_match_all("/\n\s*{$s->getAirlineName()} ?{$s->getFlightNumber()}\s*\(\s*{$s->getDepCode()}\s*-\s*{$s->getArrCode()}\s*\)\s*-\s*(?<seat>\d{1,3}[A-Z])(?:\n|$)/", $seatText, $m)
            ) {
                $s->extra()
                    ->seats($m['seat']);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Itinerary-Receipt']) && !empty($dict['Booking Information'])
                && $this->http->XPath->query("//text()[{$this->contains($dict['Itinerary-Receipt'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($dict['Booking Information'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // mardi. Mai 09, 2023, 21:35
            '/^\s*\w+[.,\s]+([[:alpha:]]+)\s+(\d{1,2})[,\s]+\s*(\d{4})\s*.\s*(\d+:\d+)\s*$/su', // 11 nov., 2017
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
