<?php
namespace Knit\Store;

interface StoreInterface
{

    public function get($collection, array $criteria = array(), $params = array());

    public function count($collection, array $criteria = array(), $params = array());

    public function add($collection, array $properties);

    public function update($collection, array $criteria, array $values);

    public function delete($collection, array $criteria);

}