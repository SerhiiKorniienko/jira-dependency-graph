<?php

namespace App\Services;

use Graphp\GraphViz\GraphViz;
use Graphp\Graph\Graph;
use App\Interfaces\GraphImageGenerator;

class GraphGenerator implements GraphImageGenerator
{
    private array $vertices = [];

    public function generateGraph(array $issues): Graph
    {
        $graph = new Graph();

        foreach ($issues as $issue) {
            $this->extractDependencies($issue, $graph);
        }

        return $graph;
    }

    public function createImageFile(Graph $graph): string
    {
        $graphviz = new GraphViz();
        $graphviz->setFormat('png');
        return $graphviz->createImageFile($graph);
    }

    private function formatNode($issue): string
    {
        $issueTitle = $issue['fields']['summary'];
        $issueStatus = $issue['fields']['status']['name'] ?? 'No Status';
        $issueEstimation = isset($issue['fields']['timeoriginalestimate']) ? round($issue['fields']['timeoriginalestimate'] / 28800, 1) . 'd' : 'No Estimation';
        $issueLabels = isset($issue['fields']['labels']) ? implode(', ', $issue['fields']['labels']) : 'No Labels';
        $dueDate = $issue['fields']['duedate'] ?? 'No Due Date';
        $loggedTime = $issue['fields']['timetracking']['timeSpent'] ?? 'No Logged Time';
        $assignee = $issue['fields']['assignee']['displayName'] ?? 'Unassigned';
        $sprint = isset($issue['fields']['customfield_10017']) ? implode(', ', array_reverse(array_column($issue['fields']['customfield_10017'], 'name'))) : 'No Sprint';

        $statusColor = 'orange';
        if (in_array($issueStatus, ['Done', 'Resolved'])) {
            $statusColor = 'green';
        } elseif ($issueStatus === 'To Do') {
            $statusColor = 'blue';
        } elseif ($issueStatus === 'Blocked') {
            $statusColor = 'black';
        }

        return "
<table border='0' cellborder='1' cellspacing='0'>
    <tr><td><b>$issueTitle</b></td></tr>
    <tr><td align='left'>Status: <font color='$statusColor'>$issueStatus</font></td></tr>
    <tr><td align='left'>Estimation: $issueEstimation</td></tr>
    <tr><td align='left'>Labels: $issueLabels</td></tr>
    <tr><td align='left'>Due Date: $dueDate</td></tr>
    <tr><td align='left'>Assignee: $assignee</td></tr>
    <tr><td align='left'>Sprint: $sprint</td></tr>
</table>";
    }

    private function getOrCreateVertex(Graph $graph, $key, $label)
    {
        if (!isset($this->vertices[$key])) {
            $vertex = $graph->createVertex();
            $vertex->setAttribute('id', $key);
            $vertex->setAttribute('graphviz.shape', 'none');
            $vertex->setAttribute('graphviz.label_html', $label);
            $this->vertices[$key] = $vertex;
        }
        return $this->vertices[$key];
    }

    private function extractDependencies(array $issue, Graph $graph): void
    {
        $issueKey = $issue['key'];
        $label = $this->formatNode($issue);
        $vertex = $this->getOrCreateVertex($graph, $issueKey, $label);

        if (isset($issue['fields']['issuelinks'])) {
            foreach ($issue['fields']['issuelinks'] as $link) {
                if (isset($link['outwardIssue'])) {
                    $dependencyKey = $link['outwardIssue']['key'];
                    $dependencyLabel = $this->formatNode($link['outwardIssue']);
                    $dependencyVertex = $this->getOrCreateVertex($graph, $dependencyKey, $dependencyLabel);
                    $edge = $graph->createEdgeDirected($vertex, $dependencyVertex);
                    $edge->setAttribute('graphviz.label', 'blocks');
                    $edge->setAttribute('graphviz.color', 'red');
                    $edge->setAttribute('graphviz.style', 'solid');
                }
            }
        }
    }

    public function calculateTaskOrder(array $issues): array
    {
        $taskOrder = [];
        $dependencies = [];

        foreach ($issues as $issue) {
            $issueKey = $issue['key'];
            if (!isset($dependencies[$issueKey])) {
                $dependencies[$issueKey] = [];
            }

            if (isset($issue['fields']['issuelinks'])) {
                foreach ($issue['fields']['issuelinks'] as $link) {
                    if (isset($link['outwardIssue'])) {
                        $dependencyKey = $link['outwardIssue']['key'];
                        $dependencies[$issueKey][] = $dependencyKey;
                    }
                }
            }
        }

        $visited = [];
        foreach ($issues as $issue) {
            $this->topologicalSort($issue['key'], $dependencies, $visited, $taskOrder);
        }

        $formattedTasks = [];
        foreach ($taskOrder as $task) {
            $issue = array_filter($issues, fn($issue) => $issue['key'] === $task);
            $formattedTasks[] = $task . ' - ' . ($issue[array_key_first($issue)]['fields']['summary'] ?? '') . ' (Blocks: ' . count($dependencies[$task] ?? 0) . ')';
        }

        return array_reverse($formattedTasks);
    }

    private function topologicalSort($node, &$dependencies, &$visited, &$taskOrder): void
    {
        if (isset($visited[$node])) {
            return;
        }

        $visited[$node] = true;

        foreach ($dependencies[$node] as $neighbor) {
            $this->topologicalSort($neighbor, $dependencies, $visited, $taskOrder);
        }

        $taskOrder[] = $node;
    }

    public function calculateProgress(array $issues): array
    {
        $statusCounts = [
            'done' => 0,
            'inProgress' => 0,
            'toDo' => 0,
        ];
        $estimationSums = [
            'done' => 0,
            'inProgress' => 0,
            'toDo' => 0,
        ];

        foreach ($issues as $issue) {
            $status = strtolower($issue['fields']['status']['name']);
            $estimation = isset($issue['fields']['timeoriginalestimate']) ? round($issue['fields']['timeoriginalestimate'] / 28800, 1) : 0;

            if (in_array($status, ['done', 'resolved'])) {
                $statusCounts['done']++;
                $estimationSums['done'] += $estimation;
            } elseif ($status === 'in progress') {
                $statusCounts['inProgress']++;
                $estimationSums['inProgress'] += $estimation;
            } elseif ($status === 'to do') {
                $statusCounts['toDo']++;
                $estimationSums['toDo'] += $estimation;
            }
        }

        $totalTasks = array_sum($statusCounts);
        $totalEstimation = array_sum($estimationSums);

        return [
            'statusCounts' => $statusCounts,
            'estimationSums' => $estimationSums,
            'totalTasks' => $totalTasks,
            'totalEstimation' => $totalEstimation,
            'progress' => [
                'done' => $statusCounts['done'] / $totalTasks * 100,
                'inProgress' => $statusCounts['inProgress'] / $totalTasks * 100,
                'toDo' => $statusCounts['toDo'] / $totalTasks * 100,
            ]
        ];
    }
}
