<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class HResCreditCardDetails extends \TAccountChecker
{
    public $mailFiles = "agoda/it-1892367.eml, agoda/it-27179829.eml";

    public $reFrom = "agoda.com";
    public $reBody = [
        'en' => ['Agoda Customer Support', 'Departure'],
        'es' => ['Equipo de Atención al Cliente de Agoda.com', 'Salida'],
        'zh' => ['Agoda客戶服務部門', '入住日期'],
    ];
    public $reSubject = [
        '#Hotel Reservation-Credit Card Details Received Booking ID.*?\d+#',
        '#Agoda Booking ID \d+ Encuesta de Satisfacción del Cliente#',
        '#預訂編號 Agoda Booking ID \d+ 取消預訂#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'CancelledStatus' => 'Cancellation Charge:',
        ],
        'es' => [
            'Booking ID' => 'ID de la reserva',
            //            'Customer First Name' => '',
            //            'Customer Last Name' => '',
            'Arrival'                                    => 'Llegada',
            'Departure'                                  => 'Salida',
            'City'                                       => 'Ciudad',
            'Country'                                    => 'País',
            'Hotel'                                      => 'Hotel',
            'Address'                                    => 'Dirección postal',
            'Room Type'                                  => 'Tipo de habitación',
            'Number of Rooms'                            => 'Número de habitaciones',
            'Number of Adults'                           => 'Número de adultos',
            'Number of Children'                         => 'Número de niños',
            'Total/Room Charge with Tax/Service Charges' => 'Coste total/ Cargo a la tarjeta de crédito',
            //''=>'ID del miembro',
        ],
        'zh' => [
            'Booking ID'          => '預訂編號',
            'Customer First Name' => '住客名字',
            'Customer Last Name'  => '住客姓氏',
            'Arrival'             => '入住日期',
            'Departure'           => '退房日期',
            //            'City' => '',
            //            'Country' => '',
            'Hotel' => '飯店名稱',
            //            'Address' => '',
            'Room Type' => '房型',
            //            'Number of Rooms' => '',
            //            'Number of Adults' => '',
            //            'Number of Children' => '',
            'Total/Room Charge with Tax/Service Charges' => '總金額',
            //''=>'ID del miembro',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'agoda.com')]")->length > 0
            || $this->http->XPath->query("//*[contains(normalize-space(.),'Agoda Hotel Hotline')]")->length > 0
            || $this->http->XPath->query("//*[contains(normalize-space(.),'Agoda Company')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
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
        $h = $email->add()->hotel();
        $text = str_replace('&nbsp', '', text($this->http->Response['body']));

        $traveller = trim($this->re("#{$this->opt($this->t('Customer First Name'))}\s*[:： ]\s*(.+)#u",
                $text) . ' ' . $this->re("#{$this->opt($this->t('Customer Last Name'))}\s*[:： ]\s*(.+)#u", $text));

        if (!empty($traveller)) {
            $h->general()
                ->traveller($traveller);
        }
        $h->general()
            ->confirmation($this->re("#{$this->opt($this->t('Booking ID'))}\s*[:： ]\s*(\d+)#u", $text));

        $addAddress = '';
        $city = $this->re("#{$this->opt($this->t('City'))}\s*[:： ]\s*(.*)#", $text);

        if (!empty($city)) {
            $addAddress .= ', ' . $city;
        }
        $country = $this->re("#{$this->opt($this->t('Country'))}\s*[:： ]\s*(.*)#i", $text);

        if (!empty($country)) {
            $addAddress .= ', ' . $country;
        }
        $address = $this->re("#{$this->opt($this->t('Address'))}\s*[:： ]\s*(.*)#", $text) . $addAddress;
        $h->hotel()
            ->name($this->re("#{$this->opt($this->t('Hotel'))}\s*[:： ]\s*(.*)#", $text))
        ;

        if (!empty($address)) {
            $h->hotel()
                ->address($address);
        } else {
            $h->hotel()->noAddress();
        }

        $checkIn = $this->dateStringToEnglish($this->re("#{$this->opt($this->t('Arrival'))}\s*[:： ]\s*(.*)#", $text));

        if (!empty($time = $this->re("/ArrivalTime\:\"([\d\:]+)/", $text))) {
            $checkIn = $checkIn . ', ' . $time;
        }

        $checkOut = $this->dateStringToEnglish($this->re("#{$this->opt($this->t('Departure'))}\s*[:： ]\s*(.*)#", $text));
        $h->booked()
            ->rooms($this->re("#{$this->opt($this->t('Number of Rooms'))}\s*:\s+(.*)#", $text), true, true)
            ->guests($this->re("#{$this->opt($this->t('Number of Adults'))}\s*:\s+(.*)#", $text), true, true)
            ->kids($this->re("#{$this->opt($this->t('Number of Children'))}\s*:\s+(.*)#", $text), true, true)
            ->checkIn(strtotime($checkIn))
            ->checkOut(strtotime($checkOut));

        $type = $this->re("#{$this->opt($this->t('Room Type'))}\s*[:： ]\s*(.*)#", $text);
        if (!empty($type)) {
            $h->addRoom()->setType($type);
        }

        $sum = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Total/Room Charge with Tax/Service Charges'))}\s*[:： ]\s*(.*)#",
            $text));

        if (!empty($sum['Total']) && !empty($sum['Currency'])) {
            $h->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
        }

        if (!empty($this->re("/({$this->opt($this->t('CancelledStatus'))})/", $text))) {
            $h->general()
                ->status('cancelled')
                ->cancelled();
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
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
