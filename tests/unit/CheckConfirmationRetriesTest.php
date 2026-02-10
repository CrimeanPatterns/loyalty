<?php

namespace Tests\Unit;
use AppBundle\Model\Resources\CheckConfirmationRequest;
use AppBundle\Model\Resources\CheckConfirmationResponse;
use AppBundle\Model\Resources\InputField;

/**
 * @backupGlobals disabled
 */
class CheckConfirmationRetriesTest extends \Tests\Unit\BaseWorkerTestClass
{

    private function getRequest($confNo, $lastName)
    {
        $fields = [];
        $fields[] = (new InputField())->setCode('ConfNo')->setValue($confNo);
        $fields[] = (new InputField())->setCode('LastName')->setValue($lastName);

        return (new CheckConfirmationRequest())
                ->setProvider('testprovider')
                ->setUserid(md5(time()))
                ->setPriority(9)
                ->setFields($fields);
    }

    public function testRetry()
    {
        $request = $this->getRequest('Checker.RetryConfirmation', 'TESTNAME');
        $response = new CheckConfirmationResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->expectException(\ThrottledException::class);
        $this->getCheckConfirmationWorker()
             ->processRequest($request, $response, $this->row);

        /** @var \ThrottledException $e */
        $e = $this->getExpectedException();
        $this->assertEquals(20, $e->retryInterval);
        $this->assertEquals(ACCOUNT_UNCHECKED, $response->getState());
    }

    public function testRetryUnknownError()
    {
        $request = $this->getRequest('Checker.RetryConfirmation', '-u');
        $response = new CheckConfirmationResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->row->setRetries(1);
        $this->getCheckConfirmationWorker()
             ->processRequest($request, $response, $this->row);

        $this->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());
    }

}
