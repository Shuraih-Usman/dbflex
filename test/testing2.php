<?php

require_once('../src/DBFlex.php');

$db = new DBFlex('localhost', 'root', '', 'dbflex');


$data =  [
    'name' => 'Malam Garba',
    'email' => 'testing@gmail.com',
    'address' => 'Ganaganaaakjskjskj',
    'status' => 1

];





$rows = $db->table('users')->limit(2)->get();

foreach($rows as $row) 
{
    echo $row['name']." ".$row['email']."<br>";
}

