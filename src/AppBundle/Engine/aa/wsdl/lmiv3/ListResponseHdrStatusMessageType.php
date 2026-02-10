<?php

namespace LMIV3;

class ListResponseHdrStatusMessageType
{
    public const __default = 'InvalidWSDLversion';
    public const InvalidWSDLversion = 'Invalid WSDL version';
    public const NoResults = 'No Results';
    public const PartialResults = 'Partial Results';
    public const SchemaValidationError = 'Schema Validation Error';
    public const Success = 'Success';
    public const UnknownError = 'Unknown Error';
}
