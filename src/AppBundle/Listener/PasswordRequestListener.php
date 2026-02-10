<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 18/04/2018
 * Time: 17:40
 */

namespace AppBundle\Listener;


use AppBundle\Document\PasswordRequestDocument;
use AppBundle\Event\CheckAccountStartEvent;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\PasswordRequestResult;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\Serializer;
use Psr\Log\LoggerInterface;

class PasswordRequestListener
{
    /** @var DocumentManager */
    private $dm;
    /** @var Serializer */
    private $serializer;
    /** @var string */
    private $passwordVaultUrl;
    /** @var \HttpDriverInterface */
    private $httpDriver;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(DocumentManager $dm, Serializer $serializer, \HttpDriverInterface $httpDriver, $passwordVaultUrl, LoggerInterface $logger)
    {
        $this->dm = $dm;
        $this->serializer = $serializer;
        $this->httpDriver = $httpDriver;
        $this->passwordVaultUrl = $passwordVaultUrl;
        $this->logger = $logger;
    }

    public function onCheckAccountStart(CheckAccountStartEvent $event)
    {
        $checkAccountRow = $event->getRow();
        /** @var CheckAccountRequest $request */
        $request = $this->serializer->deserialize(json_encode($checkAccountRow->getRequest()), CheckAccountRequest::class, 'json');

        $repo = $this->dm->getRepository(PasswordRequestDocument::class);
        $foundBy = null;
        $passwordRequestRow = $repo->findOneBy([
            'partner' => $checkAccountRow->getPartner(),
            'provider' => $request->getProvider(),
            'login' => $request->getLogin()
        ]);
        if ($passwordRequestRow) {
            $foundBy = "partner,provider,login";
        } else {
            $passwordRequestRow = $repo->findOneBy([
                'partner' => $checkAccountRow->getPartner(),
                'provider' => $request->getProvider(),
                'login' => null,
            ]);
            if (!empty($passwordRequestRow)) {
                $foundBy = "partner,provider";
            }
        }

        if (!$passwordRequestRow) {
            return;
        }

        $this->logger->info("sending password request result, found by $foundBy", ["partner" => $checkAccountRow->getPartner(), "provider" => $request->getProvider(), "login" => $request->getLogin()]);
        $pass = !empty($request->getPassword()) ? DecryptPassword($request->getPassword()) : ''; // expected string, not null
        $result = (new PasswordRequestResult())
                    ->setPartner($checkAccountRow->getPartner())
                    ->setProvider($request->getProvider())
                    ->setLogin($request->getLogin())
                    ->setLogin2($request->getLogin2())
                    ->setLogin3($request->getLogin3())
                    ->setUserId($passwordRequestRow->getUserId())
                    ->setPassword(trim($pass))
                    ->setNote($passwordRequestRow->getNote());

        $httpRequest = new \HttpDriverRequest(
            $this->passwordVaultUrl,
            'POST',
            $this->serializer->serialize($result, 'json')
        );
        $httpResponse = $this->httpDriver->request($httpRequest);
        if (\HttpDriverResponse::HTTP_OK === $httpResponse->httpCode) {
            // removing from passwordRequest collection
            $this->dm->remove($passwordRequestRow);
        } else {
            $this->logger->warning("failed to send password request result", ["httpCode" => $httpResponse->httpCode, "body" => $httpResponse->body]);
        }
    }

}