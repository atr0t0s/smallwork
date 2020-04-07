<?php
//TODO: Implement an actual view renderer
class BadRequest extends Exception
{

    public function index()
    {

        $response = [
            'error' => "Bad Request"
        ];

        return $response;
    }

    public function ArgumentCountError()
    {

        $response = [
            'error' => "Wrong number of parameters."
        ];

        return $response;

    }
}
