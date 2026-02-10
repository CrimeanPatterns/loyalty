<?php

namespace AwardWallet\Engine\eurobonus\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function GetCredentialsCriteria()
    {
        return [
            'FROM "dontreply@sas.se"',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $subject = $parser->getSubject();
        $text = text($this->http->Response['body']);

        if (stripos($subject, 'Welcome to Scandinavian Airlines') !== false) {
            // credentials-1.eml
            $arr = [
                'Login'     => 'Username',
                'Title'     => 'Title',
                'FirstName' => 'Firstname',
                'LastName'  => 'Lastname',
                'BirthDate' => 'Birthdate',
                'Gender'    => 'Gender',
                'Address1'  => 'Address 1',
                'POBox'     => 'P.O.Box',
                'City'      => 'Town/City',
                'Zip'       => 'Postal/ZIP code',
                'State'     => 'State/Province',
                'Country'   => 'Country',
            ];

            foreach ($arr as $key => $value) {
                $result[$key] = re('#' . $value . ':\s+(.*)#i', $text);
            }
        }

        return $result;
    }
}
