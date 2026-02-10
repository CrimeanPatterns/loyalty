<?php

namespace Tests\Unit;
use AppBundle\Model\Resources\CheckConfirmationRequest;
use AppBundle\Model\Resources\CheckConfirmationResponse;
use AppBundle\Model\Resources\InputField;

/**
 * @backupGlobals disabled
 */
class CheckConfirmationTest extends \Tests\Unit\BaseWorkerTestClass
{

    public function testRetrieveByConfNo() {
        $fields = [];
        $fields[] = (new InputField())->setCode('ConfNo')->setValue('future.rental');
        $fields[] = (new InputField())->setCode('LastName')->setValue('TESTNAME');

        $request = new CheckConfirmationRequest();
        $request->setProvider('testprovider')
                ->setUserid(md5(time()))
                ->setPriority(9)
                ->setFields($fields);

        $response = new CheckConfirmationResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckConfirmationWorker()
             ->processRequest($request, $response, $this->row);

        $this->assertEquals(CONFNO_CHECKED, $response->getState());
    }

}