<?php

class ResponseStatusMessageEnum
{
    public const __default = 'AAdvantageCustomerIdentifierRequired';
    public const AAdvantageCustomerIdentifierRequired = 'AAdvantageCustomerIdentifier Required';
    public const AADVContactInvalid = 'AADV Contact Invalid';
    public const ContactReasonIdentifierNotFound = 'Contact Reason Identifier Not Found';
    public const ContactTypeNotAllowedForSubscription = 'Contact Type Not Allowed For Subscription';
    public const ContactValueMustBeSameInSubscriptionAndCustomer = 'Contact Value Must Be Same In Subscription And Customer';
    public const CUPIDNotFound = 'CUPID Not Found';
    public const CurrencyCodeNotFound = 'Currency Code Not Found';
    public const CustomerContactReasonAlreadyExist = 'Customer Contact Reason Already Exist';
    public const CustomerResolutionTransactionAlreadyExists = 'CustomerResolutionTransaction Already Exists';
    public const DiscontinuedContactReasonCanNotBeAssignedToCustomer = 'Discontinued Contact Reason Can Not Be Assigned To Customer';
    public const DuplicateContact = 'Duplicate Contact';
    public const DuplicateKey = 'Duplicate Key';
    public const ExceedingSubscriptionAllowContactQty = 'Exceeding Subscription Allow Contact Qty';
    public const NoResults = 'No Results';
    public const PNRIdentifierNotFound = 'PNR Identifier Not Found';
    public const ProviderSystemError = 'Provider System Error';
    public const ResolutionIdentifierNotFound = 'Resolution Identifier Not Found';
    public const SubscriptionAlreadyExistForThisProductCode = 'Subscription Already Exist For This Product Code';
    public const Success = 'Success';
    public const UnknownError = 'Unknown Error';
}
