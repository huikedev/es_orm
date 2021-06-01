<?php


namespace huikedev\es_orm\concern;


trait AggregateQuery
{
    public function aggregate(string $aggregate,string $field)
    {
        return $this->connection->aggregate($this,$aggregate,$field);
    }

    public function count(bool $force = false): int | bool
    {
        $res = $this->aggregate('count','*');
        return $force ? intval($res) : $res;
    }

    public function sum($field,bool $force = false): float | bool
    {
        $res = $this->aggregate('sum',$field);
        return $force ? floatval($res) : $res;
    }

    public function min($field, bool $force = false):float | bool
    {
        $res = $this->aggregate('min',$field);
        return $force ? floatval($res) : $res;
    }

    public function max($field, bool $force = false):float | bool
    {
        $res = $this->aggregate('max',$field);
        return $force ? floatval($res) : $res;
    }

    public function avg($field,bool $force = false): float | bool
    {
        $res = $this->aggregate('avg',$field);
        return $force ? floatval($res) : $res;
    }
}