<?php

namespace a15lam\Alarm\Services;

use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Services\BaseRestService;
use Cache;
use DreamFactory\Core\Utility\Curl;

class Alarm extends BaseRestService {
    const SESSION_ID_CACHE_KEY = 'alarm-session-id';
    const AUTH_CACHE_KEY = 'alarm-auth-key';
    const LOGIN_CACHE_KEY = 'alarm-login-key';

    const SESSION_COOKIE = 'ASP_NET_SessionId';
    const SESSION_COOKIE_REQUEST = 'ASP.NET_SessionId';
    const AUTH_COOKIE = 'auth_CustomerDotNet';
    const UNIQUE_KEY_COOKIE = 'afg';
    const UNIQUE_KEY_HEADER = 'ajaxrequestuniquekey';

    const USERNAME_FIELD_ID = 'ctl00_ContentPlaceHolder1_loginform_txtUserName';
    const PASSWORD_FIELD_NAME = 'txtPassword';

    protected $username = null;
    protected $password = null;
    protected $lastSessionId = null;

    /** {@inheritdoc} */
    public function __construct(array $settings = [])
    {
        parent::__construct($settings);

        $this->username = env('ALARM_USERNAME');
        $this->password = env('ALARM_PASSWORD');
    }

    protected function handleGET()
    {
        $resource = $this->resource;
        try{
            $sensors = $this->handle($resource);
        } catch (UnauthorizedException $e) {
            $this->bustCache();
            $sensors = $this->handle($resource);
            $sensors['cache_busted'] = true;
        }

        return ['sensor' => $sensors];
    }

    /**
     * @param null $id
     * @return null
     * @throws UnauthorizedException
     */
    protected function handle($id=null)
    {
        $this->lastSessionId = $sessionId = $this->getSessionId();
        $loginInfo = $this->getLoginInfo();
        $loginInfo[static::SESSION_COOKIE] = $sessionId;
        $authInfo = $this->getAuthInfo($loginInfo);
        $sensors = $this->getSensors($authInfo, $id);

        return $sensors;
    }

    protected function bustCache()
    {
        Cache::forget(static::SESSION_ID_CACHE_KEY);
        Cache::forget(static::AUTH_CACHE_KEY);
        Cache::forget(static::LOGIN_CACHE_KEY);
    }

    protected function getSessionId()
    {
        $sessionId = Cache::get(static::SESSION_ID_CACHE_KEY);

        if(!$sessionId) {
            $ch = curl_init('https://www.alarm.com/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            $result = curl_exec($ch);
            $sessionId = $this->getCookieValue($result, static::SESSION_COOKIE);
            Cache::put(static::SESSION_ID_CACHE_KEY, $sessionId, 30);
        }

        return $sessionId;
    }

    protected function getSensors($auth, $id=null)
    {
        $cookies = static::SESSION_COOKIE_REQUEST.'='.$auth[static::SESSION_COOKIE];
        $cookies .= ';'.static::AUTH_COOKIE.'='.$auth[static::AUTH_COOKIE];
        $headers = [static::UNIQUE_KEY_HEADER.': '. $auth[static::UNIQUE_KEY_COOKIE]];
        $result = Curl::get('https://www.alarm.com/web/api/devices/sensors', [], [
            CURLOPT_COOKIE => $cookies,
            CURLOPT_HTTPHEADER => $headers
        ]);

        if(Curl::getLastHttpCode() === 403){
            throw new UnauthorizedException('Unauthorized');
        }

        $data = $result->{'value'};

        if($id){
            foreach ($data as $sensor) {
                if($sensor->{'id'} === $id){
                    return $sensor;
                }
            }
            return [];
        } else {
            return $data;
        }
    }

    protected function getLoginInfo()
    {
        $inputs = Cache::get(static::LOGIN_CACHE_KEY, []);

        if(empty($inputs)) {
            $attributes = [
                '__VIEWSTATE',
                '__VIEWSTATEGENERATOR',
                '__PREVIOUSPAGE',
                '__EVENTVALIDATION',
                'IsFromNewSite',
                static::USERNAME_FIELD_ID,
                static::PASSWORD_FIELD_NAME
            ];
            $result = Curl::get('https://www.alarm.com/login.aspx');
            $dom = new \DOMDocument();
            @$dom->loadHTML($result);
            foreach ($dom->getElementsByTagName('input') as $input) {
                $id = $input->getAttribute('id');
                $name = $input->getAttribute('name');
                if (in_array($id, $attributes) || in_array($name, $attributes)) {
                    $value = $input->getAttribute('value');
                    if ($id === static::USERNAME_FIELD_ID) {
                        $value = $this->username;
                    } else if ($name === static::PASSWORD_FIELD_NAME) {
                        $value = $this->password;
                    }
                    $inputs[$input->getAttribute('name')] = $value;
                }
            }

            Cache::put(static::LOGIN_CACHE_KEY, $inputs, 10);
        }

        return $inputs;
    }

    protected function getAuthInfo($login)
    {
        $authData = Cache::get(static::AUTH_CACHE_KEY);

        if(!$authData) {
            $authData = $this->doLogin($login);

            Cache::put(static::AUTH_CACHE_KEY, $authData, 5);
        }

        return $authData;
    }

    protected function doLogin($login)
    {
        $cookies = static::SESSION_COOKIE . '=' . array_get($login, static::SESSION_COOKIE);
        $postData = $this->getLoginPostData($login);
        $ch = curl_init('https://www.alarm.com/web/Default.aspx');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $result = curl_exec($ch);

        return $this->getCookies($result);
    }

    protected function getLoginPostData($login)
    {
        $data = [];
        foreach ($login as $k => $v){
            if($k !== static::SESSION_COOKIE) {
                $data[] = $k . '=' . $v;
            }
        }

        return join('&', $data);
    }


    protected function getCookieValue($response, $cookieName, $default = null)
    {
        $cookies = $this->getCookies($response);

        return array_get($cookies, $cookieName, $default);
    }

    protected function getCookies($response)
    {
        // get cookie
        // multi-cookie variant contributed by @Combuster in comments
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $response, $matches);
        $cookies = [];
        foreach ($matches[1] as $item) {
            parse_str($item, $cookie);
            $cookies = array_merge($cookies, $cookie);
        }

        return $cookies;
    }
}

