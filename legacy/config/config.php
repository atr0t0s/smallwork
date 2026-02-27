<?php

namespace Config;

require_once(__DIR__ . '/bootstrap.php');

include_once Boot::strap()['path']['project'] . 'includes/autoload.php';
date_default_timezone_set(Boot::strap()['general']['default_timezone']);
ini_set("display_errors", Boot::strap()['general']['display_errors']);
ini_set("expose_php", Boot::strap()['general']['expose_php']);

class Config
{
  private $basepath;
  private $appmode;
  private $rawdomain;
  private $apiroute;
  private $webroute;
  private $webviews;

  function __construct()
  {
    $conf = Boot::strap();
    $this->basepath   = $conf['path']['base'];
    $this->appmode    = $conf['general']['app_mode'];
    $this->rawdomain  = $conf['general']['raw_domain'];
    $this->apiroute   = $conf['path']['api_route'];
    $this->webroute   = $conf['path']['web_route'];
    $this->webviews   = $conf['path']['web_views'];
  }

  public function get()
  {
    return [
      "base_path"   => $this->basepath,
      "app_mode"    => $this->appmode,
      "raw_domain"  => $this->rawdomain,
      "api_route"   => $this->apiroute,
      "web_route"   => $this->webroute,
      "web_views"   => $this->webviews
    ];
  }
}
