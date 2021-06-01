<?php


namespace huikedev\es_orm\model\concern;


use think\Collection;
use think\db\exception\DbException as Exception;
use think\helper\Str;
use think\Model;
use think\model\Collection as ModelCollection;
use think\model\relation\OneToOne;

trait Conversion
{
    /**
     * 数据输出显示的属性
     * @var array
     */
    protected array $visible = [];

    /**
     * 数据输出隐藏的属性
     * @var array
     */
    protected array $hidden = [];

    /**
     * 数据输出需要追加的属性
     * @var array
     */
    protected array $append = [];

    /**
     * 场景
     * @var array
     */
    protected array $scene = [];

    public function append(array $append = [])
    {
        $this->append = $append;

        return $this;
    }

    public function scene(string $scene)
    {
        if (isset($this->scene[$scene])) {
            $data = $this->scene[$scene];
            foreach (['append', 'hidden', 'visible'] as $name) {
                if (isset($data[$name])) {
                    $this->$name($data[$name]);
                }
            }
        }

        return $this;
    }

    public function hidden(array $hidden = [])
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function visible(array $visible = [])
    {
        $this->visible = $visible;

        return $this;
    }

    public function toArray(): array
    {
        $item       = [];
        $hasVisible = false;

        foreach ($this->visible as $key => $val) {
            if (is_string($val)) {
                if (strpos($val, '.')) {
                    [$relation, $name]          = explode('.', $val);
                    $this->visible[$relation][] = $name;
                } else {
                    $this->visible[$val] = true;
                    $hasVisible          = true;
                }
                unset($this->visible[$key]);
            }
        }

        foreach ($this->hidden as $key => $val) {
            if (is_string($val)) {
                if (strpos($val, '.')) {
                    [$relation, $name]         = explode('.', $val);
                    $this->hidden[$relation][] = $name;
                } else {
                    $this->hidden[$val] = true;
                }
                unset($this->hidden[$key]);
            }
        }

        // 合并关联数据
        $data = $this->data;


        foreach ($data as $key => $val) {
            if ($val instanceof Model || $val instanceof ModelCollection) {
                // 关联模型对象
                if (isset($this->visible[$key]) && is_array($this->visible[$key])) {
                    $val->visible($this->visible[$key]);
                } elseif (isset($this->hidden[$key]) && is_array($this->hidden[$key])) {
                    $val->hidden($this->hidden[$key]);
                }
                // 关联模型对象
                if (!isset($this->hidden[$key]) || true !== $this->hidden[$key]) {
                    $item[$key] = $val->toArray();
                }
            } elseif (isset($this->visible[$key])) {
                $item[$key] = $this->getAttr($key);
            } elseif (!isset($this->hidden[$key]) && !$hasVisible) {
                $item[$key] = $this->getAttr($key);
            }
        }

        // 追加属性（必须定义获取器）
        foreach ($this->append as $key => $name) {
            $this->appendAttrToArray($item, $key, $name);
        }

            foreach ($item as $key => $val) {
                $name = Str::snake($key);
                if ($name !== $key) {
                    $item[$name] = $val;
                    unset($item[$key]);
                }
            }

        return $item;
    }

    protected function appendAttrToArray(array &$item, $key, $name)
    {
        $value       = $this->getAttr($name);
        $item[$name] = $value;
    }

    public function toJson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function __toString()
    {
        return $this->toJson();
    }

    // JsonSerializable
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    public function toCollection(iterable $collection = []): Collection
    {
        return new \think\Collection($collection);
    }

}