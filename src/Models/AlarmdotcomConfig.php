<?php

namespace a15lam\Alarm\Models;


use DreamFactory\Core\Models\BaseServiceConfigModel;

class AlarmdotcomConfig extends BaseServiceConfigModel
{
    /** @var string */
    protected $table = 'alarmdotcom_config';

    /** @var array */
    protected $fillable = ['service_id', 'username', 'password'];

    protected $casts = [
        'service_id' => 'integer'
    ];

    /** @var array */
    protected $encrypted = ['password'];

    /** @var array */
    protected $protected = ['password'];

    /** {@inheritdoc} */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        switch ($schema['name']) {
            case 'username':
                $schema['label'] = 'Username';
                $schema['description'] = 'Alarm.com Username';
                break;
            case 'password':
                $schema['label'] = 'Password';
                $schema['description'] = 'Alarm.com Password';
                break;
        }
    }
}