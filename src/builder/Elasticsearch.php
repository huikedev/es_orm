<?php


namespace huikedev\es_orm\builder;

use huikedev\es_orm\connector\Elasticsearch as Connection;
use huikedev\es_orm\contract\BuilderInterface;
use huikedev\es_orm\contract\ConnectionInterface;
use huikedev\es_orm\contract\QueryInterface;
use huikedev\es_orm\exception\ElasticsearchException;
use huikedev\es_orm\query\Query;
use think\db\exception\DbException as Exception;
use think\helper\Str;

class Elasticsearch implements BuilderInterface
{
    protected Connection $connection;
    protected array $body = [];
    protected array $params = [];
    protected mixed $id;
    protected int $pageSize = 10;
    protected array $aggregate = ['sum','avg','min','max'];

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getLastParams(): array
    {
        return $this->params;
    }


    public function optionsToParams(QueryInterface $query): array
    {
        $this->parseBody($query);
        $this->limit($query);
        $this->order($query);
        $this->field($query);
        $this->params = array_merge($this->params,$query->getAppendParams());
        return $this->params;
    }


    public function select(QueryInterface $query): array
    {
        return $this->optionsToParams($query);
    }

    protected function limit(QueryInterface $query)
    {
        $options = $query->getOptions();
        $this->params['size'] = $options['size'] ?? $this->pageSize;
        $this->params['from'] = $options['from'] ?? 0;
    }

    protected function order(QueryInterface $query)
    {
        $options = $query->getOptions();
        if(isset($options['order'])){
            foreach ($options['order'] as $field =>$order){
                if(is_int($field)){
                    $this->params['body']['sort'][] = [$order=>'asc'];
                }else{
                    $this->params['body']['sort'][] = [$field=>$order];
                }

            }
        }
    }

    protected function field(QueryInterface $query)
    {
        $options = $query->getOptions();
        if(isset($options['field'])){
            $this->params['body']['_source'] = $options['field'];
        }
    }

    public function save(QueryInterface $query): array
    {
        // parseIndex 必须放在最前面
        $this->parseBody($query);
        $options        = $query->getOptions();
        $this->params['body'] = $options['data'];
        if (array_key_exists($query->getPk(), $this->params['body'])) {
            $this->params['id'] = $this->params['body'][$query->getPk()];
        }
        $this->params = array_merge($this->params,$query->getAppendParams());
        return $this->params;
    }

    public function saveAll(QueryInterface $query,iterable $dataSet = []): array
    {
        $this->parseBody($query);
        foreach ($dataSet as $data){
            $index = ['index'=>['_index'=> $query->getIndexName()]];
            if(array_key_exists($query->getPk(), $data)){
                $index['index']['_id']=$data[$query->getPk()];
            }
            $this->params['body'][] = $index;
            $this->params['body'][] = $data;
        }
        $this->params = array_merge($this->params,$query->getAppendParams());
        return $this->params;
    }

    public function delete(QueryInterface $query): array
    {
        $options = $query->getOptions();
        if(isset($options['id'])){
            $this->parseIndex($query);
            $this->params['id'] = $options['id'];
            $this->params = array_merge($this->params,$query->getAppendParams());
            return $this->params;
        }
        if(empty($query->getBody()) === false){
            $this->parseBody($query);
            return $this->params;
        }
        throw new \think\Exception('未指定ID或未指定删除条件');
    }

    public function count(QueryInterface $query):array
    {
        $this->parseBody($query);
        return $this->params;

    }

    public function aggregate(QueryInterface $query,string $field,string $type): array
    {
        $key = 'HUIKE_'.$type.'_'.$field;
        $this->parseBody($query);
        $this->params['body']['size'] = 0;
        $aggregations[$key] = [
            $type=>[
                'field' => $field
            ]
        ];
        $this->params['body']['aggs'] = $aggregations;
        return $this->params;
    }


    public function getBody(): array
    {
        return $this->body;
    }

    protected function buildBody(QueryInterface $query,$where): array
    {
        if (empty($where)) {
            $where = [];
        }
        $body = [];
        foreach ($where as $boolean => $val) {
            foreach ($val as $field => $condition){
                $body[$boolean][] = $this->parseBodyItem($query,$condition);
            }
        }
        return $body;
    }

    protected function parseBodyItem(QueryInterface $query,$condition)
    {
        if($condition instanceof \Closure){
            $newQuery = $query->newQuery();
            $condition($newQuery);
            return ['bool'=> $this->buildBody($newQuery, $newQuery->getBody())];
        }else{
            return $condition;
        }
    }

    protected function parseIndex(QueryInterface $query):void
    {
        $this->params = [];
        $this->params['index'] = Str::snake($query->getIndexName());
    }

    protected function parseBody(QueryInterface $query):void
    {
        $this->parseIndex($query);
        $body = $query->getBody();
        if(empty($body) === false){
            $this->params['body']['query']['bool'] = $this->buildBody($query,$body);
        }
    }
}