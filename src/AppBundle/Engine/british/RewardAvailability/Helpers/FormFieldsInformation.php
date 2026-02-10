<?php


namespace AwardWallet\Engine\british\RewardAvailability\Helpers;


class FormFieldsInformation
{
    private static $title = [
        "Mr" => "Mr", "Mrs" => "Mrs",
    ];

    private static $gender = [
        "male" => "Male", "female" => "Female",
    ];

    public static function getRegisterFields()
    {
        return [
            'Title' => [
                'Type'     => 'string',
                'Caption'  => 'Title',
                'Required' => true,
                'Options'  => static::$title,
            ],
            'FirstName' => [
                'Type'     => 'string',
                'Caption'  => 'First Name (2-25 length)',
                'Required' => true,
            ],
            'LastName' => [
                'Type'     => 'string',
                'Caption'  => 'Last Name (2-26 length)',
                'Required' => true,
            ],
            'Email' => [
                'Type'     => 'string',
                'Caption'  => 'Email',
                'Required' => true,
            ],
            'Password' => [
                'Type'     => 'string',
                'Caption'  => 'Password (Must be 8-16 characters and include at least 1 lowercase letter, 1 uppercase letter, 1 number)',
                'Required' => true,
            ],
            'Address' => [
                'Type'     => 'string',
                'Caption'  => 'Address (Must 0-29 characters or numbers long (include . , / \ and space) )',
                'Required' => true,
            ],
            'City' => [
                'Type'     => 'string',
                'Caption'  => 'City (0-29 characters)',
                'Required' => true,
            ],
            'State' => [
                'Type'     => 'string',
                'Caption'  => 'State (2-16 characters, US only)',
                'Required' => true,
            ],
            'ZipCode' => [
                'Type'     => 'string',
                'Caption'  => 'Zip (5 characters, US only, real)',
                'Required' => true,
            ],
            'BirthdayDate' => [
                'Type'     => 'date',
                'Caption'  => 'Your date of birth, older than 18 (MM/DD/YYYY)',
                'Required' => true,
            ],
            'Gender' => [
                'Type'    => 'string',
                'Caption' => 'Gender',
                'Required' => true,
                'Options'  => static::$gender,
            ],
            'PhoneNumber' => [
                'Type'     => 'string',
                'Caption'  => 'Phone Number (10 max-length, US only)',
                'Required' => true,
            ],
        ];
    }
}