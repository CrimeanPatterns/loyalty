<?php

namespace AwardWallet\Engine\fedex\Credentials;

class YourFedexcomUserID extends \TAccountCheckerExtended
{
    public $mailFiles = [
        'login-1.eml',
    ];

    public function getCredentialsImapFrom()
    {
        return [
            'onlineservices@fedex.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            'Your fedex.com user ID',
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Email',
            'Login',
        ];
    }

    public function getRetrieveFields()
    {
        return [
            'Email',
        ];
    }

    public function RetrieveCredentials($data)
    {
        $this->http->GetURL("https://www.fedex.com/fcl/web/jsp/forgotPassword.jsp?appName=fcltrk&locale=us_en&step3URL=https%3A%2F%2Fwww.fedex.com%2FTracking?action=myfedex%26cntry_code=us&afterwardsURL=https%3A%2F%2Fwww.fedex.com%2FTracking?action=myfedex%26cntry_code=us");
        $this->http->FormURL = "https://www.fedex.com/fcl/forgotUserIdAction.do";
        $this->http->Form = [
            'resume'          => 'no',
            'Method'          => '',
            'cc_lang'         => 'us',
            'curl'            => 'https://www.fedex.com/fcl/web/jsp/logon.jsp?appName=fclmfx&locale=us_en&step3URL=https://www.fedex.com/myfedex/go/fclstep3?cc=US',
            'surl'            => 'https://www.fedex.com/myfedex/go/home?cc=US',
            'lsession'        => '',
            'AdminCookie'     => '',
            'appname'         => 'fclmfx',
            'ssoguest'        => 'n',
            'appName'         => 'fcltrk',
            'step3URL'        => 'https://www.fedex.com/Tracking?action=myfedex&cntry_code=us',
            'afterwardsURL'   => 'https://www.fedex.com/Tracking?action=myfedex&cntry_code=us',
            'locale'          => 'us_en',
            'fclqrs'          => '',
            'programIndicator'=> '',
            'invitationError' => '',
            'addressType'     => '',
            'fromLoginPage'   => '',
            'email'           => $data['Email'], //set email
            'action2'         => 'Continue',
        ];

        $res = $this->http->PostForm();

        if (!$res) {
            $this->http->Log('Failed to post form', LOG_LEVEL_ERROR);

            return false;
        }

        if ($this->http->FindSingleNode("//*[contains(text(), 'Thank you. User ID(s) associated with')]")) {
            return true;
        } else {
            return false;
        }
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $result['Email'] = $parser->getCleanTo();
        $result['Name'] = re('#\s+Dear\s+(.*?):#i', $this->text());
        $result['Login'] = $this->http->FindSingleNode("//*[contains(text(), 'User ID')]/ancestor::tr[1]/following-sibling::*[1]/td[1]");

        return $result;
    }
}
