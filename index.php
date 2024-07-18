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

if (isset($_GET['svg'])) {
    $imagePath = $jiraGraphService->generateGraphImage($issueKey);
    header('Content-Type: image/svg+xml');
    echo file_get_contents($imagePath);
    unlink($imagePath);
    exit;
}

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
            overflow-x: hidden; /* Prevent horizontal scrolling */
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        h1 {
            margin: 0;
            color: #333;
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
            bottom: 125%; /* Position the tooltip above the text */
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .tooltip .tooltiptext::after {
            content: '';
            position: absolute;
            top: 100%; /* Arrow at the bottom */
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
        .pdf-button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }
        .pdf-button:hover {
            background-color: #45a049;
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/svg2pdf@1.1.1/src/index.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/svg-pan-zoom@3.5.0/dist/svg-pan-zoom.min.js"></script>
</head>
<body>
<div class="header">
    <h1>Jira Task Graph for Issue: <?php echo $issueKey; ?></h1>
    <button class="pdf-button" onclick="downloadPDF()">Download as PDF</button>
</div>

<h2>Dependency Graph</h2>
<!--<div id="svg-container">-->
    <object style="background-color: #b3b3b3; width: 100%; height: 550px;" id="graph-svg" type="image/svg+xml" data="index.php?issue=<?php echo $issueKey; ?>&svg=true"></object>
<!--</div>-->

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

        // Initialize SVG Pan Zoom
        var svgObject = document.getElementById('graph-svg');
        svgObject.addEventListener('load', function() {
            var svgDoc = svgObject.contentDocument;
            var svgPanZoomInstance = svgPanZoom(svgDoc.querySelector('svg'), {
                zoomEnabled: true,
                controlIconsEnabled: true,
                fit: true,
                center: true,
                minZoom: 0.5,
                maxZoom: 10
            });

            // Fit SVG to the container width
            svgPanZoomInstance.resize();
            svgPanZoomInstance.fit();
            svgPanZoomInstance.center();

            // Prevent page scroll when zooming with mouse wheel
            svgDoc.addEventListener('wheel', function(event) {
                event.preventDefault();
            }, { passive: false });
        });
    });

    function downloadPDF() {
        var { jsPDF } = window.jspdf;
        var doc = new jsPDF('p', 'mm', 'a4');

        // Add SVG to PDF
        var svgElement = document.getElementById('graph-svg').contentDocument.querySelector('svg');
        svg2pdf(svgElement, doc, {
            xOffset: 10,
            yOffset: 10,
            width: 190 // Full width of A4 page
        });

        // Add the rest of the page content
        html2canvas(document.body, { scale: 3 }).then(canvas => {
            var imgData = canvas.toDataURL('image/png');
            var imgWidth = 210;
            var pageHeight = 295;
            var imgHeight = canvas.height * imgWidth / canvas.width;
            var heightLeft = imgHeight;

            var position = 0;

            doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;

            while (heightLeft >= 0) {
                position = heightLeft - imgHeight;
                doc.addPage();
                doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
            }

            doc.save('Jira_Task_Graph.pdf');
        });
    }
</script>
</body>
</html>
