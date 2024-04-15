<?php

class BadRequest extends Exception
{

  public static function index()
  {
    header("Content-type: application/json");

    $response = [
      'error' => "Bad Request"
    ];

    echo json_encode($response);
  }

  public static function ArgumentCountError()
  {
    header("Content-type: application/json");

    $response = [
      'error' => "Wrong number of parameters."
    ];

    echo json_encode($response);
  }
}
