<?php

namespace AwardWallet\Engine\delta\Email;

class Forward extends \TAccountChecker
{
    protected $forward = []; /*
        '/[@.]delta\b/',
        '/[@.]gogoair\b/',
        '/[@.]medallia\b/',
        '/[@.]mltvacations\b/',
        '/[@.]wheresmysuitcase\b/',
        '/[@.]skymilesexperiences\b/',
        '/[@.]fimigroup\b/',
        '/[@.]travelguard\b/',
        '/[@.]magsformiles\b/',
        '/[@.]qgemail\.com\b/', // administrator@qgemail.com
        '/[@.]deltavacations\.com\b/',
        'allianzresearch@qualtrics-research.com',
        'deltamillionmiler@hartmann.com',
        'deltabcs@luggagepick.com',
    ];*/

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && $this->checkAddress($headers['from']);
    }

    public function detectEmailFromProvider($from)
    {
        return $this->checkAddress($from);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return [
            'parsedData' => [],
            'emailType'  => 'forward',
        ];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    protected function checkAddress($from)
    {
        foreach ($this->forward as $check) {
            if (stripos($check, '/') === 0 && preg_match($check, $from) || stripos($from, $check) !== false) {
                return true;
            }
        }

        return false;
    }
}
