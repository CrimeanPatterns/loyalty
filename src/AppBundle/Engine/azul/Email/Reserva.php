<?php

namespace AwardWallet\Engine\azul\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Reserva extends \TAccountChecker
{
    public $mailFiles = "azul/it-10044393.eml, azul/it-12891690.eml, azul/it-12961884.eml, azul/it-16722066.eml, azul/it-29873251.eml, azul/it-4350168.eml, azul/it-4371686.eml, azul/it-4408498.eml, azul/it-50822669.eml, azul/it-6542605.eml, azul/it-6573323.eml, azul/it-73520360.eml, azul/it-8194238.eml, azul/it-148567028.eml, azul/it-145759683.eml";

    public $reFrom = "voeazul.com.br";

    public $reBody = [
        'pt'  => ['Passageiro', 'Lembretes para sua viagem'],
        'pt2' => ['Passageiro', 'Dicas para sua viagem'],
        'pt5' => ['Passageiro', 'você comprou a sua passagem com pontos'],
        'pt3' => ['O cancelamento da sua reserva foi realizado com sucesso', 'Código da Reserva'], // cancelled
        'pt4' => ['Cancelamento do localizador', 'O bilhete de sua viagem para com partida em'], // cancelled
        'en'  => ['Passenger', 'Air ticket total'],
    ];

    public $subject;

    // used in azul/FlightReservation
    // used in azul/HotelReservation
    public static $reSubject = [
        '/Reserva\s+[A-Z\d]{5,}(?:\s+Realizada\s+com)?\s+(?<status>Sucesso|confirmada|Cancelada)(?:\s*[,.:;!?]|$)/i',
        '/Reservation\s+[A-Z\d]{5,}\s+is\s+(?<status>confirmed)(?:\s*[,.:;!?]|$)/i',
    ];

    public $lang = 'pt';

    public static $dict = [
        'pt' => [
            //			"Código da Reserva" => "",
            "Passageiro" => ["Passageiro", "Passageiros"],
            //			"Conexão" => "",
            //			"Total da Passagem" => "",
            "Taxas" => ["Total em taxas", "Taxas"],
            //			"Assento" => "",
            //			'Nº TudoAzul' => '',
            'cancelledPhrases' => ['O cancelamento da sua reserva foi realizado com sucesso!'],
        ],
        'en' => [
            "Código da Reserva" => "Reservation code",
            "Passageiro"        => ["Passenger", "Passengers"],
            "Conexão"           => "Connection",
            "Total da Passagem" => "Air ticket total",
            "Taxas"             => "Fees",
            "Assento"           => "Seat",
            'Nº TudoAzul'       => ['Nº TudoAzul', 'TudoAzul'],
            // 'cancelledPhrases' => [''],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->subject = $parser->getSubject();

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'voeazul.com')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset(self::$reSubject)) {
            foreach (self::$reSubject as $sPattern) {
                if (preg_match($sPattern, $headers["subject"])) {
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
        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Código da Reserva'))}]/following::text()[normalize-space()][1])[1]", null, true, '/^[A-Z\d]{5,}$/'); // it-6542605.eml

        if (empty($confirmation)) {
            $confirmation = $this->re("/(?:^|:)\s*Reserva\s*([A-Z\d]{5,})\s+/", $this->subject);
        }

        $f->general()
           ->confirmation($confirmation);

        foreach (self::$reSubject as $sPattern) {
            if (preg_match($sPattern, $this->subject, $m) && !empty($m['status'])) {
                $f->general()
                    ->status($m['status']);

                if (stripos($m['status'], 'cancelada') !== false) {
                    $f->general()
                        ->cancelled();
                }

                break;
            }
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            // it-148567028.eml
            $f->general()
                ->cancelled();

            return $email;
        } elseif (empty($confirmation)) {
            // it-145759683.eml
            $confNumbers = array_filter($this->http->FindNodes("//text()[ normalize-space() and preceding::text()[normalize-space()][1][{$this->starts('Cancelamento do localizador')}] and following::text()[normalize-space()][1][{$this->contains('confirmado')}] ]", null, '/^[A-Z\d]{5,}$/'));

            if (count(array_unique($confNumbers)) === 1) {
                $confirmation = array_shift($confNumbers);
                $f->general()
                    ->confirmation($confirmation)
                    ->cancelled();

                return;
            }
        }

        $xpathTime = 'contains(translate(normalize-space(),"0123456789：.","dddddddddd::"),"d:dd")';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        // Passengers
        // AccountNumbers
        $passengers = [];
        $accountNumberCell = $this->http->XPath->query("//text()[{$this->eq($this->t('Passageiro'))}]/ancestor::tr[1]/td[{$this->eq($this->t('Nº TudoAzul'))}]");

        if ($accountNumberCell->length > 0) {
            $posAccountNumber = $this->http->XPath->query('./preceding-sibling::td', $accountNumberCell->item(0))->length + 1;
        } else {
            $posAccountNumber = 2; // it-16722066
        }
        $passengerRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Passageiro'))}]/ancestor::tr[1]/following-sibling::tr[not(.//td[1][contains(.,'" . $this->t('Conexão') . "')])]");

        if ($passengerRows->length === 0) {
            $passengerRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Passageiro'))}]/ancestor::table[1]/following-sibling::table[not(.//td[1][contains(.,'" . $this->t('Conexão') . "')])]");
        }

        foreach ($passengerRows as $passengerRow) {
            $notRoute = "not(translate(normalize-space(),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','##########################')='###-###')";
            $passengerTexts = $this->http->FindNodes(".//td[1][{$notRoute}]/descendant::text()[normalize-space(.) and not(./ancestor::small)]", $passengerRow);
//            $passengerTexts = array_filter($this->http->FindNodes(".//td[1][{$notRoute}]/descendant::text()[normalize-space(.) and not(./ancestor::small)]", $passengerRow),
//                function($s){return preg_match("/\d+/", $s)===0 && preg_match("/^Tudo$/", $s)===0 && preg_match("/^Azul$/", $s)===0 && preg_match("/^Tudo\s*Azul$/", $s)===0;}  );
            $passengerText = implode(' ', $passengerTexts);

            if ($passengerText) {
                $passengerText = $this->re("/^({$patterns['travellerName']})\s*(?:Nº|Tudo|\(|$)/iu", $passengerText);
                $passengers[] = $passengerText;
            }

            $accountNumber = $this->http->FindSingleNode(".//td[{$posAccountNumber}]", $passengerRow, true,
                '/^(\d{5,})$/');

            if (empty($accountNumber)) {
                $nodeTexts = array_filter($this->http->FindNodes(".//td[1][{$notRoute}]/descendant::text()[normalize-space(.)!='']",
                    $passengerRow, "/^\d+$/"));

                if (count($nodeTexts) === 1) {
                    $accountNumber = array_shift($nodeTexts);
                }
            }

            if (!empty($accountNumber) && !in_array($accountNumber, array_column($f->getAccountNumbers(), 0))) {
                $f->program()
                    ->account($accountNumber, false, $passengerText);
            }
        }

        if (!empty($passengers[0])) {
            $f->general()
                ->travellers(array_unique($passengers));
        }

        // R$ 1.067,94    |    R$ 5,085.16    |    16.000 pontos + R$ 211,52
        $totalPrice = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Total da Passagem'))}]/ancestor::td[1]/following-sibling::td[1])[1]", null, true, '/^.*\d.*$/');

        if ($totalPrice === null) {
            $totalPrice = implode(' + ', array_filter($this->http->FindNodes("//tr/*[{$this->eq($this->t('Pagamento aprovado'))}]/following-sibling::*[1]", null, '/^.*\d.*$/')));
        }

        if (preg_match("/(\d[,.\'\d ]*pontos)/i", $totalPrice, $m)
            || preg_match("/^(\d[,.\'\d ]*)$/", $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Total de pontos'))}]/ancestor::td[1]/following-sibling::td[1])[1]"), $m)
        ) {
            $f->price()
                ->spentAwards($m[1]);

            $tot = $this->getTotalCurrency($this->http->FindSingleNode("(//text()[{$this->contains($this->t('Taxas'))}]/ancestor::td[1]/following-sibling::td[1])[1]"));

            if ($tot['Total'] !== '') {
                $f->price()
                    ->tax($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }
        $tot = $this->getTotalCurrency($totalPrice);

        if ($tot['Total'] !== '') {
            $f->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);

            $tot = $this->getTotalCurrency($this->http->FindSingleNode("(//text()[{$this->contains($this->t('Taxas'))}]/ancestor::td[1]/following-sibling::td[1])[1]"));

            if ($tot['Total'] !== '' && empty($f->getPrice()->getFees())) {
                $f->price()
                    ->tax($tot['Total'])
                    ->currency($tot['Currency']);
            }
        } else {
            $totals = $this->http->FindNodes("//text()[{$this->contains($this->t('Total'))}]/ancestor::tr[1]/following-sibling::tr[{$this->contains($this->t('Aprovado'))}]/td[last()]");
            $sum = 0.0;

            foreach ($totals as $total) {
                if (preg_match("/^([^-]+\s+\d[,.\'\d ]*)$/", $total, $m)) {
                    $mm = $this->getTotalCurrency($m[1]);
                    $currency = $mm['Currency'];
                    $sum += $mm['Total'];
                }
            }

            if (!empty($sum) && isset($currency)) {
                $f->price()
                    ->total($sum)
                    ->currency($currency);
            }
        }

        $xpath = "//*[ tr[normalize-space()][2] and count(tr[normalize-space()][1]/*[{$xpathTime}])=2 and count(tr[normalize-space()][2]/*[string-length(normalize-space())=3])=2 ]/tr[normalize-space()][1]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $isConnection = false;

            $routePrefixes = [
                'IDA', 'VOLTA', // pt
                'DEPARTURE', 'ARRIVAL', 'RETURN', 'ROUND', 'BACK', // en
            ];
            $dateVal = $this->http->FindSingleNode("ancestor::table[ preceding-sibling::table[normalize-space()] ][1]/preceding-sibling::table[normalize-space()][last()]/descendant::tr[not(.//tr) and normalize-space()][1]", $root, true, "/^(?:{$this->opt($routePrefixes)}\s+)?(.*?\d.*?)(?:\s+[[:alpha:]]+\s+{$patterns['time']}|$)/iu");
            $date = strtotime($this->normalizeDate($dateVal));

            $s = $f->addSegment();

            $s->departure()
                ->date(strtotime($this->http->FindSingleNode("*[normalize-space()][1]", $root), $date))
                ->code($this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/*[normalize-space()][1]", $root));

            $s->arrival()
                ->date(strtotime($this->http->FindSingleNode("*[normalize-space()][2]", $root), $date))
                ->code($this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/*[normalize-space()][2]", $root));

            $flightVol = implode("\n", $this->http->FindNodes("ancestor::td[1]/preceding-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/(?<firstWord>[[:alpha:]]{3,}\b)?\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s+(?<flightNumber>\d+)(?:\s+\(\s*(?<aircraft>[^)(\n]{2,}?)\s*\))?(?:\n+{$this->opt($this->t('Operado por'))}\s*:\s*(?<operator>.{2,}))?/u", $flightVol, $m)) {
                if (!empty($m['firstWord']) && trim($m['firstWord']) == $this->t('Conexão')) {
                    $isConnection = true;
                }

                if (!empty($m['airline'])) {
                    $s->airline()
                        ->name($m['airline']);
                } else {
                    if ($this->http->XPath->query("//img[contains(@src, 'voeazul.com.br/AzulWebCheckin')]")->length > 0
                        || $this->http->XPath->query("//td[contains(normalize-space(.), 'voeazul.com') and contains(normalize-space(.), 'Azul')]")->length > 0
                    ) {
                        $s->airline()
                            ->name('AD');
                    } else {
                        $s->airline()
                            ->noName();
                    }
                }

                $s->airline()
                    ->number($m['flightNumber']);

                if (!empty($m['aircraft'])) {
                    $s->extra()
                        ->aircraft($m['aircraft']);
                }

                if (!empty($m['operator'])) {
                    $s->airline()
                        ->operator($m['operator']);
                }
            }

            $seats = array_filter($this->http->FindNodes("//text()[normalize-space()='{$s->getDepCode()}-{$s->getArrCode()}']/ancestor::td[1]/following-sibling::td[descendant::img[contains(@src,'seat')]][1]", null, "#(\d+[A-Z])#"));

            if (count($seats) === 0) {
                if ($isConnection) {
                    $seats = array_filter($this->http->FindNodes("./ancestor::table[contains(.,'" . $this->t('Assento') . "')][1]/descendant::text()[normalize-space(.)='" . $this->t('Assento') . "']/ancestor::tr[1]/following-sibling::tr[contains(./td[1],'" . $this->t('Conexão') . "') and string-length(normalize-space(./td[2]))<2]/td[descendant::img[contains(@src,'seat')]][1]",
                        $root, "#(\d+[A-Z])#"));
                } else {
                    $seats = array_filter($this->http->FindNodes("./ancestor::table[contains(.,'" . $this->t('Assento') . "')][1]/descendant::text()[normalize-space(.)='" . $this->t('Assento') . "']/ancestor::tr[1]/following-sibling::tr[not(contains(./td[1],'" . $this->t('Conexão') . "'))]/td[descendant::img[contains(@src,'seat')]][1]",
                        $root, "#(\d+[A-Z])#"));

                    if (count($seats) === 0) {
                        $seats = array_filter($this->http->FindNodes("./ancestor::table[contains(.,'" . $this->t('Assento') . "')][1]/descendant::text()[normalize-space(.)='" . $this->t('Assento') . "']/ancestor::table[1]/following-sibling::table[not(contains(./td[1],'" . $this->t('Conexão') . "'))]//td[descendant::img[contains(@src,'seat')]][1]",
                            $root, "#(\d+[A-Z])#"));
                    }

                    if (count($seats) === 0) {
                        $seats = array_filter($this->http->FindNodes("./following::text()[starts-with(normalize-space(), '" . $this->t('Assento') . "')]/ancestor::table[1]/descendant::tr/td[3][not(contains(normalize-space(), 'Seat'))]",
                            $root, "#(\d+[A-Z])#"));
                    }
                }
            }

            if (count($seats) > 0) {
                $s->extra()
                    ->seats($seats);
            }
        }

        return $email;
    }

    private function normalizeDate(?string $date): string
    {
        $in = [
            // 04 Março 2017    |    Sábado, 04 Março 2017    |    Sexta, 29 de julho de 2022
            '/^(?:[-[:alpha:]]+[,\s]+)?(\d{1,2})\s*(?:de\s+)?([[:alpha:]]+)(?:\s+de)?\s*(\d{2,4})$/u',
            // Monday, August 14 2017
            '/^(?:[-[:alpha:]]+[,\s]+)?([[:alpha:]]+)\s*(\d{1,2})[,\s]+(\d{2,4})$/u',
        ];
        $out = [
            '$1 $2 $3',
            '$2 $1 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
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
            $body = $this->http->Response['body'];

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false
                    || $this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($reBody[1])}]")->length > 0
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish(string $date): string
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("R$", "BRL", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("€", "EUR", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)) {
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) {
            return "contains($text, \"{$s}\")";
        }, $field));
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) {
            return "$text=\"{$s}\"";
        }, $field));
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
