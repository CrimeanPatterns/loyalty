<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers aeroplan/HasBeenCancelled (in favor of aeroplan/FlightCancelled)

class FlightCancelled extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-107881955-fr.eml, aeroplan/it-424546067-es.eml, aeroplan/it-660151384.eml, aeroplan/it-92968000.eml";

    public $lang = '';

    public $detectLang = [
        'en' => [
            'Your booking has been cancelled',
            'Your payment is still being processed',
            'prevents us from displaying the details in this email',
        ],
        'fr' => [
            'Votre réservation a été annulée',
            'Votre paiement est en cours de traitement',
            "problème technique nous empêche d'afficher les détails dans ce courriel",
        ],
        'es' => [
            'Se ha cancelado su reserva',
        ],
    ];

    public static $dictionary = [
        "en" => [
            'statusPhrases'                   => ['Your booking has been', 'Your booking is'],
            'statusVariants'                  => ['confirmed', 'cancelled', 'canceled', 'completed'],
            'Booking reference:'              => [
                'Booking reference:', 'Booking reference :',
                'Booking Reference:', 'Booking Reference :',
            ],
            'Ticket no.:'                     => ['Ticket no.:', 'Ticket #:'],
        ],
        "fr" => [
            'statusPhrases'                   => ['Votre réservation a été', 'Votre réservation est'],
            'statusVariants'                  => ['confirmée', 'annulée', 'effectuée'],
            'Your booking has been cancelled' => 'Votre réservation a été annulée',
            'Purchase summary'                => 'Sommaire de l\'achat',
            'Total amount paid'               => 'Tarif total payé',
            'account no.'                     => 'Aéroplan numéro',
            'Booking reference:'              => 'Numéro de réservation:',
            'Ticket no.:'                     => ['Nº de billet :', 'Billet #:'],
        ],
        "es" => [
            // 'statusPhrases' => [''],
            'statusVariants'                  => ['cancelado'],
            'Your booking has been cancelled' => 'Se ha cancelado su reserva',
            'Purchase summary'                => 'Resumen de la compra',
            'Total amount paid'               => 'Tarifa total pagada',
            // 'account no.' => '',
            'Booking reference:'              => 'Código de reserva:',
            'Ticket no.:'                     => ['Boleto #:', 'Boleto # :'],
        ],
    ];

    /*
        [EXTERNAL] Air Canada - 06 Sep 2023: Toronto - Dublin (Booking Reference: 2W9UPZ) - Your booking has been cancelled
            or
        Air Canada - 11 juil. 2023: Pereira - Montréal (Numéro de réservation: 22X6GE)
    */
    private $mainPattern = "/^(?<operator>.{2,}?)\s*-\s*(?<date>\d{1,2}[.\s]*[[:alpha:]]+[.\s]*\d{2,4})\s*:\s*(?<depName>\D{3,}?)\s*-\s*(?<arrName>\D{3,}?)\s*\(/u";

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'aircanada.ca') !== false) {
            return preg_match($this->mainPattern, $headers['subject']) > 0;
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//a[contains(@href, 'www.aircanada.com')]")->length > 0) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Your booking has been cancelled'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Purchase summary'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aircanada\.ca$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference:'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference:'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        } elseif (preg_match("/\(\s*({$this->opt($this->t('Booking reference:'))})[:\s]*([A-Z\d]{5,})\s*\)/u", $parser->getSubject(), $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        }

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Booking reference:'))}]/ancestor::table[1]/descendant::tr[not({$this->contains($this->t('Date'))})]/*[1]", null, "/^{$patterns['travellerName']}$/u"));

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, true);
        }
        $infants = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Booking reference:'))}]/ancestor::table[1]/descendant::tr[not({$this->contains($this->t('Date'))})]/*[1]", null,
            "/^\s*({$patterns['travellerName']})\s*{$this->opt($this->t('Infant'))}\s*\(/u"));

        if (count($infants) > 0) {
            $f->general()->infants($infants, true);
        }

        $tickets = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Booking reference:'))}]/ancestor::table[1]/descendant::tr[not({$this->contains($this->t('Date'))})]/*[2]", null, "/{$this->opt($this->t('Ticket no.:'))}[:\s]*({$patterns['eTicket']})$/"));

        if (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
        }

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Your booking has been cancelled'))}]")->length > 0) {
            $f->general()
                ->cancelled()
                ->status('cancelled');
        }

        $totalText = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total amount paid'))}]/ancestor::tr[1]/td[2]");

        if (preg_match("/(?:^|[+])\s*(?<currency>[A-Z]+\s*[$]|[A-Z]{3})\s*(?<amount>\d[,.‘\'\d ]*)\s*$/u", $totalText, $matches)) {
            // 139,000 pts + CA $234.24    |    CA $234.24
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $f->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        if (preg_match("/^\s*(?<points>\d[,\d ]*?)\s*pts\s*(?:[+]|$)/i", $totalText, $matches)) {
            // 139,000 pts + CA $234.24
            $f->price()->spentAwards($matches['points']);
        }

        if (preg_match($this->mainPattern, $parser->getSubject(), $m)) {
            if ($m['depName'] !== $m['arrName']) {
                $s = $f->addSegment();

                if (preg_match("/(?:[\]}:][ ]*|^)(\w.+\w)$/", $m['operator'], $m2)) {
                    $s->airline()->operator($m2[1]);
                }

                $s->airline()->noName()->noNumber();

                $date = $this->normalizeDate($m['date']);

                $s->departure()
                    ->noCode()
                    ->name($m['depName'])
                    ->noDate()
                    ->day($date);

                $s->arrival()
                    ->noCode()
                    ->name($m['arrName'])
                    ->noDate();
            }
        }

        $account = $this->http->FindSingleNode("//text()[{$this->contains($this->t('account no.'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('account no.'))}\s*\•+(\d+)/u");

        if (!empty($account)) {
            $f->program()->account('**' . $account, true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeCurrency(string $string): ?string
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'CAD' => ['CA $'],
            'USD' => ['US $'],
            'TWD' => ['TW $'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//*[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
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

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }

    private function normalizeDate(string $date)
    {
        $in = [
            // 11 juil. 2023
            "/^\s*(\d{1,2})[.\s]*([[:alpha:]]+)[.\s]*(\d{2,4})\s*$/u",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("/\d\s+([[:alpha:]]+)\s+\d/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
