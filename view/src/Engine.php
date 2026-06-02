<?php

namespace Nexion\View;

class Engine
{
    protected string $viewDir;
    protected string $cacheDir;
    protected array $sections = [];
    protected array $sectionStack = [];
    protected ?string $layout = null;

    public function __construct(string $viewDir, string $cacheDir)
    {
        $this->viewDir = rtrim($viewDir, '/') . '/';
        $this->cacheDir = rtrim($cacheDir, '/') . '/';

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    public function render(string $view, array $data = []): string
    {
        $viewPath = $this->viewDir . str_replace('.', '/', $view) . '.php';
        if (!file_exists($viewPath)) {
            throw new \Exception("View [$view] not found.");
        }

        $content = file_get_contents($viewPath);
        $compiled = $this->compile($content);

        extract($data);
        ob_start();
        
        $tempFile = $this->cacheDir . md5($view) . '.php';
        file_put_contents($tempFile, $compiled);
        
        include $tempFile;
        
        $rendered = ob_get_clean();

        // Handle layouts
        if ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null; // Reset for subsequent renders
            
            if (!isset($this->sections['content'])) {
                $this->sections['content'] = $rendered;
            }
            return $this->render($layout, $data);
        }

        return $rendered;
    }

    public function startSection(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function endSection(): void
    {
        if (empty($this->sectionStack)) {
            return;
        }
        $name = array_pop($this->sectionStack);
        $this->sections[$name] = ob_get_clean();
    }

    public function yieldSection(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    protected function compile(string $content): string
    {
        // Replace {{ $var }} with htmlspecialchars echo
        $content = preg_replace('/\{\{\s*(.*?)\s*\}\}/', '<?php echo htmlspecialchars($1); ?>', $content);

        // Replace @extends
        $content = preg_replace('/@extends\(\'(.*?)\'\)/', '<?php $this->layout = \'$1\'; ?>', $content);

        // Replace @section
        $content = preg_replace('/@section\(\'(.*?)\'\)/', '<?php $this->startSection(\'$1\'); ?>', $content);

        // Replace @endsection
        $content = preg_replace('/@endsection/', '<?php $this->endSection(); ?>', $content);

        // Replace @yield
        $content = preg_replace('/@yield\(\'(.*?)\'\)/', '<?php echo $this->yieldSection(\'$1\'); ?>', $content);

        // Replace @if(condition)
        $content = preg_replace('/@if\(((?:[^()]*|\([^()]*\))*)\)/', '<?php if($1): ?>', $content);
        $content = preg_replace('/@else/', '<?php else: ?>', $content);
        $content = preg_replace('/@endif/', '<?php endif; ?>', $content);

        // Replace @foreach
        $content = preg_replace('/@foreach\(((?:[^()]*|\([^()]*\))*)\)/', '<?php foreach($1): ?>', $content);
        $content = preg_replace('/@endforeach/', '<?php endforeach; ?>', $content);

        return $content;
    }
}
