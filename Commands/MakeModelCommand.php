<?php

/**
 * Created by PhpStorm.
 * User: ziqing
 * Date: 2018/5/29
 * Time: 下午2:22
 */

namespace ziqing\ddd\tool;

use Illuminate\Database\Capsule\Manager;
use Symfony\Component\Dotenv\Dotenv;
use ziqing\ddd\tool\traits\CollectPropertiesFromConsoleTrait;
use ziqing\ddd\tool\traits\DataGenerateTrait;
use ziqing\ddd\tool\traits\DealClassFileNameTrait;
use ziqing\ddd\tool\traits\PreviewTrait;
use ziqing\ddd\tool\values\Column;
use ziqing\ddd\tool\values\Property;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeModelCommand extends BaseCommand
{
    use DataGenerateTrait;
    use CollectPropertiesFromConsoleTrait;
    use DealClassFileNameTrait;
    use PreviewTrait;

    protected $generatorType = 'model';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:model 
                                {className : 指定模型类名称}
                                {--table=  : 指定对应表名称}
                                {--sub-domain=Core : 指定模型所属子域} 
                                {--preview : 预览，不写入文件}
                                {--force   : 强制覆盖}
                                {--env-file= : 指定配置有MySQL数据库连接信息的 env 文件}
                                ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'make a Model class refer one exist table; env file should look like:
        DB_HOST=127.0.0.1
        DB_PORT=3306
        DB_USERNAME=root
        DB_PASSWORD=123456
        DB_DATABASE=demo
    ';

    private $connection = '';

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->connection = $this->hasOption('connection') ? $this->option('connection') : getenv('DB_CONNECTION');

        $this->table = $this->option('table');
        $this->validateTableExists($this->table);

        $className = $this->argument('className');
        $subDomain = $this->option('sub-domain');
        $this->setPackage($subDomain);
        $this->setClassName($className);

        $filename = $this->getFilename();
        $this->doConfirmWhenFileExists($filename);

        $template = file_get_contents(__DIR__ . "/../templates/Model.tpl");
        $content = $this->buildFileContent($template);

        $this->previewOrWriteNow($filename, $content);
        return 0;
    }

    private function validateTableExists($table)
    {
        $this->initMysqlConnection();
        if (!Manager::schema($this->connection)->hasTable($table)) {
            $this->error("Table:$table not exists.");
            die;
        }
    }

    private $table;

    /**
     * @param string $template
     * @return string
     */
    protected function buildFileContent(string $template)
    {
        $this->getTableDefinition($this->table);

        $searches = [
            '{{namespace}}',
            '{{className}}',
            '{{package}}',
            '{{properties}}',
            '{{table}}',
            '{{connection}}'
        ];

        $replaces = [
            $this->getNamespace(),
            $this->getClassName(),
            $this->getPackage(),
            $this->getProperties(),
            $this->table,
            $this->connection
        ];

        return str_replace($searches, $replaces, $template);
    }

    private function initMysqlConnection()
    {
        $envFile = $this->option('env-file');
        $envFile || $envFile = getcwd() . '/.env';
        file_exists($envFile) && (new Dotenv())->load($envFile);

        $conf = [
            'driver' => 'mysql',
            'host' => getenv('DB_HOST'),
            'port' => getenv('DB_PORT'),
            'username' => getenv('DB_USERNAME'),
            'password' => getenv('DB_PASSWORD'),
            'database' => getenv('DB_DATABASE'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ];
        $manager = new Manager();
        $manager->setAsGlobal();
        $this->connection = 'default';
        $manager->addConnection($conf, $this->connection);
    }

    private function getTableDefinition($table)
    {
        $names = Manager::schema($this->connection)->getColumnListing($table);

        $list = [];
        foreach ($names as $name) {
            $col = Manager::connection($this->connection)->getDoctrineColumn($table, $name);

            $column = new Column();
            $column->name = $col->getName();
            $column->type = $col->getType();
            $column->default = $col->getDefault();
            $column->notNull = $col->getNotnull();
            $column->length = $col->getLength();
            $column->comment = $col->getComment();
            $this->collectColumn($column);
            $list[] = $column->toArray();
        }

//        $this->table(Column::getHeader(), $list);
    }

    /**
     * @var Column[]
     */
    private $columns = [];

    private function collectColumn(Column $column)
    {
        $this->columns[] = $column;
    }

    protected function getProperties(): string
    {
        $map = [
            'datetime' => 'string',
            'date' => 'string',
            'time' => 'string',
            'string' => 'string',
            'bigint' => 'int',
            'integer' => 'int',
            'json' => 'string',
            'boolean' => 'bool',
            'float' => 'float',
            'text' => 'string',
            'decimal' => 'float',
            'blob' => 'string',
        ];

//        print_r($this->columns);die;

        foreach ($this->columns as $column) {
            $property = new Property();
            $property->name = $column->name;

            $type = strtolower($column->type);
            $type = trim($type, '\\');
            if (empty($map[$type])) {
                $this->error("Unknown property type:{$type}");
                die;
            }
            $property->type = $map[$type];
            $property->description = $column->comment;
            $property->default = $column->default;
            $this->addOneProperty($property);
        }

        $this->buildFromProperties(true);
        return $this->getNoteProperties();
    }

    protected function setClassName($className)
    {
        list($namespace, $className) = $this->buildNamespaceAndClass($className);

        $this->namespace = sprintf("infra\\models\\%s%s", $this->package, $namespace);

        $suffix = 'model';
        if (substr_compare(strtolower($className), $suffix, -strlen($suffix)) !== 0) {
            $className = $className . "Model";
        }

        $this->className = $className;
        return $this;
    }
}
