<?php

namespace AwardWallet\Engine\bestbuy\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            'BestBuyInfo@emailinfo.bestbuy.com',
            'BestBuyRewardZone@response.bestbuy.com',
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Best Buy Reward Zone",
            "Best Buy",
            "#Reward Zone#i",
            "#Best Buy#i",
            "#great savings#i",
            "#account has been updated#i",
            "#your next purchase#i",
            "#verify your Reward#i",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Name',
            'Email',
            'Login',
            //'Number'
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $subject = $parser->getSubject();

        $text = text($this->http->Response['body']);
        $result['Login'] = $result['Email'] = $parser->getCleanTo();

        $result['Name'] = orval(
            re('#(.*), verify your#i', $subject),
            re('#Dear (.*),#i', $text),
            re('#Hello, (.*) \w\.#i', $text)
        );
        /*
        $result['Number'] = orval(
            re('#Member ID\s*:\s*(\d+)#i', $text),
            re('#\s+ID\s*:\s*(\d+)#i', $text)
        );
          */
        return $result;
    }
}
