<?php

namespace AwardWallet\Engine\aena\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "aena/it-664159762.eml, aena/it-661761413-es.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'entry'                  => ['Entrada:', 'Entrada :'],
            'exit'                   => ['Salida:', 'Salida :'],
            'confNumber'             => 'Localizador:',
            'Airport:'               => 'Aeropuerto:',
            'Car Park:'              => 'Parking:',
            'Product:'               => 'Producto:',
            'Price:'                 => 'Precio:',
            'TOTAL (incl. taxes)'    => 'TOTAL (impuestos incluidos)',
            'Taxes:'                 => 'Impuestos:',
            'PERSONAL DETAILS'       => 'DATOS PERSONALES',
            'Name:'                  => 'Nombre:',
            'Surname:'               => 'Apellidos:',
            'Email address:'         => 'Correo electrónico:',
            'Vehicle licence plate:' => 'Matrícula:',
        ],
        'en' => [
            'entry'      => ['Entry:', 'Entry :'],
            'exit'       => ['Exit:', 'Exit :'],
            'confNumber' => 'Booking Ref:',
            // 'Airport:' => '',
            // 'Car Park:' => '',
            // 'Product:' => '',
            // 'Price:' => '',
            // 'TOTAL (incl. taxes)' => '',
            // 'Taxes:' => '',
            // 'PERSONAL DETAILS' => '',
            // 'Name:' => '',
            // 'Surname:' => '',
            // 'Email address:' => '',
            // 'Vehicle licence plate:' => '',
        ],
    ];

    private $subjects = [
        'es' => ['Reserva de parking'],
        'en' => ['Parking reservation'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]aena\.es$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".aena.es/") or contains(@href,"www.aena.es") or contains(@href,"parking.aena.es")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thanks for choosing an official AENA")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('Reservation' . ucfirst($this->lang));

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $p = $email->add()->parking();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $p->general()->confirmation($confirmation, $confirmationTitle);
        }

        $address = $this->http->FindSingleNode("//tr[ normalize-space() and preceding-sibling::*[{$this->eq($this->t('Airport:'))}] and following-sibling::*[{$this->eq($this->t('Car Park:'))}] ]");

        if (!empty($address) && !preg_match("/\bAirport\b/i", $address)) {
            $address = 'Airport ' . $address;
        }

        $parkName = $this->http->FindSingleNode("//tr[ normalize-space() and preceding-sibling::*[{$this->eq($this->t('Car Park:'))}] and following-sibling::*[{$this->eq($this->t('Product:'))}] ]");

        $p->place()->address($address)->location($parkName);

        $entryVal = $this->http->FindSingleNode("//tr[ normalize-space() and preceding-sibling::*[{$this->eq($this->t('entry'))}] and following-sibling::*[{$this->eq($this->t('exit'))}] ]");
        $exitVal = $this->http->FindSingleNode("//tr[ normalize-space() and preceding-sibling::*[{$this->eq($this->t('exit'))}] and following-sibling::*[{$this->eq($this->t('Price:'))}] ]");

        if (preg_match($pattern = "/^(?<date>.+\b\d{4})[,\s]+(?<time>\d{1,2}:\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)[h\s]*$/i", $entryVal, $m)) {
            // 25-09-2024 21:00 h
            $p->booked()->start(strtotime($m['time'], strtotime($m['date'])));
        }

        if (preg_match($pattern, $exitVal, $m)) {
            $p->booked()->end(strtotime($m['time'], strtotime($m['date'])));
        }

        $totalPrice = $this->http->FindSingleNode("//tr[{$this->eq($this->t('TOTAL (incl. taxes)'))}]/following-sibling::tr[normalize-space()][1]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)) {
            // 38.70€
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $p->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $taxes = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Taxes:'))}]/following-sibling::tr[normalize-space()][1]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $taxes, $m)) {
                $p->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        $travellerNameList = [];

        $tName = $this->http->FindSingleNode("//*[{$this->eq($this->t('PERSONAL DETAILS'))}]/following::*[ normalize-space() and preceding-sibling::*[{$this->eq($this->t('Name:'))}] and following-sibling::*[{$this->eq($this->t('Surname:'))}] ]", null, true, "/^{$patterns['travellerName']}$/u");

        if ($tName) {
            $travellerNameList[] = $tName;
        }

        $tSurName = $this->http->FindSingleNode("//*[{$this->eq($this->t('PERSONAL DETAILS'))}]/following::*[ normalize-space() and preceding-sibling::*[{$this->eq($this->t('Surname:'))}] and following-sibling::*[{$this->eq($this->t('Email address:'))}] ]", null, true, "/^{$patterns['travellerName']}$/u");

        if ($tSurName) {
            $travellerNameList[] = $tSurName;
        }

        if (count($travellerNameList) > 0) {
            $p->general()->traveller(implode(' ', $travellerNameList), count($travellerNameList) > 1);
        }

        $plate = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Vehicle licence plate:'))}]/following-sibling::tr[normalize-space()][1][not(contains(.,':'))]");
        $p->booked()->plate($plate, false, true);

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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['entry']) || empty($phrases['exit'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->eq($phrases['entry'])}]/following::*[{$this->eq($phrases['exit'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }
}
