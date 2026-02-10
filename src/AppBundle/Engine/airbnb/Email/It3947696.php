<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It3947696 extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-114244446.eml, airbnb/it-3947696.eml, airbnb/it-67339879.eml, airbnb/it-67375890.eml";

    private $reFrom = '@airbnb.com';
    private $reSubject = [
        'en' => 'Airbnb Reservation Canceled',
        'es' => 'Reserva de Airbnb cancelada',
        'Reservación de Airbnb cancelada',
        'pt' => 'Reserva Airbnb Cancelada',
        'fr' => 'Réservation Airbnb annulée',
        'he' => 'ההזמנה ב-Airbnb בוטלה',
        'nl' => 'Airbnb Reservering Geannuleerd',
        'sk' => 'Rezervácia na Airbnb zrušená',
    ];
    private $reBody = 'Airbnb';

    private $emailDate;
    private static $dictionary = [
        'en' => [
            'Reservation Canceled'  => ['Reservation Canceled', 'Reservation Cancelled', 'Reservation canceled', 'Reservation cancelled'],
            'View original receipt' => ['View original receipt', 'Show original receipt', 'Track refund'],
        ],
        'es' => [
            'Reservation Canceled'  => ['Reserva cancelada', 'Reservación cancelada'],
            'View original receipt' => ['Ver el recibo original', 'Revisa el recibo original', 'Rastrea el reembolso', 'Mostrar recibo original', 'Lleva un seguimiento del reembolso', 'Mostrar el recibo original'],
        ],
        'pt' => [
            'Reservation Canceled'  => ['Reserva Cancelada', 'Reserva cancelada'],
            'View original receipt' => ['Veja o recibo original', 'Rastrear reembolso', 'Mostrar recibo original'],
        ],
        'fr' => [
            'Reservation Canceled'  => ['Réservation annulée'],
            'View original receipt' => ['Afficher le reçu d\'origine'],
        ],
        'he' => [
            'Reservation Canceled'  => ['ההזמנה בוטלה'],
            'View original receipt' => ['מעקב אחר ההחזר הכספי', 'להצגת הקבלה המקורית'],
        ],
        'nl' => [
            'Reservation Canceled'  => ['Reservering geannuleerd'],
            'View original receipt' => ['Restitutie volgen', 'Oorspronkelijk betalingsbewijs tonen'],
        ],
        'sk' => [
            'Reservation Canceled'  => ['Rezervácia bola zrušená'],
            'View original receipt' => ['Zobraziť originál potvrdenia o zaplatení'],
        ],
    ];

    private $lang = 'en';

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'automated@airbnb.com') !== false) {
            return true;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if ( isset($dict['Reservation Canceled'], $dict['View original receipt'])
                && $this->http->XPath->query("//text()[" . $this->contains($dict['Reservation Canceled']) . "]")->length > 0
                && $this->http->XPath->query("//text()[" . $this->contains($dict['View original receipt']) . "]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;
        $body = $this->http->Response['body'];

        foreach (self::$dictionary as $lang =>$dict) {
            if ( isset($dict['Reservation Canceled'], $dict['View original receipt'])
                && $this->http->XPath->query("//text()[" . $this->contains($dict['Reservation Canceled']) . "]")->length > 0
                && $this->http->XPath->query("//text()[" . $this->contains($dict['View original receipt']) . "]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->emailDate = strtotime($parser->getDate());
        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();
        $cancelledNodes = $this->http->XPath->query('//*[' . $this->eq($this->t('Reservation Canceled')) . ']');
        if ($cancelledNodes->length > 0) {
            $root = $cancelledNodes->item(0);

            $h->general()
                ->confirmation($this->http->FindSingleNode("(//a[" . $this->eq($this->t("View original receipt")) . "])[1][contains(@href, '.airbnb.')]/@href", null, true, "/(?:\?|&)(?:code|bill_product_id)=([A-Z\d]{6,12})(?:&|$)/"))
                ->cancelled()
                ->status('Canceled')
            ;
            $subTitle = $this->http->FindSingleNode('./following::text()[normalize-space()][1]', $root);
            $dateFormatRegexp = '\w+\s+\d{1,2}|\d{1,2} \w+\.?';

            if (preg_match("/(.*?)\s*•\s*({$dateFormatRegexp})\s*-\s*({$dateFormatRegexp})\s*$/", $subTitle, $m)) {
                $h->hotel()
                    ->name($m[1])
                    ->noAddress()
                ;
                $h->booked()
                    ->checkIn($this->normalizeDate($m[2]))
                    ->checkOut($this->normalizeDate($m[3]))
                ;
            }
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
        $this->http->log('$date = ' . print_r($date, true));
        $in = [
            //Nov 29
            '/^\s*(\w+)\s+(\d+)\s*$/u',
            // 30 Nov
            '/^\s*(\d+)\s+(\w+)[.]?\s*$/u',
        ];
        $out = [
            '$2 $1',
            '$1 $2',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("/^\d+\s+([^\d\s]+)\s*$/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        $date = EmailDateHelper::parseDateRelative($date, strtotime("- 10 day", $this->emailDate));

        return $date;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
