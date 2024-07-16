<?php

namespace App\Services;

use App\Interfaces\TrackingSystemClient;
use App\Interfaces\GraphImageGenerator;

class JiraGraphService
{
    private TrackingSystemClient $jiraClient;
    private GraphImageGenerator $graphGenerator;

    public function __construct(TrackingSystemClient $jiraClient, GraphImageGenerator $graphGenerator)
    {
        $this->jiraClient = $jiraClient;
        $this->graphGenerator = $graphGenerator;
    }

    public function generateGraphImage(string $issueKey): string
    {
        $issues = $this->jiraClient->getIssueData($issueKey);
        $graph = $this->graphGenerator->generateGraph($issues);
        return $this->graphGenerator->createImageFile($graph);
    }
}
