<?php

namespace Phpify\Console\Commands;

use Phpify\Console\Command;

class MakeControllerCommand extends Command
{
    protected string $name = 'make:controller';
    protected string $description = 'Create a new controller class';

    public function execute(array $args): int
    {
        if (empty($args)) {
            $this->error("Not enough arguments (missing: name).");
            return 1;
        }

        $name = $args[0];
        if (!str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        $path = \Phpify\Foundation\Application::$app->getRootPath() . "/app/Controllers/$name.php";

        if (file_exists($path)) {
            $this->error("Controller already exists!");
            return 1;
        }

        $content = "<?php\n\nnamespace App\Controllers;\n\nuse Phpify\Controller\BaseController;\nuse Phpify\Http\Request;\n\nclass $name extends BaseController\n{\n    public function index(Request \$request)\n    {\n        // return view('index');\n    }\n}\n";

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);
        $this->info("Controller created successfully: app/Controllers/$name.php");

        return 0;
    }
}
