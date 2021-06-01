<?php


namespace huikedev\es_orm\query;


use app\common\init\HuikePaginator;
use Elasticsearch\Client;
use huikedev\es_orm\concern\AggregateQuery;
use huikedev\es_orm\concern\IndexOperation;
use huikedev\es_orm\concern\WhereQuery;
use huikedev\es_orm\contract\ConnectionInterface;
use huikedev\es_orm\contract\QueryInterface;
use huikedev\es_orm\model\EsModel;
use huikedev\huike_base\facade\AppRequest;
use think\Collection;
use think\contract\Arrayable;
use think\db\concern\ParamsBind;
use think\Model;
use think\Paginator;

class Query implements QueryInterface
{
    use ParamsBind;
    use WhereQuery;
    use IndexOperation;
    use AggregateQuery;
    protected array $body    = [];
    protected array $options =[
        'data'=>[],
        'from'=>0,
        'size'=>10
    ];

    protected EsModel $model;

    protected array $relations = [];

    protected ConnectionInterface $connection;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    public function getOptions(string $name = ''):mixed
    {
        if($name === ''){
            return $this->options;
        }
        return $this->options[$name] ?? null;
    }

    public function getOriginResult(): ?array
    {
        return $this->connection->getOriginResult();
    }

    public function getLastParams():array
    {
        return $this->connection->getLastParams();
    }

    public function getBody():array
    {
        return $this->body;
    }

    public function newQuery():QueryInterface
    {
        $query = new static($this->connection);
        if ($this->model) {
            $query->setModel($this->model);
        }

        return $query;
    }

    public function removeOption(string $option = ''):self
    {
        if ('' === $option) {
            $this->options = [];
            $this->bind    = [];
        } elseif (isset($this->options[$option])) {
            unset($this->options[$option]);
        }

        return $this;
    }

    public function order($field, string $order = ''):self
    {
        if (empty($field)) {
            return $this;
        }

        if (is_string($field)) {
            if (strpos($field, ',')) {
                $field = array_map('trim', explode(',', $field));
            } else {
                $field = empty($order) ? $field : [$field => $order];
            }
        }

        if (isset($this->options['order']) === false) {
            $this->options['order'] = [];
        }

        if (is_array($field)) {
            $this->options['order'] = array_merge($this->options['order'], $field);
        } else {
            $this->options['order'][] = $field;
        }
        return $this;
    }

    public function setCollapse(string  $field,array $innerHits = [],array $append = []):self
    {
        $collapse['field'] = $field;
        if (empty($innerHits) > 0){
            $collapse['inner_hits'] = $innerHits;
        }

        $this->options['collapse'] = array_merge($collapse,$append);
        return $this;
    }



    public function field(string | array $field):self
    {
        if (empty($field)) {
            return $this;
        }

        if (is_string($field)) {
            $field = array_map('trim', explode(',', $field));
        }

        if (isset($this->options['field'])) {
            $field = array_merge((array) $this->options['field'], $field);
        }

        $this->options['field'] = array_unique($field);

        return $this;
    }

    public function data(iterable $data):self
    {
        if($data instanceof Arrayable){
            $this->options['data'] = $data->toArray();
        }else{
            $this->options['data'] = $data;
        }
        if(isset($this->options['data'][$this->getPk()])){
            $this->options['id'] = $this->options['data'][$this->getPk()];
        }
        return $this;
    }

    public function limit(int $offset, int $length = 10):self
    {
        $this->options['from'] = $offset;
        $this->options['size'] = $length;
        return $this;
    }

    public function page(int $current = 1,int $pageSize = 10):self
    {
        $offset = ($current - 1) * $pageSize;
        return $this->limit($offset,$pageSize);
    }

    public function paginate($listRows = null):Paginator
    {
        $page = AppRequest::current();
        $pageSize = AppRequest::pageSize();
        $this->options['from'] = $this->options['from'] ?? ($page - 1) * $pageSize;
        $this->options['size'] = $this->options['size'] ?? $pageSize;
        $result = $this->select();
        $total = $this->count(true);
        return HuikePaginator::make($result,$this->options['size'],$page,$total);
    }

    public function handler()
    {
        return $this->connection->handler();
    }

    public function setOption(string $name,$value):self
    {
        $this->options[$name] = $value;
        return $this;
    }

    public function find(): EsModel | Model
    {
        if (empty($this->getBody()) && empty($this->options['order'])) {
            $result = [];
        } else {
            $this->setOption('size',1);
            $result = $this->connection->find($this);
        }
        return $this->resultToModel($result,$this->options);
    }

    protected function resultToModel(array $result,array $options = []): EsModel
    {
        $result = $this->model->newInstance($result, null);

        if (!empty($options['visible'])) {
            $result->visible($options['visible']);
        } elseif (!empty($options['hidden'])) {
            $result->hidden($options['hidden']);
        }

        if (!empty($options['append'])) {
            $result->append($options['append']);
        }
        return $result;
    }


    protected function resultToCollection(iterable $results,array $options = []): Collection
    {
        $collection = new Collection();
        foreach ($results as $result){
            $collection->push($this->resultToModel($result,$options));
        }
        return $collection;
    }

    public function findOrEmpty(): EsModel
    {
        return $this->find();
    }

    public function select(): Collection
    {
        $res = $this->connection->select($this);
        return $this->resultToCollection($res,$this->options);
    }

    public function save(iterable $data = []): bool
    {
        $this->options['data'] = array_merge($this->options['data'] ?? [], $data);
        return $this->connection->save($this);
    }

    public function saveAll(iterable $dataSet): int
    {
        return $this->connection->saveAll($this,$dataSet);
    }

    public function delete(): bool
    {
        return $this->connection->delete($this);
    }

    public function deleteById(int|string $id): bool
    {
       $this->setOption('id',$id);
        return $this->connection->delete($this);
    }

    public function column($column, string $key = ''): array
    {
        return $this->connection->column($this,$column,$key);
    }

    public function getPk(): string
    {
        return $this->getModel()->getPk();
    }

    public function setModel(EsModel $esModel): QueryInterface
    {
        $this->model = $esModel;
        return $this;
    }

    public function getModel(): EsModel
    {
        return $this->model;
    }

    public function getAppendParams(): array
    {
        return $this->model->getAppendParams();
    }

}