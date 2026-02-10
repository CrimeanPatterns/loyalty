<?php

namespace AppBundle\Extension;

use AwardWallet\Common\Partner\CallbackAuthSource;
use AwardWallet\Common\Strings;
use Psr\Log\LoggerInterface;

class HttpCallbackSender
{

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var CallbackAuthSource
     */
    private $authSource;
    /**
     * @var \HttpDriverInterface
     */
    private $httpDriver;
    /**
     * @var \Throttler
     */
    private $throttler;

    public function __construct(LoggerInterface $logger, CallbackAuthSource $authSource, \HttpDriverInterface $httpDriver, \Throttler $throttler)
    {
        $this->logger = $logger;
        $this->authSource = $authSource;
        $this->httpDriver = $httpDriver;
        $this->throttler = $throttler;
    }

    public function sendCallback(string $partner, string $url, string $body, array $logContext = []) : bool
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'])) {
            $this->logger->info("bad callback url: {$url}", $logContext);
            return false;
        }
        $key = 'cbt_' . $parts['scheme'] . '_' . $parts['host'];
        $delay = $this->throttler->getDelay($key, true);
        if ($delay > 0) {
            $this->logger->info("throttled callback to {$parts['scheme']}://{$parts['host']}, delay: $delay", $logContext);
            return false;
        }

        $auth = $this->authSource->getByUrl($partner, $url);
        if ($auth === null) {
            $this->logger->warning("empty auth for {$partner}, {$url}", $logContext);
            return false;
        }
        $logContext['auth'] = $auth->getUsername() . ':***';

        $this->logger->info('sending callback to ' . $url, $logContext);
        $response = $this->httpDriver->request(new \HttpDriverRequest(
            $url,
            'POST',
            $body,
            [
                'Content-type' => 'application/json',
                'Expect' => '',
                'Authorization' => 'Basic ' . base64_encode($auth->getUsername() . ':' . $auth->getPassword()),
            ],
            30
        ));

        $success = $response->httpCode === 200;
        $this->logger->info(
            'callback result',
            array_merge($logContext, ['success' => $response->httpCode === 200, 'responseCode' => $response->httpCode, 'isValid' => $response->httpCode === 200, 'responseLength' => is_string($response->body) ? strlen($response->body) : 0, 'trimmedResponse' => Strings::cutInMiddle($response->body, 250), 'time' => $response->duration, 'errorCode' => $response->errorCode])
        );

        if (!$success && $response->duration < 60000) {
            // punish non-successful callbacks
            $this->logger->info("non successful callback for {$partner}");
            $response->duration = 1000;
        }

        if ($response->duration >= 1000) { # 1000 only for juicymiles, as temporary measure, revert to 500
            $this->throttler->increment($key, round($response->duration / 1000));
        }

        return $success;
    }

}