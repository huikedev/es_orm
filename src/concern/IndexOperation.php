<?php


namespace huikedev\es_orm\concern;


trait IndexOperation
{
    public function getIndexName():string
    {
        return $this->getModel()->getIndex();
    }


    public function deleteIndex():bool
    {
        $params['index'] = $this->getIndexName();
        return $this->connection->deleteIndex($params);
    }

    public function createIndex():bool
    {
        $params['index'] = $this->getIndexName();
        $settings        = $this->getModel()->getEsSettings();
        $mappings        = $this->getModel()->getEsMappings();
        $aliases         = $this->getModel()->getEsAliases();

        if (count($settings) > 0) {
            $params['body']['settings'] = $settings;
        }

        if (count($mappings) > 0) {
            $params['body']['mappings'] = $mappings;
        }

        if (count($aliases) > 0) {
            $params['body']['aliases'] = $aliases;
        }
        return $this->connection->createIndex($params);
    }

    public function updateMappings():bool
    {
        $params['index'] = $this->getIndexName();
        $params['body']  = $this->getModel()->getEsMappings();
        return $this->connection->updateMappings($params);
    }

    public function getMappings():array
    {
        $params['index'] = $this->getIndexName();
        return $this->connection->getMappings($params);
    }

    public function updateSettings():bool
    {
        $params['index'] = $this->getIndexName();
        $params['body']  = $this->getModel()->getEsSettings();
        return $this->connection->updateSettings($params);
    }

    public function updateAliases():bool
    {
        $params['index'] = $this->getIndexName();
        $params['body']  = $this->getModel()->getEsAliases();
        return $this->connection->updateAliases($params);
    }
}