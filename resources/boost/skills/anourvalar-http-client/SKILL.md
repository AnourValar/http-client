---
name: anourvalar-http-client
description: Load when working with the anourvalar/http-client package - a fluent cURL wrapper exposing AnourValar\HttpClient\Http for sending HTTP requests (JSON APIs, file uploads/downloads, browser-style scraping, multi-exec) and AnourValar\HttpClient\Response / FakeResponse for inspecting results inside Laravel projects.
---

# AnourValar HTTP Client

`anourvalar/http-client` is a small, framework-agnostic cURL wrapper that plays nicely with Laravel. There are no facades or service providers; you instantiate `AnourValar\HttpClient\Http` directly and chain configuration methods (auth, headers, proxy, cookies, body, timeouts) before calling an HTTP verb. Responses come back as `AnourValar\HttpClient\Response` objects that implement `ArrayAccess` over their decoded JSON body.

## When to use

- Sending outbound HTTP requests from a Laravel app where the user explicitly wants this package (not `Illuminate\Support\Facades\Http`).
- Calling third-party JSON APIs via `asJsonClient()` with bearer/basic/digest auth.
- Scraping or polling websites using browser-style headers (`asBrowser()`, `referer()`, cookies).
- Uploading files (multipart, raw PUT, in-memory buffers) or downloading binary payloads with a size cap.
- Issuing many parallel HTTP requests with `multiExec()`.
- Stubbing responses in tests with `AnourValar\HttpClient\FakeResponse`.
- Listening for completed requests via the `HttpRequestComplete` Laravel event.

## Facades

This package ships **no facades and no service provider**. Use the `Http` class directly (typically `new \AnourValar\HttpClient\Http`). If a Laravel app wants facade-style access, the consumer must register their own binding/facade — do not invent one.

## Services

### `AnourValar\HttpClient\Http`

The main entry point. A fluent builder that internally maps to `curl_setopt`. Every configuration method returns `$this`. After each `exec()` / verb call the per-request options are flushed; options set via `remember()` persist across calls.

Constructor:

```php
public function __construct(bool $defaultOptions = true)
```

When `$defaultOptions` is `true` (default), sensible curl defaults are applied via `remember()`: `CURLOPT_ENCODING=''`, `CURLOPT_FOLLOWLOCATION`, `CURLOPT_AUTOREFERER`, `CURLOPT_RETURNTRANSFER`, `CURLOPT_HEADER=0`, an `Expect:` header reset, and (PHP 8.5+) a persistent `CURLSH` share handle.

Content-type constants on the class: `CONTENT_TYPE_JSON`, `CONTENT_TYPE_HTML`, `CONTENT_TYPE_PLAIN`, `CONTENT_TYPE_EXCEL`, `CONTENT_TYPE_PDF`, `CONTENT_TYPE_XML`, `CONTENT_TYPE_ZIP`, `CONTENT_TYPE_GZIP`, `CONTENT_TYPE_GIF`, `CONTENT_TYPE_JPG`, `CONTENT_TYPE_PNG`.

Core methods (from `Http.php`):

- `remember(callable $options): self` — run a callback whose option-mutations are stored and replayed on every subsequent request.
- `reset(bool $defaultOptions = true): self` — clear both per-request and remembered options.
- `extendInfo(): self` — make `dump()` / `curlGetInfo()` also include the request body (`POSTFIELDS` / `INFILE`).
- `addHeaders($headers): self` / `headers($headers): self` — add request headers. Accepts a string `"Foo: bar"`, an array of such strings, or an assoc array `['Foo' => 'bar']`. Later values overwrite earlier same-named headers.
- `method(string $method): self` — set HTTP verb (uppercased). `HEAD` switches to `CURLOPT_NOBODY`.
- `body($body): self` — set request body (`CURLOPT_POSTFIELDS`). If headers include `Content-Type: application/json` and the body is an array, it is auto-`json_encode`d at send time.
- `curlOption(int $name, mixed $value): self` — set a raw cURL option. `null` removes it.
- `baseUrl(?string $baseUrl): self` — prefix subsequent URLs.
- `query(?array $query): self` — append `http_build_query()`-encoded params to the URL.
- `exec(string $url): Response` — send a single request.
- `multiExec(array|string $urls, int $times = 1): array<Response>` — send several requests in parallel via `curl_multi_*`. `$times` multiplies the URL list. Returns responses keyed by the input array keys.

Verb helpers (from `HelpersTrait`):

- `get(string $url, $body = null): Response`
- `post(string $url, $body = null): Response`
- `put(string $url, $body = null): Response`
- `delete(string $url, $body = null): Response`

Each helper calls `body()` only when a second argument is actually passed, then `method(...)->exec($url)`.

Presets (from `PresetsTrait`):

- `asJsonClient(bool $accept = true, bool $contentType = true): self` — sets `Accept` / `Content-Type` to `application/json`.
- `asBrowser(?string $userAgent = null): self` — sets a Chrome-like `User-Agent` (custom UA if provided).
- `authBasic(string $login, string $password): self`
- `authDigest(string $login, string $password): self`
- `authToken(string $accessToken, string $type = 'Bearer'): self` — adds `Authorization: <type> <token>`.
- `proxy(string $host, ?string $loginPassword = null): self`
- `cookies(string $cookies): self` — raw cookie string.
- `cookiesFile(string $file): self` — file-based cookie jar (read + write).
- `timeouts(?int $connectMs = null, ?int $totalMs = null): self` — milliseconds.
- `referer(string $url): self` / `referrer(string $url): self`
- `ignoreSsl(): self` — disables peer/host verification (use sparingly).
- `sizeLimit(?int $kbytes = null): self` — aborts download once response exceeds N kB; sets `http_code = 0` and decorates `curl_error`.
- `download(string $file): self` — stream response body to a file. The file is deleted automatically if the response is not `success()`.
- `file(string $filename, ?string $mimetype = null, ?string $postname = null): \CURLFile` — helper to build a multipart file.
- `stringFile(string $content, string $mimetype = 'application/octet-stream', string $postname = 'file'): \CURLStringFile` — in-memory multipart file.
- `putFile(string $filename): self` — raw `PUT` from disk (`CURLOPT_PUT` + `CURLOPT_INFILE`).
- `putStringFile(string $content): self` — raw `PUT` from a string buffer.
- `formMultipart(array $data): string` — manually builds a `multipart/form-data` body (useful when you need to HMAC-sign it). It also sets the `Content-Type` header and the body on the request and returns the raw body string.

Test safeguard: `Http::exec()` calls `checkForAvailability()`, which throws `\LogicException('Real http request detected during testing.')` whenever `ARTISAN_BINARY` is defined and `config('app.env') === 'testing'`. Stub with `FakeResponse` instead of hitting the network in tests.

### `AnourValar\HttpClient\Response`

Returned by `exec()` / verb helpers. Implements `\ArrayAccess` (over the decoded JSON body) and `__toString()` (raw body).

- `status(): ?int` — HTTP status code (from `curl_getinfo['http_code']`).
- `success(?string $successKey = null): bool` — `true` when status is 2xx; if `$successKey` is passed, also requires that key to be truthy or an array in the JSON body.
- `body(): ?string` — raw response body.
- `json(): mixed` — `json_decode($body, true)` result (may be `null`).
- `headers(): array` — parsed response headers (repeated headers become arrays).
- `header(string $name): ?string` — case-/whitespace-insensitive header lookup.
- `curlGetInfo(): array` — full `curl_getinfo()` plus an optional `curl_error` key.
- `durationMs(): int` — `total_time * 1000`.
- `dump(bool $all = false, bool $protectSensitive = true): array` — debug array. By default it only includes `curl_getinfo` + headers on failure (or always when `$all = true`), JSON-decoded body when available, and hashes any `Authorization:` line in the request headers when `$protectSensitive` is `true`. Also decodes `cp1251` bodies to UTF-8.

### `AnourValar\HttpClient\FakeResponse`

Drop-in subclass of `Response` for tests / fixtures.

```php
public function __construct(array $responseHeaders, mixed $responseBody, mixed $curlGetInfo = 200)
```

- `$responseHeaders` — assoc or list of `"Name: value"` strings.
- `$responseBody` — string, or an array (auto JSON-encoded with `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`).
- `$curlGetInfo` — either a full info array or an int HTTP status (then expanded to `['http_code' => $status, 'total_time' => 0.1]`).

### `AnourValar\HttpClient\Events\HttpRequestComplete`

Fired (via Laravel's `event()` helper) at the end of every `exec()` / `multiExec()` call when `LARAVEL_START` is defined. Public properties: `string $uri`, `string $method`, `int $startedAt`, `int $finishedAt` (unix timestamps in seconds). Uses `Dispatchable`, `InteractsWithSockets`, `SerializesModels`. Listen for it in `EventServiceProvider` to log/instrument outbound calls.

### Traits

- `AnourValar\HttpClient\Traits\HelpersTrait` — verb helpers (`get`/`post`/`put`/`delete`). Already mixed into `Http`; consumers normally don't use it directly.
- `AnourValar\HttpClient\Traits\PresetsTrait` — preset configuration methods. Mixed into `Http`.
- `AnourValar\HttpClient\Traits\ResponseArrayAccessTrait` — `ArrayAccess` over the decoded JSON. Mixed into `Response`.

## Usage examples

### JSON API call with bearer token

```php
use AnourValar\HttpClient\Http;

$response = (new Http())
    ->asJsonClient()
    ->authToken(config('services.foo.token'))
    ->timeouts(connectMs: 2000, totalMs: 10000)
    ->post('https://api.example.com/v1/orders', ['sku' => 'ABC', 'qty' => 2]);

if (! $response->success()) {
    report(new \RuntimeException(json_encode($response->dump())));
    abort(502);
}

$orderId = $response['data']['id']; // ArrayAccess over JSON
```

### Reusable client with persistent options

```php
use AnourValar\HttpClient\Http;

$http = (new Http())->remember(function (Http $h) {
    $h->baseUrl('https://api.example.com')
      ->asJsonClient()
      ->authToken(config('services.foo.token'))
      ->timeouts(2000, 15000);
});

$users  = $http->get('/users')->json();
$orders = $http->get('/orders', ['status' => 'open'])->json();
```

### Browser-style scraping with cookie jar

```php
use AnourValar\HttpClient\Http;

$http = (new Http())
    ->asBrowser()
    ->cookiesFile(storage_path('app/cookies.txt'))
    ->referer('https://example.com/');

$html = (string) $http->get('https://example.com/page?start=10');
```

### Multipart upload

```php
use AnourValar\HttpClient\Http;

$http = new Http();

$response = $http
    ->authToken($token)
    ->post('https://api.example.com/files', [
        'meta' => 'invoice',
        'file' => $http->file(storage_path('app/invoice.pdf'), 'application/pdf'),
    ]);
```

### Raw PUT upload + size-capped download

```php
use AnourValar\HttpClient\Http;

(new Http())
    ->putFile(storage_path('app/big.bin'))
    ->exec('https://example.com/upload/big.bin');

(new Http())
    ->sizeLimit(2048) // 2 MB cap
    ->download(storage_path('app/remote.zip'))
    ->get('https://example.com/remote.zip');
```

### Parallel requests

```php
use AnourValar\HttpClient\Http;

$responses = (new Http())
    ->asJsonClient()
    ->multiExec([
        'a' => 'https://api.example.com/a',
        'b' => 'https://api.example.com/b',
    ]);

foreach ($responses as $key => $response) {
    logger()->info($key, ['status' => $response->status(), 'ms' => $response->durationMs()]);
}
```

### Stubbing in tests

```php
use AnourValar\HttpClient\FakeResponse;

$fake = new FakeResponse(
    ['Content-Type' => 'application/json'],
    ['ok' => true, 'id' => 42],
    200,
);

$this->assertTrue($fake->success());
$this->assertSame(42, $fake['id']);
```

### Listening for the completion event

```php
use AnourValar\HttpClient\Events\HttpRequestComplete;
use Illuminate\Support\Facades\Event;

Event::listen(function (HttpRequestComplete $event) {
    logger()->info('outbound http', [
        'method'   => $event->method,
        'uri'      => $event->uri,
        'duration' => $event->finishedAt - $event->startedAt,
    ]);
});
```

## Conventions / gotchas

- **No facade / no service provider.** Always `new \AnourValar\HttpClient\Http`. If you want it as a singleton, register it yourself in a service provider.
- **Per-request options are flushed after each `exec()` / verb call.** Configuration set without `remember()` does not survive the next request. Use `remember(fn ($h) => ...)` for shared defaults.
- **JSON auto-encoding requires the header.** `body([...])` is only `json_encode`d when `Content-Type: application/json` is set (e.g. via `asJsonClient()`); otherwise the array is sent as multipart form data.
- **Testing guard.** `exec()` throws `\LogicException` when `ARTISAN_BINARY` is defined and `app.env === 'testing'`. Use `FakeResponse` (or mock the `Http` class) inside tests — do not let real cURL fire.
- **`success()`** strictly means HTTP 2xx; non-numeric or `null` statuses are treated as failures. Pass a `$successKey` when an API returns 200 but signals errors in the body (`['ok' => false, ...]`).
- **`download()` cleans up failures.** If the response is not `success()`, the target file is `unlink()`ed automatically. The file handle is always closed.
- **`sizeLimit()` reports as `http_code = 0`** with `curl_error` suffixed by `(due to size limit: N kB)` once the limit triggers — check `success()` rather than relying on a specific status code.
- **`dump()` redacts `Authorization`** headers by replacing the value with `sha256(value)`; pass `$protectSensitive = false` only when you need the raw header for debugging.
- **`HttpRequestComplete` only fires inside Laravel** (`LARAVEL_START` must be defined). The package itself has no Laravel runtime dependency, but `composer.json` does not list `illuminate/*` — if you depend on the event, ensure your app already provides those packages.
- **`multiExec()` reuses one prepared cURL handle** via `curl_copy_handle`; per-URL overrides (different headers/body per request) are not supported — set anything that varies per URL before each separate `exec()` instead.
- **`PHP >= 8.1`** is required (per `composer.json`).
