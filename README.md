

# HTTP

A fairly simple HTTP client using native PHP functions (no cURL).

### Example

This is a very basic example. The class can do much more. The source is well documented, check it out to see what other methods are available.


```php
require 'HTTP.php';

$cj = realpath(dirname(__FILE__))."/cj.json";

$file = HTTP::File(realpath(dirname(__FILE__))."/testfile.txt")
	->setType("text/csv")
	->setFilename('test.csv');

$request = HTTP::Request('https://www.example.com')
	->setCookiejar($cj)
	->method("post")
	->param('user', 'bob')
	->param('myFile', $file);
	
$response = $request->send();

$headers = $response->getHeaders();
$body = $response->getBody();
```
