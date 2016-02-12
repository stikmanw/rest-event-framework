<?php

require __DIR__ . "/../vendor/autoload.php";

use Commando\Command;
use Colors\Color;
use Common\Storage\Connection\Mysql;

$cmd = new Command();
$cmd->option('configPath')
    ->describedAs('db config path to use for credentials')
    ->require();

$cmd->option('group')
    ->describedAs('group to use in db configs `default`');

$cmd->option('db')
    ->describedAs('database name')
    ->require();


$cmd->option('t')
    ->aka('table')
    ->describeAs("table to generate a model for")
    ->require();

$cmd->option('e')
    ->aka('environment')
    ->describeAs("Environment to run mysql connection against")
    ->require();

$cmd->option('d')
    ->aka('directory')
    ->describeAs("Directory to store the results")
    ->require();

$cmd->option('n')
    ->aka('namespace')
    ->describeAs("Namespace for models")
    ->require();

$mysql = new \Common\Storage\Connection\Mysql(array(
    "environment" => $cmd['e'],
    "configPath" => $cmd['configPath'],
    "database" => $cmd['db'],
    "group" => $cmd['group']
));

$columns = $mysql->getColumnList($cmd['db'], $cmd['t']);

$namespace = $cmd['n'];
$class = $cmd['table'];

$props = array();
foreach($columns as $col => $details) {
    if(in_array($col, array('DateAdded','DateTimeAdded','LastUpdated'))) { continue; }
    $props[] = "\tpublic \$" . \Common\Tool\Introspection::modelizeName($col) . ";";
}
$props = implode(PHP_EOL . PHP_EOL, $props);

$output = <<<eot
<?php
namespace $namespace;

use Common\Model\BaseModel;
class $class extends BaseModel
{
$props
}
eot;

$file = $cmd['d'] . $cmd['t'] . ".php";
echo "writing: " . $file . PHP_EOL;
file_put_contents($file, $output);

