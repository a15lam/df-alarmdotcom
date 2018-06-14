<?php

namespace a15lam\Alarm\Services;

use DreamFactory\Core\Services\BaseRestService;

class Alarm extends BaseRestService {
    /** {@inheritdoc} */
    public function __construct(array $settings = [])
    {
        parent::__construct($settings);
    }

    protected function handleGET()
    {
        return ['msg' => 'hello'];
    }
}

