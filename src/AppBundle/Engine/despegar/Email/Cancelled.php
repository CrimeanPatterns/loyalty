<?php

namespace AwardWallet\Engine\despegar\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Cancelled extends \TAccountChecker
{
    public $mailFiles = "despegar/it-41101025.eml, despegar/it-56062054.eml, despegar/it-56488518.eml, despegar/it-56593679.eml, despegar/it-57049545.eml, despegar/it-57971327.eml, despegar/it-58100698.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'otaConf'                       => ['Reserva'],
            'hello'                         => ['Hola'],
            'cancellationPhrases->hotel'    => ['De acuerdo con tu pedido, cancelamos tu reserva de hospedaje en'],
            'cancellationPhrases->transfer' => [
                ' de tu traslado en ',
                'tu reserva de servicio de Traslado Privado en',
                'tu reserva de servicio de Traslado Compartido en',
            ],
            // Rental
            'cancellationPhrases->rental' => ['Hemos anulado su solicitud de reserva nro.'],
            'rentalPreg'                  => ['/alquiler de un auto (.+?) en (.+?)\./'],
            'subjectRental'               => ['de tu auto'],

            // Event
            'cancellationPhrases->event' => [
                ['Te informamos que recibimos la solicitud de cancelación', 'de tu excursión'],
                ['Te informamos que', 'tu reserva de excursión '],
            ],
            'subjectEvent' => ['de excursion'],
            'eventPreg'    => ['/de tu excursión (.+?\)) \(/i', '/de excursión (.+?) nro\./i'],

            'cancellationPhrases->flight' => ['El equipo de Despegar'],
            'confNumber'                  => ['nro.', 'cancelar tu reserva número', 'cancelación'],

            'cancellationPhrases->common' => ['vamos a cancelar tu reserva número'],
        ],
    ];

    private $detectors = [
        'es' => [
            'De acuerdo con tu pedido, cancelamos tu reserva de hospedaje en', // hotel
            'Te informamos que recibimos la solicitud de cancelación', // transfer, // event
            'tu reserva de excursión ', // event
            'tu reserva de servicio de Traslado Privado en ', // transfer
            'tu reserva de servicio de Traslado Compartido en ', // transfer
            ' correspondiente al alquiler de un auto ', // rental
            //'De acuerdo a la solicitud que generaste, vamos a cancelar tu reserva número', // flight ?
        ],
    ];

    private $subject;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->subject = $parser->getSubject();

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseEmail($email, $parser->getSubject());

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return self::detectEmailFromProvider($headers['from']) === true
            && (preg_match('/\b(fue cancelado|fue anulada|fue cancelada)\b/iu', $headers['subject']) > 0
                || strpos($headers['subject'], 'La solicitud de cancelación de tu') !== false);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".despegar.com/") or contains(@href,".despegar.com.co/") or contains(@href,"www.despegar.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"www.despegar.com") or contains(.,"@despegar.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]despegar\.com/i', $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function parseEmail(Email $email, string $subject): void
    {
        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reserva'))}]/following-sibling::node()[normalize-space()][1]", null, true, '/^\d{5,}$/');
        $email->ota()->confirmation($otaConfirmation);

        $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('hello'))}][1]", null, true, "/{$this->opt($this->t('hello'))},?\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u");

        $xpathH = "descendant::text()[{$this->contains($this->t('cancellationPhrases->hotel'))}][1]";

        $xpathCommon = "descendant::text()[{$this->contains($this->t('cancellationPhrases->common'))}][1]";
        $commonCount = $this->http->XPath->query($xpathCommon)->length;

        if ($this->http->XPath->query($xpathH)->length > 0) {
            // it-41101025.eml (hotel)

            $h = $email->add()->hotel();

            $h->general()
                ->traveller($traveller)
                ->confirmation(preg_match("/{$this->opt($this->t('confNumber'))}\s*([A-Z\d]{5,})$/", $subject, $m) ? $m[1] : null)
                ->cancelled();

            if (($name = $this->http->FindSingleNode($xpathH, null, true, '/de hospedaje en[ ]+(.+) de .* para el/'))) {
                $h->hotel()->name($name);
            }

            if (($checkIn = $this->http->FindSingleNode($xpathH, null, true, '/para el (.+)\./'))) {
                $h->booked()->checkIn($this->normalizeDate($checkIn));
            }

            if (($total = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Valor de la reserva'))}][1]", null, true, "/{$this->opt($this->t('Valor de la reserva'))}[ ]*:[ ]*(.+)/"))
                && preg_match('/^([A-Z]{3})[ ]+(\d[,.\'\d]*)$/', $total, $m)
            ) {
                $h->price()
                    ->currency($m[1])
                    ->total($m[2]);
            }
        } elseif ($this->http->XPath->query($xpathT = "descendant::text()[{$this->contains($this->t('cancellationPhrases->transfer'))}][1]")->length > 0) {
            // it-56488518.eml (transfer)
            // 56701059
            $transfer = $email->add()->transfer();

            $reserveNumber = $this->http->FindSingleNode($xpathT, null, true, "/{$this->opt($this->t('confNumber'))}\s*([A-Z\d]{5,})\s+/");

            $transfer->general()
                ->traveller($traveller)
                ->confirmation($reserveNumber)
                ->cancelled();
        } elseif ($this->http->XPath->query($xpathT = "descendant::text()[{$this->contains($this->t('cancellationPhrases->rental'))}][1]")->length > 0
                || ($commonCount > 0 && $this->striposAll($this->subject, $this->t("subjectRental")) === true)) {
            // it-56593679.eml
            $rental = $email->add()->rental();
            $reserveNumber = $this->http->FindSingleNode($xpathT, null, true, "/{$this->opt($this->t('confNumber'))}\s*([A-Z\d]{5,})/");

            if (empty($reserveNumber)) {
                $reserveNumber = $this->http->FindSingleNode($xpathCommon, null, true, "/{$this->opt($this->t('confNumber'))}\s*([A-Z\d]{5,})/");
            }
            $text = $this->http->FindSingleNode($xpathT . '/ancestor::td[1]');

            if ($m = $this->pregMatches($text, $this->t('rentalPreg'))) {
                $rental->car()->model($m[1]);
                $rental->pickup()->location($m[2]);
            }
            $rental->general()
                ->traveller($traveller)
                ->cancellationNumber($reserveNumber)
                ->cancelled();
        } elseif ($this->http->XPath->query($xpathT = "//p[{$this->contains($this->t('cancellationPhrases->event')[0], '.', 'and')}][1]")->length > 0
                || $this->http->XPath->query($xpathT = "//p[{$this->contains($this->t('cancellationPhrases->event')[1], '.', 'and')}][1]")->length > 0
                || ($commonCount > 0 && $this->striposAll($this->subject, $this->t("subjectEvent")) === true)) {
            // it-56062054.eml
            $event = $email->add()->event();
            $reserveNumber = $this->http->FindSingleNode($xpathT, null, true, "/{$this->opt($this->t('confNumber'))}\s*([A-Z\d]{5,})/");

            if (empty($reserveNumber)) {
                $reserveNumber = $this->http->FindSingleNode($xpathCommon, null, true, "/{$this->opt($this->t('confNumber'))}\s*([A-Z\d]{5,})/");
            }
            $text = $this->http->FindSingleNode($xpathT . '/ancestor::td[1]');

            if ($m = $this->pregMatches($text, $this->t('eventPreg'))) {
                $event->setName($m[1]);
            }
            $event->general()
                ->traveller($traveller)
                ->cancellationNumber($reserveNumber)
                ->cancelled();
        }
        /*elseif ($this->http->XPath->query("//text()[{$this->contains($this->t('cancellationPhrases->flight'))}][1]")->length > 0) {
            $flight = $email->add()->flight();
            $reserveNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('cancelar tu reserva número'))}]", null, true, "/{$this->opt($this->t('confNumber'))}\s*([\w\-]{5,})/");
            $flight->general()
                ->traveller($traveller)
                ->cancellationNumber($reserveNumber)
                ->cancelled();
        }*/
    }

    private function normalizeDate($str)
    {
        $in = [
            '/(\d{1,2}) (\w+) (\d{2,4})/i',
        ];
        $out = [
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

                if ($this->http->XPath->query("//text()[{$this->contains($phrase)}]")->length > 0) {
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
            if (!is_string($lang) || empty($phrases['otaConf']) || empty($phrases['hello'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['otaConf'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['hello'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function pregMatches($haystack, $arrayNeedle): array
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (preg_match($needle, $haystack, $m)) {
                return $m;
            }
        }

        return [];
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = '', $separator = 'or'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(" {$separator} ", array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
