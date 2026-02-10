<?php

namespace AwardWallet\Engine\emiles\Transfer;

class Register extends \TAccountCheckerEmiles
{
    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public function registerAccount(array $fields)
    {
        $this->ArchiveLogs = true;
        $this->http->GetURL('https://www.e-miles.com/awenroll');

        if (!$this->http->ParseForm('enrollmentForm')) {
            return false;
        }
        $query = $this->http->FindSingleNode('//input[@class="submitButtonEnrollment"]/@onclick', null, true, '/doSubmit\(\'enrollmentForm\', \'([\w\d]+)\'\)/');
        $this->http->FormURL .= '?' . $query;

        foreach ([
            'person.first' => 'FirstName',
            'person.last' => 'LastName',
            'person.email' => 'Email',
            'address.primary_zip' => 'PostalCode',
            'person.gender' => 'Gender',
            'person.birthMonth' => 'BirthMonth',
            'person.birthDay' => 'BirthDay',
            'person.birthYear' => 'BirthYear',
            'person.password' => 'Password',
            'verifyPassword' => 'Password',
        ] as $n => $f) {
            $this->http->Form[$n] = $fields[$f];
        }

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($error = $this->http->FindSingleNode('//div[@id="errorBox"]/text()[1]')) {
            throw new \UserInputError($error);
        } // Is it always user input error?

        if ($this->http->FindSingleNode('//text()[contains(., "Your e-Miles® membership is important to us.")]')) {
            $this->ErrorMessage = 'Your e-Miles® membership is important to us. Please confirm your email address by clicking on the "confirm email address" button in your welcome email';
            $this->http->Log('got address form, count as success');

            return true;
        }
        $this->http->Log('did not get address form', LOG_LEVEL_ERROR);

        return false;
    }

    public function getRegisterFields()
    {
        return [
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
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'PostalCode' => [
                'Type'     => 'string',
                'Caption'  => 'Postal Code, US only',
                'Required' => true,
            ],
            'Gender' => [
                'Type'     => 'string',
                'Caption'  => 'Gender',
                'Required' => true,
                'Options'  => self::$genders,
            ],
            "BirthDay" => [
                "Type"     => "integer",
                "Caption"  => "Day of Birth Date",
                "Required" => true,
            ],
            "BirthMonth" => [
                "Type"     => "integer",
                "Caption"  => "Month of Birth Date",
                "Required" => true,
            ],
            "BirthYear" => [
                "Type"     => "integer",
                "Caption"  => "Year of Birth Date",
                "Required" => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password',
                'Required' => true,
            ],
        ];
    }
}
