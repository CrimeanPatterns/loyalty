<?php

namespace AwardWallet\Engine\aeroplan\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Verification extends \TAccountChecker
{
    public $mailFiles = "aeroplan/statements/it-64858676.eml, aeroplan/statements/it-73100428.eml, aeroplan/statements/it-102163710.eml, aeroplan/statements/it-103323073.eml, aeroplan/statements/it-104393178.eml";
    private $lang = '';
    private $reFrom = ['info@communications.aeroplan.com'];

    private $reSubject = [
        'fr' => 'Code de vérification pour accéder à votre compte',
        'en' => 'Verification code to access your account',
    ];
    private $reBody = [
        'fr' => [
            ['AUTHENTIFICATION REQUISE', 'Pour assurer la livraison de vos courriels, veuillez ajouter', 'Veuillez utiliser ce code pour accéder à votre compte'],
        ],
        'en' => [
            ['AUTHENTICATION REQUIRED', 'To ensure delivery to your inbox, please add', 'Please use this code to access your'],
        ],
    ];
    private static $dictionary = [
        'fr' => [
            'Hello'                => 'Bonjour',
            'yourVerificationCode' => ['Veuillez utiliser le code de vérification suivant pour accéder à votre compte Aéroplan', 'Veuillez utiliser ce code pour accéder à votre compte Aéroplan'],
            'VERIFICATION CODE:'   => [
                'CODE DE VÉRIFICATION:', 'CODE DE VÉRIFICATION :',
                'Code de vérification:', 'Code de vérification :',
            ],
        ],
        'en' => [
            'yourVerificationCode' => ['Please use the following verification code to access your Aeroplan', 'Please use this code to access your Aeroplan'],
            'VERIFICATION CODE:'   => [
                'VERIFICATION CODE:', 'VERIFICATION CODE :',
                'Verification code:', 'Verification code :',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('Verification' . ucfirst($this->lang));

        $verificationCode = $this->http->FindSingleNode("//text()[{$this->contains($this->t('yourVerificationCode'))}]/following::text()[{$this->starts($this->t('VERIFICATION CODE:'))}]", null, true, "/^{$this->opt($this->t('VERIFICATION CODE:'))}[:\s]*(\d{3,})$/")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('yourVerificationCode'))}]/preceding::text()[{$this->starts($this->t('VERIFICATION CODE:'))}]", null, true, "/^{$this->opt($this->t('VERIFICATION CODE:'))}[:\s]*(\d{3,})$/")
        ;

        if ($verificationCode !== null) {
            // it-102163710.eml
            $code = $email->add()->oneTimeCode();
            $code->setCode($verificationCode);
        } elseif (stripos($parser->getCleanFrom(), 'communications') === false) {
            // без этой проверки, под детект "To ensure delivery to your inbox, please add" будут попадать письма других форматов, в точ числе с резервациями
            return false;
        }

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $st = $email->add()->statement();

        $name = null;

        $rootText = $this->http->FindSingleNode('.', $root);

        if (preg_match("/^{$this->opt($this->t('Hello'))}[,\s]+(?<name>[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/u", $rootText, $m)
            || preg_match("/^(?<name>[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])$/u", $rootText, $m)
        ) {
            $name = $m['name'];
            $st->addProperty('Name', $name);
        }

        if ($name) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".aeroplan.com/") or contains(@href,".aircanada.com/") or contains(@href,"www.aeroplan.com") or contains(@href,"mail.aircanada.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"This email was sent by Air Canada") or contains(.,"@communications.aeroplan.com") or contains(.,"@Mail.aircanada.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findRoot()->length === 1;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $xpathHide = "ancestor-or-self::*[contains(@style,'display:none') or contains(normalize-space(@style),'display: none')]";

        // it-73100428.eml
        $nodes = $this->http->XPath->query("//*[count(tr[normalize-space()])=1]/tr[count(*[normalize-space() and not({$xpathHide})])=1]/*[descendant::img]/following-sibling::*[ normalize-space() and not({$xpathHide}) and following-sibling::*[normalize-space()='' and descendant::img[contains(@src,'/header_icon')]] ]");

        if ($nodes->length !== 1) {
            // it-64858676.eml
            $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Hello'))}]/following-sibling::node()[normalize-space()][1]");
        }

        if ($nodes->length !== 1) {
            // it-102163710.eml
            $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Hello'))}]");
        }

        return $nodes;
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
