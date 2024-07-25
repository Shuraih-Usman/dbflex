<?php


class DBFlex
{
    private static $instance = null;
    protected $table;
    protected $select = '*';
    protected $where = [];
    protected $bindings = [];
    protected $orderBy = '';
    protected $limit = '';
    protected $offset = '';
    protected $joins = [];
    protected $rawSql = '';
    protected $groupBy = '';
    protected $search = [];
    protected $pdo;

    public function __construct($dbhost, $dbuser, $dbpassword, $dbname) 
    {
        $dsn = "mysql:host=$dbhost;dbname=$dbname;charset=utf8";
        $this->pdo = new PDO($dsn, $dbuser, $dbpassword);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }



    public function startTransaction() 
    {
        $this->pdo->beginTransaction();
    }

    public function commit() 
    {
        $this->pdo->commit();
    }

    public function rollback() 
    {
        $this->pdo->rollBack();
    }


    public function table($table)
    {
        $this->table = $table;
        return $this;
    }

    public function select($columns = '*')
    {
        $this->select = is_array($columns) ? implode(', ', $columns) : $columns;
        return $this;
    }

    public function where($column, $value, $operator = '=')
    {
        $this->where[] = "$column $operator ?";
        $this->bindings[] = $value;
        return $this;
    }

    public function orderBy($column, $direction = 'ASC')
    {
        $this->orderBy = strtoupper($column) === 'RAND()' ? "ORDER BY RAND()" : "ORDER BY $column $direction";
        return $this;
    }

    public function groupBy($column)
    {
        $this->groupBy = "GROUP BY $column";
        return $this;
    }

    public function search($columns, $value)
    {
        $searchConditions = [];
        foreach ($columns as $column) {
            $searchConditions[] = "$column LIKE ?";
            $this->bindings[] = "%$value%";
        }
        $this->search[] = '(' . implode(' OR ', $searchConditions) . ')';
        return $this;
    }

    public function limit($limit)
    {
        $this->limit = "LIMIT $limit";
        return $this;
    }

    public function offset($offset)
    {
        $this->offset = "OFFSET $offset";
        return $this;
    }

    public function join($table, $first, $operator, $second, $type = 'INNER')
    {
        $this->joins[] = "$type JOIN $table ON $first $operator $second";
        return $this;
    }

    public function leftJoin($table, $first, $operator, $second)
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function rightJoin($table, $first, $operator, $second)
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    public function raw($sql, $bindings = [])
    {
        $this->rawSql = $sql;
        $this->bindings = $bindings;
        return $this;
    }

    protected function buildSelectSql()
    {
        $sql = "SELECT $this->select FROM $this->table";



        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        if (!empty($this->search)) {
            $conditions = array_merge($this->where, $this->search);
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        } else {

            if(!empty($this->where)) {
                $sql .= ' WHERE ' . implode(' AND ', $this->where);
            }
        }

        if (!empty($this->orderBy)) {
            $sql .= " $this->orderBy";
        }

        if (!empty($this->limit)) {
            $sql .= " $this->limit";
        }

        if (!empty($this->offset)) {
            $sql .= " $this->offset";
        }

        return $sql;
    }

    public function get()
    {
        $sql = $this->rawSql ?: $this->buildSelectSql();
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->resetState();
        return $result;
    }

    public function first()
    {
        $this->limit(1);
        $results = $this->get();
        return !empty($results) ? $results[0] : null;
    }

    public function insert($data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO $this->table ($columns) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));

        $this->resetState();
        return $this->pdo->lastInsertId();
    }

    public function update($data)
    {
        $set = '';
        $bindings = array_values($data);

        foreach ($data as $column => $value) {
            $set .= "$column = ?, ";
        }

        $set = rtrim($set, ', ');
        $bindings = array_merge($bindings, $this->bindings);

        $sql = "UPDATE $this->table SET $set";

        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($bindings);

        $this->resetState();
        return $result;
    }

    public function delete()
    {
        $sql = "DELETE FROM $this->table";

        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($this->bindings);

        $this->resetState();
        return $result;
    }

    public function count()
    {
        $sql = "SELECT COUNT(*) as count FROM $this->table";

        if (!empty($this->joins)) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        if (!empty($this->search)) {
            $conditions = array_merge($this->where, $this->search);
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        } else {

            if(!empty($this->where)) {
                $sql .= ' WHERE ' . implode(' AND ', $this->where);
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->resetState();
        return $result['count'];
    }

    protected function resetState()
    {
        $this->table = null;
        $this->select = '*';
        $this->where = [];
        $this->bindings = [];
        $this->orderBy = '';
        $this->limit = '';
        $this->offset = '';
        $this->joins = [];
        $this->rawSql = '';
        $this->groupBy = '';
        $this->search = [];
    }
}