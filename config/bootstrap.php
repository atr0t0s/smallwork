<?php

namespace Config;

class Boot
{
  public static function strap()
  {
    if (is_file(__DIR__ . '/config.local.ini')) {
      $conf = parse_ini_file("config.local.ini", true, INI_SCANNER_TYPED);
    } else {
      $conf = parse_ini_file("config.ini", true, INI_SCANNER_TYPED);
    }

    return $conf;
  }
}

