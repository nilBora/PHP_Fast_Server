<?php

class Response
{
    const OPTION_FORM    = "Form";
    const OPTION_URL     = "Url";
    const OPTION_METHOD  = "Method";
    const OPTION_HEADERS = "Headers";
    
    private $_params;
    
    public function __construct(string $str)
    {
        $responseData = json_decode($str, true);
        
        if (!$responseData) {
            throw new ResponseException("Data Is Not Correct");
        }
        
        $this->_params = $responseData;
    } // end __construct
    
    public function get($key)
    {
        if (!$this->has($key)) {
            throw new ResponseException();
        }
        return $this->_params[$key];
    } // get
    
    public function has($key)
    {
        return array_key_exists($key, $this->_params);
    } // end has
    
    public function getRequest()
    {
        $request = [];
        parse_str($this->get(static::OPTION_FORM), $request);
        
        return $request;
    }
    
    public function getUrl()
    {
        return $this->get(static::OPTION_URL);
    }
    
    public function getRequestMethod()
    {
        return $this->get(static::OPTION_METHOD);
    }
    
    public function getHeaders()
    {
        $headers = [];
        foreach ($this->get(static::OPTION_HEADERS) as $row) {
            $headerChunks = explode(":", $row);
            $name = trim(array_shift($headerChunks));
            $value = trim(array_shift($headerChunks));
            $headers[$name] = $value;
        }
        return $headers;
    }
}

class ResponseException extends Exception
{
}