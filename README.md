
# DBFlex

DBFlex is a lightweight PHP database abstraction library designed to simplify database operations using PDO. It provides a fluent interface for performing common database operations such as selecting, inserting, updating, deleting records, and handling transactions.

## Requirements
- PHP 7.4 or higher
- PDO extension enabled
- MySQL database
- SQLITE3 database

## Installation

### Via Composer

Add DBFlex to your project via Composer. Run the following command in your project directory:

```
composer require shuraih/dbflex
```
### Manual Installation
Download the DBFlex library and include it in your project:


## Usage

### Initialize the DBFlex Class

```
require_once 'path/to/DBFlex.php';
// Initialize the DB class with  MYSQL database credentials
$db = new DBFlex('mysql', 'localhost', 'dbuser', 'dbpassword', 'test_db');


// Initialize the DB class with  SQLITE database credentials
$dbPath = 'sqlite.db';
$db = new DBFlex('sqlite', null, null, null, null, $dbPath);

```

### Insert Data
```
$data = ['name' => 'John Doe', 
'email' => 'john@example.com'];
$id = $db->table('users')->insert($data);
echo "Inserted ID: " . $id . "\n"; ###getting last inserted ID;
```

###  Selecting specific columns
```
$db->table('users')->select('name')->where('id', $id);
$results = $db->get();
echo "Select Result:\n";
print_r($results);
```

### selecting All columns
```
$db->table('users');
$results = $db->get();
echo "Select Result:\n";
print_r($results);
```

### where clause using equality operator
```
$db->table('users')->select('name')->where('id', $id, '=');
$results = $db->get();
echo "Select Result:\n";
print_r($results);
```

### where clause using lessthan operator
```
$db->table('users')->select('name')->where('id', $id, '<');
$results = $db->get();
echo "Select Result:\n";
print_r($results);
```
### where clause using greathan operator
```
$db->table('users')->select('name')->where('id', $id, '>');
$results = $db->get();
echo "Select Result:\n";
print_r($results);
```


### where clause using default which is equality operator
```
$db->table('users')->select('name')->where('id', $id);
$results = $db->get();
echo "Select Result:\n";
print_r($results);
```
### Update Data
```
$data = ['name' => 'Jane Doe'];
$db->table('users')->where('id', $id)->update($data);
$db->table('users')->select('name')->where('id', $id);
$results = $db->get();
echo "Updated Result:\n";
print_r($results);
```

### Delete Data
```
$db->table('users')->where('id', $id)->delete();
$db->table('users')->select()->where('id', $id);
$results = $db->get();
echo "After Delete:\n";
print_r($results);
```
### Count 
```
$count = $db->table('users')->count();
echo "User Count: " . $count . "\n";
```

### OrderBy
```
$db->table('users')->select('name')->orderBy('name', 'DESC');
$results = $db->get();
echo "Ordered Results:\n";
print_r($results);
```

### Limit for pagination
```
$db->table('users')->select('name')->limit(1);
$results = $db->get();
echo "Limited Results:\n";
print_r($results);
```

### Offset for pagination
```
$db->table('users')->select('name')->offset(1)->limit(1);
$results = $db->get();
echo "Offset Results:\n";
print_r($results);
```

### Join : Inner Join
```
$db->table('users')
    ->select('users.name, orders.id')
    ->join('orders', 'users.id', '=', 'orders.user_id');
$results = $db->get();
echo "Join Results:\n";
print_r($results);
```

### Left Join
```
echo " Left Join...\n";
$db->table('users')
    ->select('users.name', 'orders.product')
    ->leftJoin('orders', 'users.id', '=', 'orders.user_id');
$results = $db->get();
echo "Left Join Results:\n";
print_r($results);
```

### Right Join
```
echo " Right Join...\n";
$db->table('users')
    ->select('users.name', 'orders.product')
    ->rightJoin('orders', 'users.id', '=', 'orders.user_id');
$results = $db->get();
echo "Right Join Results:\n";
print_r($results);
```


###  get first data
```
$db->table('users')->where('status', 1)->first();
print_r($results);
```

### Raw Query
```
$db->raw('SELECT * FROM users WHERE email = ?', ['john@example.com']);
$results = $db->get();
echo "Raw Query Results:\n";
print_r($results);
```

### GroupBy
```
$db->table('orders')->select('COUNT(*) as count')->groupBy('status');
$results = $db->get();
echo "GroupBy Results:\n";
print_r($results);
```

### Search
```
$db->table('users')->select('name')->search(['name'], 'John');
$results = $db->get();
echo "Search Results:\n";
print_r($results);
```

### Transaction 

```
$db->startTransaction();
if($db->table('users')->insert($data))
{
    $db->commit();
} else {
    $db->rollback();
}
```

### Transaction Function

```
try {
    $db->transaction(function($db) {
        $db->table('accounts')->where('id', 1)->update(['balance' => 800]);
        $db->table('accounts')->where('id', 2)->update(['balance' => 1200]);
    });

    echo "Transaction successful";
} catch (Exception $e) {
    echo "Transaction failed: " . $e->getMessage();
}
```

### Execute and run SQL

```
$db->raw("UPDATE users SET name = ? WHERE id = ?", ['Ahmed', 1])->run();

$db->execute("DELETE FROM users WHERE id = ?", [7]);
```

### find() Find row by ID

```
// default column is "id"
$user = $db->table('users')->find(3);

// you can define the column name
$user = $db->table('users')->find(3, 'user_id');
```

### lastInsertId() get the last row Insert ID

```
// default column is "id"
$id = $db->lastInsertId();


```

### Pluck() - Return values from a single column.
```
$emails = $db->table('users')->pluck('email');
// ['test@gmail.com', 'admin@x.com', ...]
```

### exists() - Check if any record matches the condition..
```
$found = $db->table('users')->where('email', 'admin@x.com')->exists();
// true or false
```


### doesntExist() - Inverse of exists().
```
$notFound = $db->table('users')->where('email', 'none@x.com')->doesntExist();
// true or false
```

### value($column) - Get the value of a single column from the first result.
```
$name = $db->table('users')->where('id', 1)->value('name');
// shuraihu
```

### increment($column, $amount = 1) - Increase a numeric field.
```
$db->table('products')->where('id', 5)->increment('stock', 10);
```

### decrement($column, $amount = 1) - Decrease a numeric field.
```
$db->table('products')->where('id', 5)->decrement('stock', 2);
```

### whereIn($column, array $values) - Filter by multiple values.
```
$admins = $db->table('users')->whereIn('role', ['admin', 'super'])->get();
```

### whereNotIn($column, array $values) - Opposite of whereIn.
```
$nonAdmins = $db->table('users')->whereNotIn('role', ['admin'])->get();

```

### truncate() - Empty the table.
```
$db->table('logs')->truncate();
```

### whereNull($column) - Filter where column IS NULL.
```
$noEmails = $db->table('users')->whereNull('email')->get();

```

### whereNotNull($column) - Filter where column IS NOT NULL.
```
$withEmails = $db->table('users')->whereNotNull('email')->get();

```

### firstOrFail() - Throws error if no result found.
```
$user = $db->table('users')->where('id', 999)->firstOrFail();

```

### toSql() - Returns the compiled SQL (for debugging).
```
$sql = $db->table('users')->where('name', 'Ali')->toSql();
echo $sql;

```
### min('column'), max(), avg() - Get min, amx and average value, 
```
	$db->table('orders')->min('amount');
    $db->table('orders')->max('amount');
    $db->table('orders')->avg('amount');
```

### Author
- Shuraihu Usman
- +2348140419490
- shuraihusman@gmail.com
