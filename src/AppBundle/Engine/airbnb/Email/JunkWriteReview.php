<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class JunkWriteReview extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-67827319.eml, airbnb/it-67842177.eml";

    private $detectFrom = 'automated@airbnb.com';

    private $detectSubjects = [
        // 'en'
        'Write a review for',
        'Reminder to write a review for',
        ' wrote you a review',
        '’s review',
        // pt
        ' um comentário para ',
        ' o comentário de ',
        // tr
        'sizin için bir değerlendirme yazdı',
        'hakkında değerlendirme yazın',
        'hakkında bir değerlendirme yazın',
        'adlı kişinin grubu ile ilgili değerlendirme yazmanız için anımsatıcı',
    ];

    private $detectBody = [
        'en' => [
            'Rate your experience with',
            'How was your stay at',
            'group what you loved and what they can do better',
            'Rate your stay at',
            'Your feedback is private until they review you too',
            'Find out how they rated you as a guest',
        ],
        'pt' => [
            'Avalie sua estadia até',
            'Avalie sua estadia no espaço de',
            'Saiba como seu anfitrião avaliou você como hóspede',
            'Enviar Meu Feedback',
            'acabou de fazer o checkout, portanto este é o momento perfeito para escrever seu comentário',
        ],
        'tr' => [
            'adlı kişinin ne yazdığını öğrenin',
            'ile konaklamanız nasıl geçti?',
            'ile ilgili değerlendirme yazmak için yalnızca 2 gününüz kaldı',
            'adlı kişinin grubuyla ilgili değerlendirme yazabilirsiniz',
        ],
    ];

    private static $dictionary = [
        'en' => [
            'Write a Review' => ['Write a Review', 'Write a response'],
        ],
        'pt' => [
            'Write a Review' => ['Enviar Meu Feedback', 'Escreva um comentário'],
        ],
        'tr' => [
            'Write a Review' => ['Değerlendirme Yazın'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (self::detectEmailByHeaders($parser->getHeaders()) == true && self::detectEmailByBody($parser) == true) {
            $email->setType('JunkWriteReview');
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

//        $this->logger->debug('Texts = '.print_r( $this->http->FindNodes("descendant::text()[not(ancestor::style)][normalize-space()][position() < 3]"),true));

        foreach ($this->detectBody as $lang => $detect) {
            if ($this->http->XPath->query("descendant::text()[not(ancestor::style)][normalize-space()][position() < 3][" . $this->contains($detect) . "]")->length > 0
                && ($this->http->XPath->query("//img[" . $this->eq('Babu_star', '@alt') . "] | //*[contains(@href, '&anchor=host-rating&overall_rating=')]")->length == 5
                    || (isset(self::$dictionary[$lang]) && !empty(self::$dictionary[$lang]['Write a Review'])
                        && $this->http->XPath->query("//a[" . $this->eq(self::$dictionary[$lang]['Write a Review']) . " and contains(@href, '.airbnb.') and " . $this->contains(['/hosting/reviews/', '.airbnb.com/reviews/', '/messaging/qt_for_reservation/', '%2Fmessaging%2Fqt_for_reservation%2F', '/users/reviews'], '@href') . "]")->length == 1)
                )
            ) {
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
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
