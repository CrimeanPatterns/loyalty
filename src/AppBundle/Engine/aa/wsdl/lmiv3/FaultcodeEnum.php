<?php

namespace LMIV3;

class FaultcodeEnum
{
    public const __default = 'wsseUnsupportedSecurityToken';
    public const wsseUnsupportedSecurityToken = 'wsse:UnsupportedSecurityToken';
    public const wsseUnsupportedAlgorithm = 'wsse:UnsupportedAlgorithm';
    public const wsseInvalidSecurity = 'wsse:InvalidSecurity';
    public const wsseInvalidSecurityToken = 'wsse:InvalidSecurityToken';
    public const wsseFailedAuthentication = 'wsse:FailedAuthentication';
    public const wsseFailedCheck = 'wsse:FailedCheck';
    public const wsseSecurityTokenUnavailable = 'wsse:SecurityTokenUnavailable';
}
