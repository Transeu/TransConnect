<?php

use Curl\Curl;
/**
 * Client Trans API
 * Generated: 2013-10-01 23:17:45
 */
class JSONRPC2ClientSecure
{
    protected static $_id = 1;
    protected $_url;
    protected $_class = null;
    protected $_response = null;
    protected $_notification = false;
    protected $_apiKey;
    protected $_secretKey;
    protected $_userSecretKey;
    protected $_debug = false;
    protected $_debugMessages = array();
    protected $_curl;
    protected $_curlOptions;

    public function __construct($url, $apiKey, $secretKey, $userSecretKey = null, array $curlOptions = array())
    {
        $this->_url = $url;
        $this->_apiKey = $apiKey;
        $this->_secretKey = $secretKey;
        $this->_userSecretKey = $userSecretKey;
        $this->_curlOptions = $curlOptions;
    }

    public function __call($method, $params)
    {
        if (!is_scalar($method)) {
            throw new ApiException('Method name has no scalar value');
        }
        if (!is_array($params)) {
            throw new ApiException('Params must be given as array');
        }

        // zaktualizuj klase jesli zostala zmieniona
        if ($this->_class !== null) {
            $this->_setUrlParam('class', $this->_class);
        }

        // zalacz parametry autentykacji,
        // przygotuj request
        // i wykonaj polaczenie
        $params = $this->_appendAuthParams($params);
        $requestData = $this->_prepareRequestData($method, $params);
        $this->_performConnection($requestData);

        // zwroc wynik zapytania API
        $response = $this->getResponse();
        if (isset($response['error'])) {
            throw new ApiException($response['error']['message'], $response['error']['code'], $response['error']['data']);
        }
        return $response['result'];
    }

    /**
     * @param Curl $curl
     */
    public function setHttpClient(Curl $curl)
    {
        $this->_curl = $curl;
    }

    /**
     * Ustawia klase, ktorej metody beda wywolywane.
     *
     * @param type $className nazwa klasy
     * @return JSONRPC2ClientSecure
     */
    public function setClass($className)
    {
        if (is_string($className)) {
            $this->_class = $className;
        }
        return $this;
    }

    /**
     * Pobiera nazwe klasy, ktorej metody beda wywolywane.
     *
     * @return string
     */
    public function getClass()
    {
        return $this->_class;
    }

    public function setUserSecretKey($userSecretKey)
    {
        $this->_userSecretKey = $userSecretKey;
        return $this;
    }

    public function getUserSecretKey()
    {
        return $this->_userSecretKey;
    }

    public function getResponse()
    {
        return $this->_response;
    }

    public function setDebug($value = true)
    {
        $this->_debug = (bool) $value;
        return $this;
    }

    public function getDebugMessages()
    {
        return $this->_debugMessages;
    }

    public function setNotification($value = true)
    {
        $this->_notification = (bool) $value;
        return $this;
    }

    protected function _setUrlParam($name, $value)
    {
        $parsed = parse_url($this->_url);
        $params = array();

        if (!isset($parsed['query'])) {
            $parsed['query'] = '';
        }

        parse_str($parsed['query'], $params);

        $params[$name] = $value;
        $params_str = http_build_query($params);

        $parsed['query'] = $params_str;
        $this->_url = Request::buildUrl($parsed);
    }

    protected function _getCurrentId()
    {
        return ($this->_notification) ? null : self::$_id;
    }

    protected function _increaseId()
    {
        if (!$this->_notification) {
            self::$_id++;
        }
    }

    protected function _prepareRequestData($method, $params)
    {
        $params = array_values($params);
        $request = array(
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $this->_getCurrentId(),
        );

        $this->_addDebugMessage('Request: ' . json_encode($request));

        return $request;
    }

    protected function _performConnection($requestData)
    {
        $this->_getHttpClient()->setHeader('Content-type', 'application/json');

        $response = $this->_getHttpClient()->post($this->_url, $requestData);

        $this->_addDebugMessage('Response: ' . $this->_getHttpClient()->raw_response);

        if ($this->_getHttpClient()->error) {
            throw new ApiException("Unable to connect to {$this->_url}. {$this->_getHttpClient()->error_message}");
        }

        if (is_string($response)) {
            $response = json_decode($response, true);
        }

        if ($response === null) {
            throw new ApiException('Incorrect response JSON format');
        }

        if ($this->_notification) {
            return true;
        }

        if ($response['id'] != $this->_getCurrentId()) {
            throw new ApiException('Incorrect response id (request id: ' . $this->_getCurrentId()
                . ', response id: ' . $response['id'] . ')');
        }

        $this->_response = $response;
        $this->_increaseId();
    }

    protected function _appendAuthParams($params)
    {
        $auth_params = array(
            'auth_apikey' => $this->_apiKey,
            'auth_timestamp' => time(),
            'auth_nonce' => $this->_generateNonce(),
        );

        if ($this->_userSecretKey !== null) {
            $auth_params['auth_userkey'] = $this->_userSecretKey;
        }

        $auth_params['auth_signature'] = $this->_generateSignature($auth_params, $params);

        $appended = array_pad($params, -(count($params) + 1), $auth_params);
        return $appended;
    }

    protected function _generateSignature($auth_params, $params)
    {
        $params = Request::assignKeyNames($params);
        $params = array_merge($auth_params, $params);

        $request = new Request('POST', $this->_url, $params);
        $signatureMethod = new SignatureHmacSha1();
        $signature = $signatureMethod->generateSignature($request, $this->_secretKey, null);

        return UrlEncoder::encode($signature);
    }

    protected function _generateNonce()
    {
        return uniqid(mt_rand(), true);
    }

    protected function _addDebugMessage($message)
    {
        if (!$this->_debug) {
            return false;
        }
        if ((!$key = ($this->_getCurrentId()))) {
            $key = 'notifications';
        }
        $this->_debugMessages[$key][] = $message;
    }

    protected function _getHttpClient()
    {
        if ($this->_curl === null) {
            $this->_curl = new Curl();
            $this->__setOptions();
            $this->_setJsonDecoder();
        }

        return $this->_curl;
    }

    protected function _setOptions()
    {
        $defaultOptions = array(
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER, 0,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 120
        );

        $options = array_merge($defaultOptions, $this->_curlOptions);

        foreach ($options as $optionName => $optionsValue) {
            $this->_curl->setOpt($optionName, $optionsValue);
        }
    }

    protected function _setJsonDecoder()
    {
        $this->_curl->setJsonDecoder(function($response) {
            $data = json_decode($response, true);
            if (!($data === null)) {
                $response = $data;
            }
            return $response;
        });
    }
}

/**
 * Klasa odzwierciedlajaca request, na podstawie ktorego
 * bedzie generowana sygnatura.
 */
class Request
{
    protected $_http_method;
    protected $_http_url;
    protected $_parameters;

    /**
     * @param type $http_method metoda HTTP (np. GET, POST)
     * @param type $http_url
     * @param type $parameters
     */
    public function __construct($http_method, $http_url, $parameters = null)
    {
        $this->_http_method = $http_method;
        $this->_http_url = $http_url;

        if (!$parameters) {
            $parameters = array();
        }

        $parameters = array_merge(self::parseParameters(
            parse_url($http_url, PHP_URL_QUERY)), $parameters);

        $this->_parameters = $parameters;
    }

    public function setParameter($name, $value)
    {
        $this->_parameters[$name] = $value;
        return $this;
    }

    public function getParameter($name)
    {
        return (isset($this->_parameters[$name])) ? $this->_parameters[$name] : null;
    }

    public function unsetParameter($name)
    {
        unset($this->_parameters[$name]);
    }

    /**
     * Zwraca znormalizowana postac metody HTTP.
     *
     * @return string
     */
    public function getNormalizedMethod()
    {
        return strtoupper($this->_http_method);
    }

    /**
     * Zwraca znormalizowana postac URL.
     *
     * @return string
     */
    public function getNormalizedUrl()
    {
        $parts = parse_url($this->_http_url);

        $scheme = (isset($parts['scheme'])) ? $parts['scheme'] : 'http';

        $port = (isset($parts['port'])) ? $parts['port'] : (($scheme == 'https') ? '443' : '80');

        if (isset($parts['host'])) {
            $host = strtolower($parts['host']);
            $path = (isset($parts['path'])) ? $parts['path'] : '/';
        } else {
            $host = (isset($parts['path'])) ? strtolower($parts['path']) : '';
            $path = '/';
        }

        if (($scheme == 'https' && $port != '443') || ($scheme == 'http' && $port != '80')) {
            $host = "{$host}:{$port}";
        }

        return "{$scheme}://{$host}{$path}";
    }

    /**
     * Zwraca znormalizowana postac parametrow.
     *
     * @return string
     */
    public function getNormalizedParameters($newStandard)
    {
        $parameters = $this->_parameters;
        ksort($parameters);

        if (isset($parameters['auth_signature'])) {
            unset($parameters['auth_signature']);
        }

        $query = http_build_query($parameters);
        if ($newStandard == true) {
            $query = str_replace('+', '%20', $query);
        }

        return $query;
    }

    /**
     * Generuje lancuch znakow bedacy podstawa
     * przy pozniejszym tworzeniu sygnatury.
     *
     * @return string
     */
    public function generateBaseString($newStandard = false)
    {
        $method = UrlEncoder::encode($this->getNormalizedMethod());
        $url = UrlEncoder::encode($this->getNormalizedUrl());
        $parameters = UrlEncoder::encode($this->getNormalizedParameters($newStandard));

        return "{$method}&{$url}&{$parameters}";
    }

    /**
     * Parsuje wejsciowy string bedacy parametrami URL.
     *
     * @param string $input
     * @return array
     */
    public static function parseParameters($input)
    {
        if (!isset($input) || empty($input)) {
            return array();
        }

        $parsed = array();
        $parameters = explode('&', $input);
        foreach ($parameters as $pairs) {
            list($key, $value) = explode('=', $pairs, 2) + array(null, null);
            $parsed[$key] = $value;
        }

        return $parsed;
    }

    /**
     * Zwraca tablice naglowkow request'a HTTP.
     *
     * @return array
     */
    public static function getRequestHeaders()
    {
        $headers = apache_request_headers();

        $result = array();
        foreach ($headers as $key => $value) {
            $key = str_replace(' ', '-', ucwords(strtolower(str_replace('-', ' ', $key))));
            $result[$key] = $value;
        }

        return $result;
    }

    protected static function _getServerParams()
    {
        $sScheme = (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on') ? 'http' : 'https';
        $sHost = $_SERVER['HTTP_HOST'];
        $sPort = $_SERVER['SERVER_PORT'];

        $oConfigApi = Zend_Registry::getInstance()->get('appConfig')->api;

        if ($sScheme == $oConfigApi->exception->scheme && $sHost == $oConfigApi->exception->host && $sPort == $oConfigApi->exception->port) {

            $sScheme = $oConfigApi->proper->scheme;
            $sHost = $oConfigApi->proper->host;
            $sPort = $oConfigApi->proper->port;
        }

        return array(
            'scheme' => $sScheme,
            'host' => $sHost,
            'port' => $sPort,
        );
    }

    /**
     * Tworzy obiekt Request
     * na podstawie request'a HTTP.
     *
     * @param string $http_method
     * @param string $http_url
     * @param array $parameters
     * @return Request
     */
    public static function createFromRequest($http_method = null, $http_url = null, $parameters = null)
    {
        $aServerParams = self::_getServerParams();
        $scheme = $aServerParams['scheme'];
        $host = $aServerParams['host'];
        $port = $aServerParams['port'];

        $http_url = ($http_url) ? $http_url : $scheme . '://' .
            $host . ':' .
            $port .
            $_SERVER['REQUEST_URI'];

        $http_method = ($http_method) ? $http_method : $_SERVER['REQUEST_METHOD'];

        if (!$parameters) {
            $parameters = self::parseParameters($_SERVER['QUERY_STRING']);

            $headers = self::getRequestHeaders();
            if ($http_method == 'POST' && isset($headers['Content-Type']) && strstr($headers['Content-Type'], 'application/x-www-form-urlencoded')
            ) {
                $postData = self::parseParameters($HTTP_RAW_POST_DATA);
                $parameters = array_merge($parameters, $postData);
            }
        }

        return new self($http_method, $http_url, $parameters);
    }

    /**
     * Przerabia zwykla tablice na tablice asocjacyjna
     * gdzie indeks `0` bedzie mial postac `param_1`, itd.
     *
     * @param array $params
     * @return array
     */
    public static function assignKeyNames($params)
    {
        $named = array();
        foreach ($params as $index => $value) {
            $named['param_' . ($index + 1)] = $value;
        }
        return $named;
    }

    /**
     * Buduje url na podstawie tablicy.
     *
     * @param array $url_data
     * @return string
     */
    public static function buildUrl(array $url_data)
    {
        $url = $url_data['scheme'] . '://';
        if (isset($url_data['user'])) {
            $url .= $url_data['user'];
            if (isset($url_data['pass'])) {
                $url .= ':' . $url_data['pass'];
            }
            $url .= '@';
        }

        $url .= $url_data['host'];

        if (isset($url_data['port'])) {
            $url .= ':' . $url_data['port'];
        }

        if (!isset($url_data['path'])) {
            $url_data['path'] = '/';
        }
        $url .= $url_data['path'];

        if (isset($url_data['query'])) {
            $url .= '?' . $url_data['query'];
        }

        if (isset($url_data['fragment'])) {
            $url .= '#' . $url_data['fragment'];
        }

        return $url;
    }
}

/**
 * Klasa umozliwia wygnerowanie sygnatury
 * wg metody szyfrowania HMAC_SHA1
 */
class SignatureHmacSha1 extends Signature
{

    /**
     * Generuje sygnature.
     *
     * @param Request $request
     * @param string $consumerSecretKey
     * @param string $tokenSecretKey
     * @return string
     */
    public function generateSignature(Request $request, $consumerSecretKey, $tokenSecretKey, $newStandard = false)
    {
        $baseString = $request->generateBaseString($newStandard);
        $keys = array(
            $consumerSecretKey,
            ($tokenSecretKey) ? $tokenSecretKey : ''
        );

        foreach ($keys as &$key) {
            $key = UrlEncoder::encode($key);
        }

        $key = implode('&', $keys);

        $signature = hash_hmac('sha1', $baseString, $key, true);
        return base64_encode($signature);
    }
}

/**
 * Klasa umozliwia kodowanie i dekodowanie
 * ciagu znakow wg standardu RFC3986.
 */
class UrlEncoder
{
    /**
     * Koduje zadany string do postaci wg standardu RFC3986.
     *
     * @param string $input
     * @return string
     */
    public static function encode($input)
    {
        $input = rawurlencode($input);
        $input = str_replace('%7E', '~', $input);

        return $input;
    }

    /**
     * Dekoduje string zakodowany wg standardu RFC3986
     *
     * @param type $input
     * @return type
     */
    public static function decode($input)
    {
        $input = urldecode($input);
        return $input;
    }
}

/**
 * Klasa bazowa dla podklas realizujacych
 * generowanie sygnatury za pomoca konkretnej metody szyfrujacej.
 */
abstract class Signature
{

    abstract function generateSignature(Request $request, $consumerSecretKey, $tokenSecretKey, $newStandard = false);

    /**
     * Weryfikuje zgodnosc sygnatur.
     *
     * @param Request $request
     * @param string $consumerSecretKey
     * @param string $tokenSecretKey
     * @param string $signature
     * @return boolean
     */
    public function verifySignature(Request $request, $consumerSecretKey, $tokenSecretKey, $signature, $newStandard = false)
    {
        $generated = $this->generateSignature($request, $consumerSecretKey, $tokenSecretKey, $newStandard);

        $l_generated = strlen($generated);
        $l_signature = strlen($signature);

        if ($l_generated == 0 || $l_signature == 0) {
            return false;
        }
        if ($l_generated != $l_signature) {
            return false;
        }

        $result = 0;
        for ($i = 0; $i < $l_signature; $i++) {
            $result |= ord($generated{$i}) ^ ord($signature{$i});
        }

        return ($result == 0);
    }
}

/**
 * @brief Exception class of Trans_Api
 *
 * TODO jest rozszerzeniem klasy Exception
 *
 * @cond INTERNAL
 * @see Exception
 *
 * @endcond
 */
class ApiException extends Exception
{
    // @cond INTERNAL
    protected $_data = array();

    public function __construct($message, $code = 0, $data = array())
    {
        parent::__construct($message, $code);
        $this->_data = $data;
    }
    // @endcond

    /**
     * @brief Returns exception data
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->_data;
    }
}