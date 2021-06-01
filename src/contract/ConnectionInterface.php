<?php


namespace huikedev\es_orm\contract;


interface ConnectionInterface
{

    public function setDb(DbManagerInterface $db):self;

    public function getBuilderClass(): string;

    public function getQueryClass(): string;

    public function deleteIndex(array $params):bool;

    public function createIndex(array $params):bool;

    public function updateMappings(array $params):bool;

    public function updateSettings(array $params):bool;

    public function updateAliases(array $params):bool;

    public function handler();

    public function getOriginResult():array | null;

    public function aggregate(QueryInterface $query, string $aggregate, $field);

    public function find(QueryInterface $query): iterable;

    public function select(QueryInterface $query): iterable;

    public function save(QueryInterface $query): bool;

    public function saveAll(QueryInterface $query,iterable $dataSet): int;

    public function delete(QueryInterface $query): bool;

    public function column(QueryInterface $query,$column, string $key = ''): array;

    public function newQuery():QueryInterface;

    public function getBuilder():BuilderInterface;

    public function getLastParams():array;
}