A PHP SDK for accessing your Trackvia application data.

## Features

1. Simple client to access the Trackvia API
 
## API Access and The User Key

Obtain a user key by enabling the API at:

  https://go.trackvia.com/#/my-info

Note, the API is only available for Enterprise level accounts

## Usage

First instantiate a TrackVia API object

require_once 'lib/Api.php';
use Trackvia\Api;
$api = new Api("userName", "password", "12345abc");