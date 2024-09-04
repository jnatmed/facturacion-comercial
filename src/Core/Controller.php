<?php

namespace Paw\Core;

use Paw\Core\Model; 
use Paw\Core\Database\QueryBuilder;
use Paw\Core\Traits\Loggable;

class Controller 
{
    use Loggable;
    
    public string $viewsDir;

    public array $menu;

    public $request;
    public $qb;
    public $model;

    public function __construct(){
        
        global $connection, $log;    
            

        $this->viewsDir = __DIR__ . '/../App/views/';

        $this->menu = [
            [
                'href' => '/',
                'name' => 'CARGAR NUEVA ORDEN DE TRABAJO'
            ]
        ];

        $this->qb = new QueryBuilder($connection, $log);
        $this->request = new Request();

        if(!is_null($this->modelName)){
            $model = new $this->modelName;
            $model->setQueryBuilder($this->qb);
            $this->setModel($model);
        }
        
    }

    public function setModel(Model $model)
    {
        $this->model = $model;
    }

    public function getQb(){
        return $this->qb;
    }


}