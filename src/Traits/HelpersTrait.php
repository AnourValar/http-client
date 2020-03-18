<?php

namespace AnourValar\HttpClient\Traits;

trait HelpersTrait
{
    /**
     * Helper: POST Request
     *
     * @param string $url
     * @param mixed $body
     * @return \AnourValar\HttpClient\Response
     */
    public function post(string $url, $body = null) : \AnourValar\HttpClient\Response
    {
        if (count(func_get_args()) > 1) {
            $this->body($body);
        }

        return $this->method('POST')->exec($url);
    }

    /**
     * Helper: GET Request
     *
     * @param string $url
     * @param mixed $body
     * @return \AnourValar\HttpClient\Response
     */
    public function get(string $url, $body = null) : \AnourValar\HttpClient\Response
    {
        if (count(func_get_args()) > 1) {
            $this->body($body);
        }

        return $this->method('GET')->exec($url);
    }

    /**
     * Helper: DELETE Request
     *
     * @param string $url
     * @param mixed $body
     * @return \AnourValar\HttpClient\Response
     */
    public function delete(string $url, $body = null) : \AnourValar\HttpClient\Response
    {
        if (count(func_get_args()) > 1) {
            $this->body($body);
        }

        return $this->method('DELETE')->exec($url);
    }

    /**
     * Helper: PUT Request
     *
     * @param string $url
     * @param mixed $body
     * @return \AnourValar\HttpClient\Response
     */
    public function put(string $url, $body = null) : \AnourValar\HttpClient\Response
    {
        if (count(func_get_args()) > 1) {
            $this->body($body);
        }

        return $this->method('PUT')->exec($url);
    }
}
