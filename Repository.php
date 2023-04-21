<?php

# Здесь содержится пространство имён...

// Класс для методов работы моделей с БД
abstract class Repository
{

    private array $wheres = [];
    private array $orderBy = [];

    abstract protected function getTableName();
    abstract protected function getIdName();
    abstract protected function getEntityClass();

    public function getOne(int $id = null, array $columns = []) : object|false
    {
        $tableName = $this->getTableName();
        $params = [];

        if (!$columns){
            $column = '*';
        }else{
            $column = '`' . implode('`, `', $columns) . '`';
        }

        $sql = "SELECT {$column} FROM `{$tableName}` WHERE ";

        if ($this->wheres){
            $sql .= "`{$this->wheres[0]['column']}` {$this->wheres[0]['comparisonChar']} :where_0";
            $params[":where_0"] = $this->wheres[0]['value'];
            $this->wheres = [];
        }else{
            $sql .= "`{$tableName}_id` = :id";
            $params['id'] = $id;
        }

        return Admiral::call()->db->queryOneObject($sql, $params, $this->getEntityClass());
    }

    public function getAll(array $columns = [], $distinct = null) : array
    {
        if (isset($distinct)) $distinct = 'DISTINCT';

        $tableName = $this->getTableName();
        $params = [];

        if (!$columns){
            $column = '*';
        }else{
            $column = '`' . implode('`, `', $columns) . '`';
        }

        $sql = "SELECT {$distinct} {$column} FROM `{$tableName}`";

        if ($this->wheres){
            $where = '';

            foreach ($this->wheres as $key => $value){
                if (isset($value['combineChar'])) $where .= " {$value['combineChar']} ";
                if ($value['comparisonChar'] == "IN"){
                    $in = '';
                    foreach ($value['value'] as $keyIn => $valueIn){
                        if (!empty($in)) $in .= ', ';
                        $in .= ":in_{$keyIn}";
                        $params[":in_{$keyIn}"] = $valueIn;
                    }
                    $where .= "`{$value['column']}` {$value['comparisonChar']} ({$in})";
                }else{
                    $where .= "`{$value['column']}` {$value['comparisonChar']} :where_{$key}";
                    $params[":where_{$key}"] = $value['value'];
                }
            }
            $sql .= " WHERE {$where}";
            $this->wheres = [];
        }

        if ($this->orderBy){
            $count = 0;
            $orders = '';

            foreach ($this->orderBy as $value){
                if (++$count > 1) $orders .= ", ";
                $orders .= "`{$value['column']}` {$value['method']}";
            }
            $sql .= " ORDER BY {$orders}";
            $this->orderBy = [];
        }
        return Admiral::call()->db->queryAll($sql, $params);
    }

    public function where($column, $value, $comparisonChar = "=") :object
    {
        $this->wheres[] = [
            'column' => $column,
            'value' => $value,
            'comparisonChar' => $comparisonChar,
        ];
        return $this;
    }

    public function andWhere($column, $value, $comparisonChar = "=") :object
    {
        $this->wheres[] = [
            'column' => $column,
            'value' => $value,
            'comparisonChar' => $comparisonChar,
            'combineChar' => '&&',
        ];
        return $this;
    }

    public function orWhere($column, $value, $comparisonChar = "=") :object
    {
        $this->wheres[] = [
            'column' => $column,
            'value' => $value,
            'comparisonChar' => $comparisonChar,
            'combineChar' => '||'
        ];
        return $this;
    }

    public function orderBy($column, $method = "ASC")
    {
        $this->orderBy[] = [
            'column' => $column,
            'method' => $method
        ];
        return $this;
    }

    public function insert(Entity $entity)
    {
        $params = [];
        $columns = [];
        $tableName = $this->getTableName();
        $id = $this->getIdName();

        foreach ($entity->props as $key => $value) {
            $params[":{$key}"] = $entity->$key;
            $columns[] = $key;
        }

        $columns = '`' . implode('`, `', $columns) . '`';
        $values = implode(', ', array_keys($params));

        $sql = "INSERT INTO `{$tableName}` ({$columns}) VALUES ({$values})";
        Admiral::call()->db->execute($sql, $params);
        $entity->$id = Admiral::call()->db->lastInsertId();

        return $this;
    }

    public function delete(Entity $entity)
    {
        $tableName = $this->getTableName();
        $id = $this->getIdName();
        $sql = "DELETE FROM `{$tableName}` WHERE `{$id}` = :id";
        return Admiral::call()->db->execute($sql, ['id' => $entity->$id]);
    }

    public function update(Entity $entity)
    {
        $params = [];
        $columns = [];
        $tableName = $this->getTableName();
        $id = $this->getIdName();

        foreach ($entity->props as $key => $value) {
            if (empty($value)) continue;
            $params[":{$key}"] = $entity->$key;
            $columns[] .= "`{$key}` = :{$key}";
            $entity->props[$key] = false;
        }
        $columns = implode(', ', $columns);
        $params[":".$id] = $entity->$id;
        $sql = "UPDATE `{$tableName}` SET {$columns} WHERE `{$id}` = :{$id}";
        return Admiral::call()->db->execute($sql, $params);
    }

    public function save(Entity $entity)
    {
        $id = $this->getIdName();
        if ($entity->$id == 0){
            return $this->insert($entity);
        }else{
            return $this->update($entity);
        }
    }

    public function getClass(){
        $class = $this->getEntityClass();
        return (new $class);
    }
}