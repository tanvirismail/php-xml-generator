# php-xml-generator

Instance Class
```php
  require_once "sitemap-generator.php";
  $sitemap = new Sitemap;
```
If you skip any url 
```php
	$sitemap->skip(["https://www.lipsum.com/skip"]);
```
if you set priority. default priority is 1.0
```php
	$sitemap->priority("1.0");
```
if you set frequency. default frequency is `daily`
```php
	$sitemap->frequency("weekly");
```
set crewl url
```php
	$sitemap->url("https://www.lipsum.com/");
```
## Output
if you output only url in array
```php
	$scan = $sitemap->scan();
  
  echo '<pre>';
	print_r ($scan);
```
if you output xml formated data in array
```php
  $render = $sitemap->render();
   
  echo '<pre>';
	print_r ($render);
```
if you output xml file
```php
  header('Content-Type: text/xml; charset=utf-8');
  echo $sitemap->makeXML();
```
if you force download
```php
	$sitemap->download(); // filename is sitemap.xml
  or
  $sitemap->download('filename.xml');
```
if you save file your directory
```php
	$sitemap->save(); // filename is sitemap.xml
  or
  $sitemap->save('filename.xml');
```
