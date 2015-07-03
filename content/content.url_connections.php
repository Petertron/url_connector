<?php

/**
* @package content
*/

require_once TOOLKIT . '/class.administrationpage.php';
require_once TOOLKIT . '/class.resourcemanager.php';

class contentExtensionURL_ConnectorURL_Connections extends AdministrationPage
{
    const E_REQ_FIELD = 1;
    const E_PARAM_DUP = 2;
    const E_PARAM_USED = 3;
    const E_VAR_USED = 4;

    private $_errors = array();
    //protected $_hilights = array();

    public function __construct()
    {
        parent::__construct();
/*
        $this->error_messages = array(
            'required-field' => __('This is a required field'),
            'param-duplication' => __('Parameter duplication'),
            'param-used' => __('Parameter used already'),
            'var-used' => __('Variable used already')
        );*/
        $this->error_messages = array(
            self::E_REQ_FIELD => __('This is a required field'),
            self::E_PARAM_DUP => __('Parameter duplication'),
            self::E_PARAM_USED => __('Parameter used already'),
            self::E_VAR_USED => __('Variable used already')
        );
    }

    public function __viewIndex()
    {
        $this->setPageType('table');
        $this->setTitle(__('%1$s &ndash; %2$s', array(__('URL Connections'), __('Symphony'))));

        $this->appendSubheading(__('URL Connections'), Widget::Anchor(
            __('Create New'), Administration::instance()->getCurrentPageURL() . 'new/',
            __('Create a new URL connection'), 'create button', null, array('accesskey' => 'c')
        ));

        $aTableHead = array(
            array(__('Name'), 'col'),
            array(__('Path From'), 'col'),
            array(__('Path To'), 'col'),
            array(__('Action'), 'col')
        );
        $aTableBody = array();

        $sql = "SELECT id, title, path_from, path_to, action, sortorder FROM `tbl_url_connections` ORDER BY sortorder";
        $routes = Symphony::Database()->fetch($sql);

        if (!is_array($routes) or empty($routes)) {
            $aTableBody = array(Widget::TableRow(array(
                Widget::TableData(__('None found.'), 'inactive', null, count($aTableHead))
            ), 'odd'));

        } else {
            foreach ($routes as $route) {
                $class = array();
                $route_edit_url = Administration::instance()->getCurrentPageURL() . 'edit/' . $route['id'] . '/';

                $col_title = Widget::TableData(Widget::Anchor($route['title'], $route_edit_url));
                $col_title->appendChild(Widget::Label(__('Select Route %s', array($route['title'])), null, 'accessible', null, array(
                    'for' => 'page-' . $route['id']
                )));
                $col_title->appendChild(Widget::Input('items[' . $route['id'] .']', 'on', 'checkbox', array(
                    'id' => 'route-' . $route['id']
                )));

/*                if (in_array($route['id'], $this->_hilights)) {
                    $class[] = 'failed';
                }
*/
                $col_route_from = Widget::TableData($route['path_from']);

                $col_route_to = Widget::TableData($route['path_to']);

                $route_actions = $this->routeActions();

                $col_action = Widget::TableData($route_actions[$route['action']]);

                $aTableBody[] = Widget::TableRow(
                    array($col_title, $col_route_from, $col_route_to, $col_action, $col_conditions),
                    implode(' ', $class)
                );
            }
        }

        $table = Widget::Table(
            Widget::TableHead($aTableHead), null,
            Widget::TableBody($aTableBody), 'orderable selectable',
            null, array('role' => 'directory', 'aria-labelledby' => 'symphony-subheading', 'data-interactive' => 'data-interactive')
        );

        $this->Form->appendChild($table);

        $version = new XMLElement('p', 'Symphony ' . Symphony::Configuration()->get('version', 'symphony'), array('id' => 'version'));
        $this->Form->appendChild($version);

        $tableActions = new XMLElement('div');
        $tableActions->setAttribute('class', 'actions');

        $options = array(
            array(null, false, __('With Selected...')),
            array('delete', false, __('Delete'), 'confirm', null, array(
                'data-message' => __('Are you sure you want to delete the selected routes?')
            ))
        );

        /**
        * Allows an extension to modify the existing options for this page's
        * With Selected menu. If the `$options` parameter is an empty array,
        * the 'With Selected' menu will not be rendered.
        *
        * @delegate AddCustomActions
        * @since Symphony 2.3.2
        * @param string $context
        * '/blueprints/pages/'
        * @param array $options
        *  An array of arrays, where each child array represents an option
        *  in the With Selected menu. Options should follow the same format
        *  expected by `Widget::__SelectBuildOption`. Passed by reference.
        */
        Symphony::ExtensionManager()->notifyMembers('AddCustomActions', '/blueprints/url-connections/', array(
            'options' => &$options
        ));

        if (!empty($options)) {
            $tableActions->appendChild(Widget::Apply($options));
            $this->Form->appendChild($tableActions);
        }
    }

    public function __viewNew()
    {
        $this->__viewEdit();
    }

    public function __viewEdit()
    {
        $this->setPageType('form');
        $this->addScriptToHead(URL . '/extensions/url_connector/assets/url_connector.js');
        $fields = array('title' => null);
        $existing = $fields;

        // Verify page exists:
        if ($this->_context[0] == 'edit') {
            if (!$route_id = (int)$this->_context[1]) {
                redirect(SYMPHONY_URL . '/blueprints/url-connections/');
            }
            //$existing = $this->_driver->fetchRouteByID($route_id);
            $existing = Symphony::Database()->fetchRow(
                0, "SELECT * FROM `tbl_url_connections` WHERE (id='$route_id')"
            );
            if (empty($existing)) {
                Administration::instance()->errorPageNotFound();
            }

            foreach (array('param_tests') as $key) {
                $existing[$key] = unserialize($existing[$key]);
            }
        }

        // Status message:
        if (isset($this->_context[2])) {
            $flag = $this->_context[2];
            $link_suffix = $message = '';
            $time = Widget::Time();

            switch ($flag) {
                case 'saved':
                    $message = __('URL Connection updated at %s.', array($time->generate()));
                    break;
                case 'created':
                    $message = __('URL Connection created at %s.', array($time->generate()));
            }

            $this->pageAlert(
                $message
                . ' <a href="' . SYMPHONY_URL . '/blueprints/url-connections/new/" accesskey="c">'
                . __('Create another?')
                . '</a> <a href="' . SYMPHONY_URL . '/blueprints/url-connections/" accesskey="a">'
                . __('View all URL Connections')
                . '</a>',
                Alert::SUCCESS
            );
        }

        // Find values:
        if (isset($_POST['fields'])) {
            $fields = $_POST['fields'];
        } elseif ($this->_context[0] == 'edit') {
            $fields = $existing;
        }
/*
        if (isset($route_id)) {
            $fields['id'] = $route_id;
        }
*/
        $title = $fields['title'];

        if (trim($title) == '') {
            $title = $existing['title'];
        }

        $this->setTitle(__(
            ($title ? '%1$s &ndash; %2$s &ndash; %3$s' : '%2$s &ndash; %3$s'),
            array(
                $title,
                __('URL Connections'),
                __('Symphony')
            )
        ));
        $this->insertBreadcrumbs(
            array(Widget::Anchor(__('URL Connections'), SYMPHONY_URL . '/blueprints/url-connections/'))
        );

        $this->appendSubheading(!empty($title) ? $title : __('Untitled'));

        $fieldset = new XMLElement('fieldset');
        $fieldset->setAttribute('class', 'settings');
        $fieldset->appendChild(new XMLElement('legend', __('Main Definition')));

        // Title

        $div_columns = new XMLElement('div', null, array('class' => 'two columns'));
        $label = Widget::Label(__('Name'), null, 'column');
        //$label->appendChild(new XMLElement('i', __('Optional')));
        $label->appendChild(Widget::Input(
            'fields[title]', $fields['title']
        ));
        $div_columns->appendChild($label);

        // Action

        $label = Widget::Label(__('Action'), null, 'column');
        $label->appendChild(Widget::Select(
            'fields[action]', $this->optionsArray($this->routeActions(true), $fields['action'])
        ));
        $div_columns->appendChild($label);

        $fieldset->appendChild($div_columns);

        // Path from

        $div_columns = new XMLElement('div', null, array('class' => 'two columns'));
        $label = Widget::Label(__('Path From'), null, 'column');
        $label->appendChild(Widget::Input('fields[path_from]', $fields['path_from']));
        if (isset($this->_errors['path_from'])) {
            $label = Widget::Error($label, $this->_errors['path_from']);
        }

        $div_columns->appendChild($label);

        // Path to

        $label = Widget::Label(__('Path To'), null, 'column');
        $label->appendChild(Widget::Input('fields[path_to]', $fields['path_to']));
        if (isset($this->_errors['path_to'])) {
            $label = Widget::Error($label, $this->_errors['path_to']);
        }

        $div_columns->appendChild($label);
        $fieldset->appendChild($div_columns);

        $this->Form->appendChild($fieldset);

        // Path parameter conditions

        $blueprint = array(
            'base_field' => 'param_tests',
            'name_label' => __('Parameter Name'),
            'templates' => array(
                // Type test
                'type' => array(
                    'data_name' => 'Type Test',
                    'data_type' => 'type',
                    'header' => __('Type Test'),
                    'value_label' => __('Type'),
                    'value_options' => array(
                        'numeric' => __('Numeric'),
                        'non-numeric' => __('Not numeric'),
                    )
                ),
                // Regexp match
                'regexp' => array(
                    'data_name' => 'Regexp Test',
                    'data_type' => 'regexp',
                    'header' => __('Regexp Match'),
                    'value_label' => __('PCRE String, omit the containing slashes')
                )
            ),
        );
        $base_field = 'param_tests';
        $input_array_action = "fields[$base_field][action][]";
        $input_array_value = "fields[$base_field][value][]";

        $fieldset = new XMLElement('fieldset');
        $fieldset->setAttribute('class', 'settings');
        $fieldset->appendChild(new XMLElement(
        'legend',
            __('Path Parameter Conditions'),
            array('id' => 'parameters-legend')
        ));

        $tmpl_name_field = Widget::Label(
            $blueprint['name_label'],
            Widget::Input("fields[$base_field][name][]"),
            'column'
        );

        $group = new XMLElement(
            'div', null,
            array('class' => 'frame', 'id' => 'parameters-duplicator')
        );
        $ol = new XMLElement(
            'ol', null,
            array(
                'data-add' => __('Add condition'),
                'data-remove' => __('Remove condition')
            )
        );

        foreach ($blueprint['templates'] as $action => $template)
        {
            $li = new XMLElement(
                'li', null,
                array(
                'class' => 'template parameter-' . $template['data_type'],
                'data-name' => $template['data_name'],
                'data-type' => $template['data_type']
                )
            );
            $header = new XMLElement('header', null, array('class' => 'frame-header', 'data-name' => $template['header']));
            $header->appendChild(new XMLElement(
                'h4',
                '<strong>New Parameter</strong><span>' . $template['header'] . '</span>'
            ));
            $li->appendChild($header);
            // Action field (hidden)
            $li->appendChild(Widget::Input($input_array_action, $action, 'hidden'));

            $div_columns = new XMLElement('div');
            $div_columns->setAttribute('class', 'two columns');

            // Name field
            $div_columns->appendChild($tmpl_name_field);

            // Value field
            $label = Widget::Label($template['value_label'], null, 'column');
            if (isset($template['value_options'])) {
                $label->appendChild(
                    Widget::Select($input_array_value, $this->optionsArray($template['value_options']))
                );
            } else {
                $label->appendChild(Widget::Input($input_array_value));
            }
            $div_columns->appendChild($label);
            $li->appendChild($div_columns);
            $ol->appendChild($li);
        }

        if (!empty($fields['param_tests'])) {
            $param_tests = $fields['param_tests'];
            for (
                $row_num = 0;
                list($action, $name, $value) = array_column($param_tests, $row_num);
                $row_num++
            ) {
                $template = $blueprint['templates'][$action];
                $li = new XMLElement('li');
                $li->setAttribute('class', 'instance expanded');
                $header = new XMLElement('header', null, array('class' => 'frame-header'));
                $header->appendChild(new XMLElement(
                    'h4',
                    '<strong>' . $name . '</strong><span>' . $template['header'] . '</span>',
                    array('data-name' => $template['header'])
                ));
                $li->appendChild($header);
                //$li->appendChild(new XMLElement('header', $template['header'], array('class' => 'frame-header')));
                // Action field (hidden)
                $li->appendChild(Widget::Input($input_array_action, $action, 'hidden'));

                $div_columns = new XMLElement('div');
                $div_columns->setAttribute('class', 'two columns');
                $label = Widget::Label(
                    $blueprint['name_label'],
                    Widget::Input("fields[$base_field][name][]", $name),
                    'column'
                );
                //if (!empty($this->_errors)) {print_r($this->_errors); die;}
                if (isset($this->_errors[$base_field . $row_num . 'name'])) {
                    $label = Widget::Error($label, $this->_errors[$base_field . $row_num . 'name']);
                }
                $div_columns->appendChild($label);
                $label = Widget::Label($template['value_label'], null, 'column');
                if (isset($template['value_options'])) {
                    $label->appendChild(
                        Widget::Select(
                            $input_array_value, $this->optionsArray($template['value_options'], $value)
                        )
                    );
                } else {
                    $label->appendChild(Widget::Input($input_array_value, $value));
                    if (isset($this->_errors[$base_field . $row_num . 'value'])) {
                        $label = Widget::Error($label, $this->_errors[$base_field . $row_num . 'value']);
                    }
                }
                $div_columns->appendChild($label);
                $li->appendChild($div_columns);
                $ol->appendChild($li);
            }
        }
        $group->appendChild($ol);
        $fieldset->appendChild($group);
        $this->Form->appendChild($fieldset);

        // PHP box
    //echo $fields['include_php']; die;
        $fieldset = new XMLElement(
            'fieldset',
            null,
            array('class' => 'settings')
        );
        $fieldset->appendChild(new XMLElement(
            'legend', __('PHP')
        ));
        //$fieldset->appendChild(Widget::Input('fields[include_php]', 'N', 'hidden'));
        $checkbox = Widget::Input('fields[include_php]', 'Y', 'checkbox', array('id' => 'include-php'));
        if ($fields['include_php']) {
            $checkbox->setAttribute('checked', 'checked');
        }
        $fieldset->appendChild(new XMLElement(
            'label',
            $checkbox->generate() . ' ' . __('Include PHP field')
        ));

        $label = Widget::Label(__('PHP Code'), null, 'column', 'php-box');
        $label->appendChild(Widget::TextArea(
            'fields[php]', 5, 80, $fields['php'], array('style' => 'resize: vertical')
        ));
        if (!$fields['include_php']) {
            $label->setAttribute('style', 'display: none');
        }
        $fieldset->appendChild($label);
        $this->Form->appendChild($fieldset);


        // Controls -----------------------------------------------------------

        /**
        * After all Page related Fields have been added to the DOM, just before the
        * actions.
        *
        * @delegate AppendPageContent
        * @param string $context
        *  '/blueprints/pages/'
        * @param XMLElement $form
        * @param array $fields
        * @param array $errors
        */
/*        Symphony::ExtensionManager()->notifyMembers(
            'AppendPageContent',
            '/blueprints/pages/',
            array(
                'form'        => &$this->Form,
                'fields'    => &$fields,
                'errors'    => $this->_errors
            )
        );
*/
        $div = new XMLElement('div');
        $div->setAttribute('class', 'actions');
        $div->appendChild(Widget::Input(
            'action[save]', ($this->_context[0] == 'edit' ? __('Save Changes') : __('Create Route')),
            'submit', array('accesskey' => 's')
        ));

        if ($this->_context[0] == 'edit') {
            $button = new XMLElement('button', __('Delete'));
            $button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'button confirm delete', 'title' => __('Delete this page'), 'accesskey' => 'd', 'data-message' => __('Are you sure you want to delete this page?')));
            $div->appendChild($button);
        }

        $this->Form->appendChild($div);
    }

    /**
    * Action index
    */
    public function __actionIndex()
    {
        $checked = (is_array($_POST['items'])) ? array_keys($_POST['items']) : null;

        if (is_array($checked) && !empty($checked)) {
            /**
            * Extensions can listen for any custom actions that were added
            * through `AddCustomPreferenceFieldsets` or `AddCustomActions`
            * delegates.
            *
            * @delegate CustomActions
            * @since Symphony 2.3.2
            * @param string $context
            *  '/blueprints/pages/'
            * @param array $checked
            *  An array of the selected rows. The value is usually the ID of the
            *  the associated object.
            */
            Symphony::ExtensionManager()->notifyMembers('CustomActions', '/blueprints/url-connections/', array(
                'checked' => $checked
            ));

            switch ($_POST['with-selected']) {
                case 'delete':
                    $this->__actionDelete($checked, SYMPHONY_URL . '/blueprints/url-connections/');
                    break;
            }
        }
    }

    public function __actionNew()
    {
        $this->__actionEdit();
    }

    public function __actionEdit()
    {
        if ($this->_context[0] != 'new' && !$route_id = (integer) $this->_context[1]) {
            redirect(SYMPHONY_URL . '/blueprints/url-connections/');
        }

        if (@array_key_exists('delete', $_POST['action'])) {
            $this->__actionDelete($page_id, SYMPHONY_URL  . '/blueprints/url-connections/');
        }

        if (@array_key_exists('save', $_POST['action'])) {

            $fields = $_POST['fields'];
            $this->_errors = array();

            $db_fields = array_fill_keys(
                array('title', 'action', 'path_from', 'path_to', 'param_tests', 'include_php', 'php', 'run_data'), null
            );

            try {
                $title = trim($fields['title']);
                $db_fields['title'] = ($title ? $title : 'Noname');

                $db_fields['action'] = $fields['action'];

                $path_from = trim($fields['path_from']);
                if ($path_from[0] != '/') {
                    $path_from = '/' . $path_from;
                }
                $db_fields['path_from'] = $path_from;

                if (preg_match_all('/(\{[\w\-]+\})/', $path_from, $matches)) {
                    $path_param_matches = $matches[0];
                    if (count(array_unique($path_param_matches)) < count($path_param_matches)) {
                        $this->setError('path_from', self::E_PARAM_DUP);
                    }
                }

                $path_to = trim($fields['path_to']);
                $db_fields['path_to'] = $path_to;
                if (!$path_to) {
                    $this->setError('path_to', self::E_REQ_FIELD);
                }

                $db_fields['include_php'] = array_key_exists('include_php', $fields) ? 'Y' : '';
                $db_fields['php'] = $fields['php'];

                $run_data = array(
                    'from_regexp' => null,
                    'param_names' => array(),
                    'param_type_tests' => array(),
                );

                // Parameter conditions

                $param_regexps = array();

                if (isset($fields['param_tests'])) {
                    $actions = $fields['param_tests']['action'];
                    $names = $fields['param_tests']['name'];
                    $values = $fields['param_tests']['value'];
                    for ($row_num = 0; $row_num < count($actions); $row_num++) {
                        if (!($name = trim($names[$row_num]))) {
                            //$this->_errors['param_tests' . $row_num . 'name'] = self::E_REQ_FIELD;
                            $this->setError('param_tests' . $row_num . 'name', self::E_REQ_FIELD);
                        }
                        if (!($value = trim($values[$row_num]))) {
                            $this->setError('param_tests' . $row_num . 'value', self::E_REQ_FIELD);
                        }
                        $action = $actions[$row_num];
                        if ($action == 'type') {
                            if (!isset($run_data['param_type_tests'][$name])) {
                                $run_data['param_type_tests'][$name] = $value;
                            } else {
                                $this->setError('param_tests' . $row_num . 'name', self::E_PARAM_USED);
                                //$this->_errors['param_tests' . $row_num . 'name'] = self::E_PARAM_USED;
                            }
                        } elseif ($action == 'regexp') {
                            if (!isset($param_regexps[$name])) {
                                $param_regexps[$name] = $value;
                            } else {
                                $this->setError('param_tests' . $row_num . 'name', self::E_PARAM_USED);
                                //$this->_errors['param_tests' . $row_num . 'name'] = self::E_PARAM_USED;
                            }
                        }
                        $names[$row_num] = $name;
                        $values[$row_num] = $value;
                    }

                    $db_fields['param_tests'] = serialize(array('action' => $actions, 'name' => $names, 'value' => $values));
                }

                $path_structure = trim($fields['path_from'], '/');
                if (isset($path_param_matches)) {
                    foreach ($path_param_matches as $match) {
                        $name = rtrim(ltrim($name, '{'), '}');
                        $run_data['param_names'][] = $name;
                        $regexp = isset($param_regexps[$name]) ? '(' . $param_regexps[$name] . ')' : '([\w\-:;@~!\[\](){}]+)';
                        $path_structure = str_replace($match, $regexp, $path_structure);
                    }
                }
                $path_structure = str_replace('/', '\/', $path_structure);
                $path_structure = str_replace('*', '[^\/\.]+', $path_structure); // replace asterisks with wildcard regexp
                $run_data['from_regexp'] = "/^$path_structure$/";
                $db_fields['run_data'] = serialize($run_data);
            }
            catch (Exception $e) {
                $this->_errors[$e->getMessage()] = __($this->messages[$e->getCode()]);
            }

            if (empty($this->_errors)) {

                $current = isset($route_id) ?
                    !empty(Symphony::Database()->fetchRow(
                        0, "SELECT 1 FROM `tbl_url_connections` WHERE id = $route_id")
                    ) : false;

                if (!$current) {

                    // New route

                    $next = Symphony::Database()->fetchVar(
                        "next", 0,
                        "SELECT MAX(p.sortorder) + 1 AS `next` FROM `tbl_url_connections` AS p LIMIT 1"
                    );
                    $db_fields['sortorder'] = $next ? $next : '1';

                    Symphony::Database()->insert($db_fields, 'tbl_url_connections');

                    if (!$route_id = Symphony::Database()->getInsertID()) {
                        $this->pageAlert(
                            __('Unknown errors occurred while attempting to save.')
                            . '<a href="' . SYMPHONY_URL . '/system/log/">'
                            . __('Check your activity log')
                            . '</a>.',
                            Alert::ERROR
                        );
                    } else {
                        $redirect = "/blueprints/url-connections/edit/{$route_id}/created/";
                    }
                } else {

                    // Update existing route

                    if (!Symphony::Database()->update($db_fields, 'tbl_url_connections', sprintf("`id` = %d", $route_id))) {
                        return $this->pageAlert(
                            __('Unknown errors occurred while attempting to save.')
                            . '<a href="' . SYMPHONY_URL . '/system/log/">'
                            . __('Check your activity log')
                            . '</a>.',
                            Alert::ERROR
                        );
                    } else {
                        $redirect = "/blueprints/url-connections/edit/{$route_id}/saved/";
                    }
                }

                // Only proceed if there were no errors saving/creating the page

                if (empty($this->_errors)) {
                    if ($redirect) {
                        redirect(SYMPHONY_URL . $redirect);
                    }
                }
            }

            // If there were any errors, return.
            if (is_array($this->_errors) && !empty($this->_errors)) {
                return $this->pageAlert(
                    //__('An error occurred while processing this form. See below for details.'),
                    __('Some errors were encountered while attempting to save.'),
                    Alert::ERROR
                );
            }
        }
    }

    public function __actionDelete($routes, $redirect)
    {
        $success = true;
        $deleted_route_ids = array();

        if (!is_array($routes)) {
            $routes = array($routes);
        }

        foreach ($routes as $route_id) {
            $route = Symphony::Database()->fetchRow(
                0, "SELECT 1 FROM `tbl_url_connections` WHERE id = $route_id"
            );

            if (empty($route)) {
                $success = false;
                $this->pageAlert(
                    __('Route could not be deleted because it does not exist.'),
                    Alert::ERROR
                );

                break;
            }

            if ($this->delete($route_id)) {
                $deleted_route_ids[] = $route_id;
            }
        }

        if ($success) {
            redirect($redirect);
        }
    }

    function delete($route_id = null)
    {
        if (!is_int($route_id)) {
            return false;
        }

        Symphony::Database()->delete('tbl_url_connections', sprintf(" `id` = %d ", $route_id));
        Symphony::Database()->query(sprintf(
            "UPDATE tbl_url_connections
            SET `sortorder` = (`sortorder` + 1)
            WHERE `sortorder` < %d",
            $route_id
        ));

        return true;
    }


    function cleanName($string)
    {
        return preg_replace('/[^\w\-]/', '', $string);
    }


    function routeActions($full = false)
    {
        return array(
            'route any' => __('Route'),
            'route get' => __('Route GET request'),
            'route post' => __('Route POST request'),
            'redirect 301' => __('Redirect 301') . ($full ? ' ' . __('(permanent)') : ''),
            'redirect 302' => __('Redirect 302') . ($full ? ' ' . __('(temporary)') : '')
        );
    }

    public function optionsArray($options, $selected = null)
    {
        $return = array();
        foreach ($options as $key => $value) {
            $return[] = array($key, $key == $selected, $value);
        }
        return $return;
    }

    function setError($field, $error)
    {
        $this->_errors[$field] = $this->error_messages[$error];
    }
}
