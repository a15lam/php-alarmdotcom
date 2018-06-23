<?php

namespace a15lam\AlarmDotCom;

use a15lam\AlarmDotCom\Workspace as WS;
use a15lam\Workspace\Utility\ArrayFunc;
use a15lam\Workspace\Utility\Request;

class AlarmDotCom
{
    const SESSION_URL = 'https://www.alarm.com/';
    const LOGIN_URL = 'https://www.alarm.com/login.aspx';
    const AUTH_URL = 'https://www.alarm.com/web/Default.aspx';
    const SENSOR_URL = 'https://www.alarm.com/web/api/devices/sensors';

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

    public function __construct($config=[])
    {
        $this->username = ArrayFunc::get($config, 'username', WS::env('ALARMDOTCOM_USERNAME'));
        $this->password = ArrayFunc::get($config, 'password', WS::env('ALARMDOTCOM_PASSWORD'));
    }

    public function sensors($id=null)
    {
        try{
            $sensors = $this->handle($id);
        } catch (\Exception $e) {
            $this->bustCache();
            $sensors = $this->handle($id);
        }

        return ['sensor' => $sensors];
    }

    protected function bustCache()
    {
        Cache::forget(static::SESSION_ID_CACHE_KEY);
        Cache::forget(static::AUTH_CACHE_KEY);
        Cache::forget(static::LOGIN_CACHE_KEY);
    }

    protected function handle($id=null)
    {
        $sessionId = $this->getSessionId();
        $loginInfo = $this->getLoginInfo();
        $loginInfo[static::SESSION_COOKIE] = $sessionId;
        $authInfo = $this->getAuthInfo($loginInfo);
        $sensors = $this->getSensors($authInfo, $id);

        return $sensors;
    }

    protected function getSessionId()
    {
        $sessionId = Cache::get(static::SESSION_ID_CACHE_KEY);

        if(empty($sessionId)) {
            $rq = new Request(static::SESSION_URL);
            $sessionId = $rq->getResponseCookies(static::SESSION_COOKIE);
            Cache::put(static::SESSION_ID_CACHE_KEY, $sessionId);
        }

        return $sessionId;
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
            $rq = new Request(static::LOGIN_URL);
            $result = $rq->getResponseBody();
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

            Cache::put(static::LOGIN_CACHE_KEY, $inputs);
        }

        return $inputs;
    }

    protected function getAuthInfo($login)
    {
        $authData = Cache::get(static::AUTH_CACHE_KEY);

        if(!$authData) {
            $authData = $this->doLogin($login);

            Cache::put(static::AUTH_CACHE_KEY, $authData);
        }

        return $authData;
    }

    protected function doLogin($login)
    {
        $cookies = static::SESSION_COOKIE . '=' . ArrayFunc::get($login, static::SESSION_COOKIE);
        $postData = $this->getLoginPostData($login);
        $rq = new Request(static::AUTH_URL, [
            CURLOPT_POST => true,
            CURLOPT_COOKIE => $cookies,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS => $postData
        ]);

        return $rq->getResponseCookies();
    }

    protected function getSensors($auth, $id=null)
    {
        $cookies = static::SESSION_COOKIE_REQUEST.'='.ArrayFunc::get($auth, static::SESSION_COOKIE);
        $cookies .= ';'.static::AUTH_COOKIE.'='.ArrayFunc::get($auth, static::AUTH_COOKIE);
        $headers = [static::UNIQUE_KEY_HEADER.': '. ArrayFunc::get($auth, static::UNIQUE_KEY_COOKIE)];

        $rq = new Request(static::SENSOR_URL, [
            CURLOPT_COOKIE => $cookies,
            CURLOPT_HTTPHEADER => $headers
        ]);
        $result = $rq->getResponseBody();
        $statusCode = $rq->getStatusCode();
        if($statusCode === 403 || !is_array($result)){
            throw new \Exception('Unauthorized');
        }

        $data = ArrayFunc::get($result, 'value');

        if($id){
            foreach ($data as $sensor) {
                if($sensor['id'] === $id){
                    return $sensor;
                }
            }
            return [];
        } else {
            return $data;
        }
    }

    /**
     * @param $login
     * @return string
     */
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

    /**
     * @param      $response
     * @param      $cookieName
     * @param null $default
     * @return mixed
     */
    protected function getCookieValue($response, $cookieName, $default = null)
    {
        $cookies = $this->getCookies($response);

        return ArrayFunc::get($cookies, $cookieName, $default);
    }

    /**
     * @param $response
     * @return array
     */
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