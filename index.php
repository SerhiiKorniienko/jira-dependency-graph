<?php

require 'vendor/autoload.php';

use App\Services\JiraClient;
use App\Services\GraphGenerator;
use App\Services\JiraGraphService;
use App\Utilities\DotenvLoader;
use GuzzleHttp\Client;

DotenvLoader::load(__DIR__);

$httpClient = new Client(['auth' => [$_ENV['JIRA_USERNAME'], $_ENV['JIRA_API_TOKEN']]]);
$jiraClient = new JiraClient($httpClient, $_ENV['JIRA_API_ENDPOINT']);
$graphGenerator = new GraphGenerator();
$jiraGraphService = new JiraGraphService($jiraClient, $graphGenerator);

$issueKey = $_GET['issue'] ?? 'SEA-255';

$imagePath = $jiraGraphService->generateGraphImage($issueKey);

// Ensure the headers are correctly set for the image
header('Content-Type: image/png');
header('Content-Length: ' . filesize($imagePath));
header('Content-Disposition: inline; filename="' . basename($imagePath) . '"');

// Read and output the image file
readfile($imagePath);

// Clean up the temporary image file
unlink($imagePath);
