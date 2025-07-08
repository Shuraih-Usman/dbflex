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
    protected $rawWheres = [];

    protected $pdo;

    public function __construct($driver, $dbhost = null, $dbuser = null, $dbpassword = null, $dbname = null, $dbpath = null)
    {
        if ($driver === 'mysql') {
            $dsn = "mysql:host=$dbhost;dbname=$dbname;charset=utf8";
            $this->pdo = new PDO($dsn, $dbuser, $dbpassword);
        } elseif ($driver === 'sqlite') {
            $dsn = "sqlite:$dbpath";
            $this->pdo = new PDO($dsn);
        }

        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }


    public function dsn()
    {
        return $this->pdo; # return the pdo object
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

    public function where($column, $operator = null, $value = null)
    {
        if (is_array($column)) {
            // Support array of conditions: [['column', '=', value], ['column2', '!=', value2]]
            foreach ($column as $condition) {
                if (is_array($condition) && count($condition) === 3) {
                    $this->where[] = "{$condition[0]} {$condition[1]} ?";
                    $this->bindings[] = $condition[2];
                } else {
                    throw new InvalidArgumentException('Each condition must be an array with 3 elements: [column, operator, value]');
                }
            }
        } else     if (func_num_args() === 1 && is_string($column)) {
            $this->where[] = $column;
        } else {
            // Normal usage: where('column', '=', value) OR where('column', value)
            if (func_num_args() === 2) {
                $value = $operator;
                $operator = '=';
            }

            $this->where[] = "$column $operator ?";
            $this->bindings[] = $value;
        }

        return $this;
    }

    public function whereRaw($condition, $bindings = [])
    {
        $this->rawWheres[] = ['condition' => $condition, 'bindings' => $bindings];
        return $this;
    }



    public function orderBy($column, $direction = 'ASC')
    {
        $this->orderBy = $column == 'rand' ? "ORDER BY RAND()" : "ORDER BY $column $direction";
        return $this;
    }

    public function groupBy($column)
    {
        $this->groupBy = "GROUP BY $column";
        return $this;
    }

    public function last()
    {
        $this->orderBy('id', 'DESC')->limit(1);
        $result = $this->get();
        return !empty($result) ? $result[0] : null;
    }

    public function has()
    {
        $result = $this->get();
        return !empty($result) ? true : false;
    }

    public function orWhere($col, $val, $oper = '=')
    {
        if (empty($this->where)) {
            $this->where[] = "$col $oper ?";
        } else {
            $lastCondition = array_pop($this->where);
            $this->where[] = "($lastCondition OR $col $oper ?)";
        }

        $this->bindings[] = $val;
        return $this;
    }

    public function aggregate($function, $col)
    {
        $sql = "SELECT $function($col) as aggregate FROM $this->table";

        if (!empty($this->joins)) {
            $sql .= " " . implode(' ', $this->joins);
        }

        if (!empty($this->search)) {
            $conditions = array_merge($this->where, $this->search);
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        } else {

            if (!empty($this->where)) {
                $sql .= ' WHERE ' . implode(' AND ', $this->where);
            }
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($this->bindings as $index => $val) {
            $stmt->bindValue($index + 1, $val);
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->resetState();
        return $result['aggregate'] ?? null;
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

    public function leftJoin($table, $first = null, $operator = null, $second = null)
    {
        if (func_num_args() === 1) {
            // Raw join with full ON clause
            $this->joins[] = "LEFT JOIN {$table}";
        } else {
            $this->joins[] = "LEFT JOIN {$table} ON {$first} {$operator} {$second}";
        }
        return $this;
    }
    
    public function rightJoin($table, $first = null, $operator = null, $second = null)
    {
        if (func_num_args() === 1) {
            $this->joins[] = "RIGHT JOIN {$table}";
        } else {
            $this->joins[] = "RIGHT JOIN {$table} ON {$first} {$operator} {$second}";
        }
        return $this;
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

        $whereClauses = [];

        if (!empty($this->where)) {
            $whereClauses = array_merge($whereClauses, $this->where);
        }

        if (!empty($this->search)) {
            $whereClauses = array_merge($whereClauses, $this->search);
        }

        if (!empty($this->rawWheres)) {
            foreach ($this->rawWheres as $raw) {
                $whereClauses[] = $raw['condition'];
            }
        }

        if (!empty($whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
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

        $allBindings = $this->bindings;

        // Add raw where bindings
        foreach ($this->rawWheres as $raw) {
            foreach ($raw['bindings'] as $binding) {
                $allBindings[] = $binding;
            }
        }

        foreach ($this->bindings as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }

        $stmt->execute();
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
        $i = 1;

        foreach ($data as $value) {
            $stmt->bindValue($i, $value);
            $i++;
        }

        $stmt->execute();
        $this->resetState();
        return $this->pdo->lastInsertId();
    }

    public function update($data)
    {
        $set = '';
        // $bindings = array_values($data);

        foreach ($data as $column => $value) {
            $set .= "$column = ?, ";
        }

        $set = rtrim($set, ', ');

        $sql = "UPDATE $this->table SET $set";

        if (!empty($this->search)) {
            $conditions = array_merge($this->where, $this->search);
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        } else {

            if (!empty($this->where)) {
                $sql .= ' WHERE ' . implode(' AND ', $this->where);
            }
        }

        // $bindings = array_merge($bindings, $this->bindings);




        $stmt = $this->pdo->prepare($sql);

        $i = 1;
        foreach ($data as $value) {
            $stmt->bindValue($i, $value);
            $i++;
        }

        foreach ($this->bindings as $value) {
            $stmt->bindValue($i, $value);
            $i++;
        }

        $result = $stmt->execute();

        $this->resetState();
        return $result;
    }

    public function delete()
    {
        $sql = "DELETE FROM $this->table";

        if (!empty($this->search)) {
            $conditions = array_merge($this->where, $this->search);
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        } else {

            if (!empty($this->where)) {
                $sql .= ' WHERE ' . implode(' AND ', $this->where);
            }
        }

        $stmt = $this->pdo->prepare($sql);

        $i = 1;
        foreach ($this->bindings as $value) {
            $stmt->bindValue($i, $value);
            $i++;
        }
        $result = $stmt->execute();

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

            if (!empty($this->where)) {
                $sql .= ' WHERE ' . implode(' AND ', $this->where);
            }
        }

        $stmt = $this->pdo->prepare($sql);

        $i = 1;
        foreach ($this->bindings as $value) {
            $stmt->bindValue($i, $value);
            $i++;
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->resetState();
        return $result['count'];
    }


    public function find($id, $column = 'id')
    {
        return $this->table($this->table)->where($column, $id)->first();
    }

    public function pluck($column)
    {
        $this->select($column);
        $results = $this->get();
        return array_column($results, $column);
    }


    public function exists()
    {
        return $this->count() > 0;
    }

    public function doesntExist()
    {
        return !$this->exists();
    }


    public function latest($column = 'created_at')
    {
        return $this->orderBy($column, 'DESC');
    }


    public function oldest($column = 'created_at')
    {
        return $this->orderBy($column, 'ASC');
    }

    public function value($column)
    {
        $this->select($column)->limit(1);
        $result = $this->get();
        return !empty($result) ? $result[0][$column] ?? null : null;
    }

    public function increment($column, $amount = 1)
    {
        $sql = "UPDATE $this->table SET $column = $column + ?";

        if (!empty($this->where)) {
            $sql .= ' WHERE ' . implode(' AND ', $this->where);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $amount);

        $i = 2;
        foreach ($this->bindings as $value) {
            $stmt->bindValue($i++, $value);
        }

        $result = $stmt->execute();
        $this->resetState();
        return $result;
    }

    public function decrement($column, $amount = 1)
    {
        return $this->increment($column, -$amount);
    }

    public function take($limit)
    {
        return $this->limit($limit);
    }

    public function toSql()
    {
        return $this->rawSql ?: $this->buildSelectSql();
    }

    public function truncate()
    {
        $sql = "DELETE FROM $this->table";
        $this->pdo->exec($sql);
        $this->resetState();
    }

    public function whereIn($column, array $values)
    {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->where[] = "$column IN ($placeholders)";
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function orWhereIn($column, array $values)
    {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        if (empty($this->where)) {
            $this->where[] = "$column IN ($placeholders)";
        } else {
            $last = array_pop($this->where);
            $this->where[] = "($last OR $column IN ($placeholders))";
        }
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function max($column)
    {
        return $this->aggregate('MAX', $column);
    }

    public function min($column)
    {
        return $this->aggregate('MIN', $column);
    }

    public function avg($column)
    {
        return $this->aggregate('AVG', $column);
    }

    public function firstOrFail()
    {
        $result = $this->first();
        if (!$result) {
            throw new Exception("No records found.");
        }
        return $result;
    }

    public function whereNull($column)
    {
        $this->where[] = "$column IS NULL";
        return $this;
    }

    public function whereNotNull($column)
    {
        $this->where[] = "$column IS NOT NULL";
        return $this;
    }

    public function run()
    {
        $sql = $this->rawSql;
        $stmt = $this->pdo->prepare($sql);

        foreach ($this->bindings as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }

        $success = $stmt->execute();
        $this->resetState();
        return $success;
    }

    public function execute($sql, $bindings = [])
    {
        $stmt = $this->pdo->prepare($sql);

        foreach ($bindings as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }

        $success = $stmt->execute();
        return $success;
    }

    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    public function transaction(callable $callback)
    {
        try {
            $this->startTransaction();
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            return $e;
        }
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
        $this->rawWheres = [];
    }
}
