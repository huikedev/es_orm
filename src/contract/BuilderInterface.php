<?php


namespace huikedev\es_orm\contract;


interface BuilderInterface
{
    public function select(QueryInterface $query): array;

    public function save(QueryInterface $query): array;

    public function saveAll(QueryInterface $query,iterable $dataSet): array;

    public function delete(QueryInterface $query): array;

    public function optionsToParams(QueryInterface $query): array;
}