<?php

class extension_URL_Connector extends Extension
{
    private $_has_run = false;

    public function install()
    {
        Symphony::Database()->query("
            CREATE TABLE IF NOT EXISTS `tbl_url_connections` (
                `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                `title` VARCHAR(255) DEFAULT NULL,
                `path_from` VARCHAR(255) DEFAULT NULL,
                `path_to` VARCHAR(255) DEFAULT NULL,
                `action` VARCHAR(255) DEFAULT NULL,
                `param_tests` VARCHAR(1023) DEFAULT NULL,
                `include_php` CHAR(1) DEFAULT NULL,
                `php` VARCHAR(8191) DEFAULT NULL,
                `run_data` VARCHAR(4095) DEFAULT NULL,
                `sortorder` INT(11) UNSIGNED NOT NULL,
                PRIMARY KEY (`id`)
            )  ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ");
    }

    /**
     * Uninstall
     */
    public function uninstall()
    {
        Symphony::Database()->query("DROP TABLE IF EXISTS `tbl_url_connections`");
        Symphony::Database()->delete('tbl_pages_types', " `type` = 'NDA'");
    }

    public function fetchNavigation()
    {
        return array(
            array(
                'location' => 'Blueprints',
                'name' => 'URL Connections',
                'link' => '/blueprints/url-connections/',
                'relative' => false
            )
        );
    }

    public function getSubscribedDelegates()
    {
        return array(
            array(
                'page' => '/backend/',
                'delegate' => 'AdminPagePostCallback',
                'callback' => 'postCallback'
            ),
            array(
                'page' => '/blueprints/pages/',
                'delegate' => 'AppendPageContent',
                'callback' => 'appendPageContent'
            ),
            array(
                'page'      => '/frontend/',
                'delegate'  => 'FrontendPrePageResolve',
                'callback'  => 'frontendPrePageResolve'
            ),
            array(
                'page' => '/frontend/',
                'delegate' => 'FrontendParamsResolve',
                'callback' => 'paramsResolve'
            )
        );
    }

    public function postCallback($context)
    {
        $driver = $context['callback']['driver'];
        if ($driver == 'blueprintsurl-connections') {
            $values = array(
                'driver' => 'blueprintsurl-connections',
                'driver_location' => EXTENSIONS . '/url_connector/content/content.url_connections.php',
                'pageroot' => '/extensions/url_connector/content/',
                'classname' => 'contentExtensionURL_ConnectorURL_Connections'
            );
        } else if ($driver == 'ajaxreorder' && $context['parts'][2] == 'blueprints/url-connections') {
            $values = array(
                'driver' => 'ajaxreorder',
                'driver_location' => EXTENSIONS . '/url_connector/content/content.ajaxreorder.php',
                'pageroot' => '/extensions/url_connector/content/',
                'classname' => 'contentExtensionURL_ConnectorAjax_Reorder'
            );
        }
        if (isset($values)) {
            $context['callback'] = array_merge($context['callback'], $values);
        }
    }

    public function appendPageContent($context)
    {
        $form = $context['form'];
        $fieldset = $form->getChildByName('fieldset', 0);
        $div = $fieldset->getChildByName('div', 0);
        $div = $div->getChildByName('div', 1);
        $ul = $div->getChildByName('ul', 0);
        $found = false;
        foreach (array_reverse($ul->getChildren()) as $tag) {
            if ($tag->getValue() == 'NDA') {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $ul->appendChild(new XMLElement('li', 'NDA'));
        }
    }

    public function frontendPrePageResolve($context)
    {
        if ($this->_has_run) return;
        $this->_has_run = true;

        $ignores = array('GET' => 'route post', 'POST' => 'route get');
        $ignore = $ignores[$_SERVER['REQUEST_METHOD']];
        $path_given = trim($context['page'], '/');
        $sql = "SELECT path_to, action, include_php, php, run_data FROM `tbl_url_connections` ORDER BY sortorder";
        $routes = Symphony::Database()->fetch($sql);
        $route_matched = null;

        //foreach ($routes as $route) {
        //while (!$route_matched && $route = each($routes)[1]) {
        for (;
            !$route_matched && $route = current($routes);
            next($routes)
        ) {
            if ($route['action'] == $ignore) continue;
            if (!$route['run_data']) continue;
            $run_data = unserialize($route['run_data']);
            if (!preg_match($run_data['from_regexp'], $path_given, $matches)) continue;

            // Path matched:

            // Make path parameter array
            if ($run_data['param_names']) {
                array_shift($matches);
                $path_params = array_combine($run_data['param_names'], $matches);
            }

            $conditions_met = true;

            while ($conditions_met && list($name, $type) = each($run_data['param_type_tests'])) {
                switch ($type) {
                    case 'numeric':
                        $conditions_met = is_numeric($path_params[$name]);
                        break;
                    case 'non-numeric':
                        $conditions_met = !is_numeric($path_params[$name]);
                        break;
                    default:
                        break;
                }
            }
            if (!$conditions_met) continue;

            // Execute PHP if there is any
            if ($route['include_php'] && $route['php']) {
                if ($this->evalPHP($route['php']) === false) continue;
            }

            $route_matched = $route['path_to'];
        }

        if ($route_matched) {
            $vars = $_SERVER;
            if (isset($path_params)) {
                $vars = array_merge($_SERVER, $path_params);
            }
            $route_matched = preg_replace_callback(
                '/\{[\w\-]+\}/',
                function($matches) use ($vars)
                {
                    return $vars[rtrim(ltrim($matches[0], '{'), '}')];
                },
                $route_matched
            );
            $action_type = explode(' ', $route['action']);
            //print_r($action_type); die;
            switch ($action_type[0]) {
                case 'route':
                    $context['page'] = $route_matched;
                    break;
                case 'redirect':
                    header('Location:' . $route_matched, true, (int) $action_type[1]);
                    exit;
            }
        } else {
            // Route not matched.
            $page = FrontEnd::Page()->resolvePage($context['page']);
            if (!$page || in_array('NDA', $page['type'])) {
                throw new FrontendPageNotFoundException();
            }
        }
    }

    function evalPHP($php)
    {
        return eval($php);
    }
}