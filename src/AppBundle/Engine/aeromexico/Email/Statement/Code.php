<?php

namespace AwardWallet\Engine\aeromexico\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Code extends \TAccountChecker
{
    public $mailFiles = "aeromexico/statements/it-250054347.eml, aeromexico/statements/it-251838970.eml";

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]clubpremier[.]com\b/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@clubpremier.com') !== false
            && isset($headers['subject']) && $this->checkSubject($headers['subject']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//img[contains(@alt, "Para validar la autenticidad de tu Cuenta, ingresa el siguiente codigo")]')->length > 0
            || $this->http->XPath->query('//img[contains(@alt, "To authenticate your Account, please enter the following verification code")]')->length > 0
            || $this->http->XPath->query('//td[normalize-space() = "Para validar la autenticidad de tu Cuenta, ingresa el siguiente codigo de verificacion en el campo correspondiente."]')->length > 0
        ;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $tds = $this->http->FindNodes('//img[
            contains(@alt, "Para validar la autenticidad de tu Cuenta, ingresa el siguiente codigo")
        or  contains(@alt, "To authenticate your Account, please enter the following verification code")]
            /following::td[not(.//td) and normalize-space(.) != ""]' .
        '| //td[normalize-space() = "Para validar la autenticidad de tu Cuenta, ingresa el siguiente codigo de verificacion en el campo correspondiente."]/following::td[not(.//td) and normalize-space(.) != ""]');
        $tds = array_filter(array_map('trim', $tds));

        if (count($tds) > 0 && ($code = array_shift($tds)) && preg_match('/^[A-Z\d]{6}(?: td\>)?$/', $code) > 0) {
            $code = substr($code, 0, 6);
            $email->add()->oneTimeCode()->setCode($code);

            $name = $this->http->FindSingleNode('//img[contains(@alt, "Estimado") or @alt="Dear"]/following-sibling::span');

            if (empty($name)) {
                $name = $this->http->FindSingleNode('(//text()[starts-with(normalize-space(), "Hola ") and contains(., ",")])[1]', null, true, "/^Hola ([[:alpha:] \-]+),$/");
            }

            $number = $this->http->FindSingleNode('(//text()[normalize-space() = "Número de Cuenta Club Premier"])[1]/following::text()[normalize-space()][1]',
                null, true, "/^\s*(\d{5,})\s*$/");

            if ($name || $number) {
                $st = $email->add()->statement();
                $st->setNoBalance(true);

                if (!empty($name)) {
                    $st->addProperty('Name', $name);
                }

                if (!empty($number)) {
                    $st->setNumber($number);
                    $st->setLogin($number);
                }
            }
        }
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function checkSubject($subject): bool
    {
        foreach ([
            'Verification code',
            'Código de autenticación',
        ] as $needle) {
            if (stripos($subject, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
