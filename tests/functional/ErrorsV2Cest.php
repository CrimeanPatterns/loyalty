<?php 
namespace Tests\Functional;

/**
 * @backupGlobals disabled
 */
class ErrorsV2Cest extends ErrorsCest
{
    protected $urlProviders = "/v2/providers/list";
    protected $urlCheckAccount = "/v2/account/check";
}