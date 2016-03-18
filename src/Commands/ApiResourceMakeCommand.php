<?php
namespace Smiarowski\Generators\Commands;

use Illuminate\Console\AppNamespaceDetectorTrait;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use ICanBoogie\Inflector;
use Smiarowski\Generators\Migrations\SchemaParser;
use Smiarowski\Generators\Migrations\SyntaxBuilder;
use Smiarowski\Generators\Migrations\ValidationBuilder;

class ApiResourceMakeCommand extends Command
{
    use AppNamespaceDetectorTrait;
    
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'make:api-resource {name} {--schema=} {--softdeletes}';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new REST API resource: controller, model, request, migration and route';

    /**
     * Name of the table for migration
     * @var string
     */
    protected $tableName;
    protected $varModelName;
    protected $varModelNamePlural;
    protected $modelName;
    protected $fileTypes = ['migration', 'controller', 'model', 'request'];
    protected $createdFiles = [];
    protected $validationRules = [];

    /**
     * @var Composer
     */
    protected $composer;
    /**
     * @var Filesystem
     */
    protected $files;
    /**
     * @var Inflector
     */
    protected $inflector;
    
    /**
     * Create a new command instance.
     *
     * @param \Illuminate\Filesystem\Filesystem $files
     * @param \Illuminate\Support\Composer $composer
     */
    public function __construct(Filesystem $files, Composer $composer) {
        parent::__construct();
        
        $this->files = $files;
        $this->composer = $composer;
        $this->inflector = Inflector::get('en');
    }
    
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire() {
        $this->setAttribs();
        foreach ($this->fileTypes as $type) {
            $this->createFile($type);
        }
        $this->addRoute();
        $this->composer->dumpAutoloads();
    }

    /**
     * Set up of attributes
     */
    protected function setAttribs() {
        $name = strtolower($this->argument('name'));
        
        $this->tableName = $this->inflector->pluralize($this->inflector->underscore($name));
        $this->varModelName = $this->inflector->singularize($this->inflector->camelize($name, Inflector::DOWNCASE_FIRST_LETTER));
        $this->varModelNamePlural = $this->inflector->pluralize($this->varModelName);
        $this->modelName = ucfirst($this->varModelName);
    }

    /**
     * Creates generated file of specified type
     * @param string $type
     */
    protected function createFile($type = 'migration') {
        if ($this->files->exists($path = $this->getPath($type))) {
            foreach ($this->createdFiles as $filePath) {
                $this->files->delete($filePath);
            }
            
            $this->error($path . ' already exists!');
            die();
        }
        
        $this->makeDirectory($path);
        $this->files->put($path, $this->compileStub($type));
        $this->createdFiles[] = $path;
        $this->info(ucfirst($type) . ' created successfully.');
    }
    
    /**
     * Add routes for resource
     */
    protected function addRoute() {
        $line .= sprintf('$app->get(\'%s\', \'%sController@index\');%s', $this->varModelName, $this->modelName, PHP_EOL);
        $line .= sprintf('$app->get(\'%s/{%s}\', \'%sController@show\');%s', $this->varModelName, $this->varModelName, $this->modelName, PHP_EOL);
        $line .= sprintf('$app->post(\'%s\', \'%sController@store\');%s', $this->varModelName, $this->modelName, PHP_EOL);
        $line .= sprintf('$app->put(\'%s/{%s}\', \'%sController@store\');%s', $this->varModelName, $this->varModelName, $this->modelName, PHP_EOL);
        $line .= sprintf('$app->delete(\'%s/{%s}\', \'%sController@destroy\');%s', $this->varModelName, $this->varModelName, $this->modelName, PHP_EOL);
        $this->files->append($this->getPath('route'), $line);
    }
    
    /**
     * Build the directory for the class if necessary.
     *
     * @param  string $path
     * @return string
     */
    protected function makeDirectory($path) {
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0755, true, true);
        }
    }
    
    /**
     * Get the path to files being created.
     *
     * @param  string $type migration|controller|model|request|route
     * @return string
     */
    protected function getPath($type = 'migration') {
        switch ($type) {
            case 'migration':
                return sprintf('%s/database/migrations/%s_create_%s_table.php', base_path(), date('Y_m_d_His'), $this->tableName);
            case 'controller':
                return sprintf('%s/Http/Controllers/%sController.php', $this->app_path(), $this->modelName);
            case 'model':
                return sprintf('%s/%s.php', $this->app_path(), $this->modelName);
            case 'request':
                return sprintf('%s/Http/Requests/%sRequest.php', $this->app_path(), $this->modelName);
            case 'route':
                return sprintf('%s/Http/routes.php', $this->app_path());
        }
        return '';
    }

    protected function app_path() {
        if (function_exists('app_path')) return app_path();

        return base_path() . '/app';
    }
    
    /**
     * Compile the migration stub.
     * @param  string $stubName Filename of the stub: migration|controller|model|request
     * @return string
     */
    protected function compileStub($stubName = 'migration') {
        $stub = $this->files->get(sprintf('%s/../stubs/%s.stub', __DIR__, $stubName));
        $this->replaceInStub($stub);
        if ($stubName === 'migration') {
            $this->replaceSchema($stub);
        }
        if ($stubName === 'request') {
            $this->replaceValidationStub($stub);
        }
        
        return $stub;
    }
    
    /**
     * Replace placeholders with proper values
     * @param  string $stub
     * @return void
     */
    protected function replaceInStub(&$stub) {
        $stub = str_replace('{{app_name}}', str_replace('\\', '', $this->getAppNamespace()), $stub);
        $stub = str_replace('{{model_name}}', $this->modelName, $stub);
        $stub = str_replace('{{var_model_name}}', $this->varModelName, $stub);
        $stub = str_replace('{{var_model_name_plural}}', $this->varModelNamePlural, $stub);
        $stub = str_replace('{{table}}', $this->tableName, $stub);
        $stub = str_replace('{{migration_class}}', sprintf('Create%sTable', $this->inflector->camelize($this->tableName)), $stub);
        $stub = str_replace('{{soft_deletes}}', $this->option('softdeletes') ? '$table->softDeletes();' : '', $stub);
        $stub = str_replace('{{guarded_soft_deletes}}', $this->option('softdeletes') ? ', \'deleted_at\'' : '', $stub);
    }

    /**
     * Replace the schema for the stub.
     *
     * @param  string $stub
     * @return $this
     */
    protected function replaceSchema(&$stub) {
        if ($schema = $this->option('schema')) {
            $schema = (new SchemaParser)->parse($schema);
        }
        $this->validationRules = (new ValidationBuilder($this->tableName, $this->option('softdeletes')))->build($schema);
        $stub = (new SyntaxBuilder)->create($schema, $stub);
        
        return $this;
    }

    /**
     * Replace validation rules in request.stub
     * @param  string $stub
     * @return void
     */
    protected function replaceValidationStub(&$stub)
    {
        $rules = '';
        foreach ($this->validationRules as $column => $rule) {
            $rules .= sprintf('%s\'%s\' => \'%s\',%s', str_repeat(' ', 12), $column, implode('|', $rule), PHP_EOL);
        }
        $stub = str_replace('{{validation_rules}}', $rules, $stub);
    }
}
