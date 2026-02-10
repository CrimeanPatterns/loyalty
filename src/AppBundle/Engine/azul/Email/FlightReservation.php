<?php

namespace AwardWallet\Engine\azul\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\azul\Email\Reserva as SubjectPatterns;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightReservation extends \TAccountChecker
{
    public $mailFiles = "azul/it-123214186.eml, azul/it-124031970.eml, azul/it-148189993.eml, azul/it-150555907.eml, azul/it-695143925.eml, azul/it-705790034.eml";

    public $lang = 'pt';
    public $date;

    public static $dictionary = [
        "pt" => [
            'Detalhes da sua viagem' => ['Detalhes da sua viagem', 'Have a great trip'],
            'Ver na web'             => ['Ver na web', 'View in Browser'],
            'confNumber'             => ['Seu código de reserva é:', 'Reservation code:', 'Código da Reserva:'],
            'cancelledPhrases'       => ['Sua reserva foi cancelada com sucesso.'],
            'totalPrice'             => ['Total da Passagem', 'Air ticket total'],
            'cost'                   => ['Tarifa Total', 'Total Fare'],
            'tax'                    => ['Taxas', 'Fees'],
            'connections'            => ['conexões', 'conexão'],
            'Assento '               => ['Assento ', 'Seat '],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@news-voeazul.com') !== false) {
            foreach (SubjectPatterns::$reSubject as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Azul')]")->length > 0) {
            return $this->http->XPath->query("//*[{$this->contains($this->t('confNumber'))}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($this->t('Seu voo para'))} or {$this->contains($this->t('cancelledPhrases'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]news\-voeazul\.com(?:\.br)?$$/', $from) > 0;
    }

    public function ParseFlight(Email $email, string $emailSubject): void
    {
        $f = $email->add()->flight();

        foreach (SubjectPatterns::$reSubject as $sPattern) {
            if (preg_match($sPattern, $emailSubject, $m) && !empty($m['status'])) {
                $f->general()->status($m['status']);

                break;
            }
        }

        $f->general()->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/'));

        $travellers = [];

        $seatsStr = array_filter($this->http->FindNodes("//td[not(.//td)][{$this->starts($this->t('Assento '))}]", null,
            "/{$this->opt($this->t('Assento '))}\s*([A-Z]{3}\s*-\s*[A-Z]{3})\s*$/"));
        $segmentsCodes = [];

        foreach ($seatsStr as $str) {
            if (preg_match("/^\s*([A-Z]{3})\s*-\s*([A-Z]{3})\s*$/", $str, $m)) {
                $segmentsCodes[] = [
                    'dep' => $m[1], 'arr' => $m[2],
                ];
            }
        }

        $xpathSeg = "img[contains(@src,'ico_aviao_02')]";
        $segments = $this->http->XPath->query('//' . $xpathSeg);
        $iSeg = -1;

        foreach ($segments as $root) {
            $s = $f->addSegment();
            $iSeg++;

            $s->airline()
                ->name('AD')
                ->number($this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Voo'))}][1]", $root, true, "/\s*(\d{2,4})$/"));

            $code = $this->http->FindSingleNode("./preceding::text()[normalize-space()][2]", $root, true, "/^([A-Z]{3})$/");

            if (empty($code)) {
                $code = $this->http->FindSingleNode("./preceding::text()[normalize-space()][3]", $root, true, "/^([A-Z]{3})$/");
            }

            if (empty($code)) {
                $code = $this->http->FindSingleNode("./preceding::text()[normalize-space()][4]", $root, true, "/^([A-Z]{3})$/");
            }

            $s->departure()
                ->code($code);

            if (!isset($segmentsCodes[$iSeg]) || $code !== $segmentsCodes[$iSeg]['dep']) {
                $segmentsCodes = [];
            }

            $depDate = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);

            if (!empty($this->date) && preg_match("/^(?<day>\d+)\/(?<month>\d+).+\s+(?<time>[\d\:]+)$/", $depDate, $m)) {
                $s->departure()
                    ->date(strtotime($m['time'], EmailDateHelper::parseDateRelative(
                        $m['day'] . '.' . $m['month'], $this->date, true, '%D%.%Y%'
                    )));
            }

            $aCode = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Voo'))}][1]/following::text()[normalize-space()][1]", $root);

            $arrDate = null;
            $arrDateStr = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Voo'))}][1]/following::text()[contains(normalize-space(), '/')][1]", $root);

            if (!empty($this->date) && preg_match("/^(?<day>\d+)\/(?<month>\d+).+\s+(?<time>[\d\:]+)$/", $arrDateStr, $m)) {
                $arrDate = strtotime($m['time'], EmailDateHelper::parseDateRelative(
                        $m['day'] . '.' . $m['month'], $this->date, true, '%D%.%Y%'
                    ));
            }
            $stop = (int) $this->http->FindSingleNode("ancestor::*[normalize-space()][1]//text()[{$this->contains($this->t('connections'))}][1]",
                $root, true, "/^[ ]*(\d{1,3})\s*{$this->opt($this->t('connections'))}/mu");

            if (empty($stop)) {
                $s->arrival()
                    ->code($aCode);

                if (!isset($segmentsCodes[$iSeg]) || $aCode !== $segmentsCodes[$iSeg]['arr']) {
                    $segmentsCodes = [];
                }
                $s->arrival()
                    ->date($arrDate);
            } elseif ($stop === 1) {
                $s->arrival()
                    ->noDate();

                $s2 = $f->addSegment();
                $iSeg++;

                $s2->airline()
                    ->name('AD')
                    ->noNumber();

                $s2->departure()
                    ->noDate();

                $s2->arrival()
                    ->code($aCode);

                if (!isset($segmentsCodes[$iSeg]) || $aCode !== $segmentsCodes[$iSeg]['arr']) {
                    $segmentsCodes = [];
                }
                $s2->arrival()
                    ->date($arrDate);
            } elseif ($stop > 1) {
                $email->removeItinerary($f);
                $email->setIsJunk(true, 'impossible to get a route for flights with 1 or more stops');

                return;
            }

            $xpathNextTable = "ancestor::table[ descendant::{$xpathSeg} and not(descendant::tr[{$this->starts($this->t('Passageiro'))}]) ][last()]/following::tr[count(*[normalize-space()])=3][1]/ancestor::table[ descendant-or-self::*/tr[normalize-space()][2] ][1][not(descendant::{$xpathSeg})]";

            $passengerRows = $this->http->XPath->query($xpathNextTable . "/descendant::tr[ *[normalize-space()][1][{$this->eq($this->t('Passageiro'))}] and *[normalize-space()][3][{$this->eq($this->t('Assentos'))}] ]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1][not(preceding-sibling::tr[normalize-space()])]/following-sibling::tr[normalize-space()][normalize-space()]", $root);

            foreach ($passengerRows as $pRow) {
                $pName = $this->http->FindSingleNode("descendant-or-self::tr[count(*)=3][1]/*[1]/descendant::tr[not(.//tr) and string-length(normalize-space())>2][1]", $pRow, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u");

                if ($pName) {
                    $travellers[] = $pName;
                }
                $seat = $this->http->FindSingleNode("descendant-or-self::tr[count(*)=3][1]/*[3]", $pRow, true, "/^\d+[A-Z]$/u");

                if ($seat) {
                    $s->extra()->seat($seat, true, true, $pName);
                }
            }
        }

        if (!empty($segmentsCodes) && count($segmentsCodes) === $iSeg + 1) {
            foreach ($f->getSegments() as $i => $seg) {
                if (empty($seg->getDepCode())) {
                    $f->getSegments()[$i]->departure()
                        ->code($segmentsCodes[$i]['dep']);
                }

                if (empty($seg->getArrCode())) {
                    $seg->arrival()
                        ->code($segmentsCodes[$i]['arr']);
                }
            }
        } else {
            if ($iSeg + 1 > $segments->length) {
                $email->removeItinerary($f);
                $email->setIsJunk(true, 'impossible to get a route for flights with 1 or more stops');

                return;
            }

            foreach ($f->getSegments() as $i => $seg) {
                if (empty($seg->getDepCode())) {
                    $seg->departure()
                        ->noCode();
                }

                if (empty($seg->getArrCode())) {
                    $seg->arrival()
                        ->noCode();
                }
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers(array_unique($travellers), true);
        }

        $price = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalPrice'))}] ]/*[normalize-space()][2]");

        if (preg_match("/^(?<points>\d[,.\'\d ]*?)[ ]*{$this->opt($this->t('pontos'))}[ ]*[+]+[ ]*(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/", $price, $matches)
            || preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $price, $matches)
        ) {
            // R$ 3.468,52    |    14.000 pontos + R$ 62,23
            if (!empty($matches['points'])) {
                $f->price()->spentAwards($matches['points']);
            }

            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $f->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('cost'))}] ]/*[normalize-space()][2]");

            if (preg_match('/(?:^|[+][ ]*)(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $baseFare, $m)) {
                $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $tax = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('tax'))}] ]/*[normalize-space()][2]");

            if (preg_match('/(?:^|[+][ ]*)(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)$/', $tax, $m)) {
                $f->price()->tax(PriceHelper::parse($m['amount'], $currencyCode));
            }
        }

        $statementText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/preceding::text()[contains(normalize-space(), 'pontos')]/ancestor::tr[1]");

        if (preg_match("/^(?<name>\D+)\,\s*você é\s*(?<status>\D+)\s*e seu saldo é de\s*(?<balance>[\d\,\.]+)\s*pontos\.\s*$/u", $statementText, $m)) {
            $st = $email->add()->statement();
            $st->addProperty('Name', trim($m['name'], ','));
            $st->addProperty('Status', trim($m['status'], ','));
            $st->setBalance(str_replace(['.'], '', $m['balance']));
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            // it-150555907.eml
            $f->general()->cancelled();

            return;
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('FlightReservation' . ucfirst($this->lang));

        $stops = $this->http->FindNodes("//tr[ *[normalize-space()][3] ]/*[normalize-space()][2]/descendant-or-self::*[{$this->contains($this->t('connections'))}][1]",
            null, "/^[ ]*(\d{1,3})\s*{$this->opt($this->t('connections'))}/u");
        rsort($stops);

        if (!empty($stops) && array_sum($stops) > 0
            && ($stops[0] >= 2
                || $stops[0] == 1 && $this->http->XPath->query("//td[not(.//td)][{$this->starts($this->t('Assento '))}]")->length === 0)
        ) {
            // it-123214186.eml
            $email->setIsJunk(true, 'impossible to get a route for flights with 1 or more stops');

            return $email;
        }

        $this->date = null;

        $partXpath = "//text()[{$this->eq($this->t('Detalhes da sua viagem'))}][following::text()[normalize-space()][1][{$this->eq($this->t('Ver na web'))}]]";

        if (stripos($parser->getCleanFrom(), '@news-voeazul.com') === false
            && $this->http->XPath->query($partXpath)->length === 1
        ) {
            $text = implode("\n", $this->http->FindNodes($partXpath . "/preceding::text()[normalize-space()][not(ancestor::style)][1]/ancestor::div[count(.//text()[normalize-space()]) > 2][not(.//text()[{$this->eq($this->t('Detalhes da sua viagem'))}])][1]//text()[normalize-space()]"));

            if (empty($text)) {
                $text = implode("\n", $this->http->FindNodes($partXpath . "/preceding::text()[normalize-space()][not(ancestor::style)][1]/ancestor::div[1]/following-sibling::*[normalize-space()][1][descendant::text()[normalize-space()][1][{$this->eq($this->t('Detalhes da sua viagem'))}]]/preceding-sibling::div[normalize-space()][position() < 7]//text()[normalize-space()]"));
            }

            if (empty($text)) {
                $text = implode("\n", $this->http->FindNodes($partXpath . "/preceding::text()[normalize-space()][not(ancestor::style)][1]/ancestor::span[count(.//text()[normalize-space()]) > 2][not(.//text()[{$this->eq($this->t('Detalhes da sua viagem'))}])][1]//text()[normalize-space()]"));
            }

            if (substr_count($text, "\n") < 10
                && preg_match("/\n[[:alpha:]]+ ?:(?:\s*\W{0,3}\s*(?:Azul Linhas Aéreas|Azul|email@news-voeazul\.com\.br)\W{0,3})+\n/u", "\n" . $text . "\n")
                && preg_match("/\n(Date|Data|Sent|Enviado) ?:\s*(?<date>.+\b20\d{2}\b.+\b\d{1,2}:\d{2}\b.*)\n/u", "\n" . $text . "\n", $m)
                && preg_match_all("/\n[[:alpha:]]+ ?:\s*(?:Reserva [A-Z\d]{5,7} Realizada com Sucesso|Reservation [A-Z\d]{5,7} is confirmed)\s*\n/u", "\n" . $text . "\n", $sm)
                && count($sm[0]) === 1
            ) {
                $this->date = $this->normalizeDateFrom($m['date']);

                if (!empty($this->date)) {
                    $this->date = strtotime('-30 day', $this->date);
                }
            }
        }

        if (empty($this->date)) {
            $this->date = strtotime('-30 day', strtotime($parser->getHeader('date')));
        }

        if (empty($this->date) && stripos($parser->getCleanFrom(), '@news-voeazul.com') === false) {
            $email->setIsJunk(true, 'impossible to determine the year');

            return $email;
        }

        $this->ParseFlight($email, $parser->getSubject());

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

    private function normalizeDateFrom($date)
    {
        // seg., 4 de dez. de 2023 09:05
        // 13 de julho de 2024 às 14:55:05 BRT
        // 15 de julho de 2024 às 18:17:40 AMT
        // ter., 16 de jul. de 2024 às 21:47
        // fre. 12. juli 2024 kl. 09:45
        // 16/07/2024 09:05 (GMT-03:00)

        // $this->logger->debug('$date in  = '.print_r( $date,true));
        $in = [
            // seg., 4 de dez. de 2023 09:05
            "/^\s*[[:alpha:]\-]+[.]?\s*[,\s]\s*(\d{1,2})(?: de | )?([[:alpha:]]{3,})[.]?(?: de | )?(20\d{2})(\s*,\s*|\s+|\s+[[:alpha:]\.]{1,4}\s+)\d{1,2}:\d{2}\b.*$/ui",
            // 22 de junho de 2024 às 00:47:04 BRT
            "/^\s*(\d{1,2})(?: de | )?([[:alpha:]]{3,})[.]?(?: de | )?(20\d{2})(\s*,\s*|\s+|\s+[[:alpha:]\.]{1,4}\s+)\d{1,2}:\d{2}\b.*/ui",
            // Mon, Jul 15, 2024 at 1:41 PM
            "/^\s*[[:alpha:]\-]+[.]?\s*[,\s]\s*([[:alpha:]]{3,})\s+(\d{1,2})\s*[\s,]\s*(20\d{2})(\s*,\s*|\s+|\s+[[:alpha:]\.]{1,4}\s+)\d{1,2}:\d{2}\b.*$/ui",
            "/^\s*(\d{1,2})\/(\d{1,2})\/(20\d{2})(\s*,\s*|\s+|\s+[[:alpha:]\.]{1,4}\s+)\d{1,2}:\d{2}\b.*$/ui",
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3",
            "$2 $1 $3",
            "$1.$2.$3",
        ];

        foreach ($in as $i => $re) {
            if (preg_match($re, $date)) {
                $date = preg_replace($in, $out, $date);

                break;
            }
        }
        // $this->logger->debug('$date repl  = '.print_r( $date,true));

        if (preg_match("#^(\D*\d{1,2})\.(\d{1,2})\.(\d{4})\s*$#u", $date, $m)) {
            $date = $m[1] . ' ' . date("F", mktime(0, 0, 0, $m[2], 1, 2011)) . ' ' . $m[3];
        }

        $result = null;

        if (preg_match("/^\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*$/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[2], 'pt')) {
                $date = $m[1] . ' ' . $en . ' ' . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'es')) {
                $date = $m[1] . ' ' . $en . ' ' . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'en')) {
                $date = $m[1] . ' ' . $en . ' ' . $m[3];
            }
            $result = strtotime($date);
        }

        return $result;
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currences = [
            'RUB' => ['Руб.'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'JPY' => ['¥'],
            'BRL' => ['R$'],
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
}
