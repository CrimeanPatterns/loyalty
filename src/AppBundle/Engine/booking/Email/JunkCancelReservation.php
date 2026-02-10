<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Schema\Parser\Email\Email;

class JunkCancelReservation extends \TAccountChecker
{
    public $mailFiles = "";
    private $lang;

    private $reSubject = [
        'O seu cliente deseja cancelar gratuitamente',
        'Seu hóspede gostaria de cancelar gratuitamente',
        'A tu cliente le gustaría cancelar gratis',
    ];
    private static $detectors = [
        'pt' => [
            'Tem um cliente que deseja cancelar gratuitamente',
            'Você tem um hóspede que gostaria de cancelar gratuitamente',
        ],
        'es' => ['Hay un cliente que quiere cancelar gratis', 'Nombre del establecimiento'],
    ];

    private static $dictionary = [
        'pt' => [
            'Institution name:'       => ['Nome da propriedade:'],
            'i would like to cancel:' => ['gostaria de cancelar:'],
        ],
        'es' => [
            'Institution name:'       => ['Nombre del establecimiento:'],
            'i would like to cancel:' => ['quiere cancelar lo siguiente:'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@booking.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers['subject'], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (stripos($text, 'booking.com') === false) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $email->setType('JunkCancelReservation');
        $this->parseEmail($email);

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function parseEmail(Email $email)
    {
        if (!self::detectBody()) {
            return false;
        }

        $email->setIsJunk(true);

        return true;
    }

    private function detectBody()
    {
        foreach (self::$detectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $words) {
            if (isset($words["Institution name:"], $words["i would like to cancel:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Institution name:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['i would like to cancel:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
}
