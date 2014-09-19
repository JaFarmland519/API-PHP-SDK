A PHP SDK for accessing your Trackvia application data.

## Features

1. Simple client to access the Trackvia API
 
## API Access and The User Key

Obtain a user key by enabling the API at:

  https://go.trackvia.com/#/my-info

Note, the API is only available for Enterprise level accounts

## Usage

First instantiate a TrackVia API object

```PHP
require_once 'lib/Api.php';
use Trackvia\Api;
$api = new Api("userName", "password", "12345abc");
```

Then make calls against the api, list views:

```PHP
$views = $api->getViewList();
```

Query records with "fish" in them from view 2:
```PHP
$records = $api->getRecordsInViewSearch(2, 'fish');
```

Create records
```PHP
$newRecord = ['data'=>array(
    [
        'Customer'=>'Acme',
        'License'=>7654321,
        'Maintenance'=>1234567,
        'State'=>'CO',
        'Account Manager'=>'Joe',
        'Close Date'=> 2014-09-19
    ]
)];

$record = $api->createRecord(2, $newRecord);
```

