<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Schema\Parser\Email\Email;

class JunkPromocode extends \TAccountChecker
{
    public $mailFiles = "booking/it-67221579.eml, booking/it-67471248.eml";

    private $detectFrom = 'noreply@booking.com';

    private static $dictionary = [
        'en' => [
            'Book with your code' => ['Book with your code', 'Book with your reward', 'Book with my reward'],
            'Conditions'          => 'Conditions',
            'detect'              => 'Planning on booking a stay? Don’t pay more than you have to',
        ],
        'es' => [
            'Book with your code' => ['Reservar con el código', 'Reservar con premio', 'Reservar con el premio'],
            'Conditions'          => 'Condiciones',
            'detect'              => 'Haz una reserva y consigue un reembolso del 10% en tu estancia.',
        ],
        'pt' => [
            'Book with your code' => ['Reserve com o seu código', 'Reserve com a sua recompensa',
                'Reservar com a minha recompensa', 'Reserve com sua recompensa',
            ],
            'Conditions' => 'Condições',
            'detect'     => 'Planejando sua próxima hospedagem? Não pague mais do que o necessário!',
        ],
        'it' => [
            'Book with your code' => 'Prenota usando il codice',
            //            'Conditions' => '',
            'detect' => 'Ricevi il 10% di rimborso in crediti di viaggio sul tuo prossimo soggiorno con noi',
        ],
        'fr' => [
            'Book with your code' => 'Réserver avec mon code',
            'Conditions'          => 'Conditions',
            'detect'              => 'Vous comptez réserver un séjour ? Ne payez pas plus que nécessaire !',
        ],
        'ru' => [
            'Book with your code' => 'Забронировать с кодом',
            'Conditions'          => 'Условия',
            'detect'              => 'Пусть отпуск пройдет отлично! Получите возврат до 5% в виде бонусов на путешествия',
        ],
        'bg' => [
            'Book with your code' => 'Резервирайте с вашия код',
            'Conditions'          => 'Условия',
            'detect'              => 'Планирате резервация на престой? Не плащайте повече от необходимото!',
        ],
        'nl' => [
            'Book with your code' => ['Boek met je beloning', 'Boek met je code'],
            //            'Conditions' => '',
            //            'detect' => '',
        ],
        'th' => [
            'Book with your code' => 'จองโดยใช้รหัสของท่าน',
            //            'Conditions' => '',
            //            'detect' => '',
        ],
        'pl' => [
            'Book with your code' => 'Zarezerwuj, korzystając z kodu',
            //            'Conditions' => '',
            //            'detect' => '',
        ],
        'sk' => [
            'Book with your code' => 'Rezervovať s kódom',
            //            'Conditions' => '',
            //            'detect' => '',
        ],
        'de' => [
            'Book with your code' => 'Mit Ihrem Code buchen',
            'Conditions'          => 'Bedingungen',
            //            'detect' => '',
        ],
        'ro' => [
            'Book with your code' => 'Rezervă cu recompensă',
            //            'Conditions' => '',
            //            'detect' => '',
        ],
        'uk' => [
            'Book with your code' => 'Забронювати з кодом',
            //            'Conditions' => '',
            //            'detect' => '',
        ],
        'zh' => [
            'Book with your code' => '使用優惠碼預訂',
            //            'Conditions' => '',
            //            'detect' => '',
        ],
        'sv' => [
            'Book with your code' => 'Boka med din kod',
            //            'Conditions' => '',
            //            'detect' => '',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) !== false
            && (preg_match("/(?: |^)\d{1,2}\s?%(?: |$)/u", $headers["subject"])
                || preg_match("/(?:[A-Z]{3}|\\$|€)\s?\d+/u", $headers["subject"])
                || preg_match("/\d+\s*(?:zł)/u", $headers["subject"])
            )
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailByHeaders($parser->getHeaders()) === false) {
            return false;
        }

        if ($this->http->XPath->query("//*[" . $this->contains(['letter-spacing: 3px;', 'letter-spacing:3px;'], '@style') . "]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['detect']) && $this->http->XPath->query("//*[" . $this->contains($dict['detect']) . "]")->length > 0
            ) {
                return true;
            }

            if (!empty($dict['Book with your code']) && !empty($dict['Conditions'])
                    && $this->http->XPath->query("//a[" . $this->eq($dict['Book with your code']) . " and contains(@href, '.booking.com')]/following::text()[" . $this->eq($dict['Conditions']) . "]")->length > 0
            ) {
                return true;
            }

            if (!empty($dict['Book with your code']) && !empty($dict['Conditions'])
                && $this->http->XPath->query("//a[" . $this->eq($dict['Book with your code']) . " and contains(@href, '.booking.com')]/following::text()[" . $this->eq($dict['Conditions']) . "]")->length > 0
            ) {
                return true;
            }

            if (!empty($dict['Book with your code'])
                && $this->http->XPath->query("//table[count(.//text()[normalize-space()]) = 1]//text()[string-length(normalize-space()) = 10]/ancestor::*[contains(@style, 'letter-spacing:3px') or contains(@style, 'letter-spacing: 3px')]/following::tr[not(.//tr)][position()<4][" . $this->eq($dict['Book with your code']) . "]//a[" . $this->eq($dict['Book with your code']) . " and contains(@href, '.booking.com')]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByBody($parser)) {
            $email->setIsJunk(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.) = "' . $s . '"';
        }, $field)) . ')';
    }
}
