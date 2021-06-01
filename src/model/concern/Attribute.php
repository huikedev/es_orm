<?php


namespace huikedev\es_orm\model\concern;


use huikedev\es_orm\exception\ElasticsearchException;
use InvalidArgumentException;
use think\contract\Arrayable;
use think\helper\Str;

trait Attribute
{
    protected string $pk        = 'id';
    private array    $data      = [];
    private array    $origin    = [];
    protected array  $json      = [];
    protected bool   $jsonAssoc = false;
    protected array  $set       = [];
    protected array  $get       = [];

    /**
     * 获取模型对象的主键
     * @access public
     * @return string
     */
    public function getPk(): string
    {
        return empty($this->pk) ? 'id' : $this->pk;
    }

    /**
     * 判断一个字段名是否为主键字段
     * @access public
     * @param string $key 名称
     * @return bool
     */
    protected function isPk(string $key): bool
    {
        $pk = $this->getPk();

        if (is_string($pk) && $pk == $key) {
            return true;
        } elseif (is_array($pk) && in_array($key, $pk)) {
            return true;
        }

        return false;
    }

    protected function getRealFieldName(string $name): string
    {
        return Str::snake($name);
    }

    public function getOrigin(string $name = null)
    {
        if (is_null($name)) {
            return $this->origin;
        }

        $fieldName = $this->getRealFieldName($name);

        return array_key_exists($fieldName, $this->origin) ? $this->origin[$fieldName] : null;
    }


    public function data(array|Arrayable $data, bool $set = false, array $allow = [])
    {
        $data = $data instanceof Arrayable ? $data->toArray() : $data;

        if(isset($data[$this->getPk()]) === false){
            throw new ElasticsearchException('Data Error: $data must contains PK:'.$this->getPk());
        }
        // 清空数据
        $this->data = array_merge($this->data,$data);

        if (!empty($allow)) {
            $result = [];
            foreach ($allow as $name) {
                if (isset($data[$name])) {
                    $result[$name] = $data[$name];
                }
            }
            $data = $result;
        }

        if ($set) {
            // 数据对象赋值
            $this->setAttrs($data);
        } else {
            $this->data = $data;
        }

        return $this;
    }

    public function set(string $name, $value): void
    {
        $name = $this->getRealFieldName($name);

        $this->data[$name] = $value;
        unset($this->get[$name]);
    }

    public function setAttrs(array $data): void
    {
        // 进行数据处理
        foreach ($data as $key => $value) {
            $this->setAttr($key, $value, $data);
        }
    }

    public function setAttr(string $name, $value, array $data = []): void
    {
        $name = $this->getRealFieldName($name);

        if (isset($this->set[$name])) {
            return;
        }

        // 检测修改器
        $method = 'set' . Str::studly($name) . 'Attr';

        if (method_exists($this, $method)) {
            $array = $this->data;

            $value = $this->$method($value, array_merge($this->data, $data));

            $this->set[$name] = true;
            if (is_null($value) && $array !== $this->data) {
                return;
            }
        }

        // 设置数据对象属性
        $this->data[$name] = $value;
        unset($this->get[$name]);
    }

    public function getAttr(string $name)
    {
        $relation = false;
        $value    = $this->getData($name);

        return $this->getValue($name, $value, $relation);
    }

    public function getData(string $name = null)
    {
        if (is_null($name)) {
            return $this->data;
        }

        $fieldName = $this->getRealFieldName($name);
        if (array_key_exists($fieldName, $this->data)) {
            return $this->data[$fieldName];
        }

        throw new InvalidArgumentException('property not exists:' . static::class . '->' . $name);
    }

    protected function getValue(string $name, $value, $relation = false)
    {
        // 检测属性获取器
        $fieldName = $this->getRealFieldName($name);

        if (array_key_exists($fieldName, $this->get)) {
            return $this->get[$fieldName];
        }

        $method = 'get' . Str::studly($name) . 'Attr';
        if (method_exists($this, $method)) {

            $value = $this->$method($value, $this->data);
        }

        $this->get[$fieldName] = $value;

        return $value;
    }
}