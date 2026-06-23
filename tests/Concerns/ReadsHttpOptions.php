<?php

namespace AnourValar\HttpClient\Tests\Concerns;

use AnourValar\HttpClient\Http;

/**
 * Gives the test access to the private option-bag of the Http builder so the
 * fluent API can be asserted without performing a real request.
 */
trait ReadsHttpOptions
{
    /**
     * "Staged" options (the ones set on the current builder chain).
     *
     * @param \AnourValar\HttpClient\Http $http
     * @return array
     */
    protected function rawOptions(Http $http): array
    {
        return (array) (new \ReflectionProperty(Http::class, 'options'))->getValue($http);
    }

    /**
     * "Remembered" options (applied to every request).
     *
     * @param \AnourValar\HttpClient\Http $http
     * @return array
     */
    protected function rememberedOptions(Http $http): array
    {
        return (array) (new \ReflectionProperty(Http::class, 'rememberOptions'))->getValue($http);
    }

    /**
     * Final cURL options, merged exactly as Http::prepare() would merge them.
     *
     * @param \AnourValar\HttpClient\Http $http
     * @return array
     */
    protected function curlOptions(Http $http): array
    {
        $merged = array_replace_recursive(
            ['curl' => []],
            $this->rememberedOptions($http),
            $this->rawOptions($http)
        );

        return $merged['curl'];
    }

    /**
     * Final non-cURL options (base_url, query, size_limit, extend_info, ...).
     *
     * @param \AnourValar\HttpClient\Http $http
     * @return array
     */
    protected function metaOptions(Http $http): array
    {
        $merged = array_replace_recursive(
            ['curl' => []],
            $this->rememberedOptions($http),
            $this->rawOptions($http)
        );

        unset($merged['curl']);

        return $merged;
    }
}
