<?php

namespace AwardWallet\Engine\alamo\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class AlamoReservation extends \TAccountChecker
{
    public $mailFiles = "alamo/it-1.eml, alamo/it-17233847.eml, alamo/it-1728488.eml, alamo/it-1826249.eml, alamo/it-1975896.eml, alamo/it-1984986.eml, alamo/it-2631874.eml, alamo/it-2639112.eml, alamo/it-2639455.eml, alamo/it-2656174.eml, alamo/it-2694150.eml, alamo/it-2695056.eml, alamo/it-2926076.eml, alamo/it-2926109.eml, alamo/it-96636908.eml, alamo/it-119977132.eml";

    public $reFrom = ["goalamo.com", 'alamoargentina.com.ar'];
    public $reBody = [
        'es' => ['Recogida y devolución'],
        'pt' => ['Retirada e Devolver'],
        'en' => ['Your Vehicle & Add-Ons', 'Your Vehicle and Extras', 'Pickup & Return', 'Pickup & Drop Off'],
    ];
    public $reSubject = [
        'Alamo Reservation Confirmation',
        'Confirmación de la reservación de Alamo', 'Confirmación de Reserva', 'Confirmação de reserva',
        'Alamo Skip the Counter Confirmation',
        'Alamo Reservation Cancellation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Pickup'            => ['Pickup', 'Pick-up'],
            'Dropoff'           => ['Drop Off', 'Return'],
            'Car Summary'       => ['Car Summary', 'SFDR'],
            'Driver Name'       => ['Driver Name', 'Renter Name:'],
            'Estimated Total:'  => ['Estimated Total:', 'Your Amount Due*:'],
            'regConfNo'         => 'Your\s+Confirmation\s+Number',
            '/regConfNoSubj/'   => '/Alamo\s+Reservation\s+(?:Confirmation|Cancell?ation)(?:\s+for\s+Reservation)?\s+(?-i)([-A-Z\d]{5,})(?:\s*[,.;:!?(]|$)/i',
            'statusPhrases'     => ['your online check-in with Alamo has been'],
            'statusVariants'    => ['confirmed'],
            '/regStatusCancel/' => '#(?:Alamo \w+ Cancellation|Alamo \w+ Cancelation|Your\s+Cancell?ed\s+Reservation)#i',
        ],
        'es' => [ // it-96636908.eml
            'Pickup'           => ['Recogida'],
            'Dropoff'          => ['Devolución'],
            'Car Summary'      => ['Resumen del automóvil', 'Automóvil'],
            'Driver Name'      => 'Nombre del conductor',
            // 'Alamo Insider Number' => '',
            'Estimated Total:' => ['Estimated Total:', 'Total estimado:'],
            'regConfNo'        => 'Su\s+número\s+de\s+confirmación',
            '/regConfNoSubj/'  => '/Confirmación\s+de\s+Reserva\s+(?-i)([-A-Z\d]{5,})(?:\s*[,.;:!?(]|$)/i',
            // 'statusPhrases' => [''],
            // 'statusVariants' => [''],
            // '/regStatusCancel/' => '//i',
        ],
        'pt' => [ // it-119977132.eml
            'Pickup'               => ['Retirada'],
            'Dropoff'              => ['Devolver'],
            'Car Summary'          => ['Resumo do automóvel'],
            'Driver Name'          => 'Nome do motorista',
            'Alamo Insider Number' => 'Nº Alamo Insiders',
            'Estimated Total:'     => ['Total Estimado:'],
            'regConfNo'            => 'Seu\s+número\s+de\s+confirmação',
            '/regConfNoSubj/'      => '/Confirmação\s+de\s+reserva\s+na\s+Alamo\s+(?-i)([-A-Z\d]{5,})(?i)(?:\s+de\s+|\s*[,.;:!?(]|$)/i',
            // 'statusPhrases' => [''],
            // 'statusVariants' => [''],
            '/regStatusCancel/' => '/Cancelamento\s+da\s+reserva\s+Alamo\s+\w+/i',
        ],
    ];
    private $keywordProv = 'Alamo';
    private $subject;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        if (empty($parser->getHTMLBody())) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($parser->getHTMLBody())) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        if ($this->http->XPath->query("//img[@alt='Alamo' or contains(@src,'.alamo.com')] | //a[contains(@href,'.alamo.com/') or contains(@href,'www.alamo.com') or contains(@href,'alamoargentina.com.ar')]")->length > 0) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || $this->detectEmailFromProvider($headers['from']) !== true) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
            }
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

    private function parseEmail(Email $email): void
    {
        $r = $email->add()->rental();

        $text = text($this->http->Response['body']);

        if (stripos($this->subject, "Alamo") !== false) {
            $subj = $this->subject;
        } else {
            $subj = $text;
        }

        if (preg_match($this->t('/regConfNoSubj/'), $subj, $m)) {
            $confNo = $m[1];
        } elseif (preg_match("/{$this->t('regConfNo')}\s*(?:\D|is)\s*([-A-Z\d]{4,})\n/i", $text, $m)) {
            $confNo = $m[1];
        }

        if (isset($confNo)) {
            $r->general()->confirmation($confNo);
        }

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Driver Name'))}]/following::text()[normalize-space()!=''][1]");

        if (!empty($traveller)) {
            $r->general()
                ->traveller($traveller);
        }

        $account = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Alamo Insider Number'))}]/following::text()[normalize-space()!=''][1]");

        if (!empty($account)) {
            $r->program()->account($account, false);
        }

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[.,;:!?]|$)/");

        if ($status) {
            $r->general()->status($status);
        }

        if ($this->http->FindPreg($this->t('/regStatusCancel/'), false, $subj)) {
            $r->general()
                ->status('Cancelled')
                ->cancelled();
        }

        $keys = ['Pickup' => $r->pickup(), 'Dropoff' => $r->dropoff()];

        foreach ($keys as $key => $value) {
            $subj = implode("\n", $this->http->FindNodes("descendant::td[({$this->contains($this->t($key))}) and not(.//tr) and contains(.,':')][1]/descendant::text()[normalize-space()]"));
            $pattern = "/"
                . "\b(?<date>[[:alpha:]]{3,}\s+\d{1,2}\s*,\s*\d{4}|\d{1,2}(?:\s+de)?\s+[[:alpha:]]{3,}(?:\s+de)?[,\s]+\d{4})"
                . "(?:\s+a las)?\s+(?<time>\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?)"
                . "[.\s]*(?<location>[\s\S]{3,})"
                . "/u";

            if (preg_match($pattern, $subj, $m)) {
                $date = strtotime($this->dateStringToEnglish($this->normalizeDate($m['date'])) . ' ' . $m['time']);
                $value->date($date);
                $value->location(preg_replace('/\s+/', ' ', $m['location']));
            }
        }

        $subj = implode("\n",
            $this->http->FindNodes("//text()[({$this->contains($this->t('Car Summary'))})]/ancestor::td[1]//text()[normalize-space()!='']"));

        if (preg_match("#{$this->opt($this->t('Car Summary'))}\s+(?<type>\S[\s\S]+?)\s*\n\s*(?<model>\S.+?\s*\(?\s*{$this->opt($this->t('or similar'))}.*)#iu",
            $subj, $m)
        || preg_match("/^(?<type>.+\s*\n[A-Z]{3,4})\n(?<model>.+\n\(?or similar\)?)/", $subj, $m)) {
            $r->car()
                ->type(preg_replace("#\s+#", ' ', trim($m['type'])))
                ->model(preg_replace("#\s+#", ' ', trim($m['model'])));
        }

        $r->car()
            ->image($this->http->FindSingleNode("//text()[({$this->contains($this->t('Car Summary'))})]/ancestor::td[1]/ancestor::td[1]/preceding-sibling::td[1]//img/@src[contains(.,\"/\")]"),
                false, true);

        $total = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Estimated Total:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1])[1]");

        if ($total !== null) {
            $total = $this->getTotalCurrency($total);
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Pickup'], $words['Car Summary'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Pickup'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Car Summary'])}]")->length > 0
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

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
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
            // 23 April, 2022    |    27 de Junio de 2021
            '/^(\d{1,2})(?:\s+de)?\s+([[:alpha:]]{3,})(?:\s+de)?[,\s]+(\d{4})$/u',
        ];
        $out = [
            '$1 $2 $3',
        ];

        return preg_replace($in, $out, $text);
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace(["د.إ.‏", "ARS$", "R$", "€", "£", "₹"], ["AED", "ARS", "BRL", "EUR", "GBP", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+)$/', $node, $m)
            || preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $node, $m)
        ) {
            $cur = $m['currency'];
            $tot = PriceHelper::cost($m['amount']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
