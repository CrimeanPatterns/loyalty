<?php

namespace AwardWallet\Engine\despegar\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Event extends \TAccountChecker
{
    private $detects = [
        'es' => ['Detalle de la Reserva', 'Le informamos que se ha generado una nueva reserva'],
    ];

    private $lang = 'es';

    private $from = '/[@.]despegar\.com/';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (0 === $this->http->XPath->query("//a[contains(@href, 'despegar.com')]")->length) {
            return false;
        }

        foreach ($this->detects as $lang => $detects) {
            if (
                0 !== $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detects[0]}')]")->length
                && 0 !== $this->http->XPath->query("//node()[contains(normalize-space(.), '{$detects[1]}')]")->length
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseEmail(Email $email): void
    {
        $e = $email->add()->event();

        if (!empty($this->getNode('Fecha de la excursión'))) {
            $e->setEventType(EVENT_EVENT);
        }

        if ($pax = $this->getNode('Titular de la reserva')) {
            $e->addTraveller($pax);
        }

        if ($tel = $this->getNode('Teléfono')) {
            $e->place()
                ->phone($tel);
        }

        if ($addr = $this->getNode('Hotel o punto de interés')) {
            $e->place()
                ->address($addr)
            ;
        }

        if ($name = $this->getNode('Producto comprado')) {
            $e->place()
                ->name($name);
        }

        if ($conf = $this->getNode('Número de documento')) {
            $e->addConfirmationNumber($conf);
        }

        if (preg_match('/Adultos[ ]*:[ ]*(\d{1,2}) \| Menores[ ]*:[ ]*(\d{1,2}) \| Infantes[ ]*:[ ]*(\d{1,2})/', $this->getNode('Pasajeros'), $m)) {
            $e->booked()
                ->guests($m[1])
            ;
        }

        if ($checkIn = $this->getNode('Fecha de reserva')) {
            $e->booked()
                ->start(strtotime(str_replace([' - ', '/'], [', ', '-'], $checkIn)))
                ->noEnd()
            ;
        }
    }

    private function getNode(string $s)
    {
        return $this->http->FindSingleNode("//font[starts-with(normalize-space(.), '{$s}') and not(.//text) and not(.//td)]/following-sibling::node()[normalize-space(.)][1]");
    }
}
