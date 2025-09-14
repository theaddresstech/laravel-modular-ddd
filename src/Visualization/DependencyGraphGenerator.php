<?php

declare(strict_types=1);

namespace TaiCrm\LaravelModularDdd\Visualization;

use TaiCrm\LaravelModularDdd\Contracts\ModuleManagerInterface;
use TaiCrm\LaravelModularDdd\ValueObjects\ModuleInfo;
use Illuminate\Support\Collection;

class DependencyGraphGenerator
{
    public function __construct(
        private ModuleManagerInterface $moduleManager
    ) {}

    public function generateGraph(array $options = []): array
    {
        $modules = $this->moduleManager->list();
        $format = $options['format'] ?? 'json';
        $includeDisabled = $options['include_disabled'] ?? false;

        $filteredModules = $includeDisabled
            ? $modules
            : $modules->filter->isEnabled();

        $graph = [
            'metadata' => $this->generateMetadata($filteredModules),
            'nodes' => $this->generateNodes($filteredModules),
            'edges' => $this->generateEdges($filteredModules),
            'clusters' => $this->identifyClusters($filteredModules),
            'metrics' => $this->calculateGraphMetrics($filteredModules),
        ];

        return match ($format) {
            'dot' => $this->convertToDot($graph),
            'mermaid' => $this->convertToMermaid($graph),
            'cytoscape' => $this->convertToCytoscape($graph),
            'json' => $graph,
            default => $graph,
        };
    }

    public function generateModuleTree(): array
    {
        $modules = $this->moduleManager->list();
        $tree = [];

        foreach ($modules as $module) {
            $this->buildModuleTree($module, $modules, $tree);
        }

        return $this->sortTree($tree);
    }

    public function findCircularDependencies(): array
    {
        $modules = $this->moduleManager->list();
        $visiting = [];
        $visited = [];
        $cycles = [];

        foreach ($modules as $module) {
            if (!isset($visited[$module->name])) {
                $this->detectCycle($module, $modules, $visiting, $visited, $cycles, []);
            }
        }

        return $cycles;
    }

    public function analyzeDependencyImpact(string $moduleName): array
    {
        $modules = $this->moduleManager->list();
        $targetModule = $modules->firstWhere('name', $moduleName);

        if (!$targetModule) {
            throw new \InvalidArgumentException("Module '{$moduleName}' not found");
        }

        return [
            'module' => $moduleName,
            'direct_dependencies' => $this->getDirectDependencies($targetModule, $modules),
            'transitive_dependencies' => $this->getTransitiveDependencies($targetModule, $modules),
            'dependents' => $this->getModuleDependents($targetModule, $modules),
            'impact_radius' => $this->calculateImpactRadius($targetModule, $modules),
            'criticality_score' => $this->calculateCriticalityScore($targetModule, $modules),
        ];
    }

    public function generateInstallationOrder(): array
    {
        $modules = $this->moduleManager->list();
        return $this->topologicalSort($modules);
    }

    private function generateMetadata(Collection $modules): array
    {
        return [
            'generated_at' => now()->toISOString(),
            'total_modules' => $modules->count(),
            'enabled_modules' => $modules->filter->isEnabled()->count(),
            'disabled_modules' => $modules->reject->isEnabled()->count(),
            'total_dependencies' => $modules->sum(fn($m) => count($m->dependencies)),
        ];
    }

    private function generateNodes(Collection $modules): array
    {
        return $modules->map(function (ModuleInfo $module) {
            return [
                'id' => $module->name,
                'label' => $module->name,
                'version' => $module->version,
                'status' => $module->status->value,
                'enabled' => $module->isEnabled(),
                'dependencies_count' => count($module->dependencies),
                'size' => $this->calculateNodeSize($module),
                'color' => $this->getNodeColor($module),
                'shape' => $this->getNodeShape($module),
            ];
        })->values()->toArray();
    }

    private function generateEdges(Collection $modules): array
    {
        $edges = [];

        foreach ($modules as $module) {
            foreach ($module->dependencies as $dependency) {
                $dependencyModule = $modules->firstWhere('name', $dependency['name']);

                $edges[] = [
                    'source' => $module->name,
                    'target' => $dependency['name'],
                    'type' => $dependency['type'] ?? 'required',
                    'constraint' => $dependency['constraint'] ?? '*',
                    'exists' => $dependencyModule !== null,
                    'satisfied' => $dependencyModule ? $this->isDependencySatisfied($dependency, $dependencyModule) : false,
                    'style' => $this->getEdgeStyle($dependency, $dependencyModule),
                ];
            }
        }

        return $edges;
    }

    private function identifyClusters(Collection $modules): array
    {
        $clusters = [];
        $visited = [];

        foreach ($modules as $module) {
            if (isset($visited[$module->name])) {
                continue;
            }

            $cluster = $this->findConnectedComponents($module, $modules, $visited);
            if (count($cluster) > 1) {
                $clusters[] = [
                    'id' => 'cluster_' . count($clusters),
                    'modules' => $cluster,
                    'size' => count($cluster),
                    'interconnection_density' => $this->calculateInterconnectionDensity($cluster, $modules),
                ];
            }
        }

        return $clusters;
    }

    private function calculateGraphMetrics(Collection $modules): array
    {
        $totalNodes = $modules->count();
        $totalEdges = $modules->sum(fn($m) => count($m->dependencies));

        return [
            'nodes' => $totalNodes,
            'edges' => $totalEdges,
            'density' => $totalNodes > 1 ? $totalEdges / ($totalNodes * ($totalNodes - 1)) : 0,
            'average_degree' => $totalNodes > 0 ? ($totalEdges * 2) / $totalNodes : 0,
            'max_depth' => $this->calculateMaxDepth($modules),
            'circular_dependencies' => count($this->findCircularDependencies()),
            'isolated_modules' => $this->countIsolatedModules($modules),
            'hub_modules' => $this->identifyHubModules($modules),
        ];
    }

    private function convertToDot(array $graph): string
    {
        $dot = "digraph ModuleDependencies {\n";
        $dot .= "    rankdir=TB;\n";
        $dot .= "    node [shape=box, style=rounded];\n\n";

        // Add nodes
        foreach ($graph['nodes'] as $node) {
            $color = $node['enabled'] ? 'lightblue' : 'lightgray';
            $dot .= "    \"{$node['id']}\" [label=\"{$node['label']}\\n{$node['version']}\", fillcolor={$color}, style=filled];\n";
        }

        $dot .= "\n";

        // Add edges
        foreach ($graph['edges'] as $edge) {
            $style = $edge['satisfied'] ? 'solid' : 'dashed';
            $color = $edge['satisfied'] ? 'black' : 'red';
            $dot .= "    \"{$edge['source']}\" -> \"{$edge['target']}\" [style={$style}, color={$color}];\n";
        }

        $dot .= "}\n";

        return $dot;
    }

    private function convertToMermaid(array $graph): string
    {
        $mermaid = "graph TD\n";

        // Add nodes with styling
        foreach ($graph['nodes'] as $node) {
            $className = $node['enabled'] ? 'enabled' : 'disabled';
            $mermaid .= "    {$node['id']}[\"{$node['label']}\\n{$node['version']}\"]\n";
            $mermaid .= "    class {$node['id']} {$className}\n";
        }

        $mermaid .= "\n";

        // Add edges
        foreach ($graph['edges'] as $edge) {
            $arrow = $edge['satisfied'] ? '-->' : '-'.'->';
            $mermaid .= "    {$edge['source']} {$arrow} {$edge['target']}\n";
        }

        // Add styling
        $mermaid .= "\n    classDef enabled fill:#lightblue,stroke:#333,stroke-width:2px\n";
        $mermaid .= "    classDef disabled fill:#lightgray,stroke:#333,stroke-width:2px\n";

        return $mermaid;
    }

    private function convertToCytoscape(array $graph): array
    {
        $elements = [];

        // Add nodes
        foreach ($graph['nodes'] as $node) {
            $elements[] = [
                'data' => [
                    'id' => $node['id'],
                    'label' => $node['label'],
                    'version' => $node['version'],
                    'enabled' => $node['enabled'],
                    'dependencies_count' => $node['dependencies_count'],
                ],
                'classes' => $node['enabled'] ? 'enabled' : 'disabled',
            ];
        }

        // Add edges
        foreach ($graph['edges'] as $edge) {
            $elements[] = [
                'data' => [
                    'id' => $edge['source'] . '_' . $edge['target'],
                    'source' => $edge['source'],
                    'target' => $edge['target'],
                    'type' => $edge['type'],
                    'satisfied' => $edge['satisfied'],
                ],
                'classes' => $edge['satisfied'] ? 'satisfied' : 'unsatisfied',
            ];
        }

        return [
            'elements' => $elements,
            'style' => $this->getCytoscapeStyles(),
            'layout' => ['name' => 'dagre', 'rankDir' => 'TB'],
        ];
    }

    private function buildModuleTree(ModuleInfo $module, Collection $allModules, array &$tree, array $visited = []): void
    {
        if (in_array($module->name, $visited)) {
            return; // Prevent infinite recursion
        }

        $visited[] = $module->name;

        $tree[$module->name] = [
            'module' => $module->name,
            'version' => $module->version,
            'status' => $module->status->value,
            'enabled' => $module->isEnabled(),
            'dependencies' => [],
        ];

        foreach ($module->dependencies as $dependency) {
            $depModule = $allModules->firstWhere('name', $dependency['name']);
            if ($depModule) {
                $this->buildModuleTree($depModule, $allModules, $tree[$module->name]['dependencies'], $visited);
            }
        }
    }

    private function detectCycle(
        ModuleInfo $module,
        Collection $modules,
        array &$visiting,
        array &$visited,
        array &$cycles,
        array $path
    ): void {
        $visiting[$module->name] = true;
        $path[] = $module->name;

        foreach ($module->dependencies as $dependency) {
            $depName = $dependency['name'];

            if (isset($visiting[$depName])) {
                // Found a cycle
                $cycleStart = array_search($depName, $path);
                $cycles[] = array_slice($path, $cycleStart);
            } elseif (!isset($visited[$depName])) {
                $depModule = $modules->firstWhere('name', $depName);
                if ($depModule) {
                    $this->detectCycle($depModule, $modules, $visiting, $visited, $cycles, $path);
                }
            }
        }

        unset($visiting[$module->name]);
        $visited[$module->name] = true;
    }

    private function getDirectDependencies(ModuleInfo $module, Collection $modules): array
    {
        $dependencies = [];

        foreach ($module->dependencies as $dependency) {
            $depModule = $modules->firstWhere('name', $dependency['name']);
            $dependencies[] = [
                'name' => $dependency['name'],
                'constraint' => $dependency['constraint'] ?? '*',
                'type' => $dependency['type'] ?? 'required',
                'exists' => $depModule !== null,
                'version' => $depModule?->version,
                'satisfied' => $depModule ? $this->isDependencySatisfied($dependency, $depModule) : false,
            ];
        }

        return $dependencies;
    }

    private function getTransitiveDependencies(ModuleInfo $module, Collection $modules): array
    {
        $transitive = [];
        $visited = [];

        $this->collectTransitiveDependencies($module, $modules, $transitive, $visited);

        return array_values($transitive);
    }

    private function collectTransitiveDependencies(
        ModuleInfo $module,
        Collection $modules,
        array &$transitive,
        array &$visited
    ): void {
        if (isset($visited[$module->name])) {
            return;
        }

        $visited[$module->name] = true;

        foreach ($module->dependencies as $dependency) {
            $depModule = $modules->firstWhere('name', $dependency['name']);
            if ($depModule) {
                $transitive[$dependency['name']] = [
                    'name' => $dependency['name'],
                    'version' => $depModule->version,
                    'depth' => ($transitive[$dependency['name']]['depth'] ?? 0) + 1,
                ];

                $this->collectTransitiveDependencies($depModule, $modules, $transitive, $visited);
            }
        }
    }

    private function getModuleDependents(ModuleInfo $module, Collection $modules): array
    {
        $dependents = [];

        foreach ($modules as $otherModule) {
            if ($otherModule->name === $module->name) {
                continue;
            }

            foreach ($otherModule->dependencies as $dependency) {
                if ($dependency['name'] === $module->name) {
                    $dependents[] = [
                        'name' => $otherModule->name,
                        'version' => $otherModule->version,
                        'constraint' => $dependency['constraint'] ?? '*',
                        'type' => $dependency['type'] ?? 'required',
                    ];
                    break;
                }
            }
        }

        return $dependents;
    }

    private function calculateImpactRadius(ModuleInfo $module, Collection $modules): int
    {
        $affected = [];
        $toProcess = [$module->name];

        while (!empty($toProcess)) {
            $current = array_shift($toProcess);
            if (isset($affected[$current])) {
                continue;
            }

            $affected[$current] = true;

            // Find modules that depend on current
            foreach ($modules as $otherModule) {
                if (!isset($affected[$otherModule->name])) {
                    foreach ($otherModule->dependencies as $dependency) {
                        if ($dependency['name'] === $current) {
                            $toProcess[] = $otherModule->name;
                            break;
                        }
                    }
                }
            }
        }

        return count($affected) - 1; // Exclude the module itself
    }

    private function calculateCriticalityScore(ModuleInfo $module, Collection $modules): float
    {
        $dependents = $this->getModuleDependents($module, $modules);
        $impactRadius = $this->calculateImpactRadius($module, $modules);
        $requiredDependents = array_filter($dependents, fn($d) => $d['type'] === 'required');

        // Score based on number of dependents, required dependents, and impact radius
        return (count($dependents) * 0.4) + (count($requiredDependents) * 0.4) + ($impactRadius * 0.2);
    }

    private function topologicalSort(Collection $modules): array
    {
        $sorted = [];
        $temporary = [];
        $permanent = [];

        foreach ($modules as $module) {
            if (!isset($permanent[$module->name])) {
                $this->topologicalSortVisit($module, $modules, $temporary, $permanent, $sorted);
            }
        }

        return array_reverse($sorted);
    }

    private function topologicalSortVisit(
        ModuleInfo $module,
        Collection $modules,
        array &$temporary,
        array &$permanent,
        array &$sorted
    ): void {
        if (isset($permanent[$module->name])) {
            return;
        }

        if (isset($temporary[$module->name])) {
            throw new \RuntimeException("Circular dependency detected involving module: {$module->name}");
        }

        $temporary[$module->name] = true;

        foreach ($module->dependencies as $dependency) {
            $depModule = $modules->firstWhere('name', $dependency['name']);
            if ($depModule) {
                $this->topologicalSortVisit($depModule, $modules, $temporary, $permanent, $sorted);
            }
        }

        unset($temporary[$module->name]);
        $permanent[$module->name] = true;
        $sorted[] = $module->name;
    }

    private function calculateNodeSize(ModuleInfo $module): string
    {
        $dependencyCount = count($module->dependencies);

        return match (true) {
            $dependencyCount > 10 => 'large',
            $dependencyCount > 5 => 'medium',
            default => 'small',
        };
    }

    private function getNodeColor(ModuleInfo $module): string
    {
        if (!$module->isEnabled()) {
            return 'gray';
        }

        $dependencyCount = count($module->dependencies);

        return match (true) {
            $dependencyCount === 0 => 'green',  // Leaf nodes
            $dependencyCount > 10 => 'red',     // High dependency
            $dependencyCount > 5 => 'orange',   // Medium dependency
            default => 'blue',                  // Normal dependency
        };
    }

    private function getNodeShape(ModuleInfo $module): string
    {
        if (!$module->isEnabled()) {
            return 'square';
        }

        return count($module->dependencies) === 0 ? 'circle' : 'rectangle';
    }

    private function getEdgeStyle(array $dependency, ?ModuleInfo $dependencyModule): array
    {
        return [
            'width' => $dependency['type'] === 'required' ? 2 : 1,
            'style' => $dependencyModule && $this->isDependencySatisfied($dependency, $dependencyModule) ? 'solid' : 'dashed',
            'color' => $dependencyModule ? ($this->isDependencySatisfied($dependency, $dependencyModule) ? 'green' : 'red') : 'gray',
        ];
    }

    private function isDependencySatisfied(array $dependency, ModuleInfo $module): bool
    {
        $constraint = $dependency['constraint'] ?? '*';

        if ($constraint === '*') {
            return true;
        }

        // Simple version comparison - in real implementation, you'd use semantic versioning
        return version_compare($module->version, $constraint, '>=');
    }

    private function findConnectedComponents(ModuleInfo $module, Collection $modules, array &$visited): array
    {
        $component = [$module->name];
        $visited[$module->name] = true;
        $toVisit = [$module];

        while (!empty($toVisit)) {
            $current = array_shift($toVisit);

            // Find dependencies
            foreach ($current->dependencies as $dependency) {
                $depModule = $modules->firstWhere('name', $dependency['name']);
                if ($depModule && !isset($visited[$depModule->name])) {
                    $visited[$depModule->name] = true;
                    $component[] = $depModule->name;
                    $toVisit[] = $depModule;
                }
            }

            // Find dependents
            foreach ($modules as $otherModule) {
                if (!isset($visited[$otherModule->name])) {
                    foreach ($otherModule->dependencies as $dependency) {
                        if ($dependency['name'] === $current->name) {
                            $visited[$otherModule->name] = true;
                            $component[] = $otherModule->name;
                            $toVisit[] = $otherModule;
                            break;
                        }
                    }
                }
            }
        }

        return $component;
    }

    private function calculateInterconnectionDensity(array $cluster, Collection $modules): float
    {
        $clusterModules = $modules->whereIn('name', $cluster);
        $totalPossibleConnections = count($cluster) * (count($cluster) - 1);

        if ($totalPossibleConnections === 0) {
            return 0;
        }

        $actualConnections = 0;
        foreach ($clusterModules as $module) {
            foreach ($module->dependencies as $dependency) {
                if (in_array($dependency['name'], $cluster)) {
                    $actualConnections++;
                }
            }
        }

        return $actualConnections / $totalPossibleConnections;
    }

    private function calculateMaxDepth(Collection $modules): int
    {
        $maxDepth = 0;

        foreach ($modules as $module) {
            $depth = $this->getModuleDepth($module, $modules);
            $maxDepth = max($maxDepth, $depth);
        }

        return $maxDepth;
    }

    private function getModuleDepth(ModuleInfo $module, Collection $modules, array $visited = []): int
    {
        if (in_array($module->name, $visited)) {
            return 0; // Prevent infinite recursion
        }

        $visited[] = $module->name;
        $maxDependencyDepth = 0;

        foreach ($module->dependencies as $dependency) {
            $depModule = $modules->firstWhere('name', $dependency['name']);
            if ($depModule) {
                $depth = $this->getModuleDepth($depModule, $modules, $visited);
                $maxDependencyDepth = max($maxDependencyDepth, $depth);
            }
        }

        return $maxDependencyDepth + 1;
    }

    private function countIsolatedModules(Collection $modules): int
    {
        $isolated = 0;

        foreach ($modules as $module) {
            $hasDependencies = !empty($module->dependencies);
            $hasDependents = $modules->contains(function ($other) use ($module) {
                return collect($other->dependencies)->contains('name', $module->name);
            });

            if (!$hasDependencies && !$hasDependents) {
                $isolated++;
            }
        }

        return $isolated;
    }

    private function identifyHubModules(Collection $modules, int $threshold = 5): array
    {
        $hubs = [];

        foreach ($modules as $module) {
            $dependents = $this->getModuleDependents($module, $modules);
            if (count($dependents) >= $threshold) {
                $hubs[] = [
                    'name' => $module->name,
                    'dependent_count' => count($dependents),
                    'criticality_score' => $this->calculateCriticalityScore($module, $modules),
                ];
            }
        }

        return $hubs;
    }

    private function sortTree(array $tree): array
    {
        uksort($tree, 'strcmp');

        foreach ($tree as &$node) {
            if (!empty($node['dependencies'])) {
                $node['dependencies'] = $this->sortTree($node['dependencies']);
            }
        }

        return $tree;
    }

    private function getCytoscapeStyles(): array
    {
        return [
            [
                'selector' => 'node',
                'style' => [
                    'content' => 'data(label)',
                    'text-valign' => 'center',
                    'text-halign' => 'center',
                    'background-color' => '#6FB1FC',
                    'border-color' => '#4A90E2',
                    'border-width' => 2,
                    'color' => '#000',
                ],
            ],
            [
                'selector' => 'node.enabled',
                'style' => [
                    'background-color' => '#6FB1FC',
                    'border-color' => '#4A90E2',
                ],
            ],
            [
                'selector' => 'node.disabled',
                'style' => [
                    'background-color' => '#D3D3D3',
                    'border-color' => '#A9A9A9',
                    'color' => '#666',
                ],
            ],
            [
                'selector' => 'edge',
                'style' => [
                    'width' => 2,
                    'line-color' => '#ccc',
                    'target-arrow-color' => '#ccc',
                    'target-arrow-shape' => 'triangle',
                    'curve-style' => 'bezier',
                ],
            ],
            [
                'selector' => 'edge.satisfied',
                'style' => [
                    'line-color' => '#4CAF50',
                    'target-arrow-color' => '#4CAF50',
                ],
            ],
            [
                'selector' => 'edge.unsatisfied',
                'style' => [
                    'line-color' => '#F44336',
                    'target-arrow-color' => '#F44336',
                    'line-style' => 'dashed',
                ],
            ],
        ];
    }
}