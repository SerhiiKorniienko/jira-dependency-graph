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
}
