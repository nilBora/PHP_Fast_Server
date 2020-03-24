<?php

class UnixSocketServer implements ISocketServer
{
    const UNIX_SOCKET = "unix";
    const UNIX_SOCKET_ADDR = "/tmp/echo.sock";
    const SOCKET_TIMEOUT = 60;
    
    private $_type;
    private $_addr;
    private $_socket;

    public function __construct($address=self::UNIX_SOCKET_ADDR)
    { 
        $this->_addr = $address;
        
        if (file_exists($address)) {
            $cmd = "rm ".$address;
            `$cmd`;
        }

        $socket = stream_socket_server(static::UNIX_SOCKET."://".$address, $errno, $errstr);

        if (!$socket) {
            throw new SocketServerException("$errstr ($errno)");
        }
        $this->_socket = $socket;
    }

    public function run($function)
    {
        while(true) {
            $this->runSocket($function);
        }
    }

    protected function runSocket($function)
    {
        //XXX: Need handle Warning
        while ($conn = @stream_socket_accept($this->_socket, static::SOCKET_TIMEOUT)) { // XXX:  Operation timed out
            $response = fread($conn, 1024);
            $this->preparedResponse($response);
            

            ob_start();
            call_user_func($function);
            $result = ob_get_contents();
            ob_end_clean();

            $this->doSendStream($conn, str_replace("\n", "", $result)."\n");
            //$this->_doSendStream($conn, $result."~");
            fclose($conn);
        }
    }

    protected function preparedResponse(string $str)
    {
        $response = new Response($str);
        
        print_r($response);
        $GET = [];
        $POST = [];
        $COOKIE = [];
        
        $GET = $response->getRequest();

        $_GET = $GET;
        $_REQUEST = array_merge($GET, $POST, $COOKIE);
        $_SERVER = $this->getPreparedServerConf($response);
    }

    protected function getPreparedServerConf(Response $response): array
    {
        $server = [];

        $server['REQUEST_URI'] = $response->getUrl();
        $server['REQUEST_TIME'] = time();
        $server['REQUEST_TIME_FLOAT'] = microtime(true);
        //$server['REMOTE_ADDR'] = $response['attributes']['ipAddress'] ?? $response['remoteAddr'] ?? '127.0.0.1';
        $server['REQUEST_METHOD'] = $response->getRequestMethod();

        $server['HTTP_USER_AGENT'] = '';
        
        $headers = $response->getHeaders();
        foreach ($headers as $name => $value) {
            $name = strtoupper(str_replace('-', '_', $name));
            if (in_array($name, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $server[$name] = $value;
                continue;
            }
            
            $server['HTTP_' . $name] = $value;
        }

        return $server;
    }

    protected function doSendStream($conn, $string)
    {
        $fwrite = 0;
        for ($written = 0; $written < strlen($string); $written += $fwrite) {
            $fwrite = fwrite($conn, substr($string, $written));
            if ($fwrite === false) {
                return $written;
            }
        }
        return $written;
    }

    public function __destruct()
    {
        fclose($this->_socket);
    }
}