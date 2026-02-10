<?php

namespace AwardWallet\Engine\autoslash\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarRentalIn extends \TAccountChecker
{
    public $mailFiles = "autoslash/it-98454567.eml";
    public $subjects = [
        '/Discounted Rates for Your [\d\/]+ Car Rental in \w+/',
    ];

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
            'Your discounted one-way car rental rate quote is ready' => [
                'Your discounted one-way car rental rate quote is ready',
                'Your discounted car rental rate quote is ready',
            ],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@autoslash.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'AutoSlash')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Click here to see your rates!'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Car size'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]autoslash.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'This is a one-time quote. No further emails will be sent for this request.')]")->count() > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your discounted one-way car rental rate quote is ready'))}]")->length > 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'trip ID')]")->length === 0) {
            $email->setIsJunk(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
