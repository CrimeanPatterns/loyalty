<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class JunkCanceledForHosts extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-67827118.eml";

    private $detectFrom = 'automated@airbnb.com';

    private $detectSubjects = [
        'en' => 'Reservation canceled - ', // Reservation canceled - Oct 8-12, 2a1 Comfy Master Suite W. Sub Cls Loyola+Downtown
        'Reservation cancelled - ',
        'Reservation cancelled – ',
        ' Canceled', //  Reservation HM2WI38QXN on June 14, 2020 Canceled
        ' Cancelled.',
        'fr' => 'Réservation annulée',
        'pt' => 'Reserva cancelada - ',
        'es' => 'Reserva cancelada - ',
        'Reservación cancelada - ',
        'ru' => 'Бронирование отменено - ',
        'bs' => 'Otkazana je rezervacija – ',
        'de' => 'Buchung storniert – ',
    ];

    private $detectBody = [ // [1,2]
        'en'  => ['Unfortunately, your guest', 'had to cancel reservation'],
        'en2' => ['Your guest ', 'can’t travel due to the COVID-19 pandemic. They’ve canceled their stay'],
        'en3' => ['Your guest ', 't stay at your place anymore because of the COVID-19 affecting'],
        'en4' => ['Unfortunately, your guest ', 'decided not to travel due to the COVID-19, which means they’ve cancelled'],
        'fr'  => ['Votre voyageur,', 'a malheureusement dû annuler la réservation'],
        'pt'  => ['Infelizmente, o hóspede', 'teve de cancelar a reserva'],
        'pt2' => ['Infelizmente, o seu hóspede', 'teve de cancelar a reserva'],
        'es'  => ['Sentimos informarte de que tu huésped,', 'ha cancelado la reserva'],
        'es2' => ['Desafortunadamente, tu huésped', 'tuvo que cancelar la reservación'],
        'ru'  => ['К сожалению, гость ', 'вынужден отменить бронирование'],
        'bs'  => ['Nažalost, vaš gost ', 'je morao da otkaže rezervaciju'],
        'de'  => ['Leider musste dein Gast', 'die Buchung'],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (self::detectEmailByHeaders($parser->getHeaders()) == true && self::detectEmailByBody($parser) == true) {
            $email->setType('JunkCanceledForHosts');
            $email->setIsJunk(true);
        }

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject']) || self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src, '.airbnb.com')]")->length == 0
            && $this->http->XPath->query("//a[contains(@href, '.airbnb.')]")->length == 0
        ) {
            return false;
        }

        foreach ($this->detectBody as $detect) {
            if ($this->http->XPath->query("//text()[contains(normalize-space(), '" . $detect[0] . "') and contains(normalize-space(), '" . $detect[1] . "')]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'fr', 'pt', 'es', 'ru', 'bs'];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }
}
