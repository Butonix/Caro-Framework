<?php

namespace Modules\Backend\Controllers;

use Phalcon\Dispatcher;
use Phalcon\Mvc\Controller;

class ControllerBase extends Controller
{
    protected $model_name;
    protected $list_view;
    protected $edit_view;
    protected $detail_view;

    protected $controller_name;
    protected $action_name;

    protected function initialize()
    {
        $this->tag->prependTitle('Caro Framework | ');
    }

    public function beforeExecuteRoute(Dispatcher $dispatcher)
    {
        $this->controller_name = $dispatcher->getControllerName();
        $this->action_name = $dispatcher->getActionName();
    }

    protected function getModel()
    {
        if ($this->model_name) {
            $model = '\\Modules\Backend\Models\\' . $this->model_name;
            return new $model();
        }

        return null;
    }

    // BASE ACTION //
    /**
     * List
     */
    public function listAction()
    {
        $model = $this->getModel();

        if (is_null($model)) {
            $this->response->redirect('/admin/dashboard');
        }

        $list_data = $model::find();

        $this->view->data = $list_data;
        $this->view->list_view = $this->list_view;

        $exists = $this->view->exists(strtolower($this->controller_name) . '/' . strtolower($this->action_name));
        if (!$exists) {
            $this->view->pick('view_default/list');
        }
    }

    /**
     * Edit
     */
    public function editAction()
    {

    }

    /**
     * Detail
     */
    public function detailAction()
    {

    }
}
