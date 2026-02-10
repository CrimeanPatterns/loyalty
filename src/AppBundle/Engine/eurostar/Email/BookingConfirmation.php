<?php

namespace AwardWallet\Engine\eurostar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "eurostar/it-634395085.eml";

    public $detectFrom = "noreply@eurostar.com";
    public $detectSubject = [
        // en
        'Booking confirmation | ',
        // fr
        'Confirmation de votre réservation | ',
        // no
        'Boekingsbevestiging | ',
        // de
        'Buchungsbestätigung | ',
    ];
    public $detectBody = [
        'en' => [
            'Your reservation is confirmed.',
        ],
        'fr' => [
            'Nous confirmons la modification de votre réservation',
        ],
        'nl' => [
            'Je boeking is bevestigd.',
        ],
        'de' => [
            'Ihre Buchung wurde bestätigt.',
        ],
    ];

    public $lang;
    public static $dictionary = [
        'en' => [
            'Booking Reference:'  => ['Booking Reference:', 'Booking reference:'],
            'routeName'           => ['Outbound', 'Return'],
            'Coach'               => 'Coach',
            'statusPhrases'       => ['Your reservation is', 'Your journey has'],
            'statusVariants'      => ['confirmed', 'changed'],
            // 'Seat'  => '',
            // 'Total paid'  => '',
        ],
        'fr' => [
            'Booking Reference:'  => 'Référence de réservation:',
            'routeName'           => ['Aller', 'Retour'],
            'Coach'               => 'Voiture',
            'statusPhrases'       => ['Votre réservation est'],
            'statusVariants'      => ['confirmée'],
            'Seat'                => 'Place',
            'Total paid'          => 'Total payé',
        ],
        'nl' => [
            'Booking Reference:'  => 'Boekingsreferentie:',
            'routeName'           => ['Heen', 'Terug'],
            'Coach'               => 'Rijtuig',
            'statusPhrases'       => ['je boeking is'],
            'statusVariants'      => ['bevestigd'],
            'Seat'                => 'Zitplaats',
            'Total paid'          => 'Totaal betaald',
        ],
        'de' => [
            'Booking Reference:'  => 'Buchungsreferenz:',
            'routeName'           => ['Hinreise', 'Rückreise'],
            'Coach'               => 'Wagen',
            'statusPhrases'       => ['Ihre Buchung wurde', 'Ihre Buchungen wurden'],
            'statusVariants'      => ['bestätigt'],
            'Seat'                => 'Sitzplatz',
            'Total paid'          => 'Insgesamt bezahlt',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]eurostar\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['.eurostar.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Eurostar International Limited'])}]")->length === 0
        ) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $this->parseEmailHtml($email);

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

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (
                !empty($dict["Booking Reference:"]) && $this->http->XPath->query("//*[{$this->contains($dict['Booking Reference:'])}]")->length > 0
                && !empty($dict["Coach"]) && $this->http->XPath->query("//*[{$this->contains($dict['Coach'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email): void
    {
        $xpathTime = 'starts-with(translate(normalize-space(),"0123456789","dddddddddd"),"dd:dd")';
        $t = $email->add()->train();

        // General
        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}(?:\s+been)?[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $t->general()->status($status);
        }

        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference:'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,})\s*$/"))
            ->travellers(array_unique($this->http->FindNodes("//*[count(.//td[not(.//td)][normalize-space()]) = 2][descendant::td[not(.//td)][normalize-space()][2][{$this->starts($this->t('Coach'))}]]/descendant::td[not(.//td)][normalize-space()][1]")), true)
        ;

        // Price
        $total = $this->http->FindSingleNode("//*[{$this->eq($this->t('Total paid'))}]/following::text()[normalize-space()][1]/ancestor::*[self::td or self::div][1]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $currency = $this->currency($m['currency']);
            $t->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
        }

        // Segments
        $xpath = "//text()[{$xpathTime}]/ancestor::*[.//text()[{$this->eq($this->t('routeName'))}]][1][count(.//text()[{$xpathTime}]) = 2]";
        // $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            $node = '';

            if ($this->http->XPath->query("descendant::text()[normalize-space()][not(ancestor::div/ancestor::td[not(.//td)])]", $root)->length == 0) {
                $node = implode("\n", $this->http->FindNodes("descendant::div[not(.//div)][normalize-space()]", $root));
            } else {
                $node = implode("\n", $this->http->FindNodes("descendant::td[not(.//td)][normalize-space()]", $root));
            }
            $re = "/^.+(?<cabin>\n.+)?\n(?<date>.*\d{4}.*)\n(?<dName>.+)\n(?<aName>.+)\n(?<dTime>\d{1,2}:\d{2}\d{0,5})\n(?<aTime>\d{1,2}:\d{2}\d{0,5})\n(?<duration>\d+ ?hrs \d+ ?mins)\b/";

            if (preg_match($re, $node, $m)) {
                $date = $this->normalizeDate($m['date']);

                // Departure
                $s->departure()
                    ->name($m['dName'] . ', Europe')
                    ->date($date ? strtotime($m['dTime'], $date) : null)
                ;

                // Arrival
                $s->arrival()
                    ->name($m['aName'] . ', Europe')
                    ->date($date ? strtotime($m['aTime'], $date) : null)
                ;

                $s->extra()
                    ->noNumber()
                    ->duration($m['duration'])
                ;
            }

            $coach = array_unique($this->http->FindNodes(".//td[not(.//td)][{$this->starts($this->t('Coach'))}]", $root, "/^\s*{$this->opt($this->t('Coach'))}\s+(\w+)\s*-\s*{$this->opt($this->t('Seat'))}/"));
            $s->extra()
                ->car(implode(', ', $coach));

            $seats = array_unique($this->http->FindNodes(".//td[not(.//td)][{$this->starts($this->t('Coach'))}]", $root, "/^\s*{$this->opt($this->t('Coach'))}\s+\w+\s*-\s*{$this->opt($this->t('Seat'))}\s+(\w+)\s*$/"));
            $s->extra()
                ->seats($seats);

            if (!empty($m['cabin'])) {
                $s->extra()
                    ->cabin($m['cabin']);
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods
    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date)
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        $in = [
            // mercredi, 29 novembre 2023
            "/^\s*[[:alpha:]]+\s*\,\s*(\d+)\s+([[:alpha:]]+)\s+(\d{4})\s*$/iu",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $date = $m[1] . $en . $m[3];
            }
        }
        // $this->logger->debug('$str = '.print_r( $date,true));

        if (preg_match("/\b\d{4}\b/", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function opt($field, $delimiter = '/'): string
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function currency($s): ?string
    {
        if ($code = $this->re("#\b([A-Z]{3})\b$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
