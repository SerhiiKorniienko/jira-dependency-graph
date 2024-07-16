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

$issueKey = $_GET['issue'] ?? 'JRA-42';

$imagePath = $jiraGraphService->generateGraphImage($issueKey);
$taskOrder = $jiraGraphService->getTaskOrder($issueKey);
$progress = $jiraGraphService->getProgress($issueKey);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Jira Task Graph</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        h1, h2 {
            color: #333;
        }
        img {
            max-width: 100%;
            height: auto;
            display: block;
            margin-bottom: 20px;
        }
        .progress-bar {
            display: flex;
            margin-bottom: 20px;
            width: 100%;
            height: 55px;
            border-radius: 5px;
            overflow: hidden;
            background-color: #ccc;
        }
        .progress-segment {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            text-align: center;
            border-right: 1px solid #fff;
            height: 100%;
        }
        .progress-segment:last-child {
            border-right: none;
        }
        .progress-todo {
            background-color: blue;
        }
        .progress-inprogress {
            background-color: orange;
        }
        .progress-done {
            background-color: green;
        }
        .tooltip {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #555;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px 0;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .tooltip .tooltiptext::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #555 transparent transparent transparent;
        }
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        .collapsible {
            background-color: #777;
            color: white;
            cursor: pointer;
            padding: 10px;
            width: 100%;
            border: none;
            text-align: left;
            outline: none;
            font-size: 15px;
            margin-top: 10px;
            border-radius: 5px;
        }
        .active, .collapsible:hover {
            background-color: #555;
        }
        .content {
            padding: 0 18px;
            display: none;
            overflow: hidden;
            background-color: #f1f1f1;
            border-radius: 5px;
        }
    </style>
</head>
<body>
<h1>Jira Task Graph for Issue: <?php echo $issueKey; ?></h1>

<h2>Dependency Graph</h2>
<img src="data:image/png;base64,<?php echo base64_encode(file_get_contents($imagePath)); ?>" alt="Jira Task Graph">

<h2>Progress</h2>
<div class="progress-bar">
    <div class="progress-segment progress-todo tooltip" style="width: <?php echo $progress['progress']['toDo']; ?>%;">
        To Do: <?php echo round($progress['progress']['toDo'], 2); ?>%<br>
        <?php echo $progress['estimationSums']['toDo']; ?>d
        <span class="tooltiptext">Pending tasks</span>
    </div>
    <div class="progress-segment progress-inprogress tooltip" style="width: <?php echo $progress['progress']['inProgress']; ?>%;">
        In Progress: <?php echo round($progress['progress']['inProgress'], 2); ?>%<br>
        <?php echo $progress['estimationSums']['inProgress']; ?>d
        <span class="tooltiptext">Tasks in progress</span>
    </div>
    <div class="progress-segment progress-done tooltip" style="width: <?php echo $progress['progress']['done']; ?>%;">
        Done: <?php echo round($progress['progress']['done'], 2); ?>%<br>
        <?php echo $progress['estimationSums']['done']; ?>d
        <span class="tooltiptext">Completed tasks</span>
    </div>
</div>

<h2>Task Order</h2>
<button class="collapsible active">Suggested issues order</button>
<div class="content" style="display: block">
    <ul>
        <?php foreach ($taskOrder as $task): ?>
            <li><?php echo $task; ?></li>
        <?php endforeach; ?>
    </ul>
</div>

<?php unlink($imagePath);?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var coll = document.getElementsByClassName("collapsible");
        for (var i = 0; i < coll.length; i++) {
            coll[i].addEventListener("click", function () {
                this.classList.toggle("active");
                var content = this.nextElementSibling;
                if (content.style.display === "block") {
                    content.style.display = "none";
                } else {
                    content.style.display = "block";
                }
            });
        }
    });
</script>
</body>
</html>