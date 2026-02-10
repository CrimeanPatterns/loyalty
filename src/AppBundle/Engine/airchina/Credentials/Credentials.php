<?php

namespace AwardWallet\Engine\airchina\Credentials;

class Credentials extends \TAccountCheckerExtended
{
    public function getCredentialsImapFrom()
    {
        return [
            "ffp@enews.airchina.com.cn",
        ];
    }

    public function getCredentialsSubject()
    {
        return [
            "Account News August (AD)",
            "Earn miles with",
        ];
    }

    public function getParsedFields()
    {
        return [
            'Login',
            'Name',
        ];
    }

    public function ParseCredentialsEmail(\PlancakeEmailParser $parser)
    {
        $text = text($this->http->Response['body']);
        $result['Name'] = beautifulName(re('#Dear\s+(M[\.rsi]+\s+|)(.*?)(,|)\s+You are#msi', $text, 2));
        $result['Login'] = re('#Card\s+No.\s*:\s*([A-Z]*)(\d+)#i', $text) . preg_replace("#^0+#", "", re(2));

        return $result;
    }
}
