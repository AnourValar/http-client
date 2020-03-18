<?php

namespace AnourValar\HttpClient;

class Response
{
    /**
     * @var array
     */
    private $responseHeaders;

    /**
     * @var string|NULL
     */
    private $responseBody;

    /**
     * @var mixed
     */
    private $responseBodyJson;

    /**
     * @var array
     */
    private $curlGetInfo;

    /**
     * Setters
     *
     * @param string $responseHeaders
     * @param string $responseBody
     * @param array $curlGetInfo
     */
    public function __construct(?string $responseHeaders, ?string $responseBody, array $curlGetInfo)
    {
        $this->responseHeaders = $this->parseHeaders($responseHeaders);

        $this->responseBody = $responseBody;
        $this->responseBodyJson = json_decode($responseBody, true);

        $this->curlGetInfo = $curlGetInfo;
    }

    /**
     * Get response headers
     *
     * @return array
     */
    public function headers() : array
    {
        return $this->responseHeaders;
    }

    /**
     * Get response header (by name)
     *
     * @param string $name
     * @return string|NULL
     */
    public function header(string $name) : ?string
    {
        $name = str_replace(' ', '', mb_strtolower($name));

        foreach ($this->headers() as $key => $value) {
            if ($name === str_replace(' ', '', mb_strtolower($key))) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Get response status code
     *
     * @return integer|NULL
     */
    public function status() : ?int
    {
        return ( $this->curlGetInfo['http_code'] ?? null );
    }

    /**
     * Check if request succeeded (2xx http code)
     *
     * @return bool
     */
    public function success() : bool
    {
        $status = $this->status();

        if (! isset($status)) {
            return false;
        }

        if (!is_numeric($status) || $status != (int)$status) {
            return false;
        }

        if ($status < 200) {
            return false;
        }
        if ($status > 299) {
            return false;
        }

        return true;
    }

    /**
     * Get response body
     *
     * @return string|NULL
     */
    public function body() : ?string
    {
        return $this->responseBody;
    }

    /**
     * Get parsed response body (json)
     *
     * @return mixed
     */
    public function json()
    {
        return $this->responseBodyJson;
    }

    /**
     * Get curl_getinfo
     *
     * @return array
     */
    public function curlGetInfo() : array
    {
        return $this->curlGetInfo;
    }

    /**
     * Dump
     *
     * @param boolean $all
     * @return array
     */
    public function dump(bool $all = false) : array
    {
        $result = [];

        if (!$this->success() || $all) {
            $result['curl_getinfo'] = $this->curlGetInfo();
            $result['response_headers'] = $this->headers();
        }

        if ($this->json()) {
            $result['response_body'] = $this->json();
        } else {
            $body = $this->body();

            if (stripos(( $this->curlGetInfo()['content_type'] ?? '' ), 'cp1251') !== false) {
                $body = mb_convert_encoding($body, 'utf-8', 'cp1251');
            }

            $result['response_body'] = $body;
        }

        return $result;
    }

    /**
     * @param string $headers
     * @return array
     */
    private function parseHeaders(?string $headers) : array
    {
        $result = [];

        foreach (explode("\n", $headers) as $header) {
            $header = trim($header);
            if (! mb_strlen($header)) {
                continue;
            }

            $header = explode(':', $header, 2);
            $header = array_map('trim', $header);

            if (isset($header[1])) {
                $result[$header[0]] = $header[1];
            } else {
                $result[] = $header[0];
            }
        }

        return $result;
    }
}
