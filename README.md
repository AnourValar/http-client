## Usage

### API Client (JSON)
```php
$http = new \AnourValar\HttpClient\Http;

$http->asJsonClient()->authToken('...')->body(['foo' => 'bar'])->exec('https://google')->dump();
```
