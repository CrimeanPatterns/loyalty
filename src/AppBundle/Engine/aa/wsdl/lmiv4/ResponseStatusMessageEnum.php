<?php

namespace LMIV4;

class ResponseStatusMessageEnum
{
    public const __default = 'AAdvantageAccountNotFoundForCUPID';
    public const AAdvantageAccountNotFoundForCUPID = 'AAdvantage Account Not Found For CUPID';
    public const CannotUpdateMemberHasSecurityRestrictions = 'Cannot Update-Member Has Security Restrictions';
    public const CannotUpdateMemberisaFromMergerAccount = 'Cannot Update-Member is a From Merger Account';
    public const CannotUpdateMemberispartofaMergeinProcess = 'Cannot Update-Member is part of a Merge in Process';
    public const CUPIDNotFound = 'CUPID Not Found';
    public const InvalidcharactersinName = 'Invalid characters in Name';
    public const InvalidPreferenceAndGroup = 'Invalid Preference And Group';
    public const NoResults = 'No Results';
    public const PermissionDeniedChangeRulesviolated = 'Permission Denied-Change Rules violated';
    public const ProviderSystemError = 'Provider System Error';
    public const Success = 'Success';
    public const UnknownError = 'Unknown Error';
}
