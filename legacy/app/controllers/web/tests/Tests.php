<?php
//TODO: Implement an actual view renderer
class Tests
{

    public function test($arg, $arg2, $arg3)
    {

        header("Content-type: application/json");

        $response = [
            'first' => $arg,
            'second' => $arg2,
            'third' => $arg3
        ];

        echo json_encode($response);
    }
}
