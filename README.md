## Usage

### API Client (JSON)
$http = new \AnourValar\HttpClient\Http;

$http->asJsonClient()->authToken('...')->body(['foo' => 'bar'])->exec('https://google')->dump();
```
