<?php

namespace AnourValar\HttpClient\Traits;

trait PresetsTrait
{
    /**
     * Returns an object for file uploading
     *
     * @param string $filename
     * @param string $mimetype
     * @param string $postname
     * @return \CurlFile
     */
    public function attachment(string $filename, string $mimetype = null, string $postname = null): \CurlFile
    {
        return new \CurlFile($filename, $mimetype, $postname);
    }

    /**
     * Set browser specific headers
     *
     * @param string $userAgent
     * @return self
     */
    public function asBrowser($userAgent = null): self
    {
        if ($userAgent === null) {
            $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.212 Safari/537.36';
        }

        $this->curlOption(CURLOPT_USERAGENT, $userAgent);

        return $this;
    }

    /**
     * Set api client specific headers
     *
     * @param boolean $accept
     * @param boolean $contentType
     * @return self
     */
    public function asJsonClient($accept = true, $contentType = true): self
    {
        if ($accept) {
            $this->addHeaders('Accept: '.self::CONTENT_TYPE_JSON);
        }

        if ($contentType) {
            $this->addHeaders('Content-Type: '.self::CONTENT_TYPE_JSON);
        }

        return $this;
    }


    /**
     * Set proxy
     * Example: 127.0.0.1:80 / login:password
     *
     * @param string $host
     * @param string $loginPassword
     * @return self
     */
    public function proxy(string $host, string $loginPassword = null): self
    {
        $this->curlOption(CURLOPT_PROXY, $host);

        if ($loginPassword !== null) {
            $this->curlOption(CURLOPT_PROXYUSERPWD, $loginPassword);
        }

        return $this;
    }

    /**
     * Set cookies from string
     *
     * @param string $cookies
     * @return self
     */
    public function cookies(string $cookies): self
    {
        $this->curlOption(CURLOPT_COOKIE, $cookies);

        return $this;
    }

    /**
     * Store cookies in the file
     *
     * @param string $file
     * @return self
     */
    public function cookiesFile(string $file): self
    {
        $this->curlOption(CURLOPT_COOKIEFILE, $file);
        $this->curlOption(CURLOPT_COOKIEJAR, $file);

        return $this;
    }

    /**
     * Set request timeouts
     *
     * @param integer $connectMs
     * @param integer $responseMs
     * @return self
     */
    public function timeouts(int $connectMs = null, int $responseMs = null): self
    {
        if ($connectMs !== null) {
            $this->curlOption(CURLOPT_CONNECTTIMEOUT_MS, $connectMs);
        }

        if ($responseMs !== null) {
            $this->curlOption(CURLOPT_TIMEOUT_MS, $responseMs);
        }

        return $this;
    }

    /**
     * Basic Auth
     *
     * @param string $login
     * @param string $password
     * @return self
     */
    public function authBasic(string $login, string $password): self
    {
        $this->curlOption(CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $this->curlOption(CURLOPT_USERPWD, "$login:$password");

        return $this;
    }

    /**
     * Digest Auth
     *
     * @param string $login
     * @param string $password
     * @return self
     */
    public function authDigest(string $login, string $password): self
    {
        $this->curlOption(CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        $this->curlOption(CURLOPT_USERPWD, "$login:$password");

        return $this;
    }

    /**
     * Token Auth
     *
     * @param string $accessToken
     * @param string $type
     * @return self
     */
    public function authToken(string $accessToken, string $type = 'Bearer'): self
    {
        $this->addHeaders("Authorization: $type $accessToken");

        return $this;
    }

    /**
     * Ignore SSL verification (not the best idea)
     *
     * @return self
     */
    public function ignoreSsl(): self
    {
        $this->curlOption(CURLOPT_SSL_VERIFYPEER, 0);
        $this->curlOption(CURLOPT_SSL_VERIFYHOST, 0);

        return $this;
    }

    /**
     * Set referrer url
     *
     * @param string $url
     * @return self
     */
    public function referer(string $url): self
    {
        $this->curlOption(CURLOPT_REFERER, $this->canonizeUrl($url));

        return $this;
    }

    /**
     * Alias for self::referer()
     *
     * @param string $url
     * @return self
     */
    public function referrer(string $url): self
    {
        return $this->referer($url);
    }

    /**
     * Save response body to the file
     *
     * @param string $file
     * @return self
     */
    public function download(string $file): self
    {
        $this->curlOption(CURLOPT_FILE, fopen($file, 'w+'));

        return $this;
    }
}
