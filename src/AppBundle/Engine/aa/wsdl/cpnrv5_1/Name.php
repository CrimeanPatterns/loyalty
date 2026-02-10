<?php

namespace CPNRV5_1;

class Name
{
    /**
     * @var FirstName
     */
    public $FirstName = null;

    /**
     * @var LastName
     */
    public $LastName = null;

    /**
     * @var MiddleName
     */
    public $MiddleName = null;

    /**
     * @var PrefixTitle
     */
    public $PrefixTitle = null;

    /**
     * @var SuffixTitle
     */
    public $SuffixTitle = null;

    /**
     * @var PreferredName
     */
    public $PreferredName = null;

    /**
     * @param FirstName $FirstName
     * @param LastName $LastName
     * @param MiddleName $MiddleName
     * @param PrefixTitle $PrefixTitle
     * @param SuffixTitle $SuffixTitle
     * @param PreferredName $PreferredName
     */
    public function __construct($FirstName, $LastName, $MiddleName, $PrefixTitle, $SuffixTitle, $PreferredName)
    {
        $this->FirstName = $FirstName;
        $this->LastName = $LastName;
        $this->MiddleName = $MiddleName;
        $this->PrefixTitle = $PrefixTitle;
        $this->SuffixTitle = $SuffixTitle;
        $this->PreferredName = $PreferredName;
    }
}
