<?php

namespace AwardWallet\Engine\azul\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\azul\Email\Reserva as SubjectPatterns;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelReservation extends \TAccountChecker
{
    public $mailFiles = "azul/it-697110237.eml";

    public $lang = 'pt';
    public $date;

    public static $dictionary = [
        "pt" => [
            'confNumber'           => ['Seu código de reserva é:', 'Reservation code:', 'Código da Reserva:'],
            'Seu Hotel em'         => ['Seu Hotel em'],
            'totalPrice'           => ['Total da reserva'],
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
        if ($this->http->XPath->query("//node()[contains(normalize-space(), 'Azul Linhas Aéreas')]")->length > 0) {
            return $this->http->XPath->query("//*[{$this->contains($this->t('confNumber'))}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($this->t('Seu Hotel em'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]news\-voeazul\.com(?:\.br)?$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('HotelReservation' . ucfirst($this->lang));

        $this->ParseHotel($email, $parser->getSubject());

        return $email;
    }

    public function ParseHotel(Email $email, string $emailSubject): void
    {
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]",
            null, true, '/^[A-Z\d]{5,}$/'));

        $hXpath = "//text()[{$this->starts($this->t('Seu Hotel em'))}]/ancestor::*[{$this->contains($this->t('Check-In'))}][1]";
        $hNodes = $this->http->XPath->query($hXpath);

        foreach ($hNodes as $root) {
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->noConfirmation();

            foreach (SubjectPatterns::$reSubject as $sPattern) {
                if (preg_match($sPattern, $emailSubject, $m) && !empty($m['status'])) {
                    $h->general()->status($m['status']);

                    break;
                }
            }

            $travellers = [];
            $passengerRows = $this->http->XPath->query("following::tr[{$this->eq($this->t('Viajante'))}][following-sibling::tr[normalize-space()]][1]/following-sibling::tr[normalize-space()]", $root);

            foreach ($passengerRows as $pRow) {
                $values = $this->http->FindNodes("descendant::td[not(.//td)][normalize-space()]", $pRow);

                if (preg_match('/^\s*[A-Z]{2}\s*$$/', $values[0] ?? '')) {
                    $travellers[] = $values[1] ?? '';
                }
            }

            $h->general()
                ->travellers($travellers);

            // Hotel
            $name1 = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Seu Hotel em'))}]/following::text()[normalize-space()][1]", $root);
            $name2 = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Seu Hotel em'))}]", $root, true, "/{$this->opt($this->t('Seu Hotel em'))}\s*(.+)/");
            $h->hotel()
                ->name((!empty($name1) && !empty($name2)) ? $name1 . ', ' . $name2 : null)
                ->noAddress();

            // Booked
            $date = strtotime(preg_replace("#^\s*(\d+)/(\d+)/(\d{4})\s*$#", '$1.$2.$3',
                $this->http->FindSingleNode(".//tr[{$this->eq($this->t('Check-In'))}]/following-sibling::tr[1]", $root)));
            $time = $this->http->FindSingleNode(".//tr[{$this->eq($this->t('Check-In'))}]/following-sibling::tr[2]", $root,
                true, "/^\s*\D*\b(\d{1,2}:\d{2}(?: *[ap]m)?)\b\D*\s*$/i");

            if (!empty($date) && !empty($time)) {
                $date = strtotime($time, $date);
            }
            $h->booked()
                ->checkIn($date)
            ;
            $date = strtotime(preg_replace("#^\s*(\d+)/(\d+)/(\d{4})\s*$#", '$1.$2.$3',
                $this->http->FindSingleNode(".//tr[{$this->eq($this->t('Check-Out'))}]/following-sibling::tr[1]", $root)));
            $time = $this->http->FindSingleNode(".//tr[{$this->eq($this->t('Check-Out'))}]/following-sibling::tr[2]", $root,
                true, "/^\s*\D*\b(\d{1,2}:\d{2}(?: *[ap]m)?)\b\D*\s*$/i");

            if (!empty($date) && !empty($time)) {
                $date = strtotime($time, $date);
            }
            $h->booked()
                ->checkOut($date)
            ;
        }
        // Price
        $price = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalPrice'))}] ]/*[normalize-space()][2]");

        if (preg_match("/^(?<points>\d[,.\'\d ]*?)[ ]*{$this->opt($this->t('pontos'))}[ ]*[+]+[ ]*(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/",
                $price, $matches)
            || preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $price, $matches)
        ) {
            // R$ 3.468,52    |    14.000 pontos + R$ 62,23
            if (!empty($matches['points'])) {
                $email->price()->spentAwards($matches['points']);
            }

            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()
                ->currency($currency)
                ->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $statementText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/preceding::text()[contains(normalize-space(), 'pontos')]/ancestor::tr[1]");

        if (count($email->getItineraries()) > 0
            && preg_match("/^(?<name>\D+)\,\s*você é\s*(?<status>\D+)\s*e seu saldo é de\s*(?<balance>[\d\,\.]+)\s*pontos\.\s*$/u", $statementText, $m)
        ) {
            $st = $email->add()->statement();
            $st->addProperty('Name', trim($m['name'], ','));
            $st->addProperty('Status', trim($m['status'], ','));
            $st->setBalance(str_replace(['.'], '', $m['balance']));
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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
