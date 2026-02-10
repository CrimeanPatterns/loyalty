<?php

namespace AwardWallet\Engine\azul\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Subscription extends \TAccountChecker
{
    public $mailFiles = "azul/statements/it-126021193.eml, azul/statements/it-71844328.eml, azul/statements/it-72051080.eml, azul/statements/it-72052942.eml, azul/statements/it-72072679.eml, azul/statements/it-72716473.eml, azul/statements/it-72898687.eml";

    private $format = null;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@news-voeazul.com.br') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'TudoAzul')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//img[contains(@src,'_aviao_')]")->length > 0) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $email->setType('Subscription' . $this->format);
        $root = $roots->item(0);

        $patterns = [
            'travellerName'  => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]',
            'statusVariants' => '(TudoAzul|Sapphire|Safira|Topaz|Topázio|Diamond|Diamante)',
        ];

        $st = $email->add()->statement();

        $name = $status = $number = $balance = $balanceDate = null;

        $rootHtml = $this->http->FindHTMLByXpath('.', null, $root);
        $rootText = $this->htmlToText($rootHtml);

        if ($this->format === 1
            && preg_match("/^\s*Olá\s*,\s*(?<name>{$patterns['travellerName']})[ ]*\n+[ ]*Saldo:\s*(?<balance>\d[,.\'\d ]*)\s*pontos(?:[ ]*\n|\s*$)/iu", $rootText, $m)
        ) {
            /*
                        Olá, Cecilia
                Saldo: 75,515 pontos
                       > Minha Conta
            */
            $name = $m['name'];
            $balance = $this->normalizeAmount($m['balance']);
        }

        if ($this->format === 2) {
            $name = $this->http->FindSingleNode('*[2]', $root, true, "/^Olá[ ]*,[ ]*({$patterns['travellerName']})(?:\s*[:;!?]|$)/u");

            $statusAndNumber = $this->http->FindSingleNode('*[3]', $root);

            if (preg_match("/^TudoAzul(?:[ ]+(?<status>{$patterns['statusVariants']}))?[ ]*nº[ ]*(?<number>[-A-Z\d]{5,})$/i", $statusAndNumber, $m)) {
                // TudoAzul Safira nº 6530478282    |    TudoAzul nº 6180146900
                if (!empty($m['status'])) {
                    $status = $m['status'];
                }
                $number = $m['number'];
            }

            $balanceText = implode(' ', $this->http->FindNodes("*[4]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^Seu saldo em\s*(?<date>.{6,}?)[*:\s]+(?<balance>\d[,.\'\d ]*)\s*pontos(?:\s*[,.:;!?(]|$)/i", $balanceText, $m)) {
                // Seu saldo em 06/12/2019*: 1.000 pontos
                $balanceDate = $this->normalizeDate($m['date']);
                $balance = $this->normalizeAmount($m['balance']);
            }
        }

        if ($this->format === 999
            && preg_match("/^(?<name>{$patterns['travellerName']})\s*,\s*(?:sua categoria é|você é|quer viajar ainda mais)\s*(?<status>{$patterns['statusVariants']})?(?:\s+|\s*[,.:;!?(]|$)/i", $rootText, $m)
        ) {
            // Lucas, sua categoria é TudoAzul    |    Rodrigo, quer viajar ainda mais?
            $name = $m['name'];

            if (!empty($m['status'])) {
                $status = $m['status'];
            }
        }

        if ($this->format === 999
            && preg_match("/\s+e seu saldo é de\s*(?<balance>\d[,.\'\d ]*)\s*pontos?(?:\s*[,.:;!?(]|$)/i", $rootText, $m)
        ) {
            // e seu saldo é de 30.000 pontos.
            $balance = $this->normalizeAmount($m['balance']);
            $balanceDate = $this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Saldo e categoria atualizados em')]/ancestor::tr[1]", null, true, "/Saldo e categoria atualizados em\:?\s*(\d+\/\d+\/\d{4})/"));
        }

        $tables = $this->http->XPath->query("//tr[descendant::img[contains(@src,'/bnn-principal_03.')] and normalize-space()='']/following::tr[normalize-space()][1]/descendant-or-self::tr[ count(*)=7 and *[1][normalize-space()=''] and *[2][normalize-space()] and *[3][normalize-space()=''] ]");

        if ($tables->length === 1) {
            // it-72052942.eml
            $tableRoot = $tables->item(0);
            $balance = $this->normalizeAmount($this->http->FindSingleNode('*[2]', $tableRoot, true, "/^\d[,.\'\d ]*$/"));
            $balanceDate = $this->normalizeDate($this->http->FindSingleNode('*[4]', $tableRoot, true, "/^.{6,}$/"));
        }

        if ($name) {
            $st->addProperty('Name', $name);
        }

        if ($status) {
            $st->addProperty('Status', $status);
        }

        if ($balance !== null) {
            $st->setBalance($balance);

            if ($balanceDate) {
                $st->parseBalanceDate($balanceDate);
            }
        }

        if (!$number) {
            $number = $this->http->FindSingleNode("//tr[not(.//tr) and starts-with(normalize-space(),'Seu número TudoAzul')]", null, true, "/Seu número TudoAzul[:\s]+([-A-Z\d ]{5,})/i");
        }

        if (!$number) {
            $number = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Seu número TudoAzul')]/ancestor::tr[1]", null, true, "/Seu número TudoAzul[:\s]+([-A-Z\d ]{5,})/i");
        }

        if ($number) {
            $st->setNumber($number);
        }

        if ($balance === null && ($name || $status || $number)) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $this->format = 1; // it-72716473.eml
        $nodes = $this->http->XPath->query("//tr/*[not(.//tr) and starts-with(normalize-space(),'Olá') and contains(normalize-space(),'Saldo:') and contains(normalize-space(),'Minha Conta')]");

        if ($nodes->length === 0) {
            $this->format = 2; // it-72898687.eml
            $nodes = $this->http->XPath->query("//tr[ *[2][starts-with(normalize-space(),'Olá')] and *[4][contains(normalize-space(),'pontos')] ]");
        }

        if ($nodes->length === 0) {
            $this->format = 999;
            // Lucas, sua categoria é TudoAzul e seu saldo é de 30.000 pontos.
            // Francisco, você é Diamante e seu saldo é de 166.642 pontos.
            $xpathFirstTopRow = "//tr[not(.//tr) and .//img and normalize-space()='']/following::tr[not(.//tr) and normalize-space()][1]";
            $nodes = $this->http->XPath->query($xpathFirstTopRow . "[contains(normalize-space(),'sua categoria é') or contains(normalize-space(),'você é') or contains(normalize-space(),'quer viajar ainda mais')]");
            $this->logger->error($xpathFirstTopRow . "[contains(normalize-space(),'sua categoria é') or contains(normalize-space(),'você é') or contains(normalize-space(),'quer viajar ainda mais') or contains(normalize-space(),'Onde os sonhos voam')]");
        }

        if ($nodes->length === 0) {
            $this->format = 999;
            // Andrews, sua categoria é TudoAzul e seu saldo é de 0 ponto.
            $nodes = $this->http->XPath->query("//text()[contains(normalize-space(), 'sua categoria é') or contains(normalize-space(), 'e seu saldo é de')]/ancestor::tr[1]");
        }

        return $nodes;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
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

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 26/11/20
            '/^(\d{1,2})\s*\/\s*(\d{1,2})\s*\/\s*(\d{2,4})$/',
        ];
        $out = [
            '$2/$1/$3',
        ];

        return preg_replace($in, $out, $text);
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
