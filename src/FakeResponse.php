<?php

namespace AnourValar\HttpClient;

class FakeResponse extends Response
{
    /**
     * Setters
     *
     * @param array $responseHeaders
     * @param mixed $responseBody
     * @param mixed $curlGetInfo
     */
    public function __construct(array $responseHeaders, $responseBody, $curlGetInfo = 200)
    {
        $responseHeaders = $this->parseHeaders($responseHeaders);

        if (is_array($responseBody)) {
            $responseBody = json_encode($responseBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (! is_array($curlGetInfo)) {
            $curlGetInfo = ['http_code' => $curlGetInfo];
        }

        parent::__construct($responseHeaders, $responseBody, $curlGetInfo);
    }

    /**
     * @param array $headers
     * @return string|NULL
     */
    private function parseHeaders(array $headers) : ?string
    {
        $result = [];

        foreach ($headers as $key => $value) {
            if (! is_numeric($key)) {
                $result[] = "$key: $value";
            } else {
                $result[] = $value;
            }
        }

        if ($result) {
            return implode("\n", $result);
        }

        return null;
    }
}
