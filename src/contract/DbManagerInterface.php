<?php


namespace huikedev\es_orm\contract;


interface DbManagerInterface
{
    public function connect(string $name='default',bool $force = false):ConnectionInterface;

    public function getConfig(string $name = '', $default = null);
}