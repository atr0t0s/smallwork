<?php
namespace Config;

require_once(__DIR__ . '/bootstrap.php');

include_once Boot::strap()['path']['project'] . 'includes/autoload.php';
date_default_timezone_set(Boot::strap()['general']['default_timezone']);
ini_set("display_errors", Boot::strap()['general']['display_errors']);
ini_set("expose_php", Boot::strap()['general']['expose_php']);

class Config {
    public function get() {
        $conf = Boot::strap();
        $config['base_path'] = $conf['path']['base'];
        $config['app_mode'] = $conf['general']['app_mode'];
        $config['raw_domain'] = $conf['general']['raw_domain'];
        $config['api_route'] = $conf['path']['api_route'];
        $config['web_route'] = $conf['path']['web_route'];
        $config['web_views'] = $conf['path']['web_views'];

        return $config;
    }
}
