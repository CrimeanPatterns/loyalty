<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class JunkComingSoon extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-43253851.eml, airbnb/it-9268376.eml, airbnb/it-9396833.eml, airbnb/it-9396835.eml, airbnb/it-9396836.eml, airbnb/it-9579386.eml, airbnb/it-9579742.eml, airbnb/it-9628065.eml, airbnb/it-9786884.eml";

    private $prov = 'Airbnb';

    private $subjects = [
        // en
        'en'  => 'Reservation reminder:',
        'Reminder: You can pre-approve',
        'Action required: Payment failed for',
        'Pre-approval at',
        // de
        'de'  => 'Buchung der Unterkunft',
        'Buchungsanfrage für ',
        // es
        'es'  => 'Reserva confirmada',
        "Solicitud de reserva enviada para",
        'Solicitud de reservación enviada para',
        // fr
        'fr'  => 'Demande pour',
        'Réservation confirmée',
        // pt
        'pt'  => 'Reserva para',
        // it
        'it' => 'Fai una domanda per',
        'Prenotazione per',
        'Pre-approvazione per',
        'Messaggio da',
        'Prenotazione confermata ',
        // nl
        'nl' => 'Reserveringsaanvraag voor',
        'Aanvraag bij',
        // ru
        'ru' => 'Запрос о',
        'Бронирование подтверждено',
        // da
        'Bekræftet reservation',
        // ko
        '예약 요청 전송 완료',
        //zh
        '预订',
    ];

    private $detects = [
        'en'  => [
            'Get ready for',
            'Pre-approve / Decline',
            'Pre-approval at',
            'Inquiry at',
            'the reservation will be immediately confirmed',
            'and pre-approved your trip for',
        ],
        'de'  => [
            'eine Nachricht zu senden',
            'Deine Anfrage wurde verschickt',
        ],
        'es'  => [
            'Prepare-se para a chegada de',
            'Prepárate para la llegada de',
            "Tu reserva no está confirmada todavía",
            "Esta reservación todavía no está confirmada",
        ],
        'fr'  => [
            'Vous disposez de 24 heures pour répondre',
            'Préparez-vous pour l\'arrivée de',
        ],
        'pt'  => [
            'Responder',
        ],
        'it'  => [
            'un alloggio a',
            'Rispondi',
            'Preparati all\'arrivo di ',
        ],
        'zh'  => [
            '立刻预订',
            '回复',
            '此预订当前尚未得到确认。',
        ],
        'nl'  => [
            'Accepteren/weigeren',
            'Aanvraag bij',
        ],
        'ru'  => [
            'Запрос о',
            'Подготовьтесь к прибытию гостя ',
        ],
        'da'  => ['Tjek, at din gæst ved, hvordan han/hun finder din bolig'],
        'ko'  => ['아직 예약이 확정된 것은 아닙니다'],
    ];

    private $words = [
        // en
        'Send',
        'Pre-approve / Decline',
        'Book It',
        'Pre-approval at',
        'Reply',
        'Resubmit Payment',
        // de
        'Antworten',
        'Zu deinen Reisen',
        // es
        'Envie uma mensagem para',
        'Envía un mensaje a',
        'Accede a tus viajes',
        'Ir a tus viajes',
        // fr
        'Pré-approuver / Refuser',
        'Envoyez un message à',
        // pt
        'Responder',
        // it
        'Rispondi',
        'un alloggio a',
        'Prenota',
        'Invia un messaggio a',
        //zh
        '立刻预订',
        '回复',
        // nl
        'Accepteren/weigeren',
        'Reserveer',
        // ru
        'Забронировать',
        'Отправить сообщение пользователю',
        // da
        ' en besked',
        // ko
        '여행 목록 보기',
        //zh
        '前往旅程',
    ];

    private $reFrom = '/[@.]airbnb\.com/i';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $email->setType('JunkComingSoon');
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        if ($this->parseEmail($email)) {
            return $email;
        }

        return null;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'], $headers['subject']) || true !== $this->detectEmailFromProvider($headers['from'])) {
            return false;
        }

        foreach ($this->subjects as $subject) {
            if (false !== stripos($headers['subject'], $subject)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        if (
            0 === $this->http->XPath->query("//img[contains(@src, 'airbnb')]")->length
            && 0 === $this->http->XPath->query("//node()[contains(normalize-space(.), 'Airbnb')]")->length
        ) {
            return false;
        }

        foreach ($this->detects as $detects) {
            foreach ($detects as $detect) {
                if (false !== stripos(strip_tags($body), $detect) || $this->http->XPath->query("//a[({$this->contains($this->words)}) and contains(@href, 'airbnb')]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reFrom, $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'de', 'es', 'fr', 'pt', 'it', 'zh', 'nl', 'ru'];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function parseEmail(Email $email)
    {
        $this->logger->warning("//a[({$this->contains($this->words)}) and (contains(@href, 'airbnb') or contains(@href, '//abnb.me/')) and {$this->contains(['#ff5a5f', 'rgb(255,90,95)'], '@style')}]");

        if (0 === $this->http->XPath->query("//a[({$this->contains($this->words)}) and (contains(@href, 'airbnb') or contains(@href, '//abnb.me/')) and {$this->contains(['#ff5a5f', 'rgb(255,90,95)'], '@style')}]")->length) {
            return false;
        }

        $anchor = true;

        foreach ($this->detects as $detect) {
            if (0 < $this->http->XPath->query("//text()[{$this->contains($detect)}]")->length) {
                $anchor = false;
            }
        }

        if ($anchor) {
            return false;
        }
        $email->setIsJunk(true);

        return true;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'contains(normalize-space(' . $node . '),"' . $s . '")';
                }, $field))
            . ')';
    }
}
