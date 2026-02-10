<?php

namespace AwardWallet\Engine\aegean\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Upgrade extends \TAccountChecker
{
    public $mailFiles = "aegean/it-120026413.eml";

    public $detectSubject = [
        // en
        'Interested in an upgrade?',
        'Get Upgraded on your Aegean Airlines flight!',
        'Aegean Airlines - Upgrade Request',
        'Aegean Airlines- Upgrade Request',
        "You've been upgraded!",
        ': Unable to Upgrade',
        // el
        'Έχετε αναβαθμιστεί!',
        'Η πτήση σας για Athens πλησιάζει! Αναβαθμιστείτε σήμερα',
        // pt
        'A sua licitação relativa ao seu voo foi ultrapassada!',
        // it
        'questa è l\'ultima possibilità per avere un upgrade sul Suo volo Aegean Airlines!',
        // ru
        'Повысьте категорию своего билета на рейс Aegean Airlines!',
        // de
        'Holen Sie sich noch heute ein Upgrade',
    ];
    public $lang = '';
    public static $dict = [
        'el' => [
            'TRAVEL DATE'        => ['ΗΜΕΡΟΜΗΝΙΑ', 'ΗΜΕΡΑ ΤΑΞΙΔΙΟΥ'],
            'FLIGHT'             => 'ΠΤΗΣΗ',
            'Booking Reference:' => 'Κωδικός Κράτησης:',
        ],
        'pt' => [
            'TRAVEL DATE'          => 'Data',
            'FLIGHT'               => 'Flight',
            'Booking Reference:'   => 'Código de reserva:',
        ],
        'it' => [
            'TRAVEL DATE'          => 'DATA DI VIAGGIO',
            'FLIGHT'               => 'VOLO',
            'Booking Reference:'   => 'Numero di prenotazione:',
        ],
        'ru' => [
            'TRAVEL DATE'          => 'ДАТА ПОЕЗДКИ',
            'FLIGHT'               => 'РЕЙС',
            'Booking Reference:'   => 'Номер брони:',
        ],
        'de' => [
            'TRAVEL DATE'          => 'REISEDATUM',
            'FLIGHT'               => 'FLUG',
            'Booking Reference:'   => 'Buchungsreferenz:',
        ],
        'en' => [ // always last!
            'TRAVEL DATE' => ['TRAVEL DATE', 'DATE', 'Date'],
            'FLIGHT'      => ['FLIGHT', 'Flight'],
            // 'Booking Reference:' => '',
        ],
    ];

    /*
    public $detectBody = [
        'en' => [
            'Don\'t miss out on your chance to upgrade your ',
            'One or more of your upcoming flights are eligible for an upgrade to',
            'Per your request, your upgrade offer for',
            'and your flight has been upgraded to',
            'Your upcoming flight is eligible for an upgrade',
            'If you are not upgraded, you will keep the original ticket',
            'Unable to Upgrade',
            'Please note that your flight has changed and your Aegean Upgrade Challenge offer is not valid anymore',
        ],
        'el' => [
            'Η προσφορά σας έγινε δεκτή και η πτήση σας αναβαθμίστηκε σε',
            'Σε περίπτωση αναβάθμισης',
            'Η πτήση σας είναι υποψήφια για αναβάθμιση',
        ],
        'it' => [
            'Il Suo prossimo volo è idoneo per un upgrade.',
        ],
        'ru' => [
            'ПОВЫСЬТЕ КАТЕГОРИЮ СВОЕГО БИЛЕТА!',
            'Μια ή περισσότερες πτήσεις σας είναι υποψήφιες για αναβάθμιση σε',
            'Μην χάσετε την ευκαιρία να αναβαθμίσετε το',
        ],
        'de' => [
            'FÜHREN WIR IHR UPGRADE DURCH!',
        ],
    ];
    */

    private $providerCode = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language and Format
        return $this->assignLang() && $this->findSegments()->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aegeanair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || $this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->detectSubject as $detectSubject) {
                if (stripos($headers["subject"], $detectSubject) !== false) {
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

    public static function getEmailProviders()
    {
        return ['tapportugal', 'aegean'];
    }

    private function findSegments(array &$columns = []): \DOMNodeList
    {
        $xpath = "//tr[*[1][{$this->contains($this->t('TRAVEL DATE'))}] and *[2][{$this->contains($this->t('FLIGHT'))}]]/following::tr[1]/ancestor::*[1]/tr[normalize-space()]";
        $nodes = $this->http->XPath->query($xpath);
        $columns = [
            'date'      => 1,
            'flight'    => 2,
            'departure' => 3,
            'arrival'   => 4,
            'status'    => 6,
        ];

        if ($nodes->length === 0) {
            $xpath = "//tr[*[4][{$this->contains($this->t('TRAVEL DATE'))}] and *[1][{$this->contains($this->t('FLIGHT'))}]]/following::tr[1]/ancestor::*[1]/tr[normalize-space()]";
            $nodes = $this->http->XPath->query($xpath);
            $columns = [
                'date'      => 4,
                'flight'    => 1,
                'departure' => 2,
                'arrival'   => 3,
            ];
        }

        $this->logger->debug('Segments root: ' . $xpath);

        return $nodes;
    }

    private function parseEmail(Email $email): void
    {
        $r = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference:'))}]/following::text()[normalize-space()!=''][1]",
            null, true, "/^\s*([A-Z\d]{5,7})\s*$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference:'))}][1]",
                null, true, "/:(?:\s*\{\d*\})?\s*([A-Z\d]{5,7})\s*$/");
        }

        if (empty($conf) && empty($this->http->FindSingleNode("(//*[{$this->contains(preg_replace('/\W*$/', '', $this->t('Booking Reference:')))}])[1]"))) {
            $r->general()
                ->noConfirmation();
        } else {
            $r->general()
                ->confirmation($conf);
        }
//            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference'))}]/following::text()[normalize-space()!=''][1]"))
//            ->travellers($this->http->FindNodes("//tr[({$this->eq($this->t('Passengers'))}) and not(.//tr)]/following-sibling::tr[1]/td[1]/descendant::text()[normalize-space(.)!='']"));

        $columns = [];
        $segments = $this->findSegments($columns);

        foreach ($segments as $root) {
            $s = $r->addSegment();

            $airline = $this->http->FindSingleNode('*[' . $columns['flight'] . ']', $root);

            if (preg_match('/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s+(\d{1,5})\s*$/', $airline, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            $s->departure()
                ->code($this->http->FindSingleNode('*[' . $columns['departure'] . ']', $root, true, "/.+\(([A-Z]{3})\)\s*$/"))
                ->name($this->http->FindSingleNode('*[' . $columns['departure'] . ']', $root, true, "/(.+?)\s*\([A-Z]{3}\)\s*$/"))
            ;

            $dateVal = $this->http->FindSingleNode('*[' . $columns['date'] . ']', $root);
            $date = $this->normalizeDate($dateVal);

            if (preg_match("/\b\d{1,2}:\d{2}(?:\b|\D)/", $dateVal)) {
                $s->departure()->date($date);
            } else {
                $s->departure()->day($date)->noDate();
            }

            $s->arrival()
                ->code($this->http->FindSingleNode('*[' . $columns['arrival'] . ']', $root, true, "/.+\(([A-Z]{3})\)\s*$/"))
                ->name($this->http->FindSingleNode('*[' . $columns['arrival'] . ']', $root, true, "/(.+?)\s*\([A-Z]{3}\)\s*$/"))
                ->noDate()
            ;

            if (isset($columns['status'])) {
                $status = $this->http->FindSingleNode('*[' . $columns['status'] . ']', $root);

                if ($status == 'Cancelled') {
                    $r->general()
                        ->status($status)
                        ->cancelled();
                }
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@flytap.com') !== false
            || strpos($headers['subject'], 'TP Flight') !== false
            || $this->http->XPath->query('//a[normalize-space()="www.flytap.com"]')->length > 0
        ) {
            $this->providerCode = 'tapportugal';

            return true;
        }

        if (stripos($headers['from'], '@aegeanair.com') !== false
            || strpos($headers['subject'], 'Aegean Airlines') !== false
            || $this->http->XPath->query(
                "//text()[starts-with(normalize-space(),'©') and contains(normalize-space(),'Aegean Airlines')]"
                . " | //a[contains(@href,'.aegeanair.com/')]"
                . " | //img[contains(@src,'.aegeanair.com') or contains(@alt,'Aegean Airlines')]"
                . " | //*[{$this->contains(['https://aegeanairlines.custhelp', 'Best regards,Aegean Airlines', 'Cordiali saluti,Aegean Airlines'])}]"
            )->length > 0
        ) {
            $this->providerCode = 'aegean';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['TRAVEL DATE'])) {
                if ($this->http->XPath->query("//tr/*[{$this->eq($words['TRAVEL DATE'])}]")->length > 0) {
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

    private function normalizeDate($str)
    {
        $in = [
            //            "#^\s*(\d{2})/([^\W\d]+)/(\d{4})\s*$#", // 14/Oct/2019
            //            "#^\s*(\d{2})/(\d{2})/(\d{4})\s*$#", // 04/05/2019
        ];

        $out = [
            //            '$1 $2 $3',
            //            '$2.$1.$3',
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
