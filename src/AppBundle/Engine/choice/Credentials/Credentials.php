<?php

namespace AwardWallet\Engine\choice\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'email_choiceprivileges@your.choicehotels.com',
            'email_choiceprivileges@choicehotels.com',
            'PROFILE_SUPPORT@choicehotels.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Thanks for Joining Choice Privileges!",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'FirstName',
            'Number',
            'Email',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Number'] = re('#Member\s*\#\s*:\s*([^\s!,\.]+)#i', $text);
        $result['Login'] = re('#Username\s*:\s*([^\s!,\.]+)#i', $text);
        $result['FirstName'] = re('#Hello,{0,1}\s*([^\s!,\.]+)#i', $text);
        $result['Email'] = $parser->getCleanTo();

        return $result;
    }
}
