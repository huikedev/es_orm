<?php


namespace huikedev\es_orm\concern;


use Closure;
use huikedev\es_orm\exception\ElasticsearchException;
use huikedev\es_orm\query\Query;

trait WhereQuery
{

    protected array $exp = [
        '=' => 'eq',
        '>' => 'gt',
        '>=' => 'gte',
        '<' => 'lt',
        '<=' => 'lte',
        'in' => 'in',
        'exists' => 'exists',
        'between' => 'between',
        'like' => 'match'
    ];

    protected array $must = [];

    protected array $filter = [];

    protected array $should = [];

    protected array $mustNot = [];

    public function queryFilter(Closure $callback,?string $key = null):self
    {
        if(is_null($key)){
            $this->body['filter'][] = $callback;
            return $this;
        }
        $this->body['filter'][$key] = $callback;
        return $this;
    }

    public function queryMust(Closure $callback,?string $key = null):self
    {
        if(is_null($key)){
            $this->body['must'][] = $callback;
            return $this;
        }
        $this->body['must'][$key] = $callback;
        return $this;
    }

    public function queryShould(Closure $callback,?string $key = null):self
    {
        if(is_null($key)){
            $this->body['should'][] = $callback;
            return $this;
        }
        $this->body['should'][$key] = $callback;
        return $this;
    }

    public function queryMustNot(Closure $callback,?string $key = null):self
    {
        if(is_null($key)){
            $this->body['must_not'][] = $callback;
            return $this;
        }
        $this->body['must_not'][$key] = $callback;
        return $this;
    }

    public function where(string $field,string $op,$condition,array $append = []):self
    {
        return $this->parseWhereExp('must', $field, $op, $condition,$append);
    }

    public function whereOr(string $field,string $op,$condition,array $append = []):self
    {
        return $this->parseWhereExp('should', $field, $op, $condition,$append);
    }

    public function whereFilter(string $field,string $op,$condition,array $append = []):self
    {
        return $this->parseWhereExp('filter', $field, $op, $condition,$append);
    }

    public function whereNot(string $field,string $op,$condition,array $append = []):self
    {
        return $this->parseWhereExp('must_not', $field, $op, $condition,$append);
    }

    protected function parseWhereExp(string $logic,string $field,string $op, $condition,array $append = []):self
    {
        $search = $this->parseOpToSearch($op);
        if(in_array($op,['>','<','>=','<='])){
            $this->body[$logic][$field][$search][$field][$this->exp[$op]] = $condition;
        }elseif ($op === 'exists'){
            $this->body[$logic][$field]['exists']['field'] = $field;
        }else{
            $this->body[$logic][$field][$search][$field] = $condition;
        }
        if($field === $this->getPk() && $op==='='){
            $this->options['id'] = $condition;
        }
        return $this;
    }

    protected function parseOpToSearch(string $op): string
    {
        if($op === '='){
            return 'term';
        }
        if($op === 'in'){
            return 'terms';
        }
        if($op === 'exists'){
            return 'exists';
        }
        if($op === 'like'){
            return 'match';
        }
        if($op === 'phrase'){
            return 'match_phrase';
        }
        if(in_array($op,['>','<','>=','<='])){
            return 'range';
        }
        throw new ElasticsearchException('Operator Error: not support operator '.$op);
    }

    public function whereTerm(string $field,$value,string $logic = 'must',array $append = []):self
    {
        return $this->parseWhereExp($this->parseLogic(__METHOD__,$logic),$field,'=',$value,$append);
    }

    public function whereTerms(string $field,array $value,string $logic = 'must', array $append = []):self
    {
        if(empty($value)){
            return $this->parseWhereExp('must_not',$field,'exists',$value,$append);
        }
        return $this->parseWhereExp($this->parseLogic(__METHOD__,$logic),$field,'in',$value,$append);
    }

    protected function parseLogic(string $method,string $logic): string
    {
        $logic = strtolower($logic);
        $boolean = [
          'not'=>'must_not'
        ];
        $logicArray = ['must','should','filter','not','must_not'];
        if(in_array($logic,$logicArray) === false){
            throw new ElasticsearchException('Logic Error: '.$method .' logic only support [ '.implode(' , ',$logicArray).' ]');
        }
        return $boolean[$logic] ?? $logic;
    }

    public function whereMatch(string $field,string $value,string $logic='must',array $append = []):self
    {
        return $this->parseWhereExp($this->parseLogic(__METHOD__,$logic),$field,'like',$value,$append);
    }

    public function whereMatchPhrase(string $field,string $value,string $logic='must',array $append = []):self
    {
        return $this->parseWhereExp($this->parseLogic(__METHOD__,$logic),$field,'phrase',$value,$append);
    }

    public function whereIn(string $field,array $value,string $logic='must',array $append = []):self
    {
        return $this->whereTerms($field,$value,$logic,$append);
    }

    public function whereBetween(string $field,array $value,string $logic='must',array $append = []):self
    {
        if(count($value) !== 2){
            throw new ElasticsearchException('Condition Error: whereBetween $condition only support 2 elements array');
        }
        $this->parseWhereExp($this->parseLogic(__METHOD__,$logic),$field,'>=',$value[0],$append);
        $this->parseWhereExp($this->parseLogic(__METHOD__,$logic),$field,'<=',$value[1],$append);
        return $this;
    }

    public function whereExists(string $field,string $logic='must',$append = []):self
    {
        return $this->parseWhereExp($this->parseLogic(__METHOD__,$logic), $field, 'exists', true,$append);
    }
}