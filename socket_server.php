<?php
include_once "SocketServer.php";

class TestResponse
{
    public function request()
    {
        switch($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $this->get();
                break;
            case 'POST':
                $this->post();
                break;
            case 'DELETE':
                $this->delete();
                break;
            case 'PUT':
                $this->put();
                break;
            default: 
                $this->get();
        }
    }

    public function get()
    {
        echo file_get_contents("template/index.html");
    }

    public function post()
    {
        print_r($_POST);
        print_r($_REQUEST);
        print_r(file_get_contents("php://input"));
    }

    public function put()
    {
        echo "<pre>";
        print_r($_SERVER);
        echo "PUT Method";
    }

    public function delete()
    {
        echo "delete method";
    }


}
$tResponse = new TestResponse();


$socketServer = SocketServer::factory("unix", "/tmp/echo.sock");

$socketServer->run(function() use ($tResponse) {
    $tResponse->request();
});


