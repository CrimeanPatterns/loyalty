<?php

namespace Tests\Unit;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AwardWallet\Common\Itineraries\ItinerariesCollection;

/**
 * @backupGlobals disabled
 */
class CheckPastItinerariesTest extends \Tests\Unit\BaseWorkerTestClass
{

    protected function getRequest(){
        $request = new CheckAccountRequest();
        return $request->setProvider('testprovider')
                       ->setLogin('Itineraries.PastItineraries')
                       ->setUserid('SomeID')
                       ->setParseitineraries(true)
                       ->setPassword('g5f4'.rand(1000,9999).'_q');
    }

    /**
     * @dataProvider dataCheckItineraries
     */
    public function testPastItineraries(CheckAccountRequest $request, ?int $itCount)
    {
        $response = new CheckAccountResponse();
        $response->setRequestid(bin2hex(random_bytes(10)));
        $this->getCheckAccountWorker()
             ->processRequest($request, $response, $this->row);

        $this->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $itineraries = $response->getItineraries();
        if ($itCount === null) {
            $this->assertNull($itineraries);
        } else {
            $this->assertEquals($itCount, count($itineraries));
        }
    }

    public function dataCheckItineraries()
    {
        return [
            [$this->getRequest()->setParsePastItineraries(true), 3], // test With Past Its
            [$this->getRequest(), 1], // test Without Past Its
            [$this->getRequest()->setParseitineraries(false), null], // test Without Its
        ];
    }

    protected function getMasterSolver()
    {
        return $this->container->get('aw.solver.master');
    }

}
