<?php


namespace huikedev\es_orm\contract;


use Closure;
use huikedev\es_orm\model\EsModel;
use think\Collection;
use think\Model;
use think\Paginator;

interface QueryInterface
{
    public function getIndexName():string;

    public function deleteIndex():bool;

    public function createIndex():bool;

    public function updateMappings():bool;

    public function updateSettings():bool;

    public function updateAliases():bool;

    public function getBody():array;

    public function getOptions():mixed;

    public function handler();

    public function find(): EsModel | Model;

    public function findOrEmpty(): EsModel | Model;

    public function select(): Collection;

    public function save(iterable $data = []): bool;

    public function data(iterable $data):self;

    public function saveAll(iterable $dataSet): int;

    public function delete(): bool;

    public function column($column, string $key = ''): array;

    public function limit(int $offset, int $length = 10):self;

    public function page(int $current = 1,int $pageSize = 10):self;

    public function count(bool $force = false): int | bool;

    public function sum($field,bool $force = false): float | bool;

    public function min($field, bool $force = false):float | bool;

    public function max($field, bool $force = false):float | bool;

    public function avg($field,bool $force = false): float | bool;

    public function paginate($listRows = null):Paginator;

    public function newQuery():self;

    public function getPk():string;

    public function setModel(EsModel $esModel):self;

    public function getModel():EsModel;

    public function getAppendParams():array;

    public function queryFilter(Closure $callback,?string $key = null):self;

    public function queryMust(Closure $callback,?string $key = null):self;

    public function queryShould(Closure $callback,?string $key = null):self;

    public function queryMustNot(Closure $callback,?string $key = null):self;

    public function where(string $field,string $op,$condition,array $append = []):self;

    public function whereOr(string $field,string $op,$condition,array $append = []):self;

    public function whereFilter(string $field,string $op,$condition,array $append = []):self;

    public function whereNot(string $field,string $op,$condition,array $append = []):self;

    public function whereTerm(string $field,$value,string $logic = 'must',array $append = []):self;

    public function whereTerms(string $field,array $value,string $logic = 'must', array $append = []):self;

    public function whereMatch(string $field,string $value,string $logic='must',array $append = []):self;

    public function whereMatchPhrase(string $field,string $value,string $logic='must',array $append = []):self;

    public function whereIn(string $field,array $value,string $logic='must',array $append = []):self;

    public function whereExists(string $field,string $logic='must',$append = []):self;

    public function whereBetween(string $field,array $value,string $logic='must',array $append = []):self;
}