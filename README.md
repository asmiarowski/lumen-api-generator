# lumen-api-generator
Generates boilerplate for lumen REST API: migration, controller, model, request and route.

<h3>Installation</h3>
```
composer require --dev asmiarowski/lumen-api-generator
```
<p>Add this to app\Providers\AppServiceProvider inside boot() method:</p>
```
if ($this->app->environment() == 'local') {
    $this->app->register('Smiarowski\Generators\GeneratorsServiceProvider');
}
```
For POST / PUT data to work you either have to send your request with `Accept: application/json` header or set up json responses globally in app/Http/Requests/Request.php like so:
```
/**
 * Overwrite Laravel Request method because API is always returning json
 * @return bool
 */
public function wantsJson()
{
    return true;
}
```
<h3>Command syntax</h3>
```
php artisan make:api-resource <table_name> --schema="<column_name>:<column_type>(<arguments>):<option>(<arguments>); [...]" --softdeletes
```
<h3>Command options</h3>
<p>--schema - required, schema of your migration, validators will be set based on fields and types specified.</p>
<p>--softdeletes - optional, add softDeletes() to migration</p>
<h3>Column types</h3>
<p>http://laravel.com/docs/5.1/migrations#creating-columns</p>
<h3>Custom types</h3>
<p>- email - puts string type column in your migration and email validation for your request</p>
<h3>Column options</h3> 
<p>foreign, index, unique, default, nullable, first, after, unsigned</p>
<h3>Example command</h3>
```
php artisan make:api-resource emails --schema="email:email:unique; title:string; body:text; status:integer:default(1); user_id:integer:foreign(users)" --softdeletes
```
<p>Creates:</p> 
<p>app/Http/Controllers/EmailController.php</p>
<p>app/Htpp/Requests/EmailRequest.php</p>
<p>app/Email.php</p>
<p>database/migrations/*timestamp*_create_emails_table.php</p>
<p>And appends resource routes to app/routes.php with pattern for id of the resource.</p>
