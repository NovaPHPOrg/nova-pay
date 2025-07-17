<?php

namespace nova\plugin\pay;

use nova\framework\core\ConfigObject;

class PayConfig extends ConfigObject
{
    public string $url = "";

    public string $client_id = "";

    public string $client_secret = "";
}