<?php

namespace AwardWallet\Engine\aviancataca\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class PurchaseStatus extends \TAccountChecker
{
    public $mailFiles = "aviancataca/it-47596437.eml, aviancataca/it-55343562.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Reservation code' => ['Reservation code'],
            'Outbound Flight'  => ['Outbound Flight', 'Return Flight', 'Journey 1', 'Journey 2'],
            'Rate Option'      => ['Rate Option', 'Fare option'],
        ],
        'es' => [
            'Reservation code' => ['Código de Reserva'],
            'Outbound Flight'  => ['Vuelo de Ida'],
            'Details'          => ['Detalles'],
            'Passenger'        => ['Pasajero'],
            'Total Price'      => ['Precio Total'],
            'Flight Number'    => ['Número de Vuelo'],
            'From'             => ['Desde'],
            'To'               => ['A'],
            'Rate Option'      => 'Opción Tarifaria',
        ],
    ];

    private $subjects = [
        'en' => ['Purchase Status'],
        'es' => ['Purchase Status'],
    ];

    private $detectors = [
        'en' => ['Your purchase has been processed successfully', 'Thanks for your purchase'],
        'es' => ['Vuelo de Ida'],
    ];

    private $xpathDetails;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@avianca.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Avianca.com') === false) {
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
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".avianca.com/") or contains(@href,"www.avianca.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"www.avianca.com") or contains(.,"@avianca.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseFlight($email);
        $email->setType('PurchaseStatus' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $formats = 2; // segments 2;
        $cnt = $formats * count(self::$dictionary);

        return $cnt;
    }

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        $xpathDetails = $this->xpathDetails = "//h2[{$this->starts($this->t('Details'))}]";

        $passengers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger details'))}]/following::table[1]//tr[normalize-space()!=''][not({$this->contains($this->t('Email:'))})]/td[1]/descendant::text()[normalize-space()!=''][2]");

        if (!empty($passengers)) {
            $f->general()->travellers($passengers, true);
        } else {
            $passenger = $this->http->FindSingleNode($xpathDetails . "/following::tr[ *[1][{$this->starts($this->t('Passenger'))}] ]/following-sibling::tr[normalize-space()][1]/*[1]",
                null, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
            $f->general()->traveller($passenger, true);
        }

        $confirmation = $this->http->FindSingleNode($xpathDetails . "/following::tr[ *[3][{$this->starts($this->t('Reservation code'))}] ]/following-sibling::tr[normalize-space()][1]/*[3]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode($xpathDetails . "/following::tr/*[3][{$this->starts($this->t('Reservation code'))}]", null, true, '/^(.+?)[\s:]*$/');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        } else {
            $confirmation = $this->http->FindSingleNode($xpathDetails . "/following::tr[ *[1][{$this->starts($this->t('Reservation code'))}] ]/following-sibling::tr[normalize-space()][1]/*[1]", null, true, '/^[A-Z\d]{5,}$/');

            if ($confirmation) {
                $confirmationTitle = $this->http->FindSingleNode($xpathDetails . "/following::tr/*[1][{$this->starts($this->t('Reservation code'))}]", null, true, '/^(.+?)[\s:]*$/');
                $f->general()->confirmation($confirmation, $confirmationTitle);
            }
        }

        $xpathPayment = $this->http->XPath->query("//text()[{$this->eq($this->t('Payment information'))}]/following::table[1][{$this->contains($this->t('Total for all passengers'))}]");

        if ($xpathPayment->length > 0) {
            $rootPayment = $xpathPayment->item(0);
            $totalPrice = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Total for all passengers'))}]/following-sibling::td[normalize-space()!=''][1]",
                $rootPayment);

            if (preg_match('/^[$ ]*(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[A-Z]{3})$/', $totalPrice, $m)) {
                // $1.627.440,00  COP   |    $1.583,01 USD
                $f->price()
                    ->currency($m['currency'])
                    ->total($this->normalizeAmount($m['amount']));
            }
            $costPrice = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Fare + Surcharge'))}]/following-sibling::td[normalize-space()!=''][1]",
                $rootPayment);

            if (preg_match('/^[$ ]*(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[A-Z]{3})$/', $costPrice, $m)) {
                // $1.627.440,00  COP   |    $1.583,01 USD
                $f->price()
                    ->currency($m['currency'])
                    ->cost($this->normalizeAmount($m['amount']));
            }
            $taxPrice = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Fees + Taxes'))}]/following-sibling::td[normalize-space()!=''][1]",
                $rootPayment);

            if (preg_match('/^[$ ]*(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[A-Z]{3})$/', $taxPrice, $m)) {
                // $1.627.440,00  COP   |    $1.583,01 USD
                $f->price()
                    ->currency($m['currency'])
                    ->tax($this->normalizeAmount($m['amount']));
            }
            $feePrice = $this->http->FindSingleNode("./descendant::td[{$this->starts($this->t('Administration fee'))}]/following-sibling::td[normalize-space()!=''][1]",
                $rootPayment);

            if (preg_match('/^[$ ]*(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[A-Z]{3})$/', $feePrice, $m)) {
                // $1.627.440,00  COP   |    $1.583,01 USD
                $f->price()
                    ->fee($this->t('Administration fee'), $this->normalizeAmount($m['amount']));
            }
        } else {
            $totalPrice = $this->http->FindSingleNode($xpathDetails . "/following::tr[ *[1][{$this->starts($this->t('Total Price'))}] ]/following-sibling::tr[normalize-space()][1]/*[1]");

            if (preg_match('/^(?<currency>[A-Z]{3})[$ ]*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)) {
                // COP $1.627.440,00    |    USD $1.583,01
                $f->price()
                    ->currency($m['currency'])
                    ->total($this->normalizeAmount($m['amount']));
            } else {
                $totalPrice = $this->http->FindSingleNode($xpathDetails . "/following::tr[ *[2][{$this->starts($this->t('Total Price'))}] ]/following-sibling::tr[normalize-space()][1]/*[2]");

                if (preg_match('/^(?<currency>[A-Z]{3})[$ ]*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)) {
                    // COP $1.627.440,00    |    USD $1.583,01
                    $f->price()
                        ->currency($m['currency'])
                        ->total($this->normalizeAmount($m['amount']));
                }
            }
        }

        $xpath = "//h2[{$this->starts($this->t('Outbound Flight'))}]/following::tr[ *[1][{$this->starts($this->t('From'))}] and *[3][{$this->starts($this->t('To'))}] ]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length > 0) {
            $this->logger->debug("XPATH-segment\n" . $xpath);
            $this->parseSegment_1($segments, $f);

            return;
        }
        $xpath = "//h2[{$this->starts($this->t('Outbound Flight'))}]/following::tr[ *[1][{$this->starts($this->t('From'))}] and *[2][{$this->starts($this->t('To'))}] ]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length > 0) {
            $this->logger->debug("XPATH-segment\n" . $xpath);
            $this->parseSegment_2($segments, $f);

            return;
        }
    }

    private function parseSegment_1(\DOMNodeList $segments, Flight $f)
    {
        $this->logger->notice(__METHOD__);

        foreach ($segments as $key => $segment) {
            $s = $f->addSegment();

            $s->extra()
                ->cabin($this->http->FindSingleNode("preceding::td[{$this->starts($this->t('Rate Option'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!='']/td[normalize-space()!=''][2]", $segment));

            if ($key === 0
                && $this->http->XPath->query("preceding::h2[{$this->starts($this->t('Return Flight'))}]", $segment)->length === 0
            ) {
                $flightNumberValue = $this->http->FindSingleNode($this->xpathDetails . "/following::tr[ *[2][{$this->starts($this->t('Flight Number'))}] ]/following-sibling::tr[normalize-space()][1]/*[2]");

                if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flightNumberValue, $m)) {
                    $s->airline()
                        ->name($m['name'])
                        ->number($m['number']);
                }
            } else {
                $s->airline()
                    ->noName()
                    ->noNumber();
            }

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[normalize-space()!=''][1]/*[2]", $segment)));

            // El Dorado Airport, 1 BOG 14:48
            $pattern1 = '/^(?<name>.{3,}?)\s+(?<code>[A-Z]{3})\s+(?<time>\d{1,2}(?:[:]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)$/';

            // El Dorado Airport, 1
            $pattern2 = '/^(?<name>.{3,}?)\s*,\s*(?<terminal>[A-Z\d]+)$/';

            $departure = implode(' ', $this->http->FindNodes("following-sibling::tr[normalize-space()!=''][2]/*[1]/descendant::text()[normalize-space()!='']", $segment));

            if (preg_match($pattern1, $departure, $m)) {
                if (preg_match($pattern2, $m['name'], $m2)) {
                    $s->departure()
                        ->name($m2['name'])
                        ->terminal($m2['terminal']);
                } else {
                    $s->departure()->name($m['name']);
                }
                $s->departure()->code($m['code']);

                if ($date) {
                    $s->departure()->date(strtotime($m['time'], $date));
                }
            }

            $arrival = implode(' ', $this->http->FindNodes("following-sibling::tr[normalize-space()!=''][2]/*[3]/descendant::text()[normalize-space()!='']", $segment));

            if (preg_match($pattern1, $arrival, $m)) {
                if (preg_match($pattern2, $m['name'], $m2)) {
                    $s->arrival()
                        ->name($m2['name'])
                        ->terminal($m2['terminal']);
                } else {
                    $s->arrival()->name($m['name']);
                }
                $s->arrival()->code($m['code']);

                if ($date) {
                    $s->arrival()->date(strtotime($m['time'], $date));
                }
            }
        }
    }

    private function parseSegment_2(\DOMNodeList $segments, Flight $f)
    {
        $this->logger->notice(__METHOD__);

        foreach ($segments as $key => $segment) {
            $s = $f->addSegment();

            $s->extra()
                ->cabin($this->http->FindSingleNode("preceding::td[{$this->starts($this->t('Rate Option'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][1]/td[normalize-space()!=''][3]",
                    $segment));

            $s->airline()
                ->noName()
                ->noNumber();

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("preceding::text()[normalize-space()!=''][1]",
                $segment)));

            /*
Bogota
Colombia
El Dorado Airport, 1
BOG
Bogota(BOG)
             * */
            $pattern1 = '/\n(?<name>.+)(?:, (?<terminal>\w+))?\n(?<code>[A-Z]{3})\n/';

            $departure = implode("\n",
                $this->http->FindNodes("following-sibling::tr[normalize-space()!='']/*[1]/descendant::text()[normalize-space()!='']",
                    $segment));

            if (preg_match($pattern1, $departure, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code']);

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->departure()->terminal($m['terminal']);
                }

                if ($date) {
                    $s->departure()
                        ->date($date);
                }
            }

            $arrival = implode("\n",
                $this->http->FindNodes("following-sibling::tr[normalize-space()!='']/*[2]/descendant::text()[normalize-space()!='']",
                    $segment));

            if (preg_match($pattern1, $arrival, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                    ->noDate();

                if (isset($m['terminal']) && !empty($m['terminal'])) {
                    $s->arrival()->terminal($m['terminal']);
                }
            }
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Reservation code']) || empty($phrases['Outbound Flight'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Reservation code'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Outbound Flight'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 23/04/2020
            '/^(\d{1,2})[ ]*\/[ ]*(\d{1,2})[ ]*\/[ ]*(\d{2,4})$/',
            //Sunday, March 8, 2020 1:30 PM
            '/^[\w\-]+, (\w+) (\d+), (\d{4}) (\d+:\d+ [ap]m)$/iu',
        ];
        $out = [
            '$2/$1/$3',
            '$2 $1 $3, $4',
        ];

        return preg_replace($in, $out, $text);
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }
}
