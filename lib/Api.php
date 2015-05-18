<?php
namespace Trackvia;

require_once 'EventDispatcher.php';
require_once 'Request.php';
require_once 'Authentication.php';


class Api extends EventDispatcher {


    private $request;

    private $user;

    private $accountId;

    private $formCache = array();

    private $baseUrl;

    private $userKey;

    public function __construct($user, 
                        $password, 
                        $userKey, 
                        $baseUrl = "https://go.trackvia.com/") {
        $this->baseUrl = $baseUrl;
        $this->userKey = $userKey;
        $this->request = new Request();
        $this->auth = new Authentication($this->request, $user, $password, $baseUrl);
        $this->auth->on('new_access_token', array($this, 'onNewAccessToken'));
    }

    /**
     * Method to handle the new_token even trigger by the authentication class
     * Bubble up the event with token data for the client.
     */
    public function onNewAccessToken($data)
    {
        $this->trigger('new_token', $data);
    }

    public function login(){

        $this->user = $this->getUser();

        if(!empty($this->user['accounts'])){
            $account = array_shift($this->user['accounts']);
            if(isset($account['id'])){
                $this->accountId = $account['id'];
            } else {
                throw new \Exception('Unable to determine accountId, this is not good');
            }
        } else {
            throw new \Exception('Unable to find account for user, will not be able to get data');
        }
        return $this->user;
    }

    /**
     * Authenticate the user with OAuth2
     * @return array Access token data
     */
    public function authenticate()
    {
        return $this->auth->authenticate();
    }


    /**
     * Make an api request.
     *
     * @param  string $url
     * @param  string $httpMethod The http method to use with this request
     * @param  string $data Optional data to send with request
     * @param  string $contentType
     * @return array The json parsed response from the server
     */
    public function api($url, $httpMethod = 'GET', $data = null, $contentType = null)
    {
        $url = $this->baseUrl . $url;
        // trigger an event
        $this->trigger('api_request_init', array('url' => $url));

        $this->authenticate();

        $accessToken = $this->auth->getAccessToken();
        if (!$accessToken) {
            // should have a token at this point
            // if not, something went wrong
            throw new \Exception('Cannot make an api request without an access token');
        }

        // save this request in case we need to use the refresh token
        $lastRequest = array(
            'url'    => $url,
            'method' => $httpMethod,
            'data'   => $data
        );

        // add the access token onto the url
        $pre = "?";
        if(strpos($url, "?") !== false){
            $pre = "&";
        }
        $url = $url . $pre.'access_token='.urlencode($accessToken);
        //add the user_key
        $url = $url . '&user_key=' . $this->userKey;


        $this->request
            ->setMethod($httpMethod)
            ->setData($data);

        if ($contentType) {
            $this->request->setContentType($contentType);
        }

        $this->trigger('api_request_send', array('url' => $url, 'http_method' => $httpMethod, 'data' => $data));

        // now send the request
        $this->request->send($url);

        $this->trigger('api_request_complete', array('url' => $url, 'response' => $this->request->getResponse()));

        // check the response for any errors
        $vaild = $this->checkResponse();

        if (!$vaild && $this->isTokenExpired) {
            // blow out the current token so a new one gets requested
            $this->auth->clearAccessToken();

            // redo the last api request
            $this->api(
                $lastRequest['url'],
                $lastRequest['method'],
                $lastRequest['data']
            );
        }

        return $this->request->getResponse();
    }

    /**
     * Check if the response failed and if the token is expired.
     *
     * Any errors returned from the API server will be thrown as an Exception.
     *
     * @param  array $response
     * @return boolean
     */
    private function checkResponse()
    {
        $response = $this->request->getResponse();
        if (is_array($response) && isset($response['error_description'])) {
            if($response['error_description'].strpos('Access token expired') !== false) {
                $this->isTokenExpired = true;
                // return here so we don't throw this error
                // so we can use the refresh token
                return false;
            }

            // throw an \Exception with the returned error message
            throw new \Exception('API Error :: ' . $response['error_description']);
        }

        return true;
    }


    /**
     * returns a list of views.
     * @return an array of views
     */
    public function getViewList(){
        $url = "/openapi/views";
        return $this->api($url, 'GET');
    }

    /**
     * Returns a list of views filtering on the view name
     * @param The name to filter on, works for exact matches only.
     * @return an array of views
     */
    public function getViewListFilterOnName($name){
        $url = "/openapi/views?name=" . urlencode($name);
        return $this->api($url, 'GET');
    }


    /**
     * Gets the records in a view
     * @param The numeric ID of the view you want to get records from
     * @param The start index of the results, defaults to 0
     * @param The max number of results to return, defaults to 1000 
     * @return an object containing the meta data for the fields in the view and the records themselves
     */
    public function getRecordsInView($viewId, $start = 0, $max = 1000){
        $url = '/openapi/views/' . $viewId . '?start=' . $start . '&max=' . $max;
        return $this->api($url, 'GET');
    }

    /**
     * Gets the records in a view
     * @param The numeric ID of the view you want to get records from
     * @param The query string to filter records by
     * @param The start index of the results, defaults to 0
     * @param The max number of results to return, defaults to 1000 
     * @return an object containing the meta data for the fields in the view and the records themselves
     */
    public function getRecordsInViewSearch($viewId, $queryString, $start = 0, $max = 1000){
        $url = '/openapi/views/' . $viewId . '/find?start=' . $start . '&max=' . $max 
            . '&q=' . urlencode($queryString);
        return $this->api($url, 'GET');
    }

    /**
     * Gets the applications in the current account
     * @return a list of applications
     */
    public function getApps(){
        $url = '/openapi/apps';
        return $this->api($url, 'GET');
    }

    /**
     * Gets the users in the current account
     * @return a list of users and meta data about the fields in the users table
     */
    public function getUsers(){
        $url = '/openapi/users';
        return $this->api($url, 'GET');
    }

    /**
     * Creates a user
     * @param first name of the new user
     * @param last name of the new user
     * @param email address of the new user
     * @param prefered timezone, optional, specified by name, such as 'America/Denver'
     * @return an object representing the newly created user
     */
    public function createUser($firstName, $lastName, $email, $timeZone = null){
        $url = '/openapi/users';
        $url = $url . '?firstName=' . urlencode($firstName);
        $url = $url . '&lastName=' . urlencode($lastName);
        $url = $url . '&email=' . urlencode($email);
        if($timeZone != null){
            $url = $url . '&timeZoie=' . urlencode($timeZone);
        }
        return $this->api($url, 'POST');
    }

    /**
     * Gets the data for one record
     * @param the numeric ID of the view to get the record from
     * @param the numeric ID of the record to get
     * @return a map containing the requested data
     */
    public function getRecord($viewId, $recordId){
        $url = '/openapi/views/' . $viewId . '/records/' . $recordId;
        return $this->api($url, 'GET');
    }

    /**
     * Create a new record in a given view
     * @param the numeric ID of the view to create the record in
     * @param a map representing the data the new record should contain
     * @return a map containing the newly created record
     */
    public function createRecord($viewId, $data){
        $url = '/openapi/views/' . $viewId . '/records';
        $content_type = "application/json;charset=UTF-8";
        $json = json_encode($data);
        return $this->api($url, 'POST', $json, $content_type);
    }

    /**
     * Update a record in a view
     * @param the numeric ID of the view the record is in
     * @param the numeric ID of the record you want to update
     * @param a map representing the values you want to update
     * @return a map containing the newly updated record
     */
    public function updateRecord($viewId, $recordId, $data){
        $url = '/openapi/views/' . $viewId . '/records/' . $recordId;
        $content_type = "application/json;charset=UTF-8";
        $json = json_encode($data);
        return $this->api($url, 'PUT', $json, $content_type);
    }

    /**
     * Delete a record in a view
     * @param the numeric ID of the view the record is in
     * @param the numeric ID of the record you want to update
     * @return Nothing if successful, else an error message
     */
    public function deleteRecord($viewId, $recordId){
        $url = '/openapi/views/' . $viewId . '/records/' . $recordId;
        return $this->api($url, 'DELETE');
    }

    /**
     * Delete all records in a view
     * @param the numeric ID of the view the records are in
     * @return Nothing if successful, else an error message
     */
    public function deleteAllRecords($viewId){
        $url = '/openapi/views/' . $viewId . '/records/all';
        return $this->api($url, 'DELETE');
    }

    /**
     * Upload a file to a record in a view
     * @param the numeric ID of the view the record is in
     * @param the numeric ID of the record you want to update
     * @param the name of the field to upload the file to.
     * @param the path to the file to upload
     * @param the content type of the file, defaults to application/octet-stream
     * @return a map containing the newly updated record
     */
    public function uploadFileToRecord($viewId, $recordId, $fieldName, $filePath,
            $contentType = 'application/octet-stream'){
        $url = '/openapi/views/' . $viewId . '/records/' . $recordId;
        $url = $url . '/files/' . urlencode($fieldName);
        
        $filePath = realpath($filePath);
        $data = file_get_contents($filePath);
        $fileName = call_user_func("end", explode(DIRECTORY_SEPARATOR, $filePath));

        $body[] = implode("\r\n", array(
            "Content-Disposition: form-data; name=\"file\"; filename=\"$fileName\"",
            "Content-Type: $contentType",
            "",
            $data, 
        ));

        //print_r ($body);
        //exit;

         // generate safe boundary 
        do {
            $boundary = "---------------------" . md5(mt_rand() . microtime());
        } while (preg_grep("/{$boundary}/", $body));

        // add boundary for each parameters
        array_walk($body, function (&$part) use ($boundary) {
            $part = "--{$boundary}\r\n{$part}";
        });
    
        // add final boundary
        $body[] = "--{$boundary}--";
        $body[] = "";

        $contentType = "multipart/form-data; boundary={$boundary}";

        return $this->api($url, 'POST', implode("\r\n", $body), $contentType);
    }



    /**
     * Download a file from a field in a record in a view
     * @param the numeric ID of the view the record is in
     * @param the numeric ID of the record you want to get the file from
     * @param the name of the field to download the file from.
     * @return binary data of the file
     */
    public function downloadFileFromRecord($viewId, $recordId, $fieldName, $params = null){

        $url = 'openapi/views/' . $viewId . '/records/' . $recordId;
        $url = $url . '/files/' . urlencode($fieldName);

        $url = $this->baseUrl . $url;
        //authenticate
        $this->authenticate();
        $accessToken = $this->auth->getAccessToken();
        // add the access token onto the url
        $pre = "?";
        if(strpos($url, "?") !== false){
            $pre = "&";
        }
        $url = $url . $pre.'access_token='.urlencode($accessToken);
        //add the user_key
        $url = $url . '&user_key=' . $this->userKey;
        if($params != null){
            foreach($params as $key=>$value){
                $url = $url . '&'.$key.'='.urlencode($value);
            }
        }

        echo "\nURL: ". $url . "\n";

        $data = file_get_contents($url);
        $jsonData = json_decode($data);

        if (is_array($jsonData) && isset($jsonData['error_description'])) {
            if($jsonData['error_description'].strpos('Access token expired') !== false) {
                $this->auth->clearAccessToken();
                $url = 'openapi/views/' . $viewId . '/records/' . $recordId;
                $url = $url . '/files/' . urlencode($fieldName);

                $url = $this->baseUrl . $url;
                //authenticate
                $this->authenticate();
                $accessToken = $this->auth->getAccessToken();
                // add the access token onto the url
                $pre = "?";
                if(strpos($url, "?") !== false){
                    $pre = "&";
                }
                $url = $url . $pre.'access_token='.urlencode($accessToken);
                //add the user_key
                $url = $url . '&user_key=' . $this->userKey;
                if($params != null){
                    foreach($params as $key=>$value){
                        $url = $url . '&'.$key.'='.urlencode($value);
                    }
                }

                $data = file_get_contents($url);
            }
        }
        return $data;

    }

     /**
     * Download an image from a field in a record in a view with the given width
     * @param the numeric ID of the view the record is in
     * @param the numeric ID of the record you want to get the file from
     * @param the name of the field to download the file from.
     * @param the desired width of the image.
     * @return binary data of the file
     */
    public function downloadFileFromRecordWidth($viewId, $recordId, $fieldName, $width){
        $param = array('width'=>$width);
        return $this->downloadFileFromRecord($viewId, $recordId, $fieldName, $param);
    }

    /**
     * Download an image from a field in a record in a view with the given width
     * @param the numeric ID of the view the record is in
     * @param the numeric ID of the record you want to get the file from
     * @param the name of the field to download the file from.
     * @param the desired max dimension of the image
     * @return binary data of the file
     */
    public function downloadFileFromRecordMaxDim($viewId, $recordId, $fieldName, $maxDim){
        $param = array('maxDimension'=>$maxDim);
        return $this->downloadFileFromRecord($viewId, $recordId, $fieldName, $param);
    }
        

    /**
     * Delete a file from a field in a record in a view
     * @param the numeric ID of the view the record is in
     * @param the numeric ID of the record you want to delete the file from
     * @param the name of the field to delete the file from.
     */    
    public function deleteFileFromRecord($viewId, $recordId, $fieldName){

        $url = 'openapi/views/' . $viewId . '/records/' . $recordId;
        $url = $url . '/files/' . urlencode($fieldName);
        return $this->api($url, 'DELETE');
    }
    







}
