# HTTP Client

## Installation

```bash
composer require anourvalar/http-client
```


## Usage

### API Client (JSON)
```php
$http = new \AnourValar\HttpClient\Http;

$http->asJsonClient()->authToken('...')->body(['foo' => 'bar'])->post('https://google')->dump();
```


### Web Browser (Robot)
```php
$http = new \AnourValar\HttpClient\Http;

$http->asBrowser()->referer('https://google.com')->get('https://google.com/?start=10')->dump();
```
