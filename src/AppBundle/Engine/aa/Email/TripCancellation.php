<?php

namespace AwardWallet\Engine\aa\Email;

use AwardWallet\Schema\Parser\Email\Email;

class TripCancellation extends \TAccountCheckerAa
{
    public $mailFiles = "aa/it-40914532.eml, aa/it-663500781.eml, aa/it-96479374.eml, aa/statements/it-560828193.eml";

    public $reFrom = ["@notify.email.aa.com", "no-reply@info.email.aa.com"];
    public $reBody = [
        'en' => ['Trip canceled on:', 'Your trip was canceled on'],
        'pt' => ['Sua viagem foi cancelada'],
        'es' => ['Su viaje fue cancelado', 'Su viaje está cancelado'],
    ];
    public $reSubject = [
        'Trip Cancellation',
        // pt
        'Cancelamento da viagem',
        // es
        'Cancelación de viaje',
        'Cancelación del viaje',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Record locator:' => ['Record locator:', 'Record locator', 'Confirmation code:'],
            'to'              => ['to', 'Your trip is canceled'],
            'Ticket #'        => ['Ticket #', 'Ticket number:'],
        ],
        'pt' => [
            'Record locator:' => ['Código de reserva:', 'Código de confirmação:'],
            'to'              => 'Sua viagem foi cancelada em',
            'Ticket #'        => ['Bilhete #', 'Número do bilhete:'],
            'Your trip is'    => 'Sua viagem foi',
        ],
        'es' => [
            'Record locator:' => ['Código de reservación:', 'Código de confirmación:'],
            'to'              => ['Viaje cancelado el:', 'Su viaje fue cancelado el'],
            'Ticket #'        => ['Boleto #', 'Boleto No.', 'Número del boleto:'],
            'Your trip is'    => ['Su viaje fue', 'Su viaje está'],
        ],
    ];
    private $keywordProv = 'American Airlines';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');
        }

        $this->parseEmail($email);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'.aa.com')] | //img[contains(@src,'.aa.com')]")->length > 0
            && $this->detectBody()
        ) {
            return $this->assignLang();
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

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || stripos($headers["subject"], $this->keywordProv) != '')
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

    private function parseEmail(Email $email)
    {
        $r = $email->add()->flight();

        $confirmation = trim($this->http->FindSingleNode("//text()[{$this->eq($this->t('Record locator:'))}]/following::text()[normalize-space()!=''][1]"), ':');

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//span[{$this->eq($this->t('Record locator:'))}]/ancestor::td[1]/descendant::span[3]");
        }
        $r->general()
            ->confirmation($confirmation)
            ->status($status = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Your trip is'))}])[1]", null, false,
                "#{$this->opt($this->t('Your trip is'))}\s*(.+)#"));

        if ($status === 'canceled' || $status === 'cancelled' || $status === 'cancelada' || $status === 'cancelado') {
            $r->general()->cancelled();
        }

        $containsInfo = true;

        if ($this->http->XPath->query("//text()[{$this->eq('Get the American Airlines app')}]/preceding::text()[normalize-space()]")->length < 20) {
            $containsInfo = false;
        }

        $travellers = $this->http->FindNodes("//text()[{$this->starts($this->t('Ticket #'))}]/ancestor::*[{$this->starts($this->t('Ticket #'))}]/preceding-sibling::*[not({$this->starts($this->t('Ticket #'))})]");

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//text()[" . $this->eq(preg_replace("/\s*#$/", '', $this->t('Ticket #'))) . "]/ancestor::*[{$this->starts($this->t('Ticket #'))}]/preceding-sibling::*[not({$this->starts($this->t('Ticket #'))})]");
        }

        if (!empty(array_filter($travellers)) || $containsInfo === true) {
            $r->general()
                ->travellers(array_unique(array_filter($travellers)));
        }

        $tickets = $this->http->FindNodes("//text()[{$this->starts($this->t('Ticket #'))}]/ancestor::td[1]",
                null, "#{$this->opt($this->t('Ticket #'))}\s*.*?(\d{8,})$#");

        if (empty($tickets)) {
            $tickets = $this->http->FindNodes("//text()[" . $this->eq(preg_replace("/\s*#$/", '', $this->t('Ticket #'))) . "]/ancestor::div[{$this->starts($this->t('Ticket #'))}]",
                null, "#{$this->opt($this->t('Ticket #'))}\s*.*?(\d+)$#");
        }

        if (!empty(array_filter($tickets)) || $containsInfo === true) {
            $r->issued()
                ->tickets(array_filter($tickets), false);
        }

        if ($containsInfo === false) {
            return true;
        }

        foreach ($this->http->FindNodes("//tr[{$this->contains($this->t('to'))} and not(.//tr)]") as $node) {
            if (preg_match("#^([A-Z]{3})\s*{$this->opt($this->t('to'))}\s*([A-Z]{3})$#", $node, $m)) {
                $s = $r->addSegment();
                $s->departure()->code($m[1]);
                $s->arrival()->code($m[2]);

                break;
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

    private function detectBody()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Record locator:'], $words['to'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Record locator:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->eq($words['to'])}]")->length > 0
                ) {
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '#'));
        }, $field)) . ')';
    }
}
