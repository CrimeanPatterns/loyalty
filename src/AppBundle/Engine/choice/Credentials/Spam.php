<?php

namespace AwardWallet\Engine\choice\Credentials;

class Spam extends \TAccountCheckerExtended
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
            "Like Gift Cards?",
            "Support those Impacted by the Nepal Earthquake",
        ];
    }

    public function getParsedFields()
    {
        return [
            'FirstName',
            'Number',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result = [];
        $text = text($this->http->Response['body']);
        $result['Number'] = re('#Member\s*\#\s*:\s*([^\s!,\.]+)#i', $text);
        $result['FirstName'] = re('#Hello,{0,1}\s*([^\s!,\.]+)#i', $text);

        return $result;
    }
}
