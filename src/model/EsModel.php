<?php


namespace huikedev\es_orm\model;


use ArrayAccess;
use Closure;
use Elasticsearch\Client;
use huikedev\es_orm\contract\QueryInterface;
use huikedev\es_orm\DbManager;
use huikedev\es_orm\contract\DbManagerInterface;
use huikedev\es_orm\exception\ElasticsearchException;
use huikedev\es_orm\model\concern\Attribute;
use huikedev\es_orm\model\concern\Conversion;
use huikedev\es_orm\query\Query;
use JsonSerializable;
use think\Collection;
use think\contract\Arrayable;
use think\contract\Jsonable;
use think\helper\Str;

/**
 * Class EsModel
 * @package huikedev\es_orm\model
 * @mixin Query
 * @method bool updateMappings() static 更新mapping
 * @method bool deleteIndex() static 更新mapping
 * @method bool createIndex() static 更新mapping
 * @method Client handler() static 更新mapping
 * @method bool updateSettings() static 更新mapping
 * @method bool updateAliases() static 更新mapping
 * @method iterable select() static 更新mapping
 * @method QueryInterface queryFilter(Closure $callback,?string $key = null) static filter查询
 * @method QueryInterface queryMust(Closure $callback,?string $key = null) static filter查询
 * @method QueryInterface queryShould(Closure $callback,?string $key = null) static filter查询
 * @method QueryInterface queryMustNot(Closure $callback,?string $key = null) static filter查询
 * @method EsModel find() static filter查询
 * @method EsModel findOrEmpty() static filter查询
 * @method QueryInterface where(string $field,string $op,$condition,array $append = []) static filter查询
 * @method QueryInterface whereOr(string $field,string $op,$condition,array $append = []) static filter查询
 * @method QueryInterface whereFilter(string $field,string $op,$condition,array $append = []) static filter查询
 * @method QueryInterface whereNot(string $field,string $op,$condition,array $append = []) static filter查询
 * @method QueryInterface whereTerm(string $field,$value,string $logic = 'must',array $append = []) static filter查询
 * @method QueryInterface whereTerms(string $field,array $value,string $logic = 'must', array $append = []) static filter查询
 * @method QueryInterface whereMatch(string $field,string $value,string $logic='must',array $append = []) static filter查询
 * @method QueryInterface whereMatchPhrase(string $field,string $value,string $logic='must',array $append = []) static filter查询
 * @method QueryInterface whereIn(string $field,array $value,string $logic='must',array $append = []) static filter查询
 * @method QueryInterface whereBetween(string $field,array $value,string $logic='must',array $append = []) static filter查询
 * @method QueryInterface whereExists(string $field,string $logic='must',$append = []) static filter查询
 * @method array | null getOriginResult() static
 */
abstract class EsModel implements JsonSerializable, ArrayAccess, Arrayable, Jsonable
{
    use Attribute;
    use Conversion;

    /**
     * 索引名称
     * @var string
     */
    protected string $index;
    /**
     * 连接配置
     * @var string
     */
    protected string $connection = 'default';

    protected bool $exists = false;

    protected static array $initialized = [];

    protected static null | DbManagerInterface  $db = null;

    protected array $esMappings = [];

    protected array $esAliases = [];

    protected array $esSettings = [];

    protected array $appendParams = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
        // 记录原始数据
        $this->origin = $this->data;
        if (empty($this->index)) {
            // 当前模型名
            $this->index = Str::snake(class_basename(static::class));
        }
        $this->initialize();
    }

    private function initialize(): void
    {
        if (!isset(static::$initialized[static::class])) {
            static::$initialized[static::class] = true;
            static::init();
        }
        if(is_null(self::$db)){
            self::$db = new DbManager();
        }
    }

    protected static function init()
    {
    }

    public function getIndex(): string
    {

        return $this->index;
    }

    public static function setDb(DbManagerInterface $db=null)
    {
        self::$db = $db;
    }

    public function db(): QueryInterface
    {
        return self::$db->connect($this->connection)->newQuery()->setModel($this);
    }

    public function getEsMappings(): array
    {
        return $this->esMappings;
    }

    public function getEsAliases(): array
    {
        return $this->esAliases;
    }

    public function getEsSettings(): array
    {
        return $this->esSettings;
    }

    public function getAppendParams(): array
    {
        return $this->appendParams;
    }

    public function saveAll(iterable $dataSet): Collection
    {
        $res =  $this->db()->saveAll($dataSet);
        return new Collection($dataSet);
    }

    /**
     * 设置数据是否存在
     * @access public
     * @param bool $exists
     * @return $this
     */
    public function exists(bool $exists = true)
    {
        $this->exists = $exists;
        return $this;
    }

    /**
     * 判断数据是否存在数据库
     * @access public
     * @return bool
     */
    public function isExists(): bool
    {
        return $this->exists;
    }

    /**
     * 判断模型是否为空
     * @access public
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    public function save(): bool
    {
        if ($this->isEmpty()) {
            throw new ElasticsearchException('Data Error: $data is empty');
        }
        $result = $this->db()->save($this->data);

        if (false === $result) {
            return false;
        }

        // 重新记录原始数据
        $this->origin   = $this->data;
        $this->set      = [];

        return true;
    }

    public function delete(): bool
    {
        if (!$this->exists || $this->isEmpty()) {
            return false;
        }
        if(isset($this->data[$this->getPk()]) === false){
            return false;
        }
        return $this->db()->setOption('id',$this->data[$this->getPk()])->delete();
    }

    public function newInstance(array $data = [], $where = null):EsModel
    {
        $model = new static($data);
        if (empty($data)) {
            return $model;
        }
        $model->exists(true);
        return $model;
    }

    public function offsetSet($offset, $value)
    {
        $this->setAttr($offset, $value);
    }

    public function offsetExists($offset): bool
    {
        return $this->__isset($offset);
    }

    public function offsetUnset($offset)
    {
        $this->__unset($offset);
    }

    public function offsetGet($offset)
    {
        return $this->getAttr($offset);
    }

    public function __isset(string $offset): bool
    {
        return !is_null($this->getAttr($offset));
    }

    public function __unset(string $offset): void
    {
        unset($this->data[$offset],
            $this->get[$offset],
            $this->set[$offset]);
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->db(), $method], $args);
    }

    public static function __callStatic($method, $args)
    {
        $model = new static();
        return call_user_func_array([$model->db(), $method], $args);
    }

    public function __set(string $name, $value): void
    {
        $this->setAttr($name, $value);
    }

    public function __get(string $name)
    {
        return $this->getAttr($name);
    }

}