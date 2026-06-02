<?php

namespace Phpify\Console\Commands;

use Phpify\Console\Command;

class MakeModelCommand extends Command
{
    protected string $name = 'make:model';
    protected string $description = 'Create a new model class';

    public function execute(array $args): int
    {
        if (empty($args)) {
            $this->error("Not enough arguments (missing: name).");
            return 1;
        }

        $name = $args[0];
        $path = \Phpify\Foundation\Application::$app->getRootPath() . "/app/Models/$name.php";

        if (file_exists($path)) {
            $this->error("Model already exists!");
            return 1;
        }

        $content = "<?php\n\nnamespace App\Models;\n\nuse Phpify\Database\Model;\n\nclass $name extends Model\n{\n    protected string \$table = '" . strtolower($name) . "s';\n}\n";

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->info("Model created successfully: app/Models/$name.php");

        return 0;
    }
}
