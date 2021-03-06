<?php
/**
 * Created by Jacky.
 * User: Jacky
 * E-Mail: jacky@carocrm.com or jacky@youaddon.com
 * Date: 7/24/2015
 * Time: 4:58 PM
 * Project: carofw
 * File: SettingsController.php
 */

namespace Modules\Backend\Controllers;

use Modules\Backend\Models\Settings;
use Modules\Core\MyMail;
use Phalcon\Db\Column;
use Phalcon\Db\Index;
use Phalcon\Text;

class SettingsController extends ControllerBase
{
    /**
     * @var string model_name
     */
    protected $model_name = 'Settings';

    /**
     * @var array data config
     */
    private $data_config = [
        'application' => [
            'baseUrl',
            'backendUrl',
            'title',
            'theme'
        ],
    ];

    /**
     * dashboard
     */
    public function indexAction()
    {

    }

    /**
     * Repair database
     * read database structure from config/database_structures.php
     * generate to database
     */
    public function repairAction()
    {
        // repair database
        $this->_repairDatabase();

        $this->flash->success('Repair success!');
        $this->backendRedirect('/settings');
    }

    /**
     * Repair database
     */
    private function _repairDatabase()
    {
        if (is_file(__DIR__ . "/../../config/database_structures.ini.php")) {
            $tables = include __DIR__ . "/../../config/database_structures.ini.php";
        } else {
            $tables = include __DIR__ . "/../../config/database_structures.php";
        }

        foreach ($tables as $table_name => $table_data) {
            $exists = $this->db->tableExists($table_name);

            if ($exists) {
                $current_fields = $this->db->describeColumns($table_name);
                foreach ($table_data['fields'] as $field_name => $options) {
                    $exists_field = true;
                    foreach ($current_fields as $c_field) {
                        if ($c_field->getName() == $field_name) {
                            $exists_field = true;
                            break;
                        } else {
                            $exists_field = false;
                        }
                    }

                    if ($exists_field == true) {
                        $this->db->modifyColumn($table_name, null, new Column($field_name, $options));
                    } else {
                        $this->db->addColumn($table_name, null, new Column(
                            $field_name, $options));
                    }
                }

                $current_indexes = $this->db->describeIndexes($table_name);
                foreach ($table_data['indexes'] as $index => $index_data) {
                    $create_index = true;
                    foreach ($current_indexes as $c_index => $c_index_fields) {
                        if ($index == $c_index) {
                            if (
                                $index_data['fields'] != $c_index_fields->getColumns()
                                || $index_data['type'] != $c_index_fields->getType()
                            ) {
                                $this->db->dropIndex($table_name, null, $c_index);
                                $create_index = true;
                            } else {
                                $create_index = false;
                            }
                        }
                    }

                    if ($create_index == true) {
                        if (strtolower($index_data['type']) == 'index') {
                            $this->db->addIndex($table_name, null, new Index($index, $index_data['fields']));
                        } else {
                            $this->db->addIndex($table_name, null, new Index($index, $index_data['fields'], $index_data['type']));
                        }
                    }
                }

            } else {
                $new_columns = array(
                    'columns' => array(
                        new Column(
                            'id',
                            array(
                                'type' => Column::TYPE_INTEGER,
                                'size' => 10,
                                'notNull' => true,
                                'autoIncrement' => true,
                                'unsigned' => true
                            )
                        ),
                        new Column(
                            'created',
                            array(
                                'type' => Column::TYPE_DATETIME,
                                'notNull' => true,
                            )
                        ),
                        new Column(
                            'user_created_id',
                            array(
                                'type' => Column::TYPE_INTEGER,
                                'size' => 10,
                                'notNull' => true
                            )
                        ),
                        new Column(
                            'deleted',
                            array(
                                'type' => Column::TYPE_INTEGER,
                                'size' => 1,
                                'notNull' => true,
                                'default' => 0
                            )
                        )
                    ),
                    'indexes' => array(
                        new Index('PRIMARY', array('id'))
                    )
                );

                foreach ($table_data['fields'] as $field_name => $options) {
                    $new_columns['columns'][] = new Column($field_name, $options);
                }

                foreach ($table_data['indexes'] as $index => $index_data) {
                    if (strtolower($index_data['type']) == 'index') {
                        $new_columns['indexes'][] = new Index($index, $index_data['fields']);
                    } else {
                        $new_columns['indexes'][] = new Index($index, $index_data['fields'], $index_data['type']);
                    }
                }

                $this->db->createTable($table_name, null, $new_columns);
            }
        }
    }

    /**
     * Clear cache
     */
    public function clear_cacheAction()
    {
        // clear cache router
        $this->_repairRouter('backend');
        $this->_repairRouter('frontend');

        // clear cache volt
        $cache_files = glob(APP_PATH . 'apps/cache/volt/*.php');
        foreach ($cache_files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        // success
        $this->flash->success('Clear success!');
        $this->backendRedirect('/settings');
    }

    /**
     * Repair backend router
     */
    private function _repairRouter($main_module)
    {
        $ucfirst_main_module = ucfirst($main_module);

        // module folder
        $module_folder = APP_PATH . 'apps/' . $main_module . '/src';
        $modules = include APP_PATH . 'apps/config/modules.php';

        // current router
        $router = include APP_PATH . 'apps/' . $main_module . '/config/router.php';

        // merge module router
        foreach ($modules['backend'] as $module => $enable) {
            if ($enable == true && is_file($module_folder . '/' . $module . '/config/router.php')) {
                $module_router = include $module_folder . '/' . $module . '/config/router.php';
                $module_router_fixed = [];

                foreach ($module_router as $url => $options) {
                    $options['namespace'] = 'Modules\\' . $ucfirst_main_module . '\\Src\\' . $module . '\\Controllers';
                    $options['module'] = $main_module;
                    $module_router_fixed[$url] = $options;
                }

                $router = array_merge($router, $module_router_fixed);
            }
        }

        $router_file = APP_PATH . 'apps/' . $main_module . '/config/router.ini.php';
        $file = fopen($router_file, 'w');
        fwrite($file, "<?php\n\nreturn " . var_export($router, true) . ";\n\n");
        fclose($file);
    }

    /**
     * Rebuild cache resource permission (controller/action)
     * all controller action will write in cache
     * in config backend/config/resources.php
     * structure
     * return array(
     *   '<controller> => array(
     *      <action>
     *      <action>
     *  )
     * )
     */
    public function rebuild_resourcesAction()
    {
        $this->view->disable();
        $resources = $this->_getAllResources();

        if (!empty($resources)) {
            $file = fopen(APP_PATH . "apps/backend/permissions/resources.php", "w");
            fwrite($file, "<?php\n return " . var_export($resources, true) . ";\n");
            fclose($file);
        }

        $this->flash->success($this->t->_('Rebuild Resources success'));
        $this->backendRedirect('/settings');
    }

    /**
     * scan folder controllers and get all controller in here
     * @return array array(
     *  <controller> => (
     *      <action>
     *      <action>
     *  )
     * )
     */
    private function _getAllResources()
    {
        $controllers = array();
        $controller_path = APP_PATH . '/apps/backend/controllers/*Controller.php';

        foreach (glob($controller_path) as $controller) {
            $name = basename($controller, '.php');
            $className = 'Modules\Backend\Controllers\\' . $name;
            $resource = strtolower(str_replace('Controller', '', $name));
            $controllers[$resource] = [];
            $methods = (new \ReflectionClass($className))->getMethods(\ReflectionMethod::IS_PUBLIC);

            foreach ($methods as $method) {
                if (Text::endsWith($method->name, 'Action')) {
                    $controllers[$resource][] = str_replace('Action', '', $method->name);
                }
            }
        }

        return $controllers;
    }

    /**
     * Page Send Mail config
     */
    public function mail_configAction()
    {
        $data = null;
        $mail_config = Settings::findFirst("name = 'mail_config'");
        if ($mail_config && $mail_config->value) {
            $data = json_decode($mail_config->value);
        }

        $this->view->data = $data;

        if ($this->request->isPost()) {
            // get data from form
            $from_name = $this->request->getPost('from_name');
            $from_email = $this->request->getPost('from_email');
            $smtp_server = $this->request->getPost('smtp_server');
            $smtp_port = $this->request->getPost('smtp_port');
            $smtp_security = $this->request->getPost('smtp_security');
            $smtp_username = $this->request->getPost('smtp_username');
            $smtp_password = $this->request->getPost('smtp_password');
            $smtp_test = $this->request->getPost('smtp_test');

            // check data
            if (!$from_name || !$from_email || !$smtp_server) {
                $this->flash->error($this->t->_('Please input data'));
                $this->backendRedirect('/settings/mail_config');
            }

            // default port
            if (!$smtp_port) {
                $smtp_port = 25;
            }
            // default not security
            if (!$smtp_security) {
                $smtp_security = 0;
            }

            // send test mail
            if ($smtp_test == '1') {
                $email = new MyMail();
                $email->setMailSettings(array(
                    'fromName' => $from_name,
                    'fromEmail' => $from_email,
                    'smtp' => array(
                        'server' => $smtp_server,
                        'port' => $smtp_port,
                        'security' => $smtp_security,
                        'username' => $smtp_username,
                        'password' => $smtp_password
                    )
                ));

                try {
                    $email->send($from_email, 'Caro Framework Send Test Mail', 'Caro Framework Send Test Mail');
                    $this->flash->warning($this->t->_('Please check your email to see mail test.') . ' Email: ' . $from_email);
                } catch (\Swift_SwiftException $e) {
                    $this->flash->error($e->getMessage());
                }

                $this->backendRedirect('/settings/mail_config');

            } else {
                // create/update settings
                if ($mail_config) {
                    $mail_config->name = 'mail_config';
                    $mail_config->value = json_encode(array(
                        'from_name' => $from_name,
                        'from_email' => $from_email,
                        'smtp_server' => $smtp_server,
                        'smtp_port' => $smtp_port,
                        'smtp_security' => $smtp_security,
                        'smtp_username' => $smtp_username,
                        'smtp_password' => $smtp_password,
                    ));
                    $mail_config->update();

                } else {
                    $settings = new Settings();
                    $settings->name = 'mail_config';
                    $settings->value = json_encode(array(
                        'from_name' => $from_name,
                        'from_email' => $from_email,
                        'smtp_server' => $smtp_server,
                        'smtp_port' => $smtp_port,
                        'smtp_security' => $smtp_security,
                        'smtp_username' => $smtp_username,
                        'smtp_password' => $smtp_password,
                    ));
                    $settings->save();
                }

                $this->flash->success($this->t->_('Update mail server is success'));
                $this->backendRedirect('/settings/mail_config');
            }
        }
    }

    /**
     * config global variable
     *
     * @return \Phalcon\Http\Response|\Phalcon\Http\ResponseInterface
     */
    public function configAction()
    {
        $this->tag->appendTitle('Global Config');

        $data_config = $this->data_config;

        $file_config = APP_PATH . '/apps/config/config.ini';

        if ($this->request->isPost()) {
            $post = $this->request->getPost();

            $write_config = '';

            foreach ($post as $group => $conf) {
                $write_config .= "[{$group}]\n";

                foreach ($conf as $key => $value) {
                    $write_config .= "{$key} = {$value}\n";
                }
            }

            $file = fopen($file_config, 'w');
            fwrite($file, $write_config);
            fclose($file);

            return $this->backendRedirect('/settings/config');
        }

        $config = new \Phalcon\Config\Adapter\Ini($file_config);
        $this->view->config = $config;

        $this->view->data_config = $data_config;

        // get theme
        $themes = [];
        $folder_theme = APP_PATH . 'public/themes';
        $scan_themes = scandir($folder_theme);

        foreach ($scan_themes as $sub_folder) {
            if (substr($sub_folder, 0, 1) != '.' && $sub_folder != 'backend') {
                $themes[$sub_folder] = $sub_folder;
            }
        }

        $this->view->themes = $themes;
    }

    /**
     * application const create/edit
     */
    public function const_editAction($mkey = null, $key = null)
    {
        $file_const = APP_PATH . 'apps/config/const.php';
        $const_strings = include $file_const;

        // save const setting
        if ($this->request->isPost()) {
            $this->view->disable();
            header('Content-Type: application/json');

            $const = $this->request->getPost('const');
            $mkey = $this->request->getPost('mkey');
            $key = $this->request->getPost('key');

            if ($this->request->getPost('is_new') == 1) {
                $const_strings[$mkey][$key] = [];

                $file = fopen($file_const, 'w');
                fwrite($file, "<?php\n\nreturn " . var_export($const_strings, true) . ";\n");
                fclose($file);

                echo json_encode([
                    'mkey' => $mkey,
                    'key' => $key,
                    'url' => $this->url->get($this->router->getRewriteUri(), ['mkey' => $mkey, 'key' => $key])
                ]);
                die();
            }

            if ($mkey && $key) {
                $const_strings[$mkey][$key] = [];
                foreach ($const[$mkey][$key]['label'] as $k => $label) {
                    $const_strings[$mkey][$key][$label] = $const[$mkey][$key]['value'][$k];
                }

                $file = fopen($file_const, 'w');
                fwrite($file, "<?php\n\nreturn " . var_export($const_strings, true) . ";\n");
                fclose($file);

                echo json_encode([
                    'type' => 'success',
                    'title' => $this->t->_('Successful'),
                    'message' => $this->t->_('You saved successful')
                ]);
                die();
            }

            echo json_encode([
                'type' => 'error',
                'title' => $this->t->_('Error'),
                'message' => $this->t->_('Have some error. Please try again')
            ]);
            die();
        }

        // load const setting
        if (!$mkey && !$key) {
            $main_keys = [];
            foreach ($const_strings as $main_key => $value) {
                $main_keys[$main_key] = $main_key;
            }

            $this->view->mkey = $this->request->get('mkey');
            $this->view->key = $this->request->get('key');
            $this->view->main_keys = $main_keys;

        } else if ($mkey && !$key) {
            $this->view->disable();

            $keys_select = '<option value="">' . $this->t->_('Please choose') . '</option>';

            foreach ($const_strings[$mkey] as $select_key => $value) {
                $keys_select .= '<option value="' . $select_key . '">' . $select_key . '</option>';
            }

            $keys_select .= '<option value="_new_key">' . $this->t->_('Create new key') . '</option>';

            echo $keys_select;
            die();

        } else if ($mkey && $key) {
            $this->view->disable();

            if ($key == '_new_key') {
                echo '
                    <div class="form-group">
                        <div class="col-sm-6">
                            <input type="text" class="form-control" name="_new_key" placeholder="' . $this->t->_('New key') . '">
                        </div>
                        <div class="col-sm-6">
                            <input type="button" class="btn btn-default" onclick="createNewKey(\'' . $mkey . '\', this)" value="' . $this->t->_('Create') . '">
                        </div>
                    </div>
                ';
                die();
            }

            $select_string = '';

            foreach ($const_strings[$mkey][$key] as $value => $label) {
                $select_string .= '<div class="form-group">';
                $select_string .= '<div class="col-sm-5"><input name="const[' . $mkey . '][' . $key . '][value][]" class="form-control" value="' . $value . '"></div>';
                $select_string .= '<div class="col-sm-5"><input name="const[' . $mkey . '][' . $key . '][label][]" class="form-control" value="' . $label . '"></div>';
                $select_string .= '<div class="col-sm-2"><button type="button" class="btn btn-danger" onclick="$(this).parent().parent().remove()"><i class="fa fa-remove"></i></button></div>';
                $select_string .= '</div>';
            }

            echo $select_string
                . '<div id="select-box-content"></div>'
                . '<button type="button" class="btn" onclick="addSelect(\'' . $mkey . '\', \'' . $key . '\')">' . $this->t->_('Add') . '</button>'
                . '<button type="submit" class="btn btn-info" value="Save" style="margin-left: 5px">' . $this->t->_('Save') . '</button>';
            die();

        } else {
            $this->flash->error($this->t->_('Have some error. Please try again!'));
            return $this->backendRedirect('/settings/const_edit');
        }
    }

}