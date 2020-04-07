<?php

class BadRequest extends Exception
{

    public function index()
    {
        header("Content-type: application/json");

        $response = [
            'error' => "Bad Request"
        ];

        echo json_encode($response);

    }

    public function ArgumentCountError()
    {
        header("Content-type: application/json");

        $response = [
            'error' => "Wrong number of parameters."
        ];

        echo json_encode($response);
    }

}
