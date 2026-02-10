<?php

namespace AwardWallet\Engine\despegar\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// May cross PDF.php
class TuViajeAccomodationHtml extends \TAccountChecker
{
    public $mailFiles = "despegar/it-38239161.eml, despegar/it-41859832.eml, despegar/it-46776703.eml, despegar/it-65557335.eml";
    public $reBody = [
        'es' => ['Sobre tu alojamiento', 'Sobre tu vuelo', 'Sobre tus vuelos', 'Ya tienes tu vuelo y asistencia', 'Tu voucher está adjunto.'],
        'pt' => ['Sobre sua hospedagem'],
    ];
    public $reSubject = [
        '¡Genial! Tu viaje está confirmado',
    ];
    public $lang = '';
    public static $dict = [
        'es' => [
            'Nro. reserva'                       => ['Nro. reserva', 'Reserva'],
            'escala'                             => ['escala'],
            'Entrada:'                           => ['Entrada:', 'IDA'],
            'Salida:'                            => ['Salida:', 'VUELTA'],
            'Paquete para'                       => ['Paquete para', 'Alojamiento para', 'Vuelo para'],
            'personas'                           => ['personas', 'persona'],
            // 'Cargos' => '',
            // 'Descuento' => '',
            'TOTAL'                              => ['TOTAL', 'Total'],
            'El alojamiento quiere decirte algo' => [
                'El alojamiento quiere decirte algo', 'El alojamiento desea decirte algo...', '¡Por tiempo limitado!',
                'Actividades', 'Políticas de cambio y cancelación', 'Condiciones del alojamiento', 'Solicitudes especiales', ],
        ],
        'pt' => [
            //            'escala'                           => [''],
            'Entrada:'                           => 'Entrada:',
            'Salida:'                            => 'Saída:',
            'Paquete para'                       => ['Hospedagem para', 'Pacote para'],
            'personas'                           => ['pessoa'],
            'Cargos'                             => 'Encargos',
            'Descuento'                          => 'Desconto do pacote',
            'Nro. reserva'                       => ['Nº de reserva', 'Reserva'],
            'Impuestos y tasas'                  => 'Impostos e taxas',
            'Sobre tu alojamiento'               => 'Sobre sua hospedagem',
            'El alojamiento quiere decirte algo' => ['A hospedagem tem um recado para você', 'Pedidos especiais', 'Condições da hospedagem'],
            'Código de ingreso al alojamiento'   => 'Código de acesso à hospedagem',
            'Duración'                           => 'Duração',
        ],
    ];

    private $year = null;
    private $date;
    private $code = null;
    private static $providers = [
        // decolar should be the fist
        'decolar' => [
            'from'        => ['noreply@decolar.com'],
            'keywordProv' => ['Decolar'],
            'subj'        => [
                '¡Genial! Tu viaje está confirmado',
                'Sua viagem está confirmada',
            ],
            'body' => [
                '//img[@alt=\'Decolar.com\' or contains(@src,\'.decolar.com\')] | //a[contains(@href,\'.decolar.com\')]',
            ],
        ],
        'despegar' => [
            'from'        => ['noreply@despegar.com'],
            'keywordProv' => ['Despegar'],
            'subj'        => [
                '¡Genial! Tu viaje está confirmado',
                'Sua viagem está confirmada',
            ],
            'body' => [
                '//img[@alt=\'Despegar.com\' or contains(@src,\'.despegar.com\')] | //a[contains(@href,\'.despegar.com\')]',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // TODO: Letters from PDF let parsing PDF.php there is more data
        $pdfs = $parser->searchAttachmentByName('(voucher|\d+)_flight_.*?\.pdf');

        if (count($pdfs) > 0) {
            return $email;
        }

        $this->date = strtotime($parser->getDate());

        if (preg_match('/Flight arrival\s+\d+ \w+ (\d{4})/', substr($parser->getHTMLBody(), 0, -5000), $m) // Flight arrival 28 Feb 2020
            || preg_match('/Vigencia: \w+ \d+ \w+ (\d{4})/', substr($parser->getHTMLBody(), 0, -5000), $m) // Vigencia: desde 12 nov 2019 hasta
            || preg_match('/©\s*(\d{4})\s*Todos los derechos reservados/', substr($parser->getHTMLBody(), -2000), $m)
            || preg_match('/Date:\s+(\d{4})-\d+-\d+T\d+/', substr($parser->getHTMLBody(), 0, 200), $m) // Date: 2019-11-16T16:10:23.000Z
            || preg_match('/antes del\s*\d+\/\d+\/(\d{4})/u', $parser->getBodyStr(), $m) // Podés hacerlo antes del 18/05/2022
        ) {
            $this->year = $m[1];
        } else {
            $this->year = $this->http->FindSingleNode("(//a[contains(@href,'/xselling-service/track-and-redirect')]/@href)[1]", null, false, '/(\d{4})-\d+-\d+/')
                ?? $this->http->FindSingleNode("//text()[contains(normalize-space(),'Cadastre-se antes do dia')]", null, false, '/Cadastre-se antes do dia\s+\d{1,2}\/\d{1,2}\/(\d{4})/')
                ?? date('Y', $this->date ? $this->date : null)
            ;
        }

        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        if (null !== ($code = $this->getProvider($parser)) && $code !== 'despegar') {
            $email->setProviderCode($code);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (null !== ($code = $this->getProviderByBody())
            && $this->detectBody($this->http->Response['body'])
        ) {
            return $this->assignLang();
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $arr) {
            if ($this->stripos($from, $arr['from'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $code => $arr) {
            $byFrom = $bySubj = false;

            if ($this->stripos($headers['from'], $arr['from'])
                || $this->stripos($headers["subject"],
                    $arr['keywordProv'])
            ) {
                $byFrom = true;
            }

            if ($this->stripos($headers['subject'], $arr['subj'])) {
                $bySubj = true;
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    private function parseEmail(Email $email): void
    {
        $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Nro. reserva'))}]", null,
            false, "#{$this->opt($this->t('Nro. reserva'))}\s+(.+)#");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Nro. reserva'))}]/following-sibling::*[normalize-space()][1]");
        }
        $email->ota()
            ->confirmation($conf, $this->http->FindSingleNode("//text()[{$this->starts($this->t('Nro. reserva'))}]", null, false, "#{$this->opt($this->t('Nro. reserva'))}#"));

        // payment
        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('TOTAL'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // MXN$ 56,127
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $preRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('TOTAL'))}] ]/ancestor::div[ preceding-sibling::div[normalize-space()] ][1]/preceding-sibling::div[normalize-space()][1]/descendant::*[ tr[count(*[normalize-space()])=2] ][1]/tr[normalize-space()]");

            foreach ($preRows as $i => $priceRow) {
                $priceName = $this->http->FindSingleNode("*[normalize-space()][1]", $priceRow);
                $priceCharge = $this->http->FindSingleNode("*[normalize-space()][2]", $priceRow, true, '/^(.*?\d.*?)(?:\s*\(|$)/');

                if ($i === 0 && preg_match("/^{$this->opt($this->t('Paquete para'))}\s+.*\s*\b{$this->opt($this->t('personas'))}/", $priceName)
                    && (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $priceCharge, $m)
                        || preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $priceCharge, $m)
                    )
                ) {
                    $email->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));

                    continue;
                }

                if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $priceCharge, $m)
                    || preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $priceCharge, $m)
                ) {
                    $email->price()->fee($priceName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }

            $discount = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Descuento'))}] ]/*[normalize-space()][2]", null, true, '/^-\s*(.*\d.*)$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $discount, $m)) {
                $email->price()->discount(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        $this->parseFlight($email);
        $this->parseHotel($email);
    }

    private function parseFlight(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);
        $nodes = $this->http->XPath->query("//img[contains(@src,'stops-0-dots.png') or contains(@src,'stops-1-dots.png')]/ancestor::div[contains(@style,'margin:20px') or contains(@style,'margin: 20px')]");

        if ($nodes->length == 0) {
            $this->logger->notice('Not found flights');

            return;
        }
        $f = $email->add()->flight();

        if (empty($this->year)) {
            $this->logger->debug('Not found date!');

            return;
        }

        $f->general()->noConfirmation();
        $this->logger->debug("Found {$nodes->length} segments");

        foreach ($nodes as $node) {
            $this->logger->debug($node->nodeValue);

            if (
                // IDA   28 de febrero          Sky Airline      SCL   12:47      Directo        EZE   14:47     Duración   02:00        No incluye equipaje
                preg_match('/\s{3,}(?<date>\d+.+?)\s{3,}(?<airName>.+?)\s{3,}/', $node->nodeValue, $m)
                // Tramo 1Ciudad de México - Roma30 de abril AlitaliaMEX23:30DirectoFCO18:25Duración11:55  No incluye equipaje para despachar
                || preg_match('/Tramo \d+.+?(?<date>\d+ de \w+)\s*(?<airName>.+?)[A-Z]{3}\s*\d+:/u', $node->nodeValue, $m)
            ) {
                $s = $f->addSegment();
                $s->airline()->noNumber();
                $s->airline()->name($m[2]);
                $date = $this->normalizeDate($m[1]);
            }

            $this->logger->warning("/([A-Z]{3})\s*(\d+:\d+).*?([A-Z]{3})\s*(\d+:\d+)\s*{$this->opt($this->t('Duración'))}\s*(\d+:\d+|\d+h \d+m|\d+h)\b/");

            if (preg_match("/([A-Z]{3})\s*(\d+:\d+).*?([A-Z]{3})\s*(\d+:\d+)\s*{$this->opt($this->t('Duración'))}\s*(\d+:\d+|\d+h \d+m|\d+h|\d+m)\b/", $node->nodeValue, $m)) {
                $s->departure()
                    ->code($m[1])
                    ->date((!empty($date)) ? strtotime($m[2], $date) : null);
                $s->arrival()
                    ->code($m[3])
                    ->date((!empty($date)) ? strtotime($m[4], $date) : null);
                $s->extra()->duration($m[5]);
            }

            if (preg_match("/\s(\d+)\s*{$this->opt($this->t('escala'))}/", $node->nodeValue, $m)) {
                $s->extra()->stops($m[1]);
            }
        }
    }

    private function parseHotel(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);
        // hotel reservations
        $xpath = "//text()[{$this->eq($this->t('Sobre tu alojamiento'))}]/ancestor::*[./following-sibling::*[{$this->contains($this->t('Entrada'))} or .//img[contains(@src,'/location.png')]]]/following-sibling::*[normalize-space()][not({$this->starts($this->t('El alojamiento quiere decirte algo'))})]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH]: " . $xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->hotel();

            if (empty($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][4][{$this->starts($this->t('Código de ingreso al alojamiento'))}]", $root))) {
                $confNo = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Código de ingreso al alojamiento'))}]/following::text()[normalize-space()!=''][1]");

                if ($confNo) {
                    $r->general()->confirmation($confNo);
                } else {
                    $r->general()->noConfirmation();
                }
            } else {
                if ($confNo = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][5]", $root)) {
                    $r->general()->confirmation($confNo);
                }
            }

            $r->hotel()
                ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root))
                ->address($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2]", $root))
                ->phone($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][3]", $root, null, '/[\d.\-\s()+]+/'), true, true);

            $checkIn = $this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Entrada:'))}]/following::text()[normalize-space()!=''][1]", $root));

            if ($checkIn) {
                $r->booked()
                    ->checkIn($checkIn)
                    ->checkOut($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Salida:'))}]/following::text()[normalize-space()!=''][1]", $root)));
            } elseif (!$this->http->FindSingleNode("(//text()[{$this->contains($this->t('Entrada:'))} or {$this->contains($this->t('Salida:'))}])[1]")) {
                $r->booked()->noCheckIn()->noCheckOut();
            }
        }
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            //Sab 18/5 15:00 hs  |   Seg. 12/8 15:00 hs
            '#^(\w+)\.?\s+(\d+)\/(\d+)\s+(\d+:\d+)\s+hs$#u',
            // 28 de febrero
            '/(\d+) de (\w+)/',
        ];
        $out = [
            $year . '-$3-$2, $4',
            '$1 $2 ' . $this->year,
        ];
        $outWeek = [
            '$1',
            '',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function getProvider(\PlancakeEmailParser $parser): ?string
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        return $this->getProviderByBody();
    }

    private function getProviderByBody(): ?string
    {
        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body): bool
    {
        if (null == ($code = $this->getProviderByBody())) {
            return false;
        }

        if (isset($this->reBody)) {
            foreach ($this->reBody as $reBody) {
                if ($this->stripos($body, $reBody)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                foreach ($reBody as $words) {
                    if ($this->http->XPath->query("//*[{$this->contains($words)}]")->length > 0) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
            'BRL' => ['R$'],
            'MXN' => ['MXN$'],
            'EUR' => ['€'],
            'GBP' => ['£'],
            'INR' => ['₹'],
            'CRC' => ['₡'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
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

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
