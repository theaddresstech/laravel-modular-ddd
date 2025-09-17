<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;
use TaiCrm\LaravelModularDdd\Visualization\DependencyGraphGenerator;

class ModuleVisualizationCommand extends Command
{
    protected $signature = 'module:visualize
                            {--format=json : Output format (json, dot, mermaid, cytoscape)}
                            {--output= : Output file path}
                            {--include-disabled : Include disabled modules in visualization}
                            {--show-tree : Show dependency tree instead of graph}
                            {--analyze= : Analyze specific module dependencies}
                            {--find-cycles : Find circular dependencies}
                            {--installation-order : Show recommended installation order}
                            {--export-html : Export interactive HTML visualization}
                            {--serve : Start local server for interactive visualization}';
    protected $description = 'Generate visualizations of module dependencies and relationships';

    public function __construct(
        private DependencyGraphGenerator $generator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('find-cycles')) {
            return $this->findCircularDependencies();
        }

        if ($this->option('installation-order')) {
            return $this->showInstallationOrder();
        }

        if ($analyze = $this->option('analyze')) {
            return $this->analyzeModule($analyze);
        }

        if ($this->option('show-tree')) {
            return $this->showDependencyTree();
        }

        if ($this->option('export-html')) {
            return $this->exportHtmlVisualization();
        }

        if ($this->option('serve')) {
            return $this->serveVisualization();
        }

        return $this->generateVisualization();
    }

    private function generateVisualization(): int
    {
        $format = $this->option('format');
        $includeDisabled = $this->option('include-disabled');
        $outputFile = $this->option('output');

        $this->info("Generating dependency visualization in {$format} format...");

        $options = [
            'format' => $format,
            'include_disabled' => $includeDisabled,
        ];

        $visualization = $this->generator->generateGraph($options);

        if ($outputFile) {
            $this->saveToFile($visualization, $outputFile, $format);
        } else {
            $this->displayVisualization($visualization, $format);
        }

        return 0;
    }

    private function showDependencyTree(): int
    {
        $this->info('Module Dependency Tree:');
        $this->line('');

        $tree = $this->generator->generateModuleTree();

        if (empty($tree)) {
            $this->warn('No modules found or no dependencies detected.');

            return 0;
        }

        $this->renderTree($tree);

        if ($outputFile = $this->option('output')) {
            file_put_contents($outputFile, json_encode($tree, JSON_PRETTY_PRINT));
            $this->info("Dependency tree exported to: {$outputFile}");
        }

        return 0;
    }

    private function findCircularDependencies(): int
    {
        $this->info('Analyzing for circular dependencies...');
        $this->line('');

        $cycles = $this->generator->findCircularDependencies();

        if (empty($cycles)) {
            $this->info('✓ No circular dependencies found!');

            return 0;
        }

        $this->error('⚠ Circular dependencies detected:');
        $this->line('');

        foreach ($cycles as $i => $cycle) {
            $this->line(sprintf(
                '<error>Cycle %d:</error> %s → %s',
                $i + 1,
                implode(' → ', $cycle),
                $cycle[0], // Complete the cycle
            ));
        }

        $this->line('');
        $this->warn('Circular dependencies can cause installation and runtime issues.');
        $this->line('Consider restructuring these modules to remove cyclic dependencies.');

        if ($outputFile = $this->option('output')) {
            file_put_contents($outputFile, json_encode($cycles, JSON_PRETTY_PRINT));
            $this->info("Circular dependencies analysis exported to: {$outputFile}");
        }

        return 1; // Return error code to indicate issues found
    }

    private function showInstallationOrder(): int
    {
        $this->info('Calculating recommended installation order...');
        $this->line('');

        try {
            $order = $this->generator->generateInstallationOrder();

            if (empty($order)) {
                $this->warn('No modules found.');

                return 0;
            }

            $this->info('Recommended installation order:');
            $this->line('');

            foreach ($order as $i => $moduleName) {
                $this->line(sprintf(
                    '<comment>%2d.</comment> %s',
                    $i + 1,
                    $moduleName,
                ));
            }

            $this->line('');
            $this->info('💡 Install modules in this order to satisfy all dependencies.');

            if ($outputFile = $this->option('output')) {
                file_put_contents($outputFile, json_encode($order, JSON_PRETTY_PRINT));
                $this->info("Installation order exported to: {$outputFile}");
            }
        } catch (RuntimeException $e) {
            $this->error('Cannot determine installation order: ' . $e->getMessage());
            $this->line('This usually indicates circular dependencies.');
            $this->line('Run with --find-cycles to identify problematic dependencies.');

            return 1;
        }

        return 0;
    }

    private function analyzeModule(string $moduleName): int
    {
        $this->info("Analyzing dependencies for module: {$moduleName}");
        $this->line('');

        try {
            $analysis = $this->generator->analyzeDependencyImpact($moduleName);

            // Direct dependencies
            $this->info('Direct Dependencies:');
            if (empty($analysis['direct_dependencies'])) {
                $this->line('  None');
            } else {
                foreach ($analysis['direct_dependencies'] as $dep) {
                    $status = $dep['satisfied'] ? '✓' : '✗';
                    $color = $dep['satisfied'] ? 'info' : 'error';
                    $this->line("  <{$color}>{$status}</> {$dep['name']} ({$dep['constraint']})");
                }
            }

            $this->line('');

            // Transitive dependencies
            $this->info('Transitive Dependencies:');
            if (empty($analysis['transitive_dependencies'])) {
                $this->line('  None');
            } else {
                foreach ($analysis['transitive_dependencies'] as $dep) {
                    $this->line("  → {$dep['name']} (depth: {$dep['depth']})");
                }
            }

            $this->line('');

            // Dependents
            $this->info('Modules depending on this:');
            if (empty($analysis['dependents'])) {
                $this->line('  None');
            } else {
                foreach ($analysis['dependents'] as $dependent) {
                    $type = $dependent['type'] === 'required' ? '(required)' : '(optional)';
                    $this->line("  ← {$dependent['name']} {$type}");
                }
            }

            $this->line('');

            // Impact metrics
            $this->info('Impact Analysis:');
            $this->displayKeyValue([
                'Impact Radius' => $analysis['impact_radius'] . ' modules',
                'Criticality Score' => number_format($analysis['criticality_score'], 2),
                'Total Dependencies' => count($analysis['direct_dependencies']),
                'Total Dependents' => count($analysis['dependents']),
            ]);

            if ($outputFile = $this->option('output')) {
                file_put_contents($outputFile, json_encode($analysis, JSON_PRETTY_PRINT));
                $this->info("Module analysis exported to: {$outputFile}");
            }
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return 1;
        }

        return 0;
    }

    private function exportHtmlVisualization(): int
    {
        $outputFile = $this->option('output') ?: 'module-dependencies.html';

        $this->info('Generating interactive HTML visualization...');

        $graph = $this->generator->generateGraph(['format' => 'cytoscape']);
        $html = $this->generateHtmlVisualization($graph);

        file_put_contents($outputFile, $html);

        $this->info("Interactive visualization exported to: {$outputFile}");
        $this->line('Open this file in your browser to explore the dependency graph.');

        return 0;
    }

    private function serveVisualization(): int
    {
        $port = 8080;
        $host = '127.0.0.1';

        $this->info('Starting visualization server...');
        $this->line("Server will be available at: http://{$host}:{$port}");
        $this->line('Press Ctrl+C to stop the server');

        $graph = $this->generator->generateGraph(['format' => 'cytoscape']);
        $html = $this->generateHtmlVisualization($graph);

        // Simple PHP server implementation
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($socket, $host, $port);
        socket_listen($socket);

        while (true) {
            $client = socket_accept($socket);

            $request = socket_read($client, 1024);
            $response = "HTTP/1.1 200 OK\r\n";
            $response .= "Content-Type: text/html\r\n";
            $response .= 'Content-Length: ' . strlen($html) . "\r\n";
            $response .= "Connection: close\r\n\r\n";
            $response .= $html;

            socket_write($client, $response);
            socket_close($client);
        }

        socket_close($socket);

        return 0;
    }

    private function displayVisualization($visualization, string $format): void
    {
        switch ($format) {
            case 'json':
                $this->line(json_encode($visualization, JSON_PRETTY_PRINT));

                break;

            case 'dot':
            case 'mermaid':
                $this->line($visualization);

                break;

            case 'cytoscape':
                $this->info('Cytoscape.js format generated. Use --output to save or --export-html for interactive view.');
                $this->line(json_encode($visualization, JSON_PRETTY_PRINT));

                break;

            default:
                $this->displayGraphSummary($visualization);
        }
    }

    private function displayGraphSummary(array $graph): void
    {
        $this->info('Dependency Graph Summary:');
        $this->line('');

        $metadata = $graph['metadata'];
        $this->displayKeyValue([
            'Total Modules' => $metadata['total_modules'],
            'Enabled Modules' => $metadata['enabled_modules'],
            'Disabled Modules' => $metadata['disabled_modules'],
            'Total Dependencies' => $metadata['total_dependencies'],
            'Generated At' => $metadata['generated_at'],
        ]);

        $this->line('');
        $this->info('Graph Metrics:');
        $metrics = $graph['metrics'];
        $this->displayKeyValue([
            'Nodes' => $metrics['nodes'],
            'Edges' => $metrics['edges'],
            'Graph Density' => number_format($metrics['density'], 4),
            'Average Degree' => number_format($metrics['average_degree'], 2),
            'Max Depth' => $metrics['max_depth'],
            'Circular Dependencies' => $metrics['circular_dependencies'],
            'Isolated Modules' => $metrics['isolated_modules'],
        ]);

        if (!empty($metrics['hub_modules'])) {
            $this->line('');
            $this->info('Hub Modules (High Impact):');
            foreach ($metrics['hub_modules'] as $hub) {
                $this->line("  • {$hub['name']} ({$hub['dependent_count']} dependents, score: " . number_format($hub['criticality_score'], 2) . ')');
            }
        }

        if (!empty($graph['clusters'])) {
            $this->line('');
            $this->info('Module Clusters:');
            foreach ($graph['clusters'] as $cluster) {
                $this->line("  • Cluster {$cluster['id']}: {$cluster['size']} modules (density: " . number_format($cluster['interconnection_density'], 2) . ')');
                $this->line('    Modules: ' . implode(', ', $cluster['modules']));
            }
        }
    }

    private function renderTree(array $tree, int $depth = 0): void
    {
        foreach ($tree as $node) {
            $indent = str_repeat('  ', $depth);
            $status = $node['enabled'] ? '✓' : '✗';
            $color = $node['enabled'] ? 'info' : 'comment';

            $this->line("{$indent}<{$color}>{$status}</> {$node['module']} ({$node['version']})");

            if (!empty($node['dependencies'])) {
                $this->renderTree($node['dependencies'], $depth + 1);
            }
        }
    }

    private function displayKeyValue(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->line(sprintf('  <comment>%s:</comment> %s', $key, $value));
        }
    }

    private function saveToFile($data, string $filename, string $format): void
    {
        $content = match ($format) {
            'json' => json_encode($data, JSON_PRETTY_PRINT),
            'dot', 'mermaid' => $data,
            'cytoscape' => json_encode($data, JSON_PRETTY_PRINT),
            default => json_encode($data, JSON_PRETTY_PRINT),
        };

        file_put_contents($filename, $content);
        $this->info("Visualization saved to: {$filename}");
    }

    private function generateHtmlVisualization(array $graph): string
    {
        $elementsJson = json_encode($graph['elements']);
        $stylesJson = json_encode($graph['style']);
        $layoutJson = json_encode($graph['layout']);

        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <title>Module Dependencies Visualization</title>
                <script src="https://unpkg.com/cytoscape@3.19.0/dist/cytoscape.min.js"></script>
                <script src="https://unpkg.com/dagre@0.8.5/dist/dagre.min.js"></script>
                <script src="https://unpkg.com/cytoscape-dagre@2.3.2/cytoscape-dagre.js"></script>
                <style>
                    body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                    #cy { width: 100%; height: 80vh; border: 1px solid #ccc; }
                    .controls { margin: 10px 0; }
                    .controls button { margin: 0 5px; padding: 5px 10px; }
                    .info { background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 4px; }
                </style>
            </head>
            <body>
                <h1>Module Dependencies Visualization</h1>

                <div class="info">
                    <strong>Instructions:</strong>
                    <ul>
                        <li>Green nodes: Modules with satisfied dependencies</li>
                        <li>Red nodes: Modules with unsatisfied dependencies</li>
                        <li>Gray nodes: Disabled modules</li>
                        <li>Solid edges: Satisfied dependencies</li>
                        <li>Dashed edges: Unsatisfied dependencies</li>
                    </ul>
                </div>

                <div class="controls">
                    <button onclick="cy.fit()">Fit to Screen</button>
                    <button onclick="cy.center()">Center</button>
                    <button onclick="resetLayout()">Reset Layout</button>
                    <button onclick="toggleLabels()">Toggle Labels</button>
                </div>

                <div id="cy"></div>

                <script>
                    cytoscape.use(cytoscapeDagre);

                    var cy = cytoscape({
                        container: document.getElementById('cy'),
                        elements: {$elementsJson},
                        style: {$stylesJson},
                        layout: {$layoutJson}
                    });

                    // Event handlers
                    cy.on('tap', 'node', function(evt) {
                        var node = evt.target;
                        console.log('Clicked node:', node.data());

                        // Highlight connected edges
                        var connectedEdges = node.connectedEdges();
                        cy.elements().removeClass('highlighted');
                        node.addClass('highlighted');
                        connectedEdges.addClass('highlighted');
                    });

                    cy.on('tap', function(evt) {
                        if (evt.target === cy) {
                            cy.elements().removeClass('highlighted');
                        }
                    });

                    // Helper functions
                    function resetLayout() {
                        cy.layout({$layoutJson}).run();
                    }

                    var labelsVisible = true;
                    function toggleLabels() {
                        if (labelsVisible) {
                            cy.style().selector('node').style('content', '').update();
                            labelsVisible = false;
                        } else {
                            cy.style().selector('node').style('content', 'data(label)').update();
                            labelsVisible = true;
                        }
                    }
                </script>
            </body>
            </html>
            HTML;
    }
}
