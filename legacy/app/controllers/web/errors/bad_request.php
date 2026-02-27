<?php
//TODO: Implement an actual view renderer
class BadRequest extends Exception
{

  public static function index()
  {

    $response = [
      'error' => "Bad Request"
    ];

    return $response;
  }

  public static function ArgumentCountError()
  {

    $response = [
      'error' => "Wrong number of parameters."
    ];

    return $response;
  }
}
