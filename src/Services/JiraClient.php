<?php

namespace App\Services;

use GuzzleHttp\Client;
use App\Interfaces\TrackingSystemClient;

class JiraClient implements TrackingSystemClient
{
    private Client $client;
    private string $apiEndpoint;

    public function __construct(Client $client, string $apiEndpoint)
    {
        $this->client = $client;
        $this->apiEndpoint = $apiEndpoint;
    }

    public function getIssueData(string $issueKey): array
    {
        $jqlQuery = 'parent="' . $issueKey . '"';
        $response = $this->client->get($this->apiEndpoint . '/rest/api/3/search', [
            'query' => [
                'jql' => $jqlQuery,
                'fields' => 'key,summary,issuelinks,status,timeoriginalestimate,labels,duedate,customfield_10017,timetracking,assignee'
            ]
        ]);

        return json_decode($response->getBody(), true)['issues'];
    }
}
