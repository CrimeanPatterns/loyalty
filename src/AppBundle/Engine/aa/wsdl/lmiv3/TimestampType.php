<?php

namespace LMIV3;

class TimestampType
{
    /**
     * @var AttributedDateTime
     */
    public $Created = null;

    /**
     * @var AttributedDateTime
     */
    public $Expires = null;

    /**
     * @var string
     */
    public $any = null;

    /**
     * @var ID
     */
    public $Id = null;

    /**
     * @param AttributedDateTime $Created
     * @param AttributedDateTime $Expires
     * @param string $any
     * @param ID $Id
     */
    public function __construct($Created, $Expires, $any, $Id)
    {
        $this->Created = $Created;
        $this->Expires = $Expires;
        $this->any = $any;
        $this->Id = $Id;
    }
}
