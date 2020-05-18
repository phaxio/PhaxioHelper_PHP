<?php

class Phaxio
{
    private $debug = false;
    private $api_key = null;
    private $api_secret = null;
    private $host = "https://api.phaxio.com/v2.1/";

    public function __construct($api_key = null, $api_secret = null, $host = null)
    {
        $this->api_key = $api_key ? $api_key : $this->getApiKey();
        $this->api_secret = $api_secret ? $api_secret : $this->getApiSecret();
        if ($host != null) {
            $this->host = $host;
        }
    }

    public function faxes()
    {
        return new Phaxio\Faxes($this);
    }

    public function phoneNumbers()
    {
        return new Phaxio\PhoneNumbers($this);
    }

    public function phaxCodes()
    {
        return new Phaxio\PhaxCodes($this);
    }

    public function public()
    {
        return new Phaxio\PhaxioPublic($this);
    }

    public function account() {
        return new Phaxio\Account($this);
    }

    public function atas() {
        return new Phaxio\ATAs($this);
    }

    # Convenience methods

    public function sendFax($params) {
        return $this->faxes()->create($params);
    }

    public function initFax($id) {
        return $this->faxes()->init($id);
    }

    public function retrieveFaxFile($id, $params = array()) {
        return $this->initFax($id)->getFile()->retrieve($params);
    }

    public function listFaxes($params = array()) {
        return $this->faxes()->getList($params);
    }

    public function listATAs()
    {
        return $this->atas()->getList();
    }

    public function initATA($id) {
        return $this->atas()->init($id);
    }

    public function retrieveATA($id)
    {
        return $this->initATA($id)->retrieve();
    }

    public function retrieveDefaultPhaxCode($getMetadata = false)
    {
        return Phaxio\PhaxCode::init($this)->retrieve($getMetadata);
    }

    public function stringUpload($str, $ext = 'txt') {
        return new Phaxio\StringUpload($str, $ext);
    }

    # API client methods

    public function getApiKey()
    {
        return $this->api_key;
    }

    public function getApiSecret()
    {
        return $this->api_secret;
    }

    public function doRequest($method, $path, $params = array(), $wrapInPhaxioOperationResult = true)
    {
        $address = $this->host . $path;

        $response = $this->curlRequest($method, $address, $params);

        if ($this->debug) {
            echo "Response: \n\n";
            var_dump($response);
            echo "\n\n";
        }

        if ($wrapInPhaxioOperationResult || $response['status'] != 200) {
            $result = json_decode($response['body'], true);
        }

        switch ($response['status']) {
            case 401:
                throw new Phaxio\Error\AuthenticationException($result['message']);
                break;
            case 404:
                throw new Phaxio\Error\NotFoundException($result['message']);
                break;
            case 422:
                throw new Phaxio\Error\InvalidRequestException($result['message']);
                break;
            case 429:
                throw new Phaxio\Error\RateLimitException($result['message']);
                break;
        }

        if ($response['status'] >= 500 || (isset($result['success']) && $result['success'] != true)) {
            throw new Phaxio\Error\GeneralException($result['message']);
        }

        if ($wrapInPhaxioOperationResult) {
            $opResult = new Phaxio\OperationResult($result['success'], $result['message'], isset($result['data']) ? $result['data'] : null, isset($result['paging']) ? $result['paging'] : null);
        } else {
            $opResult = $response;
        }

        return $opResult;
    }

    private function curlRequest($method, $address, $params = array())
    {
        $handle = curl_init($address);

        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

        # Authentication
        curl_setopt($handle, CURLOPT_USERPWD, $this->getApiKey() . ':' . $this->getApiSecret());

        if ($this->debug) {
            echo "Requested resource: $method $address\n\n";
            echo "Authentication: " . $this->getApiKey() . ':' . $this->getApiSecret() . "\n\n";
        }

        $this->curlSetoptCustomPostfields($handle, $params);
        $result = curl_exec($handle);

        if ($result === false) {
            throw new Phaxio\Error\APIConnectionException('Curl error: ' . curl_error($handle));
        }

        $contentType = curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        $status = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        curl_close($handle);

        return array('status' => $status, 'contentType' => $contentType, 'body' => $result);
    }

    private function curlSetoptCustomPostfields($ch, $postfields, $headers = null)
    {
        $algos = hash_algos();
        $hashAlgo = null;

        foreach (array('sha1', 'md5') as $preferred) {
            if (in_array($preferred, $algos)) {
                $hashAlgo = $preferred;
                break;
            }
        }
        if ($hashAlgo === null) {
            list($hashAlgo) = $algos;
        }
        $boundary =
                '----------------------------' .
                substr(hash($hashAlgo, 'cURL-php-multiple-value-same-key-support' . microtime()), 0, 12);

        $body = array();
        $crlf = "\r\n";
        $fields = array();
        foreach ($postfields as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $idx => $v) {
                    $fields[] = array($key . "[" . (is_int($idx) ? '' : $idx) . "]", $v);
                }
            } else {
                $fields[] = array($key, $value);
            }
        }
        foreach ($fields as $field) {
            list($key, $value) = $field;

            if (is_resource($value)) {
                $value = $this->stringUpload(stream_get_contents($value), basename(stream_get_meta_data($value)['uri']));
            }

            if ($value instanceof Phaxio\StringUpload) {
                $body[] = '--' . $boundary;
                $body[] = 'Content-Disposition: form-data; name="' . $key . '"; filename="string.' . $value->extension . '"';
                $body[] = 'Content-Type: application/octet-stream';
                $body[] = '';
                $body[] = $value->string;
            } else {
                $body[] = '--' . $boundary;
                $body[] = 'Content-Disposition: form-data; name="' . $key . '"';
                $body[] = '';
                $body[] = (is_bool($value) ? var_export($value, true) : $value);
            }
        }
        $body[] = '--' . $boundary . '--';
        $body[] = '';
        $contentType = 'multipart/form-data; boundary=' . $boundary;
        $content = join($crlf, $body);
        $contentLength = strlen($content);

        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            array(
                'Content-Length: ' . $contentLength,
                'Expect: 100-continue',
                'Content-Type: ' . $contentType,
            )
        );

        if ($this->debug) {
            echo "Request payload:\n\n$content\n\n";
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
    }
}
