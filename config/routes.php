<?php

namespace Config;

class Routes
{

  public static function getRoutes()
  {
    return [

      /* 
                api and web routes depend on app_mode
                and cannot be used concurrently
            */

      /*-- /app/controllers/api/ --*/
      "tests" => "tests/Tests.php"

      /*-- /app/controllers/web/ --*/

    ];
  }
}
