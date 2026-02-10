<?php

namespace AwardWallet\Engine\dcard\Transfer;

class Register extends \TAccountCheckerDcard
{
    public static $fieldMap = [
        'Email' => [
            'email',
            'email2',
        ],

        'Gender'    => 'gender',
        'FirstName' => 'firstName',
        'LastName'  => 'lastName',

        'Street'      => 'street',
        'HouseNumber' => 'houseNumber',
        'PostalCode'  => 'zip',
        'City'        => 'city',
        'Country'     => 'country',

        'BirthDate' => 'birthday',

        'ChooseThePartner' => 'partner-id',
        'ThemeId'          => 'theme-id',
    ];

    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $countries = [
        'DE' => 'Deutschland',
        'BE' => 'Belgien',
        'DK' => 'Dänemark',
        'FR' => 'Frankreich',
        'LU' => 'Luxemburg',
        'NL' => 'Niederlande',
        'AT' => 'Österreich',
        'PL' => 'Polen',
        'CH' => 'Schweiz',
        'CZ' => 'Tschechien',
    ];

    public static $partners = [
        '100' => 'Edeka',
        '172' => 'Esso',
        '131' => 'Deutsche Bank',
        '110' => 'Marktkauf',
        '148' => 'Vergölst Reifen und Autoservice',
        '135' => 'Berliner Bank',
        '167' => 'sonnenklar',
        '180' => 'Hammer',
        '182' => 'ManouLenz.TV',
    ];

    private $registerUrl = 'https://www.deutschlandcard.de/registrierung';
    private $fields = [];

    public function registerAccount(array $fields)
    {
        $this->http->log('>>> ' . __METHOD__);

        $this->fields = $fields;
        $this->addFields();

        $this->checkFields();

        $this->http->getUrl($this->registerUrl);

        $data = $this->getPostData($this->fields);
        $data = array_merge($data, $this->morePostData());

        $card = $this->registerPost($data);
        $this->authenticate($card);

        return true;
    }

    public function getRegisterFields()
    {
        return [
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Gender' => [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => self::$genders,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'First Name',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name',
                'Required' => true,
            ],

            'AddressLine1' => [
                'Type'     => 'string',
                'Caption'  => 'Address Line, street name followed by house number',
                'Required' => true,
            ],
            'PostalCode' => [
                'Type'     => 'string',
                'Caption'  => 'Postal Code',
                'Required' => true,
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'City (in German)',
                'Required' => true,
            ],
            'Country' => [
                'Type'     => 'string',
                'Caption'  => 'Country',
                'Required' => true,
                'Options'  => self::$countries,
            ],
            'BirthDay' => [
                'Type'     => 'integer',
                'Caption'  => 'Day of Birth Date',
                'Required' => true,
            ],
            'BirthMonth' => [
                'Type'     => 'integer',
                'Caption'  => 'Month of Birth Date',
                'Required' => true,
            ],
            'BirthYear' => [
                'Type'     => 'integer',
                'Caption'  => 'Year of Birth Date',
                'Required' => true,
            ],
            'ChooseThePartner' => [
                'Type'     => 'string',
                'Caption'  => 'Choose the partner',
                'Required' => true,
                'Options'  => self::$partners,
            ],
        ];
    }

    private function addFields()
    {
        $this->http->log('>>> ' . __METHOD__);

        $this->fields['ThemeId'] = 'SMI'; // hardcoded card theme
        $this->fields['Country'] = strtolower($this->fields['Country']); // Provider expects lower case Alpha-2 country code
        $this->fields['BirthDate'] = $this->getBirthDate();

        if (preg_match('/^\s*(.+?)[\s-]+(\d+)\s*$/', $this->fields['AddressLine1'], $m)) {
            $this->fields['Street'] = $m[1];
            $this->fields['HouseNumber'] = $m[2];
            unset($this->fields['AddressLine1']);
        } else {
            throw new \UserInputError('Cannot parse street + house number from address line');
        }

        $this->http->log('>>> fields');
        $this->http->log(print_r($this->fields, true));
    }

    private function registerPost(array $data)
    {
        $this->http->log('>>> ' . __METHOD__);
        $this->http->postUrl('https://www.deutschlandcard.de/participant/register', $data);

        $json = $this->getJson();
        $error = arrayVal($json['registration']['errorMessage'], '$');

        if (!$error) {
            $this->http->log('>>> register successfull');
            $cardnumber = arrayVal($json['registration']['cardnumber'], '$');

            return [
                'cardnumber' => substr($cardnumber, -10),
                'pin'        => arrayVal($json['registration']['pin'], '$'),
            ];
        }
        $error = sprintf('Error: %s', $error);

        throw new \UserInputError($error); // Is it always user error?
    }

    private function authenticate(array $data)
    {
        $this->http->log('>>> ' . __METHOD__);
        $this->http->postUrl('https://www.deutschlandcard.de/participant/authenticateAsync', $data);

        $json = $this->getJson();
        $success = $json['success'];

        if ($success) {
            $this->http->log('>>> authenticate successfull');
            $msg = sprintf('Successfull registration, your card number and pin: %s, %s.',
                $data['cardnumber'],
                $data['pin']
            );
            $this->http->log($msg);
            $this->ErrorMessage = $msg;

            return true;
        } else {
            $msg = 'Unknown error';
        }

        if (isset($json['message'])) {
            throw new \EngineError($json['message']);
        } else {
            throw new \EngineError('Unexpected response on account registration request');
        }
    }

    private function getJson()
    {
        $json = json_decode($this->http->Response['body'], true);
        $this->http->log('>>> json');
        $this->http->log(print_r($json, true));

        return $json;
    }

    private function morePostData()
    {
        $more = [
            'code'                 => 'Ohne Aktionscode',
            'highlight-cardnumber' => '',
            'newsletter'           => '1',
            'offers'               => 'on',
            'regFormId'            => '201501_registrierung',
        ];

        return $more;
    }

    private function getPostData(array $fields)
    {
        $data = [];

        foreach (self::$fieldMap as $awkey => $keys) {
            if (!isset($fields[$awkey])) {
                continue;
            }

            if (!is_array($keys)) {
                $keys = [$keys];
            }

            foreach ($keys as $k) {
                $data[$k] = $fields[$awkey];
            }
        }
        $this->http->log('>>> post data');
        $this->http->log(print_r($data, true));

        return $data;
    }

    private function checkFields()
    {
        $this->http->log('>>> ' . __METHOD__);
        $current_year = date('Y');

        if ($this->fields['BirthYear'] < $current_year - 99
            || $this->fields['BirthYear'] > $current_year) {
            throw new \UserInputError(self::getRegisterFields()['BirthYear']['Caption']);
        }
    }

    private function checkStatus()
    {
        $this->http->log('>>> ' . __METHOD__);
        $this->http->log('Unknown error');

        return false;
    }

    private function getBirthDate()
    {
        $day = str_pad($this->fields['BirthDay'], 2, '0', STR_PAD_LEFT);
        $month = str_pad($this->fields['BirthMonth'], 2, '0', STR_PAD_LEFT);

        return sprintf('%s.%s.%s',
            $day,
            $month,
            $this->fields['BirthYear']
        );
    }
}
