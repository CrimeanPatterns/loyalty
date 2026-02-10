<?php

namespace AwardWallet\Engine\easyjet\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class ResetPassword extends \TAccountChecker
{
    public $mailFiles = "easyjet/statements/it-112109189.eml, easyjet/statements/it-115020445.eml, easyjet/statements/it-115434985.eml";

    public $lang = '';

    public static $dictionary = [
        'it' => [
            'membershipPhrases' => [
                'Ti confermiamo che la password del tuo account easyJet è stata modificata',
            ],
            'Hi' => 'Ciao',
        ],
        'en' => [
            'membershipPhrases' => [
                'We can confirm that the password for your easyJet account has been changed',
                'We have received a request to change your easyJet Plus password',
            ],
            'Hi' => ['Hi', 'Dear'],
        ],
    ];

    private $subjects = [
        'it' => ['La tua password è stata modificata'],
        'en' => ['Your password has been changed', 'Your easyJet Plus card membership - Reset password'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@easyjet.com') !== false || stripos($from, '@plus.easyjet.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".easyjet.com/") or contains(@href,"www.easyjet.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thanks,The easyJet Plus Team") or contains(.,"www.easyjet.com") or contains(.,"plus.easyjet.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('ResetPassword' . ucfirst($this->lang));

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $number = null;

        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Name:'))}]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi'))}]", null, true, "/^{$this->opt($this->t('Hi'))}[,\s]+({$patterns['travellerName']})(?:\s*[,:;!?]|$)/u")
        ;
        $st->addProperty('Name', preg_replace('/^(?:Mr|Ms)[.\s]+(.{2,})$/', '$1', $name));

        $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Membership Number:'))}]/following::text()[normalize-space()][1]", null, true, "/^[-A-Z\d]{5,}$/");

        if ($number) {
            $st->setNumber($number);
        }

        if ($name || $number) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['membershipPhrases'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['membershipPhrases'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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
}
