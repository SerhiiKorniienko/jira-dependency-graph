<?php

namespace App\Interfaces;

interface TrackingSystemClient
{
    public function getIssueData(string $issueKey): array;
}
