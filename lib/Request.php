<?php 
namespace Trackvia;

class Request
{
    private $curl;

    /**
     * The data returned from the last response
     * @var mixed
     */
    private $response;

    private $method = 'GET';

    private $url;

    private $postData;

    private $headers = array();

    public function __construct()
    {
        $this->curl = curl_init();
    }

    public function addHeader($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setContentType($contentType)
    {
        return $this->addHeader('Content-Type', $contentType);
    }

    public function setMethod($method)
    {
        $method = strtoupper($method);
        if ( !in_array($method, array('POST', 'GET', 'PUT', 'DELETE')) ) {
            throw new \Exception('HTTP request method "' . $method . '" not supported');
        }
        $this->method = $method;
        return $this;
    }

    public function setData($data)
    {
        $this->postData = $data;
        return $this;
    }

    public function send($url)
    {
        $data = $this->postData;

        // tack data onto the query string for GET requests
        if (($this->method == 'GET' || $this->method == 'DELETE') && is_array($data) && !empty($data)) {

            $queryString = http_build_query($data);
            if (strpos($url, '?') === false) {
                $url .= '?'.$queryString;
            } else {
                // query string is already part of the url
                $url .= '&'.$queryString;
            }
            $data = array(); //empty the data array
        }

        $ch = $this->curl;
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
        if (!empty($data)) {
            // set any post data
            
            if(is_array($data)){
                $dataSet = http_build_query($data);
            } else {
                $dataSet = $data;
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $dataSet);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        // set any headers
        if (!empty($this->headers)) {
            $headers = array();
            foreach ($this->headers as $name => $value) {
                $headers[] = "$name: $value";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $this->response = curl_exec($ch);        
        $this->response = json_decode($this->response, true);

        return $this->response;
    }

    /**
     * Get the data returned from the last response
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Get the response http code from the last request
     * @return int
     */
    public function getResponseCode()
    {
        return (int) curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
    }

    public function debug()
    {
        var_dump( curl_getinfo($this->curl));
    }

}