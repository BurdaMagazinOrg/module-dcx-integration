<?php

/**
 * @file
 * DC-X JSON API client.
 *
 * First draft of a PHP client for the DC-X DAM system JSON API.
 * TODO: Namespaces, PHPDoc, Composer support and much more.
 *
 * At DC, we’re using this client for automated integration tests,
 * so while it’s far from finished, it’s already working.
 */

class DCX_Api_Client {
  const HTTP_TIMEOUT = 30;
  const HTTP_CONNECT_TIMEOUT = 5;
  const HTTP_MAX_REDIRECTS = 5;
  const JSON_CONTENT_TYPE = 'application/json; charset=UTF-8';

  protected $url;
  protected $username;
  protected $password;
  protected $custom_http_headers = array();
  protected $http_useragent = 'DC-X Api Client (http://www.digicol.de/)';

  public function __construct($url, $username, $password, $options = array()) {

    if (substr($url, -1) !== '/') {
      $url .= '/';
    }

    $this->url = $url;
    $this->username = $username;
    $this->password = $password;

    // Custom HTTP headers.
    if (isset($options['http_headers']) && is_array($options['http_headers'])) {
      $this->custom_http_headers = $options['http_headers'];
    }

    // Custom HTTP user agent.
    if (isset($options['http_useragent'])) {
      $this->http_useragent = $options['http_useragent'];
    }
  }

  public function getContext(&$data) {

    $url = $this->fullUrl('_context');

    $cache_filename = '/tmp/dcx_api_client_context_' . md5($url) . '.ser';

    // Refresh JSON-LD context cache after 1 hour.
    $cache_maxage = (60 * 60 * 1);

    if (
          file_exists($cache_filename)
          && (filesize($cache_filename) > 0)
          && ((time() - filemtime($cache_filename)) < $cache_maxage)
        ) {
      $data = unserialize(file_get_contents($cache_filename));
      return 200;
    }

    $curl = $this->getCurlHandle($url);

    $http_code = $this->curlExec($curl, $response_body, $response_info);

    if ($this->isJson($response_info['content_type'])) {
      $data = json_decode($response_body, TRUE);
    }

    if (!is_array($data)) {
      $data = array();
      return $http_code;
    }

    if (!isset($data['@context'])) {
      $data = array();
      return $http_code;
    }

    $data = $data['@context'];

    file_put_contents($cache_filename, serialize($data));

    return $http_code;
  }
  
  public function getObject($url, array $params, &$data) {

    $url = $this->fullUrl($url) . '?' . http_build_query($params);

    $curl = $this->getCurlHandle($url);

    $http_code = $this->curlExec($curl, $response_body, $response_info);

    if ($this->isJson($response_info['content_type'])) {
      $data = $this->decodeJson($response_body);
    }

    return $http_code;
  }

  public function createObject($url, array $params, array $data, &$response_body) {

    $url = $this->fullUrl($url) . '?' . http_build_query($params);

    $json_data = json_encode($data);

    $curl = $this->getCurlHandle($url, array('Content-Type' => self::JSON_CONTENT_TYPE));

    curl_setopt($curl, CURLOPT_POSTFIELDS, $json_data);

    $http_code = $this->curlExec($curl, $response_body, $response_info);

    if ($this->isJson($response_info['content_type'])) {
      $response_body = $this->decodeJson($response_body);
    }

    return $http_code;
  }

  public function setObject($url, array $params, array $data, &$response_body) {

    $url = $this->fullUrl($url) . '?' . http_build_query($params);

    $json_data = json_encode($data);

    $curl = $this->getCurlHandle($url, array('Content-Type' => self::JSON_CONTENT_TYPE));

    curl_setopt($curl, CURLOPT_PUT, TRUE);

    // Curl insists on doing PUT uploads from a file.
    // To avoid having to write a real file to the disk, we use a temp file handle instead.
    $fp = fopen('php://temp/maxmemory:256000', 'w');

    if (!$fp) {
      return FALSE;
    }

    fwrite($fp, $json_data);
    fseek($fp, 0);

    curl_setopt($curl, CURLOPT_INFILE, $fp);
    curl_setopt($curl, CURLOPT_INFILESIZE, strlen($json_data));

    $http_code = $this->curlExec($curl, $response_body, $response_info);

    if ($this->isJson($response_info['content_type'])) {
      $response_body = $this->decodeJson($response_body);
    }

    return $http_code;
  }

  public function deleteObject($url, array $params, &$response_body) {

    $url = $this->fullUrl($url) . '?' . http_build_query($params);

    $curl = $this->getCurlHandle($url);

    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');

    $http_code = $this->curlExec($curl, $response_body, $response_info);

    if ($this->isJson($response_info['content_type'])) {
      $response_body = $this->decodeJson($response_body);
    }

    return $http_code;
  }

  public function getObjects($url, array $params, &$data) {

    $url = $this->fullUrl($url) . '?' . http_build_query($params);

    $curl = $this->getCurlHandle($url);

    $http_code = $this->curlExec($curl, $response_body, $response_info);

    if ($this->isJson($response_info['content_type'])) {
      $data = $this->decodeJson($response_body);
    }

    return $http_code;
  }

  public function uploadFile($filename, array $params, &$response_body) {

    if (!file_exists($filename)) {
      return -1;
    }

    if (empty($params['content_type'])) {
      $params['content_type'] = 'application/octet-stream';
    }

    if (empty($params['slug'])) {
      $params['slug'] = basename($filename);
    }

    $url = $this->url . '_file_upload';

    $curl = $this->getCurlHandle($url, array(
      'Content-Type' => $params['content_type'],
      'Slug' => $params['slug'],
    ));

    curl_setopt($curl, CURLOPT_POST, TRUE);
    curl_setopt($curl, CURLOPT_BINARYTRANSFER, TRUE);

    // XXX of course, this is not suitable for large files!
    curl_setopt($curl, CURLOPT_POSTFIELDS, file_get_contents($filename));

    $http_code = $this->curlExec($curl, $response_body, $response_info);

    if ($this->isJson($response_info['content_type'])) {
      $response_body = $this->decodeJson($response_body);
    }

    return $http_code;
  }

  public function upload($uploadconfig_id, array $params, &$response_body) {

    $url = $this->url . '_upload/' . urlencode($uploadconfig_id);

    $curl = $this->getCurlHandle($url, array(
       // 'Content-Type' => 'multipart/form-data'.
      ));

    $this->flattenCurlPostfields($params, $postdata);

    curl_setopt($curl, CURLOPT_SAFE_UPLOAD, TRUE);
    curl_setopt($curl, CURLOPT_POST, TRUE);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);

    $http_code = $this->curlExec($curl, $response_body, $response_info);

    if ($this->isJson($response_info['content_type'])) {
      $response_body = $this->decodeJson($response_body);
    }

    return $http_code;
  }

  public function objectIdToUrl($_type, $object_id) {

    // dcx:document, doc123 => http://example.com/dcx/api/document/doc123
    return $this->fullUrl(sprintf(
          '%s/%s',
          substr($_type, 4),
          urlencode($object_id)
      ));
  }

  public function typeToCollectionUrl($_type) {

    // dcx:document => http://example.com/dcx/api/document
    return $this->fullUrl(substr($_type, 4));
  }

  public function fullUrl($incomplete_url) {

    // document/doc123 => http://example.com/dcx/api/document/doc123
    // /dcx/api/document/doc123 => http://example.com/dcx/api/document/doc123
    if (strpos($incomplete_url, '://') !== FALSE) {
      return $incomplete_url;
    }

    if ($incomplete_url[0] !== '/') {
      return $this->url . $incomplete_url;
    }

    $url = parse_url($this->url);

    return sprintf(
          '%s://%s%s%s',
          $url['scheme'],
          $url['host'],
          (isset($url['port']) ? ':' . $url['port'] : ''),
          $incomplete_url
        );
  }

  protected function getCurlHandle($url, $http_headers = array()) {

    $curl = curl_init($url);

    curl_setopt($curl, CURLOPT_HEADER, TRUE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::HTTP_CONNECT_TIMEOUT);
    curl_setopt($curl, CURLOPT_TIMEOUT, self::HTTP_TIMEOUT);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($curl, CURLOPT_MAXREDIRS, self::HTTP_MAX_REDIRECTS);
    curl_setopt($curl, CURLOPT_USERAGENT, $this->http_useragent);

    // Patched by T. Behn, Mantis #31668.
    curl_setopt($curl, CURLOPT_USERPWD, sprintf(
          '%s:%s',
          $this->username,
          $this->password
      ));

    if (!is_array($http_headers)) {
      $http_headers = array();
    }

    $http_headers = array_merge($this->custom_http_headers, $http_headers);

    if (!isset($http_headers['Accept'])) {
      $http_headers['Accept'] = self::JSON_CONTENT_TYPE;
    }

    $set_headers = array();

    foreach ($http_headers as $key => $value) {
      $set_headers[] = sprintf('%s: %s', $key, $value);
    }

    curl_setopt($curl, CURLOPT_HTTPHEADER, $set_headers);

    return $curl;
  }

  protected function curlExec($curl, &$response_body, &$response_info) {

    $response = curl_exec($curl);

    $response_info = curl_getinfo($curl);

    curl_close($curl);

    $response_info['_header_str'] = mb_substr($response, 0, $response_info['header_size']);
    $response_body = mb_substr($response, $response_info['header_size']);

    return $response_info['http_code'];
  }

  protected function flattenCurlPostfields($values, &$result, $fieldname_prefix = '') {

    // Curl is too dumb to understand nested arrays. Flatten them, and
    // use CurlFile for the file section.
    // see http://stackoverflow.com/questions/3772096/posting-multidimensional-array-with-php-and-curl
    if (!is_array($result)) {
      $result = array();
    }

    foreach ($values as $key => $value) {
      if ($fieldname_prefix === '') {
        $fieldname = $key;
      }
      else {
        $fieldname = sprintf('%s[%s]', $fieldname_prefix, $key);
      }

      if (is_array($value)) {
        $this->flattenCurlPostfields($value, $result, $fieldname);
      }
      else {
        $result[$fieldname] = $value;
      }
    }
  }

  protected function isJson($content_type) {

    // Accept "application/json", "application/problem+json; charset=UTF-8"
    // XXX a regular expression might be better!
    list($content_type,) = array_map('trim', explode(';', $content_type));

    $parts = explode('/', $content_type);

    if ($parts[0] !== 'application') {
      return FALSE;
    }

    return (($parts[1] === 'json') || (substr($parts[1], -5) === '+json'));
  }

  protected function decodeJson($json_str) {

    $result = json_decode($json_str, TRUE);

    if (!is_array($result)) {
      return $result;
    }

    $this->resolveCompactUrls($result, $this->getCompactUrlPrefixes());

    return $result;
  }

  protected function resolveCompactUrls(&$arr, array $prefixes) {

    foreach ($arr as $key => $value) {
      if (is_array($value)) {
        $this->resolveCompactUrls($arr[$key], $prefixes);
        continue;
      }

      if ($key === '_id') {
        $arr['_id_url'] = $this->resolveCompactUrl($value, $prefixes);
      }

      ksort($arr);
    }
  }

  protected function resolveCompactUrl($url, array $prefixes) {

    $parts = explode(':', $url, 2);

    if (count($parts) === 1) {
      return $url;
    }

    list($prefix, $suffix) = $parts;

    if (substr($suffix, 0, 2) === '//') {
      return $url;
    }

    if (!isset($prefixes[$prefix])) {
      return $url;
    }

    return $prefixes[$prefix] . $suffix;
  }

  protected function getCompactUrlPrefixes() {

    $result = array();

    $this->getContext($context);

    if (!is_array($context)) {
      return $result;
    }

    foreach ($context as $key => $value) {
      if (is_string($value)) {
        $result[$key] = $value;
      }
    }

    return $result;
  }

}
