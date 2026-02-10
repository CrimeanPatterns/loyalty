<?php

namespace AppBundle\Worker\CheckExecutor;

interface BrowserExtensionRequestInterface
{

    public function getBrowserExtensionSessionId(): ?string;

}