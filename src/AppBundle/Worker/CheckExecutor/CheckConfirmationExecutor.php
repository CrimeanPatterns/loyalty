<?php

namespace AppBundle\Worker\CheckExecutor;

use AppBundle\Document\BaseDocument;
use AppBundle\Document\CheckConfirmation;
use AppBundle\Model\Resources\BaseCheckRequest;
use AppBundle\Model\Resources\CheckConfirmationRequest;
use AppBundle\Model\Resources\CheckConfirmationResponse;
use AppBundle\Model\Resources\InputField;

class CheckConfirmationExecutor extends BaseExecutor implements ExecutorInterface
{

    protected $RepoKey = CheckConfirmation::METHOD_KEY;

    /**
     * @param \TAccountChecker $checker
     * @param CheckConfirmationRequest $request
     * @param BaseDocument $row
     * @param bool $fresh
     * @throws \CheckAccountExceptionInterface
     */
    protected function processChecker(\TAccountChecker $checker, $request, BaseDocument $row, bool $fresh = true)
    {
        try {
            $result = $checker->CheckConfirmationNumber($checker->AccountFields['ConfFields'], $checker->Itineraries, []);
            $checker->ErrorCode = CONFNO_CHECKED;
            $checker->ErrorMessage = '';
            if(isset($result)){
                $checker->ErrorMessage = addslashes(CleanXMLValue($result));
                $checker->ErrorCode = CONFNO_INVALID;
            }
        } catch (\CheckRetryNeededException $e) {
            $this->processRetryException($e, $checker, $row);
        } catch (\ErrorException $e) {
            $errorMessage = $e->getMessage() . ' at ' . $e->getFile() . ' line ' . $e->getLine();
            $this->logger->error($errorMessage);
            $checker->itinerariesMaster->clearItineraries();
            $checker->ErrorCode = CONFNO_CHECKED;
            $checker->ErrorMessage = '';
        }
    }

    /**
     * @param \TAccountChecker $checker
     * @param CheckConfirmationRequest $request
     * @param CheckConfirmationResponse $response
     * @param integer $apiVersion
     */
    protected function prepareResponse(\TAccountChecker $checker, $request, &$response, $apiVersion, string $partner){
        $response->addDebuginfo($checker->DebugInfo)
                 ->setErrorreason($checker->ErrorReason)
                 ->setCheckdate(new \DateTime())
                 ->setMessage($checker->ErrorMessage)
                 ->setState($checker->ErrorCode)
                 ->setUserdata($request->getUserdata());

        $itinerariesXML = $this->handleAccountCheckerItineraries($checker, $apiVersion, $partner);
        $response->setItineraries($itinerariesXML);
        if (!in_array($checker->ErrorCode, [CONFNO_CHECKED, CONFNO_INVALID])
            || ($checker->ErrorCode === CONFNO_CHECKED && count($response->getItineraries()) === 0)
        ) {
            $fields = [];
            /** @var InputField $field */
            foreach ($request->getFields() as $field) {
                $fields[$field->getCode()] = $field->getValue();
            }
            $this->logger->notice('Failed retrieve', ['Provider' => $request->getProvider(), 'Fields' => json_encode($fields)]);
        }
        // debug
        if(!in_array($checker->ErrorCode, [0, 1, 6, 100]))
            $this->logger->notice('Result state not in [0,1,6,100] array', ['state' => $checker->ErrorCode]);
        //
    }

    /**
     * @param CheckConfirmationResponse $response
     * @param CheckConfirmation $row
     */
    protected function saveResponse($response, &$row) {
        $row->setResponse(json_decode($this->serializer->serialize($response, 'json'), true))
            ->setUpdatedate(new \DateTime());
        $this->manager->persist($row);
        $this->manager->flush();
    }

    /**
     * @param CheckConfirmationRequest $request
     * @param string $partner
     * @return array
     */
    protected function prepareAccountInfo(BaseCheckRequest $request, string $partner, BaseDocument $doc): array
    {
        $fields = [];
        /** @var InputField $field */
        foreach($request->getFields() as $field) {
            $fields[$field->getCode()] = $field->getValue();
        }

        $result = array_merge(
            parent::prepareAccountInfo($request, $partner, $doc),
            [
                //'AccountID' => 0, // for compatibility
                'ConfFields' => $fields,
            ]
        );

        // testpovider checker hack
        if ($request->getProvider() === 'testprovider') {
            $result['Login'] = $result['ConfFields']['ConfNo'];
        }

        return $result;
    }

    public function getMongoDocumentClass(): string
    {
        return CheckConfirmation::class;
    }

    protected function getRequestClass(int $apiVersion): string
    {
        return CheckConfirmationRequest::class;
    }

    protected function getResponseClass(int $apiVersion): string
    {
        if (2 === $apiVersion) {
            return \AppBundle\Model\Resources\V2\CheckConfirmationResponse::class;
        }

        return CheckConfirmationResponse::class;
    }

    public function getMethodKey(): string
    {
        return CheckConfirmation::METHOD_KEY;
    }
}