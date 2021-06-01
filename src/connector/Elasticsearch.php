<?php


namespace huikedev\es_orm\connector;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use huikedev\es_orm\contract\BuilderInterface;
use huikedev\es_orm\contract\ConnectionInterface;
use huikedev\es_orm\contract\DbManagerInterface;
use huikedev\es_orm\contract\QueryInterface;
use huikedev\es_orm\exception\ElasticsearchException;
use huikedev\es_orm\query\Query;


class Elasticsearch implements ConnectionInterface
{
    protected BuilderInterface $builder;

    protected array $config = [];

    protected null|Client $client = null;

    protected null|DbManagerInterface $db = null;

    protected array|null $result = null;

    protected array $aggregate = ['sum','avg','min','max','count'];

    public function __construct(array $config = [])
    {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }
        // 创建Builder对象
        $class = $this->getBuilderClass();

        $this->builder = new $class($this);
    }

    public function setDb(DbManagerInterface $db):self
    {
        $this->db = $db;
        return $this;
    }


    public function getBuilderClass(): string
    {
        return \huikedev\es_orm\builder\Elasticsearch::class;
    }

    public function getQueryClass(): string
    {
        return Query::class;
    }

    public function handler(): Client
    {
        $this->getClient();
        return $this->client;
    }

    public function getOriginResult():array | null
    {
        return $this->result;
    }

    public function save(QueryInterface $query): bool
    {
        $this->getClient();
        $params = $this->builder->save($query);
        $this->result = $this->client->index($params);
        if(isset($this->result['result']) && $this->result['result'] === 'updated'){
            return true;
        }
        if(isset($this->result['result']) && $this->result['result'] === 'created'){
            return true;
        }
        return false;
    }

    public function getLastParams():array
    {
        return $this->builder->getLastParams();
    }

    public function find(QueryInterface $query): array
    {
        $params = $this->builder->select($query);
        $this->getClient();
        $this->result = $this->client->search($params);
        return $this->result['hits']['hits'][0]['_source'] ?? [];
    }

    public function select(QueryInterface $query): array
    {
        $params = $this->builder->select($query);
        //halt($params);
        $this->getClient();
        $this->result = $this->client->search($params);
        $result    = [];
        if (isset($this->result['hits']['hits']) === false || empty($this->result['hits']['hits'])) {
            return $result;
        }
        foreach ($this->result['hits']['hits'] as $hit) {
            $result[] = $hit['_source'];
        }
        return $result;
    }

    public function createIndex(array $params): bool
    {
        $this->getClient();
        $this->result =  $this->client->indices()->create($params);
        // todo
        return true;
    }

    public function updateMappings(array $params): bool
    {
        $this->getClient();
        $this->result = $this->client->indices()->putMapping($params);
        return $this->result['acknowledged'] ?? false;
    }

    public function getMappings(array $params)
    {
        $this->getClient();
        $this->result = $this->client->indices()->getMapping($params);
        halt($this->result);
    }

    public function updateSettings(array $params): bool
    {
        $this->result          = $this->client->indices()->putSettings($params);
        return $this->result['acknowledged'] ?? false;
    }

    public function updateAliases(array $params): bool
    {
        $this->getClient();
        $this->result = $this->client->indices()->putAlias($params);
        return $this->result['acknowledged'] ?? false;
    }

    public function deleteIndex(array $params): bool
    {
        $this->getClient();
        $this->result = $this->client->indices()->delete($params);
        return isset($this->result['deleted']) && is_int($this->result['deleted']) ? $this->result['deleted'] : intval($this->result);
    }

    public function saveAll(QueryInterface $query,iterable $dataSet = [], int $limit = 0): int
    {
        if (!is_array(reset($dataSet))) {
            return 0;
        }
        if ($limit === 0) {
            $limit = count($dataSet) >= 5000 ? 1000 : count($dataSet);
        }
        $array = array_chunk($dataSet, $limit, true);
        $count = 0;
        $this->getClient();
        foreach ($array as $item) {
            $params = $this->builder->saveAll($query, $item);
            $this->result = $this->client->bulk($params);
            $count  += isset($this->result['items']) ? count($this->result['items']) : 0;
        }
        return $count;
    }

    public function delete(QueryInterface $query): bool
    {
        $params = $this->builder->delete($query);
        $this->getClient();
        if (isset($params['id'])) {
            $this->result = $this->client->delete($params);
        } else {
            $this->result = $this->client->deleteByQuery($params);
        }
        return isset($this->result['deleted']) && is_int($this->result['deleted']) ? $this->result['deleted'] : intval($this->result);
    }

    public function aggregate(QueryInterface $query, string $aggregate, $field)
    {
        $this->getClient();

        if(in_array($aggregate,$this->aggregate) === false){
            throw new ElasticsearchException('Aggregate Error: not support function '.$aggregate);
        }
        if ($aggregate === 'count') {
            $params = $this->builder->count($query);
            $this->result = $this->client->count($params);
            return $this->result['count'] ?? 0;
        }else{
            $key = 'HUIKE_'.$aggregate.'_'.$field;
            $params = $this->builder->aggregate($query,$field,$aggregate);
            $this->result = $this->client->search($params);
            return $this->result['aggregations'][$key]['value'] ?? false;
        }
    }

    public function column(QueryInterface $query,$column, string $key = ''): array
    {
        $options = $query->getOptions();
        if (isset($options['field'])) {
            $query->removeOption('field');
        }
        if (empty($key) || trim($key) === '') {
            $key = null;
        }

        if (is_string($column)) {
            $column = trim($column);
            if ('*' !== $column) {
                $column = array_map('\trim', explode(',', $column));
            }
        } elseif (\is_array($column)) {
            if (in_array('*', $column)) {
                $column = '*';
            }
        } else {
            throw new ElasticsearchException('not support type');
        }
        $result = $this->select($query);

        if (is_string($key) && strpos($key, '.')) {
            [$alias, $key] = explode('.', $key);
        }

        if (empty($result)) {
            $result = [];
        } elseif ('*' !== $column && count($column) === 1) {
            $column = array_shift($column);
            if (strpos($column, ' ')) {
                $column = substr(strrchr(trim($column), ' '), 1);
            }

            if (strpos($column, '.')) {
                [$alias, $column] = explode('.', $column);
            }

            $result = array_column($result, $column, $key);

        } elseif ($key) {
            $result = array_column($result, null, $key);
        }

        return $result;
    }

    public function newQuery(): QueryInterface
    {
        $class = $this->getQueryClass();
        return  new $class($this);
    }

    protected function getClient(bool $resetResult = true): self
    {
        if (is_null($this->client) === false) {
            return $this;
        }
        if($resetResult){
            $this->result = null;
        }
        $this->client = ClientBuilder::create()->setHosts($this->getHosts())->build();
        return $this;
    }

    protected function getHosts(): array
    {
        if (isset($this->config['hosts']) === false) {
            throw new ElasticsearchException('Config Error: hosts not found');
        }
        if (is_array($this->config['hosts']) === false) {
            throw new ElasticsearchException('Config Error: hosts must be array');
        }
        return $this->config['hosts'];

    }

    public function getBuilder(): BuilderInterface
    {
        return $this->builder;
    }

}