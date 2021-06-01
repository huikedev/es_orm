<?php


namespace huikedev\es_orm;


use \InvalidArgumentException;
use huikedev\es_orm\connector\Elasticsearch;
use huikedev\es_orm\contract\ConnectionInterface;
use huikedev\es_orm\contract\DbManagerInterface;
use huikedev\es_orm\model\EsModel;
use think\facade\Config;

class DbManager implements DbManagerInterface
{

    protected array $instance =[];

    protected array $config = [];

    protected int $queryTimes = 0;

    public function __construct()
    {
        $this->config = Config::get('elasticsearch');
        $this->modelMaker();
    }

    protected function modelMaker()
    {
        EsModel::setDb($this);
    }

    public function connect(string $name='default',bool $force = false): ConnectionInterface
    {
        return $this->instance($name,$force);
    }

    protected function instance(string $name='default',bool $force = false): ConnectionInterface
    {
        if ($force  || isset($this->instance[$name]) === false) {
            $this->instance[$name] = $this->createConnection();
        }

        return $this->instance[$name];
    }

    protected function createConnection(string $name='default'): Elasticsearch
    {
        $config = $this->getConnectionConfig($name);
        $connection = new Elasticsearch($config);
        $connection->setDb($this);
        return new Elasticsearch($config);
    }

    protected function getConnectionConfig(string $name): array
    {
        $connections = $this->getConfig('connections');
        if (!isset($connections[$name])) {
            throw new InvalidArgumentException('Undefined db config:' . $name);
        }

        return $connections[$name];
    }

    public function getConfig(string $name = '', $default = null)
    {
        if ('' === $name) {
            return $this->config;
        }

        return $this->config[$name] ?? $default;
    }

    public function updateQueryTimes(): void
    {
        $this->queryTimes++;
    }

    public function clearQueryTimes(): void
    {
        $this->queryTimes = 0;
    }

    public function getQueryTimes(): int
    {
        return $this->queryTimes;
    }


    public function __call($method, $args)
    {
        return call_user_func_array([$this->connect(), $method], $args);
    }
}