<?php

//  ProviderID: 1459

class TAccountCheckerAirserbia extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->setMaxRedirects(0);

        $data = [
            'recLoc'            => $this->AccountFields['Login'],
            'lastName'          => $this->AccountFields['Login2'],
            'checkSessionCache' => false,
            'silent'            => false,
        ];

        $this->http->PostURL('https://www.checkmytrip.com/cmt/apf/pnr/retrieve?SITE=NCMTNCMT&LANGUAGE=GB&OCTX=&APPVERSION=V5', ['data' => json_encode($data)]);

        if (200 == $this->http->Response['code']) {
            if (false !== strpos($this->http->Response['headers']['content-type'], 'text/xml')) {
                $xml = simplexml_load_string($this->http->Response['body']);

                //$session = json_decode((string) $xml->framework);
                $data = json_decode((string) $xml->data);

                if (false !== $data
                    && isset($data->model->errors[0]->localizedMessage)
                    && false === strpos($data->model->errors[0]->localizedMessage, 'We are unable to find this reservation number')
                ) {
                    $this->sendNotification('fish - refs #13124 [LP: Air Serbia] New Valid Account');
                }
            }
        }

        throw new CheckException('Error', ACCOUNT_ENGINE_ERROR);
    }

    public function Login()
    {
        return false;
    }

    public function Parse()
    {
        $this->SetBalanceNA();
    }
}
