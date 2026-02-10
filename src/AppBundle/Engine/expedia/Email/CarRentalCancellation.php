<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CarRentalCancellation extends \TAccountChecker
{
    public $mailFiles = "expedia/it-40372847.eml";

    public $reFrom = "expediamail.com";
    public $reBody = [
        'en' => ['your car reservation was cancelled'],
        'es' => ['se ha cancelado la reserva del coche', 'Tu renta de auto se canceló'],
        'fr' => ['votre réservation d’une voiture de location a été annulée'],
    ];
    public $reSubject = [
        // en
        'Expedia Car Rental Cancellation', //Confirmed: Expedia Car Rental Cancellation - Fox, Sat, Jun 22 - Sat, Jun 29 (Itinerary #7443589140721)
        // es
        'Confirmación: cancelación de la reserva de coche de',
        'Expedia: cancelación de renta de auto confirmada',
        // fr
        'Confirmée : annulation de votre voiture de location Expedia'
    ];
    public $date;
    public $lang = '';
    public static $dict = [
        'en' => [
//            'Itinerary' => '',
//            'Expedia Customer Support Phone Number' => '',
//            'Hi ' => '',
//            'your car reservation was cancelled' => '',
//            'Cancellation details' => '',
        ],
        'es' => [
            'Itinerary' => 'Itinerario',
            'Expedia Customer Support Phone Number' => 'Teléfono del servicio al cliente de Expedia',
            'Hi ' => '¡Hola,',
            'your car reservation was cancelled' => 'Tu renta de auto se canceló',
//            'Cancellation details' => '',
        ],
        'fr' => [
            'Itinerary' => 'Numéro d’itinéraire',
            'Expedia Customer Support Phone Number' => 'Numéro de téléphone du soutien à la clientèleExpedia',
            'Hi ' => 'Bonjour',
            'your car reservation was cancelled' => 'votre réservation d’une voiture de location a été annulée',
//            'Cancellation details' => '',
        ],
    ];
    private $keywords = [
        'foxrewards' => [
            'Fox',
        ],
        'sixt' => [
            'Sixt',
        ],
        'payless' => [
            'Payless',
        ],
        'advantagecar' => [//advrent??
            'Advantage',
        ],
        'avis' => [
            'Avis',
        ],
        'ezrentacar' => [
            'EZ Rent A Car',
            'E-Z',
        ],
        'thrifty' => [
            'Thrifty Car Rental', 'Thrifty'
        ],
        'alamo' => [
            'Alamo',
        ],
        'silvercar' => [
            'Silvercar',
        ],
        'perfectdrive' => [
            'Budget',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $date = $this->http->FindSingleNode("(//text()[" . $this->contains('EMLDTL=DATE') . "])[last()]", null, true, "#EMLDTL=DATE(\d{8})-#");

        if (preg_match("#^\s*(\d{4})(\d{2})(\d{2})\s*$#", $date, $m)) {
            $this->date = strtotime($m[3] . '.' . $m[2] . '.' . $m[1]);
        }

        if (empty($this->date)) {
            $this->date = strtotime($parser->getDate());
        }

        $this->parseEmail($email);

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'Expedia') or contains(@alt,'expedia')] | //a[contains(@href,'expedia')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!empty($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        // Travel Agency
        $confNo = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Itinerary'))}][1]/ancestor::*[1][".$this->contains(['#', 'no.'])." or not({$this->eq($this->t('Itinerary'))})])[1]", null, false,
            "/{$this->opt($this->t('Itinerary'))}\s*(?:#|no\.|:)\s*(\d+)\s*$/");
        $email->ota()
            ->confirmation($confNo);

        $node = trim($this->http->FindSingleNode("//text()[{$this->starts($this->t('Expedia Customer Support Phone Number'))}]/ancestor::tr[normalize-space()!=''][1]",
            null, false, "#{$this->opt($this->t('Expedia Customer Support Phone Number'))}: *([\d\+\-\(\) \.]+)#"));
        $email->ota()
            ->phone($node, $this->t('Expedia Customer Support Phone Number'));

        // RENTAL
        $r = $email->add()->rental();

        // General
        $r->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[".$this->starts($this->t("Hi "))." and ".$this->contains($this->t('your car reservation was cancelled'))."][1]",
                null, false, "#^".$this->opt($this->t("Hi "))."\s*(.+?)\s*[,!]\s*".$this->opt($this->t('your car reservation was cancelled'))."#u"), false);

        if (!empty($this->http->FindSingleNode("(//text()[{$this->contains($this->t('your car reservation was cancelled'))}])[1]"))) {
            $r->general()
                ->status('Cancelled')
                ->cancelled();
        }

        $details = $this->http->FindSingleNode("//tr[not(.//tr) and " . $this->eq($this->t("Cancellation details")) . "][1]/following::text()[normalize-space()][1]/ancestor::tr[1]");

        if (preg_match("#Your car reservation with (?<company>.+?) at (?<pickup>.+?) from (?<pDate>.+?) through (?<dDate>.+?) was cancelled.#", $details, $m)) {
            $r->pickup()
                ->location($m['pickup'])
                ->date($this->normalizeDate($m['pDate']));
            $r->dropoff()
                ->noLocation()
                ->date($this->normalizeDate($m['dDate']));

            $r->extra()->company($m['company']);
        }

        if (!empty($keyword = $r->getCompany())) {
            $rentalProvider = $this->getRentalProviderByKeyword($keyword);

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[" . $this->contains($reBody) . "]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//        $this->http->log('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            //Aug 5, 2018 at 1:00pm
            '#^(\w+) +(\d+),\s+(\d{4})\s*at\s*(\d+:\d+(?:\s*[ap]m)?)$#u',
            //Sun, Dec 9 at 3:30pm
            '#^(\w+),\s*(\w+)\s+(\d+)\s*at\s*(\d+:\d+(?:\s*[ap]m)?)$#u',
            //Sun, Dec 9
            '#^(\w+),\s*(\w+)\s+(\d+)\s*$#u',
        ];
        $out = [
            '$2 $1 $3, $4',
            '$1, $3 $2 ' . $year . ', $4',
            '$1, $3 $2 ' . $year,
        ];
        $str = preg_replace($in, $out, $date);

        if (preg_match('#\d+ ([[:alpha:]]+) \d+#iu', $date, $m)) {
            $monthNameOriginal = $m[1];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                $str = preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } else {
            $str = strtotime($str);
        }

        return $str;
    }

    private function getRentalProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
            }
        }

        return null;
    }
}
