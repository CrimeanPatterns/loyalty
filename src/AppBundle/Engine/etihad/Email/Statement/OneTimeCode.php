<?php

namespace AwardWallet\Engine\etihad\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OneTimeCode extends \TAccountChecker
{
    public $mailFiles = "etihad/it-27755218.eml, etihad/it-27769488.eml, etihad/it-621199390.eml";

    private $detects = [
        'You have performed an action that requires a one time password',
    ];

    private $from = '/[\.@]etihad[\.a-z]+com/i';

    private $prov = 'etihad';

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = !empty($parser->getHTMLBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();
        $this->http->SetEmailBody($text);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && is_string($headers['from']) && preg_match($this->from, $headers['from']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = !empty($parser->getHTMLBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (preg_match("/{$this->opt($detect)}/su", $body)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function parseEmail(Email $email): void
    {
        if ($otc = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'to confirm changes to your account.')]/following::text()[contains(translate(., '1234567890', 'xxxxxxxxxx'), 'xxxxx')][1]", null, true, '/^(\d{5})$/i')) {
            $email->add()->oneTimeCode()->setCode($otc);
        }
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
