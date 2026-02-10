<?php

namespace AwardWallet\Engine\airmilesme\Transfer;

class Register extends \TAccountChecker
{
    public static $genders = [
        'M' => 'Male',
        'F' => 'Female',
    ];

    public static $religions = [
        '001' => 'Christian',
        '002' => 'Hindu',
        '003' => 'Muslim',
        '004' => 'Other',
    ];

    public static $nationalities = [
        '01' => 'UAE',
        '02' => 'Other GCC',
        '03' => 'Other Arab Expat',
        '04' => 'Indian Sub-continent',
        '05' => 'Western Expat',
        '06' => 'Other Asia',
        '07' => 'South American',
        '08' => 'Other Africa',
        '09' => 'Other',
    ];

    public static $emirates = [
        'Abu Dhabi'      => 'Abu Dhabi',
        'Dubai'          => 'Dubai',
        'Sharjah'        => 'Sharjah',
        'Ajman'          => 'Ajman',
        'Ras Al Khaimah' => 'Ras Al Khaimah',
        'Fujairah'       => 'Fujairah',
        'Al Ain'         => 'Al Ain',
        'Umm Al Quwain'  => 'Umm Al Quwain',
    ];

    public static $inputFieldsMap = [
        'FirstName'        => 'firstName',
        'LastName'         => 'familyName',
        'BirthDay'         => 'dobday',
        'BirthMonth'       => 'dobmonth',
        'BirthYear'        => 'dobyear',
        'Gender'           => 'gender',
        'Religion'         => 'religion',
        'Nationality'      => 'nationality',
        'Emirate'          => 'area',
        'POBox'            => 'POBox',
        'PhoneLocalNumber' => 'mobile',
        'Email'            => [
            'email',
            'confirmEmail',
        ],
        'ReceiveEmailsFromAirMiles' => 'hostEmail',
        'ReceiveSMSFromAirMiles'    => 'hostSms',
        'Password'                  => [
            'password',
            'confirmPassword',
        ],
    ];

    public function InitBrowser()
    {
        $this->UseCurlBrowser();
        $this->ArchiveLogs = true;
        $this->http->LogHeaders = true;
        $this->http->removeCookies();
    }

    public function registerAccount(array $fields)
    {
        $this->http->GetURL('https://www.airmilesme.com/en-ae/register');

        $status = $this->http->ParseForm('edit_profile');

        if (!$status) {
            $this->http->Log('Failed to parse account registration form', LOG_LEVEL_ERROR);

            return false;
        }

        foreach (['BirthDay', 'BirthMonth'] as $k) {
            $fields[$k] = sprintf('%02d', $fields[$k]);
        }

        foreach (['ReceiveEmailsFromAirMiles', 'ReceiveSMSFromAirMiles'] as $k) {
            $fields[$k] = $fields[$k] ? 'Y' : 'N';
        }

        if ($fields['Gender'] == 'M') {
            $fields['Gender'] = 1;
        } elseif ($fields['Gender'] == 'F') {
            $fields['Gender'] = 2;
        }

        // TODO: Move this loop to some generic method
        foreach (self::$inputFieldsMap as $awKey => $provKeys) {
            if (!isset($fields[$awKey]) or $provKeys === false) {
                continue;
            }

            if (!is_array($provKeys)) {
                $provKeys = [$provKeys];
            }

            foreach ($provKeys as $provKey) {
                $this->http->SetInputValue($provKey, $fields[$awKey]);
            }
        }

        $this->http->SetInputValue('termsConsitions', 'Yes');
        $this->http->SetInputValue('submitDetails', 'SUBMIT DETAILS');
        //		d445af6ff723ac6da265f6ec2ce20db9:1

        $status = $this->http->PostForm();

        if (!$status) {
            $this->http->Log('Failed to post account registration form', LOG_LEVEL_ERROR);

            return false;
        }

        if ($errors = $this->http->FindNodes('//label[@class="error"]')) {
            $msg = implode('. ', $errors);
            $this->http->Log($msg, LOG_LEVEL_ERROR);

            throw new \UserInputError($msg); // Is it always user error?
        }

        $xpath = '//div[@class="submitbutton"]/div[@class="buttonholder"]/a[contains(., "Confirm Details")]';

        if (!$this->http->FindSingleNode($xpath)) {
            $this->http->Log('Failed to found submit account registration button', LOG_LEVEL_ERROR);

            return false;
        }

        $this->http->GetURL('https://www.airmilesme.com/en-ae/register?status=confirm');

        $regexp = '#Thank you for registering with Air Miles. You will now be sent an email with an activation link. Please check your mail to activate your Account.#';

        if ($successMessage = $this->http->FindPreg($regexp)) {
            $this->ErrorMessage = $successMessage;

            return true;
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            'FirstName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'First Name',
                    'Required' => true,
                ],
            'LastName' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Family Name',
                    'Required' => true,
                ],
            'BirthDay' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Day of Birth',
                    'Required' => true,
                ],
            'BirthMonth' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Month of Birth',
                    'Required' => true,
                ],
            'BirthYear' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'Year of Birth',
                    'Required' => true,
                ],
            'Gender' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Gender',
                    'Required' => true,
                    'Options'  => self::$genders,
                ],
            'Religion' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Religion',
                    'Required' => true,
                    'Options'  => self::$religions,
                ],
            'Nationality' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Nationality',
                    'Required' => true,
                    'Options'  => self::$nationalities,
                ],
            'Emirate' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Emirate',
                    'Required' => true,
                    'Options'  => self::$emirates,
                ],
            'POBox' =>
                [
                    'Type'     => 'integer',
                    'Caption'  => 'POBox for UAE (up to 6 numbers)',
                    'Required' => true,
                ],
            'PhoneLocalNumber' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Mobile Phone Number for UAE without country code',
                    'Required' => true,
                ],
            'Email' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Email',
                    'Required' => true,
                ],
            'ReceiveEmailsFromAirMiles' =>
                [
                    'Type'     => 'boolean',
                    'Caption'  => 'Do you wish to receive Emails from Air Miles?',
                    'Required' => true,
                ],
            'ReceiveSMSFromAirMiles' =>
                [
                    'Type'     => 'boolean',
                    'Caption'  => 'Do you wish to receive SMS from Air Miles?',
                    'Required' => true,
                ],
            'Password' =>
                [
                    'Type'     => 'string',
                    'Caption'  => 'Password (a combination of upper case, lower case, numbers and special characters)',
                    'Required' => true,
                ],
        ];
    }
}
