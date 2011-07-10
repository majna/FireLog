# FirePHP Log engine

FirePHP Handler (http://www.firephp.org/), which uses the Wildfire protocol, providing logging to Firebug Console from PHP.

WARNING: Using FirePHP on production sites can expose sensitive information. 
You must protect the security of your application by disabling FirePHP logging on production site.
 
## Usage

Add to app bootstrap:

``` php
<?php
CakePlugin::load('FireLog');
App::uses('CakeLog', 'Log');
CakeLog::config('fire', array('engine' => 'FireLog.FireLog'));
```

Cake will autoconfigure file log, but only if there's no already configured handlers. So append file config too:

``` php
CakeLog::config('file', array('engine' => 'FileLog'));
```

Test it now in controller:

``` php
CakeLog::write('error', 'Missing key: '. print_r($this->request, true));
```

## Requirements

    PHP version: PHP 5.2+
    CakePHP version: 2.x
